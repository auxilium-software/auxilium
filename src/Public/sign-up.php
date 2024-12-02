<?php

use Auxilium\EmailHandling\EmailBuilder;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\Exceptions\MessageSendException;
use Auxilium\Schemas\UserSchema;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\NavigationUtilities;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

$pb = PageBuilder::get_instance();
try
{
    $form_data = Auxilium\PersistentFormData::get();

    if($form_data == null)
    {
        $form_data = [
            "form_step" => null,
            "form_stack" => []
        ];
    }
    if($form_data["form_step"] == null)
    {
        $form_data["form_step"] = "USER_TYPE";
    }
    $form_values = $_POST;
    $form_validation_failures = [];
    $valid_submission = true;
    try
    {
        switch($form_data["form_step"])
        {
            case "INVITE_CODE":
                if(isset($_POST["invite_code"]))
                {
                    $bind_variables = [
                        "invite_code" => strtolower(str_replace(" ", "", $_POST["invite_code"]))
                    ];
                    $sql = "SELECT invite_rule, invite_code FROM invite_codes WHERE invite_code=:invite_code";
                    $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($bind_variables);
                    $returned_data = $statement->fetch();
                    if($returned_data == null)
                    {
                        $form_validation_failures["invite_code"] = true;
                    }
                    else
                    {
                        $bind_variables = [
                            "invite_code" => $returned_data["invite_code"]
                        ];
                        $sql = "DELETE FROM invite_codes WHERE invite_code=:invite_code";
                        $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($bind_variables);
                    }
                }
                break;
            case "VERIFY_ACCOUNT_EMAIL":
                if(isset($_POST["email_address_verification_code"]))
                {
                    //$form_data["form_step"] = null;

                    $bind_variables = [
                        "verification_code" => strtolower(str_replace(" ", "", $_POST["email_address_verification_code"])),
                        "user_uuid" => $form_data["user_uuid"],
                    ];
                    $sql = "SELECT user_uuid, email_address FROM email_verification_codes WHERE verification_code=:verification_code AND user_uuid=:user_uuid";
                    $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($bind_variables);
                    $returned_data = $statement->fetch();
                    if($returned_data == null)
                    {
                        $form_validation_failures["email_address_verify_code"] = true;
                    }
                    else
                    {
                        $bind_variables = [
                            "user_uuid" => $returned_data["user_uuid"],
                            "email_address" => $returned_data["email_address"],
                            "password" => $form_data["hashed_password"]
                        ];
                        $sql = "INSERT INTO standard_logins (email_address, user_uuid, password) VALUES (:email_address, :user_uuid, :password)";
                        $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($bind_variables);

                        $user_node = \Auxilium\DatabaseInteractions\Deegraph\DeegraphNode::from_id($returned_data["user_uuid"]);
                        $email_prop = Auxilium\GraphDatabaseConnection::new_node($returned_data["email_address"], "text/plain", null, Auxilium\User::get_system_node());
                        $user_node->addProperty("contact_email", $email_prop, Auxilium\User::get_system_node()); // Do all of this as the system node, since users shouldn't just be able to randomly change their email address

                        $bind_variables = [
                            "user_uuid" => $returned_data["user_uuid"]
                        ];
                        $sql = "DELETE FROM email_verification_codes WHERE user_uuid=:user_uuid";
                        $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($bind_variables);
                        $form_data["form_step"] = null;
                        setcookie("session_key", $form_data["session_key"], time() + (3600 * 48), "/", null, true, true);
                        Auxilium\PersistentFormData::set($form_data);
                        $next_location = array_pop($form_data["form_stack"]);
                        if($next_location == null)
                        {
                            NavigationUtilities::Redirect(target: "/dashboard");
                        }
                        else
                        {
                            NavigationUtilities::Redirect(target:  $next_location);
                        }
                        exit();
                    }
                }
                break;
            case "CREATE_ACCOUNT":
                if(!isset($_POST["privacy_policy_consent"]))
                {
                    $form_validation_failures["privacy_policy_consent"] = true;
                    break;
                }
                else if(!($_POST["privacy_policy_consent"] == "true"))
                {
                    $form_validation_failures["privacy_policy_consent"] = true;
                    break;
                }

                if(isset($_POST["full_name"]))
                {
                    $form_values["full_name"] = $_POST["full_name"];
                    if(strlen($_POST["full_name"]) < 3)
                    {
                        $form_validation_failures["full_name"] = true;
                        $valid_submission = false;
                    }
                }
                else
                {
                    $form_validation_failures["full_name"] = true;
                    $valid_submission = false;
                }

                if(isset($_POST["email_address"]))
                {
                    $form_values["email_address"] = $_POST["email_address"];
                    if(!filter_var($_POST["email_address"], FILTER_VALIDATE_EMAIL))
                    {
                        $form_validation_failures["email_address_valid"] = true;
                        $valid_submission = false;
                    }
                }
                else
                {
                    $form_validation_failures["email_address_valid"] = true;
                    $valid_submission = false;
                }

                if(isset($_POST["password"]))
                {
                    if(strlen($_POST["password"]) >= 8)
                    {
                        if(isset($_POST["password_confirm"]))
                        {
                            if(!($_POST["password_confirm"] == $_POST["password"]))
                            {
                                $form_values["password"] = "";
                                $form_values["password_confirm"] = "";
                                $form_validation_failures["password_confirm"] = true;
                                $valid_submission = false;
                            }
                        }
                        else
                        {
                            $form_values["password"] = "";
                            $form_values["password_confirm"] = "";
                            $form_validation_failures["password_confirm"] = true;
                            $valid_submission = false;
                        }
                    }
                    else
                    {
                        $form_values["password"] = "";
                        $form_values["password_confirm"] = "";
                        $form_validation_failures["password_length"] = true;
                        $valid_submission = false;
                    }
                }
                else
                {
                    $form_values["password"] = "";
                    $form_values["password_confirm"] = "";
                    $form_validation_failures["password_length"] = true;
                    $valid_submission = false;
                }


                $bind_variables = [
                    "email_address" => strtolower($_POST["email_address"]),
                ];
                $sql = "SELECT COUNT(*) FROM standard_logins WHERE email_address=:email_address";
                $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                $statement->execute($bind_variables);

                if($statement->fetchColumn() > 0)
                {
                    $form_validation_failures["email_address_unique"] = true;
                    $valid_submission = false;
                }

                if($valid_submission)
                {
                    $pre_hashed_password = base64_encode(hash("sha256", $_POST["password"], true));
                    // NOTE: BCrypt has a max input of 72 chars, so in order to mitigate attacks on sentence based passwords, that are long but lower complexity, we must pre-hash the password and then base64 encode to get down to 44 chars, which is under the limit. These 44 chars still have plenty of entropy thanks to sha256 being a robust hash algorithm.

                    $user_node = Auxilium\GraphDatabaseConnection::new_node(null, null, URLHandling::GetURLForSchema(UserSchema::class), Auxilium\User::get_system_node());
                    $user_node = new Auxilium\User($user_node->getId());

                    $hash_options = [
                        "cost" => 12,
                    ];
                    $hashed_password = password_hash($pre_hashed_password, PASSWORD_BCRYPT, $hash_options);

                    $word_list = json_decode(file_get_contents(WEB_ROOT_DIRECTORY . "byte-word-list.json"), true);

                    $garbage_data = openssl_random_pseudo_bytes(4);

                    $verification_code = $word_list[ord($garbage_data[0])] . " " . $word_list[ord($garbage_data[1])] . " " . $word_list[ord($garbage_data[2])] . " " . $word_list[ord($garbage_data[3])];
                    $temporary_data = [
                        "user_uuid" => $user_node->getId(),
                        "email_address" => strtolower($_POST["email_address"]),
                        "verification_code" => str_replace(" ", "", $verification_code),
                    ];
                    //$twig_variables["verification_code"] = $verification_code;
                    //$twig_variables["first_name"] = explode(" ", $data["full_name"])[0];
                    $sql = "INSERT INTO email_verification_codes (user_uuid, verification_code, email_address) VALUES (:user_uuid, :verification_code, :email_address)";
                    $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($temporary_data);

                    $email_builder = new EmailBuilder();
                    $email_builder->setTemplate("new-account-verification-code");
                    $email_builder->setTemplateProperty("verification_code", $verification_code);
                    $email_builder->setTemplateProperty("recipient_name", explode(" ", $form_values["full_name"])[0]);
                    $email_builder->setSubject("Account creation security code");
                    $email_builder->addRecipient(strtolower($_POST["email_address"]), $form_values["full_name"]);
                    $email = $email_builder->build();
                    Auxilium\InternetMessageTransport::send($email, "MIME");

                    $language_prop = Auxilium\GraphDatabaseConnection::new_node(strtoupper($pb->getCurrentLanguage()), "text/plain", null, $user_node);
                    $user_node->addProperty("preferred_language", $language_prop, $user_node); // Set it to whatever the language is currently in
                    $full_name_prop = Auxilium\GraphDatabaseConnection::new_node($form_values["full_name"], "text/plain", null, $user_node);
                    $user_node->addProperty("name", $full_name_prop, $user_node);
                    $name_prop = Auxilium\GraphDatabaseConnection::new_node(explode(" ", $form_values["full_name"])[0], "text/plain", null, $user_node);
                    $user_node->addProperty("display_name", $name_prop, $user_node); // Create this as default the user's first name - they can change it later if they want

                    $session_key = rtrim(strtr(base64_encode(openssl_random_pseudo_bytes(64)), '+/', '-_'), '='); // 512 bits should be long enough to be practically impossible to guess. Even allowing one guess per millesecond (which is already better than the bottleneck of the JISC network) it will take 5 395 141 535 403 007 094 485 264 577 years. This is conserably longer than the time we have left before the Earth is consumed by the Sun turning into a red giant.

                    $session_info = [
                        "session_uuid" => Auxilium\EncodingTools::generate_new_uuid(),
                        "session_key" => $session_key,
                        "user_uuid" => $user_node->getId(),
                        "ip_address" => $_SERVER["REMOTE_ADDR"],
                        "sub" => "auxilium/" . strtolower($_POST["email_address"]),
                        "active" => 1,
                    ];
                    $sql = "INSERT INTO portal_sessions (session_uuid, session_key, user_uuid, unique_sub, ip_address, active) VALUES (:session_uuid, :session_key, :user_uuid, :sub, :ip_address, :active)";
                    $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($session_info);

                    $form_data["user_uuid"] = $user_node->getId();
                    $form_data["session_key"] = $session_info["session_key"];
                    $form_data["email_address"] = strtolower($_POST["email_address"]);
                    $form_data["hashed_password"] = $hashed_password;

                    $form_data["form_step"] = "VERIFY_ACCOUNT_EMAIL";
                }

                break;
            case "USER_TYPE":
            default:
                if(isset($_POST["user_type"]))
                {
                    $form_data["user_type"] = $_POST["user_type"];
                }
                if(isset($form_data["user_type"]))
                {
                    if($form_data["user_type"] == "STAFF")
                    {
                        if(STAFF_SIGN_UP_INVITE_ONLY)
                        {
                            $form_data["form_step"] = "INVITE_CODE";
                        }
                        else
                        {
                            $form_data["form_step"] = "CREATE_ACCOUNT";
                        }
                    }
                    else if($form_data["user_type"] == "ORGANISATION" || $form_data["user_type"] == "LAWYER")
                    {
                        if(EXTERNAL_ORG_SIGN_UP_INVITE_ONLY)
                        {
                            $form_data["form_step"] = "INVITE_CODE";
                        }
                        else
                        {
                            $form_data["form_step"] = "CREATE_ACCOUNT";
                        }
                    }
                    else
                    {
                        if(CLIENT_SIGN_UP_INVITE_ONLY)
                        {
                            $form_data["form_step"] = "INVITE_CODE";
                        }
                        else
                        {
                            $form_data["form_step"] = "CREATE_ACCOUNT";
                        }
                    }
                }
                break;
        }
        $pb->setVariable("form_values", $form_values);
        $pb->setVariable("form_validation_failures", $form_validation_failures);
        $pb->setVariable("form_persistence_key", Auxilium\PersistentFormData::set($form_data));
        switch($form_data["form_step"])
        {
            case "VERIFY_ACCOUNT_EMAIL":
                $pb->setTemplate("Pages/sign-up-form/account-verify-email");
                $pb->setVariable("email_address", $form_data["email_address"]);
                $pb->render();
                break;
            case "CREATE_ACCOUNT":
                $pb->setTemplate("Pages/sign-up-form/account-sign-up");
                $pb->render();
                break;
            case "INVITE_CODE":
                $pb->setTemplate("Pages/sign-up-form/invite-code");
                $pb->render();
                break;
            case "USER_TYPE":
            default:
                $pb->setTemplate("Pages/sign-up-form/account-type");
                $pb->render();
                break;
        }
    }
    catch(DatabaseConnectionException|MessageSendException $e)
    {
        $pb->setDefaultVariables();
        $pb->setTemplate("ErrorPages/InternalSystemError");
        $technical_details = "Exception Type:\n    " . get_class($e);
        $technical_details .= "\nMessage:\n    " . $e->getMessage();
        $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
        $pb->setVariable("technical_details", $technical_details);
        http_response_code(500);
        $pb->render();
    }
}
catch(Exception $e)
{
    $pb->setDefaultVariables();
    $pb->setTemplate("ErrorPages/InternalSystemError");
    $technical_details = "Exception Type:\n    " . get_class($e);
    $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    $technical_details .= "\nMessage:\n" . $e->getMessage();
    $technical_details .= "\nStack Trace:\n\n" . $e->getTraceAsString();
    $pb->setVariable("technical_details", $technical_details);
    http_response_code(500);
    $pb->render();
}
