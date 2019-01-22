<?php

namespace GoogleCloudPrint;

class Client {

	const PRINT_URL             = "https://www.google.com/cloudprint/submit";
    const JOBS_URL              = "https://www.google.com/cloudprint/jobs";

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


	/**
	 * Function sendPrintToPrinter
	 *
	 * Sends document to the printer
	 *
	 * @param Printer id $printerid    // Printer id returned by Google Cloud Print service
	 *
	 * @param Job Title $printjobtitle // Title of the print Job e.g. Fincial reports 2012
	 *
	 * @param File Path $filepath      // Path to the file to be send to Google Cloud Print
	 *
	 * @param Content Type $contenttype // File content type e.g. application/pdf, image/png for pdf and images
	 */
	public function sendPrintToPrinter($printerid,$printjobtitle,$filepath,$contenttype) {

	// Check if we have auth token
		if(empty($this->accessToken)) {
			// We don't have auth token so throw exception
			throw new Exception("Please first login to Google by calling loginToGoogle function");
		}
		// Check if prtinter id is passed
		if(empty($printerid)) {
			// Printer id is not there so throw exception
			throw new Exception("Please provide printer ID");
		}
		// Open the file which needs to be print
		$handle = fopen($filepath, "rb");
		if(!$handle) {
			// Can't locate file so throw exception
			throw new Exception("Could not read the file. Please check file path.");
		}
		// Read file content
		$contents = file_get_contents($filepath);

		// Prepare post fields for sending print
		$post_fields = array(
			'printerid' => $printerid,
			'title' => $printjobtitle,
			'contentTransferEncoding' => 'base64',
			'content' => base64_encode($contents), // encode file content as base64
			'contentType' => $contenttype
		);
		// Prepare authorization headers
		$authheaders = array(
			"Authorization: Bearer " . $this->accessToken
		);

		// Make http call for sending print Job
		$this->httpRequest->setUrl(self::PRINT_URL);
		$this->httpRequest->setPostData($post_fields);
		$this->httpRequest->setHeaders($authheaders);
		$this->httpRequest->send();
		$response = json_decode($this->httpRequest->getResponse());

		// Has document been successfully sent?
		if($response->success=="1") {
			return array('status' =>true,'errorcode' =>'','errormessage'=>"", 'id' => $response->job->id);
		} else {
			return array('status' =>false,'errorcode' =>$response->errorCode,'errormessage'=>$response->message);
		}
	}

    public function jobStatus($jobid)
    {
        // Prepare auth headers with auth token
        $authheaders = array(
            "Authorization: Bearer " .$this->accessToken
        );

        // Make http call for sending print Job
        $this->httpRequest->setUrl(self::JOBS_URL);
        $this->httpRequest->setHeaders($authheaders);
        $this->httpRequest->send();
        $responsedata = json_decode($this->httpRequest->getResponse());

        foreach ($responsedata->jobs as $job)
            if ($job->id == $jobid)
                return $job->status;

        return 'UNKNOWN';
    }
}
