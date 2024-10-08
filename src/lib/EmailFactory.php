<?php
namespace auxilium;

class EmailFactory extends RFC822ObjectFactory {
    private $emailData = null;
    private $recipients = [];
    
    public function __construct() {
        parent::__construct(null);
        $this->setSchema("https://schemas.auxiliumsoftware.co.uk/v1/message.json");
        $this->emailData = [
            "sender" => null,
            "template" => "generic-case-email",
            "template_properties" => [],
            "subject" => null
        ];
    }
    
    public function setSubject($subject) {
        $this->emailData["subject"] = $subject;
        return $this;
    }
    
    public function setTemplate($template) {
        $this->emailData["template"] = $template;
        return $this;
    }
    
    public function setTemplateProperty($key, $value) {
        $this->emailData["template_properties"][$key] = $value;
        return $this;
    }
    
    public function setBody($body) {
        $this->emailData["body"] = $body;
        return $this;
    }
    
    public function setSender(?User $user) {
        $this->emailData["sender"] = $user->getUuid();
        return $this;
    }
    
    public function setSenderEmailAddress(?string $email) {
        $this->emailData["sender_email_address"] = $email;
        return $this;
    }
    
    public function addRecipient($recipient) {
        array_push($this->recipients, $recipient);
        return $this;
    }
    
    public function addRecipientBlindEmail(string $recipient) {
        array_push($this->recipients, $recipient);
        return $this;
    }
    
    // Used for security emails we don't want to save and notifications
    public function setHidden(bool $value = true) {
        $this->emailData["hidden"] = $value;
        return $this;
    }
    
    public function send(User $sendAs = null, $debug = false) {
        //$debug = true;
    
        $originalUser = Session::get_current()->getUser();
        if ($sendAs != null) {
            Session::get_current()->forceSetCurrentUser($sendAs);
        }
        
        $twigLoader = new \Twig\Loader\FilesystemLoader(WEB_ROOT_DIRECTORY."/templates");
        $twig = new \Twig\Environment($twigLoader, [
            "cache" => false,
        ]);
        
        $senderUser = null;
        if ($this->emailData["sender"] != null) {
            $senderUser = new User($this->emailData["sender"]);
        }
        if (!isset($this->emailData["template_properties"]["sender"])) { // if it's manually set don't change it!
            $this->emailData["template_properties"]["sender"] = $senderUser;
        }
        $this->emailData["template_properties"]["selected_lang"] = "en";
        if ($this->recipients[0] instanceof \auxilium\User) {
            $this->emailData["template_properties"]["recipient"] = $this->recipients[0];
            $this->emailData["template_properties"]["selected_lang"] = $this->recipients[0]->getLanguagePreference();
        }
        $templatePath = "email-templates/".$this->emailData["template"].".html";
        $content = null;
        $fixedTemplateProperties = [
            "INSTANCE_BRANDING_LOGO" => INSTANCE_BRANDING_LOGO,
            "INSTANCE_BRANDING_NAME" => INSTANCE_BRANDING_NAME,
            "INSTANCE_BRANDING_DOMAIN_NAME" => INSTANCE_BRANDING_DOMAIN_NAME,

            "INSTANCE_INFO_MAIN_EMAIL" => INSTANCE_INFO_MAIN_EMAIL,
            "INSTANCE_INFO_MAIN_PHONE" => INSTANCE_INFO_MAIN_PHONE,
            "INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS" => INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS,
            "INSTANCE_INFO_MAIN_TEXT" => INSTANCE_INFO_MAIN_TEXT,
            "INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS" => INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS,

            "INSTANCE_INFO_MAINTAINER_NAME" => INSTANCE_INFO_MAINTAINER_NAME,
            "INSTANCE_INFO_MAINTAINER_EMAIL" => INSTANCE_INFO_MAINTAINER_EMAIL,
            "INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME" => INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME,
            "INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL" => INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL
        ];
        $fullTemplateProperties = array_merge($fixedTemplateProperties, $this->emailData["template_properties"]);
        if (isset($this->emailData["body"])) {
            $content = $this->emailData["body"];
        } else {
            $filter = new \Twig\TwigFilter("uiprop", function ($string) {
                return MicroTemplate::data_type_to_human_name($string, $this->twigVariables["selected_lang"]);
            });
            $twig->addFilter($filter);
            $filter = new \Twig\TwigFilter("uitxt", function ($string) {
                return MicroTemplate::ui_text($string, $this->twigVariables["selected_lang"]);
            });
            $twig->addFilter($filter);
            $filter = new \Twig\TwigFilter("uihdg", function ($string) {
                return MicroTemplate::ui_heading($string, $this->twigVariables["selected_lang"]);
            });
            $twig->addFilter($filter);
            $filter = new \Twig\TwigFilter("unpack_string", function ($string) {
                return MicroTemplate::from_packed_template($string, $this->twigVariables["selected_lang"]);
            });
            $twig->addFilter($filter);
            $content = $twig->render($templatePath, $fullTemplateProperties);

            $this->emailData["body"] = $content;
        }
        
        $rfc822RawMessage = null;
        
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
            
            $throwawayMessageId = bin2hex(openssl_random_pseudo_bytes(16));
            //$throwawayMessageId = "beans";
            
            $payload = [
                "message" => [
                    "subject" => \auxilium\MicroTemplate::from_packed_template($this->emailData["subject"], $this->emailData["template_properties"]["selected_lang"]),
                    "body" => [
                        "contentType" => "html",
                        "content" => $content
                    ],
                    "from" => [
                        "emailAddress" => [
                            "name" => INSTANCE_BRANDING_NAME,
                            "address" => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["external_smtp_address"]
                        ]
                    ],
                    "replyTo" => [
                        [
                            "emailAddress" => [
                                "name" => INSTANCE_BRANDING_NAME,
                                "address" => INSTANCE_INFO_MAIN_EMAIL
                            ]
                        ]
                    ],
                    "sender" => [
                        "emailAddress" => [
                            "name" => INSTANCE_BRANDING_NAME,
                            "address" => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["external_smtp_address"]
                        ]
                    ],
                    "toRecipients" => [],
                    "singleValueExtendedProperties" => [
                        [
                            "id" => "String {63fd4964-1fe7-41dd-be81-8fbc6c5d49c4} Name AuxiliumMessageID",
                            "value" => $throwawayMessageId
                        ]
                    ]
                ]
            ];
            
            // Note: The "singleValueExtendedProperties" is needed to get back the message id of the sent message
            
            if ($senderUser != null) {
                $payload["message"]["from"]["emailAddress"]["name"] = $senderUser->getFullName();
                $payload["message"]["replyTo"][0]["emailAddress"]["name"] = $senderUser->getFullName();
                $payload["message"]["sender"]["emailAddress"]["name"] = $senderUser->getFullName();
            }

            $this->emailData["recipients"] = [];
            foreach($this->recipients as &$user) {
                if ($user != null) {
                    if ($user instanceof \auxilium\User) {
                        array_push($payload["message"]["toRecipients"], [
                                "emailAddress" => [
                                    "address" => $user->getEmailAddress(),
                                    "name" => $user->getFullName()
                                ]
                            ]);
                        array_push($this->emailData["recipients"], $user->getUuid());
                    } else {
                        array_push($payload["message"]["toRecipients"], [
                                "emailAddress" => [
                                    "address" => $user
                                ]
                            ]);
                    }
                }
            }
            
            $curlHandle = curl_init();
            curl_setopt($curlHandle, CURLOPT_URL, $url);
            curl_setopt($curlHandle, CURLOPT_POST, 1);
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ["Content-Type:application/json", "Authorization: Bearer ".$msft_access_token.""]);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            $serverOutput = curl_exec($curlHandle); // Send the message
            curl_close($curlHandle);
            
            if ($debug) {
                echo "<pre>".htmlentities($serverOutput)."</pre>";
            }
            
            $url = "https://graph.microsoft.com/v1.0/users/".INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["user_guid"]."/messages?\$filter=singleValueExtendedProperties/any(ep:ep/id eq 'String {63fd4964-1fe7-41dd-be81-8fbc6c5d49c4} Name AuxiliumMessageID' and ep/value eq '".$throwawayMessageId."')"; 
            
            $url = str_replace(" ", "%20", str_replace("{", "%7B", str_replace("}", "%7D", $url)));
            
            if ($debug) {
                echo "<pre>".$url."</pre>";
            }
            
            // Filter for messages that match our randomly generated id

            $matchingMessages = [];
            $tries = 0; // Just give up after 8 seconds, we can't wait forever for microsoft to get their act together
            
            while (count($matchingMessages) == 0 && $tries < 8) { // Sometimes we end up in a race condition where microsoft graph hasn't actually sent the email yet
                sleep(1); // Sigh, maybe if microsoft graph becomes somewhat performant in the future we won't need to do this
            
                $curlHandle = curl_init();
                curl_setopt($curlHandle, CURLOPT_URL, $url);
                curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ["Authorization: Bearer ".$msft_access_token.""]);
                curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
                $serverOutput = curl_exec($curlHandle);
                curl_close($curlHandle);
                
                if ($debug) {
                    echo "<pre>".htmlentities($serverOutput)."</pre>";
                }
                
                if (isset(json_decode($serverOutput, true)["value"])) {
                    $matchingMessages = json_decode($serverOutput, true)["value"];
                } else {
                    echo $serverOutput;
                    die();
                }
                
                if (count($matchingMessages) > 0) {
                    $url = "https://graph.microsoft.com/v1.0/users/".INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["user_guid"]."/messages/".$matchingMessages[0]["id"]."/\$value"; // Now we can finally get the actual sent email in RFC822 format
                    
                    $curlHandle = curl_init();
                    curl_setopt($curlHandle, CURLOPT_URL, $url);
                    curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ["Authorization: Bearer ".$msft_access_token.""]);
                    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
                    $serverOutput = curl_exec($curlHandle);
                    curl_close($curlHandle);
                    
                    $rfc822RawMessage = $serverOutput;
                }
                
                $tries++;
            }
            
            
            if ($debug) {
                echo "<pre>".htmlentities($serverOutput)."</pre>";
                //die();
            }
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
            $mail->setFrom(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"], INSTANCE_BRANDING_NAME);
            //$mail->addAddress('joe@example.net', 'Joe User');     //Add a recipient
            //$mail->addAddress('ellen@example.com');               //Name is optional
            //$mail->addReplyTo('info@example.com', 'Information');
            //$mail->addCC('cc@example.com');
            //$mail->addBCC('bcc@example.com');
            
            $this->emailData["recipients"] = [];
            foreach($this->recipients as &$user) {
                if ($user != null) {
                    if ($user instanceof \auxilium\User) {
                        $mail->addAddress($user->getEmailAddress(), $user->getFullName());
                        array_push($this->emailData["recipients"], $user->getUuid());
                    } else {
                        $mail->addAddress($user);
                    }
                }
            }
            
            // Content
            $mail->isHTML(true);                       // Set email format to HTML
            $mail->Subject = \auxilium\MicroTemplate::from_packed_template($this->emailData["subject"], $this->emailData["template_properties"]["selected_lang"]);
            $mail->Body = $content;

            if (!$mail->send()) {
                throw new MessageSendException($mail->ErrorInfo);
            }
        }
        
        Session::get_current()->forceSetCurrentUser($originalUser);
        if (isset($this->emailData["hidden"])) {
            if ($this->emailData["hidden"]) {
                return null; // Just send mail, don't actually write to database
            }
        }
        parent::fromExisting($rfc822RawMessage);
        return parent::build();
    }
}
