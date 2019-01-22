# Google Cloud Print PHP Library
This is a composer-compatible library to use Google Cloud Print. You can do things
like list printers and print documents

# Requirements
You'll first need to create a Project in the Google Developer Console. That will
generate your ClientId and ClientSecret.

Then you'll need to use an OAuth library to have a user authorize your app and
grant you access. That will give you an accessToken, and possibly a refreshToken
if you specify `access_type=offline`.

Using the accessToken, and optionally the refreshToken, clientId, and clientSecret,
you can now use this library to access the user's Google Cloud Print account

# Installation

`composer require roundsphere/googlecloudprint`

## Basic Usage:

```
use GoogleCloudPrint\Client;

$accessToken = 'a-valid-google-access-token';
$refreshToken = 'optional-refresh-token';

$clientId = 'if-using-a-refresh-token-the-client-id-is-required-to-refresh-it';
$clientSecret = 'required-if-clientId-is-present';

$gcp = new \GoogleCloudPrint\Client(
    $accessToken,
    $refreshToken,
    $clientId,
    $clientSecret
);

$whoami = $gcp->whoami();
echo "Whoami:\n";
print_r($whoami);


echo "Printers:\n";
print_r($gcp->getPrinters());
```
