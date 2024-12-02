<?php

use auxilium\TotpUtility;
use Auxilium\TwigHandling\PageBuilder2;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';

try
{

    $openid_configs_printable = [];

    foreach(INSTANCE_CREDENTIAL_OPENID_SOURCES as &$openid_config)
    {
        array_push($openid_configs_printable, [
                "unique_name" => $openid_config["unique_name"],
                "display_name" => $openid_config["brand_name"],
            ]
        );
    }

    $form_data = Auxilium\PersistentFormData::get();

    $unverified_user_data = [
        "email_address" => null,
        "password" => null,
        "user_uuid" => null,
    ];
    $email_invalid = true;
    $password_invalid = true;
    if(isset($_POST["email_address"]))
    {
        $unverified_user_data["email_address"] = strtolower($_POST["email_address"]);
    }
    if(isset($_POST["password"]))
    {
        $unverified_user_data["password"] = $_POST["password"];
    }

    if(($unverified_user_data["email_address"] == null) && ($unverified_user_data["password"] == null))
    {
        PageBuilder2::AutoRender([
                "openid_configs" => $openid_configs_printable,
                "form_validation" => [
                    "email_address" => true,
                    "password" => true,
                ],
            ]
        );
    }

    $bind_variables = [
        "email_address" => $unverified_user_data["email_address"],
    ];
    $sql = "SELECT email_address, password, user_uuid FROM standard_logins WHERE email_address=:email_address";
    $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
    $statement->execute($bind_variables);
    $user_data = $statement->fetch();
    if($user_data == null)
    {
        PageBuilder2::AutoRender([
                "openid_configs" => $openid_configs_printable,
                "form_validation" => [
                    "email_address" => false,
                    "password" => true,
                ],
            ]
        );
    }
    else
    {
        $pre_hashed_password = base64_encode(hash("sha256", $unverified_user_data["password"], true)); // NOTE: BCrypt has a max input of 72 chars, so in order to mitigate attacks on sentence based passwords, that are long but lower complexity, we must pre-hash the password and then base64 encode to get down to 44 chars, which is under the limit. These 44 chars still have plenty of entropy thanks to sha256 being a robust hash algorithm.
        if(password_verify($pre_hashed_password, $user_data["password"]))
        {

            $bind_variables = [
                "user_uuid" => $user_data["user_uuid"]
            ];
            $sql = "SELECT totp_secret, device_uuid FROM totp_secrets WHERE user_uuid=:user_uuid";
            $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
            $statement->execute($bind_variables);
            $totp_secret_data_rows = $statement->fetchAll();
            if(count($totp_secret_data_rows) != 0)
            { // Check this user actuall has TOTP secrets
                if(isset($_POST["totp-code"]))
                {
                    $totp_authed = false;

                    foreach($totp_secret_data_rows as &$secret_data)
                    {
                        if(TotpUtility::verifyTotpKey($secret_data["totp_secret"], $_POST["totp-code"]))
                        {

                            $bind_variables = [
                                "device_uuid" => $secret_data["device_uuid"],
                                "totp_code" => preg_replace("/\s+/", "", $_POST["totp-code"])
                            ];
                            $sql = "SELECT 1 FROM totp_used_codes WHERE device_uuid=:device_uuid AND totp_code=:totp_code";
                            $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                            $statement->execute($bind_variables);
                            $used_code_info = $statement->fetch();

                            if($used_code_info === false)
                            {
                                $totp_authed = true;

                                $sql = "INSERT INTO totp_used_codes (device_uuid, totp_code) VALUES (:device_uuid, :totp_code)";
                                $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                                $statement->execute($bind_variables);
                            }
                            else
                            {
                                PageBuilder2::Render(
                                    template : "Pages/login-totp.html.twig",
                                    variables: [
                                        "openid_configs" => $openid_configs_printable,
                                        "form_validation" => [
                                            "totp" => true,
                                            "totp_used" => false,
                                        ],
                                        "form_data" => [
                                            "email_address" => $unverified_user_data["email_address"],
                                            "password" => $unverified_user_data["password"],
                                        ],
                                    ]
                                );
                                //echo $twig->render($twig_variables["selected_lang"]."/login-totp.html.twig", $twig_variables);
                            }
                        }
                    }

                    if(!$totp_authed)
                    {
                        PageBuilder2::Render(
                            template : "Pages/login-totp.html.twig",
                            variables: [
                                "openid_configs" => $openid_configs_printable,
                                "form_validation" => [
                                    "totp" => false,
                                    "totp_used" => true,
                                ],
                                "form_data" => [
                                    "email_address" => $unverified_user_data["email_address"],
                                    "password" => $unverified_user_data["password"],
                                ],
                            ]
                        );
                        //echo $twig->render($twig_variables["selected_lang"]."/login-totp.html.twig", $twig_variables);
                    }
                }
                else
                {
                    PageBuilder2::Render(
                        template : "Pages/login-totp.html.twig",
                        variables: [
                            "openid_configs" => $openid_configs_printable,
                            "form_validation" => [
                                "totp" => true,
                                "totp_used" => true,
                            ],
                            "form_data" => [
                                "email_address" => $unverified_user_data["email_address"],
                                "password" => $unverified_user_data["password"],
                            ],
                        ]
                    );
                    //echo $twig->render($twig_variables["selected_lang"]."/login-totp.html.twig", $twig_variables);
                }
            }

            $session_key = rtrim(strtr(base64_encode(openssl_random_pseudo_bytes(64)), '+/', '-_'), '='); // 512 bits should be long enough to be practically impossible to guess. Even allowing one guess per millesecond (which is already better than the bottleneck of the JISC network) it will take 5 395 141 535 403 007 094 485 264 577 years. This is conserably longer than the time we have left before the Earth is consumed by the Sun turning into a red giant.

            $session_info = [
                "session_uuid" => Auxilium\EncodingTools::generate_new_uuid("sessions"),
                "session_key" => $session_key,
                "user_uuid" => $user_data["user_uuid"],
                "ip_address" => $_SERVER["REMOTE_ADDR"],
                "sub" => "auxilium/" . $user_data["email_address"],
                "active" => 1,
            ];
            $sql = "INSERT INTO portal_sessions (session_uuid, session_key, user_uuid, ip_address, unique_sub, active) VALUES (:session_uuid, :session_key, :user_uuid, :ip_address, :sub, :active)";
            $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
            $statement->execute($session_info);
            setcookie("session_key", $session_info["session_key"], time() + (3600 * 48), "/", null, true, true);
            if($form_data == null)
            {
                header("Location: /");
            }
            else
            {
                if(count($form_data["form_stack"]) > 0)
                {
                    Auxilium\PersistentFormData::set($form_data);
                    header("Location: " . array_pop($form_data["form_stack"]));
                }
                else
                {
                    header("Location: /");
                }
            }
            exit();
        }
        else
        {
            PageBuilder2::AutoRender([
                    "openid_configs" => $openid_configs_printable,
                    "form_validation" => [
                        "email_address" => true,
                        "password" => false,
                    ],
                ]
            );
        }
    }
}
catch(Exception $e)
{
    $technical_details = "Exception Type:\n    " . get_class($e);
    $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    $technical_details .= "\nMessage:\n    " . $e->getMessage();
    $technical_details .= "\nStack Trace:\n\n" . $e->getTraceAsString();

    http_response_code(500);
    PageBuilder2::Render(
        template : "ErrorPages/InternalSystemError.html.twig",
        variables: [
            "technical_details" => $technical_details,
        ]
    );
}