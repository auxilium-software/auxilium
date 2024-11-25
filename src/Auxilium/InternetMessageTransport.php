<?php
namespace Auxilium;

class InternetMessageTransport {
    public static function scan_inboxes() {
        if (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "MS_APP_GRAPH") {
            
        } elseif (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "STANDARD") {
            
        } elseif (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "AWS_SES") {
            try {
                $client = new \Aws\S3\S3Client([
                    'region' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["region"],
                    'version' => '2006-03-01',
                    'credentials' => [
                        'key' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_key"],
                        'secret' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_secret"],
                    ]
                ]);
                //
                $response = $client->listObjectsV2([
                    'Bucket' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["bucket"]
                ]);
                foreach($response["Contents"] as $object) {
                    $job_id = bin2hex(pack("J", time())).".".EncodingTools::base64_encode_url_safe(openssl_random_pseudo_bytes(3*8));
                    $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE."jobs/".$job_id.".json";

                    $job = [
                        "type" => "INGEST_S3_EMAIL",
                        "max_tries" => 3,
                        "tries" => 0,
                        "key" => $object["Key"]
                    ];
                    
                    file_put_contents($job_path, json_encode($job, JSON_PRETTY_PRINT));
                }
                return true;
            } catch (\Exception $e) {
                echo "<pre>".htmlentities($e->getMessage())."</pre>";
                //die();
                return false;
            }
        }
        return false;
    }
    
    public static function ingest_s3_object(string $key) {
        $client = new \Aws\S3\S3Client([
            'region' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["region"],
            'version' => '2006-03-01',
            'credentials' => [
                'key' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_key"],
                'secret' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_secret"],
            ]
        ]);
        //
        $response = $client->getObject([
            'Bucket' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["bucket"],
            'Key' => $key
        ]);
        $response["Body"];
        //echo "<pre>".htmlentities()."</pre>";
        //die();
    }

    public static function send_autodetect(string $internet_message) {
        $type = null;
        if (substr($internet_message, 0, 7) === "BEGIN:VCALENDAR") {
            $type = "ICALENDAR";
        }
        if ($type == null) {
            if (strpos($internet_message, substr("MIME-Version: ", 0, 2048))) {
                $type = "MIME";
            }
        }
        
        return send($internet_message, $type);
    }
    
    public static function get_default_smtp_outbound_address() {
        if (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "MS_APP_GRAPH") {
            return INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["external_smtp_address"];
        } elseif (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "STANDARD") {
            return INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"];
        } elseif (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "AWS_SES") {
            return INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_address"];
        }
    }
    
    public static function send(string $internet_message, string $type) {
        $job_id = bin2hex(pack("J", time())).".".EncodingTools::base64_encode_url_safe(openssl_random_pseudo_bytes(3*8));
        $job_change_key = EncodingTools::base64_encode_url_safe(openssl_random_pseudo_bytes(3*16));
        $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE."jobs/".$job_id.".json";
    
        if ($type == "MIME") {
            $mime_message = \ZBateson\MailMimeParser\Message::from($internet_message, false);
            $sender_headers = $mime_message->getAllHeadersByName("from");
            $senders = [];
            foreach ($sender_headers as &$sender_header) {
                $sender_header_parts = $sender_header->getParts();
                foreach ($sender_header_parts as &$sender_header_part) {
                    array_push($senders, $sender_header_part->getValue());
                }
            }
            $sender = end($senders); // We should only really have one "from"
            $recipient_headers = $mime_message->getAllHeadersByName("to");
            $recipients = [];
            foreach ($recipient_headers as &$recipient_header) {
                $recipient_header_parts = $recipient_header->getParts();
                foreach ($recipient_header_parts as &$recipient_header_part) {
                    array_push($recipients, $recipient_header_part->getValue());
                }
            }
            //var_dump($sender);
            //echo " ==> ";
            //var_dump($recipients);
            
            //$sender = "me@alexbaldwin.dev";
            
            
            $auxinbox_regex = "/auxiliuminbox\+([a-f0-9]{8}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{12})@([^><]+)/";

            if ($sender === false) {
                $mime_message->setRawHeader("From", INSTANCE_BRANDING_NAME." <".InternetMessageTransport::get_default_smtp_outbound_address().">");
            } else {
                preg_match($auxinbox_regex, $sender, $matches, PREG_UNMATCHED_AS_NULL);
                if (count($matches) > 0) {
                    $sender_user = new User($matches[1]);
                    $mime_message->removeHeader("from");
                    if (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "AWS_SES") {
                        if (isset(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_dynamic_prefix"]) && strlen(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_dynamic_prefix"]) > 0) {
                            $mime_message->setRawHeader("From", $sender_user->getFullName()." <".INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_dynamic_prefix"]."+".$sender_user->getId()."@".INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_domain"].">");
                        } else {
                            $mime_message->setRawHeader("From", $sender_user->getFullName()." <".$sender_user->getId()."@".INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_domain"].">");
                        }
                    } else {
                        $mime_message->setRawHeader("From", $sender_user->getFullName()." <".InternetMessageTransport::get_default_smtp_outbound_address().">");
                    }
                }
            }
            
            $recipient_strings = [];
            $mime_message->removeHeader("to");
            foreach ($recipients as &$recipient) {
                preg_match($auxinbox_regex, $recipient, $matches, PREG_UNMATCHED_AS_NULL);
                if (count($matches) > 0) {
                    $recipient_user = new User($matches[1]);
                    if ($recipient_user->getContactEmail() != null) {
                        array_push($recipient_strings, $recipient_user->getFullName()." <".$recipient_user->getContactEmail().">");
                    }
                } else {
                    array_push($recipient_strings, $recipient);
                }
            }
            $mime_message->setRawHeader("To", implode(", ", $recipient_strings));
            
            $mime_message->removeHeader("X-Auxilium-Message-Version");
            $mime_message->setRawHeader("X-Auxilium-Message-Transport-Version", "2.0");
            $mime_message->setRawHeader("X-Auxilium-Export-Type", "FORWARD_MESSAGE");
            
            $job = [
                "type" => "SEND_EMAIL",
                "max_tries" => 3,
                "tries" => 0,
                "job_key" => $job_change_key,
                "content" => strval($mime_message)
            ];
            
            file_put_contents($job_path, json_encode($job, JSON_PRETTY_PRINT));
            
            return $job_id.".".$job_change_key;
        }
    }
    
    public static function send_now(string $rfc822_raw_message) {
        $debug = false;
    
        if (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "MS_APP_GRAPH") {
            $msft_access_token = null;
                
            if (file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE."msft-access-token-primary.json")) {
                $msft_access_token_json = file_get_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE."msft-access-token-primary.json");
                $msft_access_token = null;
                if ($msft_access_token_json === FALSE) {
                    $msft_access_token = null;
                } else {
                    $msft_access_token_json = json_decode($msft_access_token_json, true);
                    $msft_access_token = $msft_access_token_json["access_token"];
                }
            }
            
            if ($msft_access_token != null) {
                if ($msft_access_token_json["expires_at"] <= (time() + 60)) { // If we've only got 60 seconds just refresh now - MS graph takes a while to do *anything*
                    $msft_access_token = null;
                }
            }
            
            if ($msft_access_token == null) {
                $url = "https://login.microsoftonline.com/".INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["tenant_guid"]."/oauth2/v2.0/token";
                $data = [
                    "client_id" => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["client_guid"],
                    "client_secret" => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["client_secret"],
                    "username" => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["username"],
                    "password" => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["password"],
                    "scope" => "user.read",
                    "grant_type" => "password",
                ];

                // Use key 'http' even if you send the request to https://...
                $options = [
                    "http" => [
                        "header" => "Content-type: application/x-www-form-urlencoded",
                        "ignore_errors" => true,
                        "method" => "POST",
                        "content" => http_build_query($data)
                    ]
                ];
                $context  = stream_context_create($options);
                $result = file_get_contents($url, false, $context);
                if ($result === FALSE) {
                    // Throw an error maybe?
                } else {
                    $parsed = json_decode($result, true);
                    $parsed["expires_at"] = time() + $parsed["expires_in"];
                    $msft_access_token_json = json_encode($parsed, JSON_PRETTY_PRINT)."\n";
                    $bytes_written = file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE."msft-access-token-primary.json", $msft_access_token_json);
                    if ($bytes_written === FALSE) {
                        // Throw an error maybe?
                    }
                    $msft_access_token_json = json_decode($msft_access_token_json, true);
                    $msft_access_token = $msft_access_token_json["access_token"];
                }
            }
            
            $url = "https://graph.microsoft.com/v1.0/users/".INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["user_guid"]."/sendMail";
            
            $curl_handle = curl_init();
            curl_setopt($curl_handle, CURLOPT_URL, $url);
            curl_setopt($curl_handle, CURLOPT_POST, 1);
            curl_setopt($curl_handle, CURLOPT_HTTPHEADER, ["Content-Type: text/plain", "Authorization: Bearer ".$msft_access_token.""]);
            curl_setopt($curl_handle, CURLOPT_POSTFIELDS, base64_encode($rfc822_raw_message));
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($curl_handle); // Send the message
            curl_close($curl_handle);
            
            if ($debug) {
                echo "<pre>".htmlentities($server_output)."</pre>";
                die();
            }
            
            return true;
        }
        
        if (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "STANDARD") {
            $mail = new \PHPMailer\PHPMailer\PHPMailer();

            // Settings
            $mail->IsSMTP();
            $mail->CharSet = 'UTF-8';

            $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $mail->Host = INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["smtp"];    // SMTP server example
            $mail->SMTPDebug = 0;                     // enables SMTP debug information (for testing)
            $mail->SMTPAuth = true;                  // enable SMTP authentication
            $mail->Port = 465;                    // set the SMTP port for the GMAIL server
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Username = INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"];            // SMTP account username example
            $mail->Password = INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["password"];            // SMTP account password example

            //Recipients
            //$mail->setFrom(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"], INSTANCE_BRANDING_NAME);
            //$mail->addAddress('joe@example.net', 'Joe User');     //Add a recipient
            //$mail->addAddress('ellen@example.com');               //Name is optional
            //$mail->addReplyTo('info@example.com', 'Information');
            //$mail->addCC('cc@example.com');
            //$mail->addBCC('bcc@example.com');
            
            //$this->emailData["recipients"] = [];
            //foreach($this->recipients as &$user) {
            //    if ($user != null) {
            //        if ($user instanceof \auxilium\User) {
            //            $mail->addAddress($user->getEmailAddress(), $user->getFullName());
            //            array_push($this->emailData["recipients"], $user->getUuid());
            //        } else {
            //            $mail->addAddress($user);
            //        }
            //    }
            //}
            
            // Content
            //$mail->isHTML(true);                       // Set email format to HTML
            //$mail->Subject = \auxilium\MicroTemplate::from_packed_template($this->emailData["subject"], $this->emailData["template_properties"]["selected_lang"]);
            //$mail->Body = $content;

            $message = \ZBateson\MailMimeParser\Message::from($rfc822_raw_message, false);
            //$message->getHeaderValue(HeaderConsts::FROM)->getEmail()


            $senderName = $message->getHeader(\ZBateson\MailMimeParser\Header\HeaderConsts::FROM)->getPersonName();
            if ($senderName )
            $mail->setFrom(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"], $senderName);
            foreach($message->getHeader(\ZBateson\MailMimeParser\Header\HeaderConsts::TO)->getAddresses() as &$user) {
                $mail->addAddress($user->getEmail(), $user->getName());
            }

            $text = $message->getTextContent();
            $html = $message->getHtmlContent();

            if ($html == null) {
                $mail->Body = $text;
            } else {
                $mail->isHTML(true);
                $mail->Body = $html;
            }

            /*
            $mail->Subject = $message->getHeaderValue(\ZBateson\MailMimeParser\Header\HeaderConsts::SUBJECT);
            */

            foreach($message->getAllHeaders() as &$header) {
                $headerName = $header->getName();
                switch ($headerName) {
                    default:
                        echo "CH: ".$headerName.": ".$header->getRawValue()."\n";
                        $mail->addCustomHeader($headerName, $header->getRawValue());
                        break;
                }
            }

            if (!$mail->send()) {
                throw new MessageSendException($mail->ErrorInfo);
            }

            echo "\n";

            echo $mail->getSentMIMEMessage();
            
            return true;
        }
        
        if (INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "AWS_SES") {
            $client = new \Aws\Ses\SesClient([
                'region' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["region"],
                'version' => '2010-12-01',
                'credentials' => [
                    'key' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_key"],
                    'secret' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_secret"],
                ]
            ]);
            $response = $client->sendRawEmail([
                'RawMessage' => [
                    'Data' => $rfc822_raw_message
                ]
            ]);
            return true;
        }
    }
}
