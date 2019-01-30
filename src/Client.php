<?php

namespace GoogleCloudPrint;

class Client {

	protected $accessToken;
    protected $tokenExpires = null;
    protected $refreshToken;
    protected $clientId;
    protected $clientSecret;

    static protected $guzzle;

	public function __construct($accessToken, $refreshToken = null, $clientId = null, $clientSecret = null)
    {
		$this->accessToken = $accessToken;
		$this->refreshToken = $refreshToken;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        self::$guzzle = new \GuzzleHttp\Client();
	}

    protected function sendRequest($method, $url, $options = [], $retry = true)
    {
        $options['headers']['Authorization'] = "Bearer {$this->accessToken}";

        try {
            $response = self::$guzzle->request($method, $url, $options);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $class = get_class($e);
            $response = $e->getResponse();
            if (401 == $response->getStatusCode() && $retry) {
                // An HTTP 401 Response often means the token expired. So try refreshing it
                if ($this->attemptRefreshToken()) {
                    return self::sendRequest($method, $url, $options, false);
                }
            }
            throw $e;
        } catch (\Exception $e) {
            $class = get_class($e);
            echo "UNKNOWN EXCEPTION [{$class}] : ".$e->getMessage()."\n";
            throw $e;
        }
    }

    protected function attemptRefreshToken()
    {
        $response = self::$guzzle->request(
            'POST',
            'https://www.googleapis.com/oauth2/v4/token',
            [
                'json'  => [
                    'refresh_token' => $this->refreshToken,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => "refresh_token",
                ],
            ]
        );
        $body = $response->getBody();
        $tokenResponse = json_decode($body, true);
        if (!empty($tokenResponse['access_token'])) {
            $this->accessToken = $tokenResponse['access_token'];
            if (!empty($tokenResponse['expires_in'])) {
                $this->tokenExpires = time() + $tokenResponse['expires_in'];
            }
            return true;
        }
        return false;
    }

	/**
	 * Function whoami
	 */
    public function whoami()
    {
        $response = $this->sendRequest(
            'GET',
            'https://www.googleapis.com/oauth2/v2/userinfo'
        );
		return json_decode($response->getBody(), true);
    }

	/**
	 * Function getPrintersRaw
	 */
	public function getPrintersRaw()
    {
        $response = $this->sendRequest(
            'GET',
	        'https://www.google.com/cloudprint/search'
        );
		return json_decode($response->getBody(), true);
	}

	/**
	 * Function getPrinters
	 */
    public function getPrinters()
    {
        $rawResponse = $this->getPrintersRaw();
		$printers = [];
        if (!empty($rawResponse['printers'])) {
			foreach ($rawResponse['printers'] as $printer) {
				$printers[] = [
                    'id'            => $printer['id'],
                    'name'          => $printer['name'],
                    'displayName'   => $printer['displayName'],
                    'status'        => $printer['connectionStatus'],
                ];
			}
		}
		return $printers;
    }

    public function printDocument($printerId, $jobName, $bytes, $contentType)
    {
        $postData = [
            'printerid'                 => $printerId,
            'title'                     => $jobName,
            'contentTransferEncoding'   => 'base64',
            'content'                   => base64_encode($bytes),
            'contentType'               => $contentType,
        ];

        $rawResponse = $this->sendRequest(
            'POST',
            'https://www.google.com/cloudprint/submit',
            [
                'form_params'  => $postData,
            ]
        );
        $response = json_decode($rawResponse->getBody(), true);

        if (isset($response['success']) && 1 == $response['success']) {
            return $response;
        }
        $e = new \Exception('Google CloudPrint Job Failed: '.$response['message']);
        $e->response = $response;
        throw $e;
    }
}
