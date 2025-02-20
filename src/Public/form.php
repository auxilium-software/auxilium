<?php

use Auxilium\AuxiliumScript;
use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\GraphDatabaseConnection;
use Auxilium\Helpers\FormBuilder\FormBuilderHelpers;
use Auxilium\Helpers\FormBuilder\FormBuilderOnSubmitHelpers;
use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\URLMetadata;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

$pb = PageBuilder::get_instance();

$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$last_uri_component = explode("?", end($uri_components));
$get_params = "";
if(count($last_uri_component) > 1)
{
    $get_params = $last_uri_component[1];
}
$uri_components[count($uri_components) - 1] = $last_uri_component[0];

array_shift($uri_components);
$method = array_shift($uri_components);
if(count($uri_components) > 0)
{
    if(mb_strlen(end($uri_components)) == 0)
    {
        array_pop($uri_components);
    }
}

$jwt_validation_passed = false; // This is used to make sure that a user has clicked a link that Auxilium has generated.
//This is not the current state of the url_metadata, rather the state it was in when we received the request

$url_metadata = URLMetadata::from_jwt($get_params);
if($url_metadata == null)
{
    $url_metadata = new URLMetadata();
    $url_metadata->setPath($primary_string_path);
}
else
{
    $jwt_validation_passed = $url_metadata->isValid();
    if(!$jwt_validation_passed)
    {
        $url_metadata = new URLMetadata();
    }
}
$pb->setVariable("url_metadata", $url_metadata);
$pb->setVariable("root_url_metadata", new URLMetadata());
$pb->setVariable("jwt_validation_passed", $jwt_validation_passed);

$target_node = $url_metadata->getProperty("tn");
if($target_node != null)
{
    $target_node = DeegraphNode::from_id(URLMetadata::expand_crushed_uuid($target_node));
}

$pb->setTemplate("Pages/invalid");
if(!isset($uri_components[0]))
{
    $pb->render();
}

FormBuilderHelpers::CheckForPathTraversal($uri_components);

if(file_exists(__DIR__ . "/../Configuration/FormDefinitions/" . $uri_components[0] . ".json"))
{
    $fpid = null;

    FormBuilderHelpers::CreateTempDirectory();

    if($url_metadata->getProperty("fpid") != null)
    {
        if(preg_match('/^[a-f0-9-]+$/', $url_metadata->getProperty("fpid")))
        { // As much as we should be able to trust this value since it comes from the JWT, there's nothing wrong with verifying there's nothing malicious here.
            if(file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/FormsInProgress/" . $url_metadata->getProperty("fpid") . ".json"))
            {
                $fpid = $url_metadata->getProperty("fpid");
            }
        }
    }

    $form_persistent_data = null;
    $form_persistence_file = null;

    if($fpid == null)
    {
        $fpth = null;
        do
        {
            $fpid = EncodingTools::GenerateNewUUID();
            $fpth = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/FormsInProgress/" . $fpid . ".json";
        } while(file_exists($fpth));
        $url_metadata->setProperty("fpid", $fpid);
        $form_persistence_file = fopen($fpth, "w") or die("Unable to open file!");
        $form_persistent_data = [];
    }
    else
    {
        $fpth = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/FormsInProgress/" . $fpid . ".json";
        $form_persistence_file = fopen($fpth, "r") or die("Unable to open file!");
        $form_persistent_data = file_get_contents($fpth);
        fclose($form_persistence_file);
        $form_persistence_file = fopen($fpth, "w") or die("Unable to open file!");
        $form_persistent_data = json_decode($form_persistent_data, true);
    }

    $definition = file_get_contents(__DIR__ . "/../Configuration/FormDefinitions/" . $uri_components[0] . ".json");
    $definition = json_decode($definition, true);

    if(!isset($form_persistent_data["variables"]))
    {
        $form_persistent_data["variables"] = [];
    }

    foreach($_POST as $key => &$var)
    {
        if(substr($key, 0, 1) === "\$")
        {
            $form_persistent_data["variables"][substr($key, 1)] = $var;
        }
    }

    $internal_vars = [
        "user" => Session::get_current()->getUser()
    ];

    if($target_node != null)
    {
        $internal_vars["target"] = $target_node;
    }

    foreach($form_persistent_data["variables"] as $key => &$var)
    {
        $internal_vars["form_var_$key"] = $var;
    }

    $page_index = [];

    for($idx = 0; $idx < count($definition["pages"]); $idx++)
    {
        if(!isset($definition["pages"][$idx]["id"]))
        {
            $definition["pages"][$idx]["id"] = "dyngen_page_" . $idx;
        }
    }
    for($idx = 0; $idx < count($definition["pages"]); $idx++)
    {
        $page_index[$definition["pages"][$idx]["id"]] = $definition["pages"][$idx];
    }

    $last_page = null;
    $target_page = null;
    $next_page = null;

    if(isset($form_persistent_data["last_page"]))
    {
        $last_page = $form_persistent_data["last_page"];
    }

    $allowed_page_keys = [];
    foreach(array_keys($page_index) as $page_key)
    {
        if(!isset($page_index[$page_key]["if"]))
        {
            $allowed_page_keys[] = $page_key;
        }
        elseif(Auxilium\AuxiliumScript::evaluate_expression($page_index[$page_key]["if"], $internal_vars))
        {
            $allowed_page_keys[] = $page_key;
        }
    }

    $last_page_index = 0;
    if($last_page != null)
    {
        $last_page_index = array_search($last_page, $allowed_page_keys);
        if($last_page_index === false)
        {
            $last_page_index = 0;
        }
    }
    $target_page_index = $last_page_index;

    $review_page = false;
    $form_submitted = false;

    if(isset($_POST["continue_button"]))
    {
        $target_page_index = $last_page_index + 1;
    }
    else
    {
        foreach(array_keys($_POST) as $post_key)
        {
            if(substr($post_key, 0, 11) == "nav_button_")
            {
                if(substr($post_key, 0, 20) == "nav_button_rev_from_")
                {
                    $last_page_index_from_button = array_search(substr($post_key, 20), $allowed_page_keys);
                    if($last_page_index_from_button === false)
                    {
                        $last_page_index_from_button = $last_page_index;
                    }
                    $target_page_index = ($last_page_index > 1) ? ($last_page_index - 1) : 0;
                }
                elseif(substr($post_key, 0, 20) == "nav_button_fwd_from_")
                {
                    $last_page_index_from_button = array_search(substr($post_key, 20), $allowed_page_keys);
                    if($last_page_index_from_button === false)
                    {
                        $last_page_index_from_button = $last_page_index;
                    }
                    $target_page_index = $last_page_index + 1;
                }
                else
                {
                    $target_page_temp = substr($post_key, 11);
                    $target_page_index = array_search($target_page_temp, $allowed_page_keys);
                    if($target_page_index === false)
                    {
                        $target_page_index = $last_page_index + 1;
                    }
                }
            }
        }
    }

    if($target_page_index < count($allowed_page_keys))
    {
        $target_page = $allowed_page_keys[$target_page_index];
    }
    else
    {
        // We've gone past the end and need to display the review page
        if(isset($definition["final_review"]))
        {
            if($definition["final_review"])
            {
                $review_page = true;
            }
        }
    }

    if(isset($_POST["submit_button"]))
    {
        $form_submitted = true;

        if($target_node != null)
        {
            $form_persistent_data["target_node"] = $target_node->getId();
        }

        $as_node = Session::get_current()->getUser();
        $export = null;
        $navigate = null;
        $navigate_replace = false;

        foreach($definition["on_submit"] as &$action)
        {
            $skip = true;
            if(!isset($action["if"]))
            {
                $skip = false;
            }
            elseif(AuxiliumScript::evaluate_expression($action["if"], $internal_vars))
            {
                $skip = false;
            }
            if(!$skip)
            {
                switch($action["type"])
                {
                    case "NEW_NODE":
                        FormBuilderOnSubmitHelpers::NewNode(
                            internal_vars: $internal_vars,
                            as_node      : $as_node,
                            action       : $action,
                            fvars        : $fvars,
                        );
                        break;
                    case "PERMISSION":
                        FormBuilderOnSubmitHelpers::Permission(
                            internal_vars: $internal_vars,
                            as_node      : $as_node,
                            action       : $action,
                            fvars        : $fvars,
                        );
                        break;
                    case "LINK":
                        FormBuilderOnSubmitHelpers::Link(
                            internal_vars: $internal_vars,
                            as_node      : $as_node,
                            action       : $action,
                            fvars        : $fvars,
                        );
                        break;
                    case "SET":
                        FormBuilderOnSubmitHelpers::Set(
                            internal_vars: $internal_vars,
                            action       : $action,
                        );
                        break;
                    case "EXPORT":
                        FormBuilderOnSubmitHelpers::Export(
                            internal_vars: $internal_vars,
                            action       : $action,
                            export       : $export,
                        );
                        break;
                    case "NAVIGATE":
                        FormBuilderOnSubmitHelpers::Navigate(
                            internal_vars   : $internal_vars,
                            action          : $action,
                            navigate        : $navigate,
                            navigate_replace: $navigate_replace,
                        );
                        break;
                    default:
                        // Handle unrecognized actions gracefully by displaying an error message
                        echo "<h3>UNKNOWN ACTION " . $action["type"] . "</h3>";
                }
            }
        }

        //echo "<pre>";
        //echo ($export == null) ? "Nothing to export" : ((is_string($export)) ? $export : $export->GetNodeID());
        //echo "</pre>";

        if($export != null)
        {
            if(is_a($export, DeegraphNode::class))
            {
                $url_metadata->setProperty("rcn", EncodingTools::Base64EncodeURLSafe(URLMetadata::crush_uuid($export->getId())));
                $url_metadata->setProperty("exp", null);
            }
            else
            {
                $url_metadata->setProperty("rcn", null);
                $url_metadata->setProperty("exp", $export);
            }
        }
        else
        {
            $url_metadata->setProperty("rcn", null);
            $url_metadata->setProperty("exp", null);
        }

        $return_to = $navigate;
        if($return_to == null)
        {
            $return_to = $url_metadata->popFromReturnStack();
        }
        else
        {
            if($navigate_replace)
            {
                $url_metadata->popFromReturnStack(); // Throwaway where it would have sent us
            }
        }

        if($return_to == null)
        {
            if($as_node == null)
            {
                echo "<h2>ANONYMOUS_SUBMISSION</h2>";
            }
            echo "<pre>";
            echo htmlentities(json_encode($form_persistent_data, JSON_PRETTY_PRINT));
            echo "</pre><hr />";
            echo "<pre>";
            echo ($export == null) ? "Nothing to export" : ((is_string($export)) ? $export : $export->getId());
            echo "</pre>";
            exit();
        }
        else
        {
            $ret_url_full = $return_to . "?" . $url_metadata;
            NavigationUtilities::Redirect(target: "$ret_url_full");
        }
    }

    if($target_page_index == (count($allowed_page_keys) - 1))
    {
        if(isset($definition["final_review"]))
        {
            if($definition["final_review"])
            {
                $pb->setVariable("next_page_is_review", true);
            }
            else
            {
                $pb->setVariable("next_page_is_send", true);
            }
        }
        else
        {
            $pb->setVariable("next_page_is_send", true);
        }
    }
    elseif(!$review_page)
    {
        $pb->setVariable("next_page", $allowed_page_keys[$target_page_index + 1]);
    }

    if($target_page_index > 0)
    {
        $pb->setVariable("last_page", $allowed_page_keys[$target_page_index - 1]);
    }

    if(!$review_page)
    {
        $pb->setVariable("current_page", $allowed_page_keys[$target_page_index]);
    }

    if($form_submitted)
    {
        $pb->setTemplate("Pages/form-submitted");
    }
    else
    {
        if($review_page)
        {
            $review_copy = $definition["review"];
            $review_components = [];

            foreach($review_copy["components"] as &$component_ref)
            {
                $component = $component_ref;
                $skip = true;
                if(!isset($component["if"]))
                {
                    $skip = false;
                }
                elseif(AuxiliumScript::evaluate_expression($component["if"], $internal_vars))
                {
                    $skip = false;
                }
                if(!$skip)
                {
                    switch($component["type"])
                    {
                        case "SUBHEADING":
                            if(isset($component["jump_to_page"]))
                            {
                                if(!in_array($component["jump_to_page"], $allowed_page_keys))
                                {
                                    unset($component["jump_to_page"]); // Important to hide this link - the user might get confused if we show them an edit button for an uneditable field
                                }
                            }
                        case "LABEL":
                        case "PARAGRAPH":
                            if(isset($component["value"]))
                            {
                                $var = AuxiliumScript::evaluate_variable_path($component["value"], $internal_vars);
                                if(is_a($var, DeegraphNode::class))
                                {
                                    $component["object"] = $var;
                                }
                                else
                                {
                                    $component["text"] = $var;
                                }
                            }
                            break;
                        case "DESCRIPTION_LIST":
                            if(isset($component["dictionary"]))
                            {
                                foreach($component["dictionary"] as $dkey => &$dvar)
                                {
                                    $var = AuxiliumScript::evaluate_variable_path($dvar, $internal_vars);
                                    if(is_a($var, DeegraphNode::class))
                                    {
                                        $dvar = ["object" => $var, "text" => $dvar];
                                    }
                                    else
                                    {
                                        $dvar = ["text" => $var];
                                    }
                                }
                            }
                            break;
                    }
                    $review_components[] = $component;
                }
            }

            $review_copy["components"] = $review_components;
            $pb->setVariable("variables", $form_persistent_data["variables"]);
            $pb->setVariable("review_definition", $review_copy);

            $pb->setTemplate("Pages/form-review");
        }
        else
        {
            $form_persistent_data["last_page"] = $target_page;

            //echo $target_page;

            // Display the correct page based on id
            foreach($definition["pages"] as &$page)
            {
                if($page["id"] == $target_page)
                {
                    foreach($page["components"] as &$component)
                    {
                        if(isset($component["default_value"]))
                        {
                            $component["default_value"] = AuxiliumScript::evaluate_variable_path($component["default_value"], $internal_vars);
                        }
                        elseif(isset($component["options"]))
                        {
                            foreach($component["options"] as &$option)
                            {
                                if(isset($option["value"]))
                                {
                                    $option["value"] = AuxiliumScript::evaluate_variable_path($option["value"], $internal_vars);
                                }
                            }
                        }
                    }

                    $pb->setVariable("page_definition", $page);
                }
            }

            $pb->setVariable("variables", $form_persistent_data["variables"]);
            $pb->setTemplate("Pages/form-page");
        }
    }

    fwrite($form_persistence_file, json_encode($form_persistent_data));
    fclose($form_persistence_file);
}

$pb->render();
