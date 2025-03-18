<?php

namespace Auxilium\Helpers\MSGraph;


use Auxilium\TwigHandling\PageBuilder2;
use CurlHandle;
use Exception;

class MSGraphInteractions
{
    private static string $ConfigFileLocation = __DIR__ . "/../Configuration/Configuration/msft-access-token-primary.json";

    private array $AccessTokenPayload;
    private string $AccessToken;

    public function __construct()
    {
        /**
         * The credentials file doesn't exist, fail!
         */
        if(!file_exists(filename: self::$ConfigFileLocation))
        {
            PageBuilder2::RenderInternalSystemError(
                new Exception("Missing access \"msft-access-token-primary.json\"."),
            );
        }


        /**
         * Process the credentials file into variables.
         */
        $this->AccessTokenPayload = json_decode(
            json: file_get_contents(
                filename: self::$ConfigFileLocation
            ),
            associative: true ,
        );
        $this->AccessToken = $this->AccessTokenPayload["access_token"];


        if($this->AccessToken != null)
        {
            /**
             * If we've only got 60 seconds just refresh now - MS graph takes a while to do *anything*
             */
            if($this->AccessTokenPayload["expires_at"] <= (time() + 60))
            {
                $this->AccessToken = null;
            }
        }

        /**
         * Get a new access token...
         */
        if($this->AccessToken == null)
        {
            $this->GetNewAccessToken();
        }
    }

    /**
     * Requests a new access token and replaces the credentials file with it.
     *
     * @return void
     */
    private function GetNewAccessToken(): void
    {
        $url = "https://login.microsoftonline.com/" . INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["tenant_guid"] . "/oauth2/v2.0/token";
        $data = [
            "client_id"     => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["client_guid"],
            "client_secret" => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["client_secret"],
            "username"      => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["username"],
            "password"      => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["password"],
            "scope"         => "user.read",
            "grant_type"    => "password",
        ];

        // Use key 'http' even if you send the request to https://...
        $options = [
            "http" => [
                "header"        => "Content-type: application/x-www-form-urlencoded",
                "ignore_errors" => true,
                "method"        => "POST",
                "content"       => http_build_query($data)
            ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if($result === false)
        {
            // Throw an error maybe?
        }
        else
        {
            $parsed = json_decode($result, true);
            $parsed["expires_at"] = time() + $parsed["expires_in"];
            $this->AccessTokenPayload = $parsed;
            $bytes_written = file_put_contents(
                filename: self::$ConfigFileLocation,
                data: json_encode($this->AccessTokenPayload, JSON_PRETTY_PRINT) . "\n"
            );
            if($bytes_written === false)
            {
                // Throw an error maybe?
            }
            $this->AccessTokenPayload = json_decode($this->AccessTokenPayload, true);
            $this->AccessToken = $this->AccessTokenPayload["access_token"];
        }
    }

    public function SendMail($rfc822_raw_message, $debug): void
    {
        $url = "https://graph.microsoft.com/v1.0/users/" . INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["user_guid"] . "/sendMail";

        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_POST, 1);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, [
            "Content-Type: text/plain",
            "Authorization: Bearer " . $this->AccessToken . ""
        ]);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, base64_encode($rfc822_raw_message));
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($curlHandle); // Send the message
        curl_close($curlHandle);

        if($debug)
        {
            echo "<pre>" . htmlentities($server_output) . "</pre>";
            die();
        }
    }
}
