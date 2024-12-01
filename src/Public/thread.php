<?php

use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\Schemas\CaseSchema;
use Auxilium\Schemas\UserSchema;
use Auxilium\TwigHandling\PageBuilder;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';

$pb = PageBuilder::get_instance();
try
{
    $pb->requireLogin();

    $pb->setVariable("progressive_load", false);
    if(isset($_COOKIE["progressiveload"]))
    {
        if($_COOKIE["progressiveload"] == "true")
        {
            $pb->setVariable("progressive_load", true);
        }
    }

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
    if(count($uri_components) > 0)
    {
        if(mb_strlen($uri_components[0]) == 0)
        {
            array_shift($uri_components);
        }
    }
    if(count($uri_components) == 0)
    {
        header("Location: /thread/new");
        exit();
    }

    $path_primary = [];
    $action = "@view";
    $path_secondary = [];
    $sec_toggle = false;
    foreach($uri_components as &$uri_component)
    {
        if($sec_toggle)
        {
            array_push($path_secondary, $uri_component);
        }
        else
        {
            if(substr($uri_component, 0, 1) === "@")
            {
                $action = mb_strtolower($uri_component);
            }
            else
            {
                array_push($path_primary, $uri_component);
            }
        }
    }

    $last_prop = null;
    if($action == "@unlink")
    { // Remove the last element of the url
        $last_prop = array_pop($path_primary);
    }

    $primary_string_path = implode("/", $path_primary);
    $pb->setVariable("primary_string_path", $primary_string_path);

    $jwt_validation_passed = false; // This is used to make sure that a user has clicked a link that Auxilium has generated. 
    //This is not the current state of the url_metadata, rather the state it was in when we received the request

    $url_metadata = Auxilium\URLMetadata::from_jwt($get_params);
    if($url_metadata == null)
    {
        $url_metadata = new Auxilium\URLMetadata();
        $url_metadata->setPath($primary_string_path);
    }
    else
    {
        $jwt_validation_passed = $url_metadata->isSecureMatch(); // We want to use this as a CSRF token for a particular user
        if(!$jwt_validation_passed)
        {
            $url_metadata = new Auxilium\URLMetadata();
            $url_metadata->setPath($primary_string_path);
        }
    }
    $pb->setVariable("url_metadata", $url_metadata);
    $pb->setVariable("jwt_validation_passed", $jwt_validation_passed);

    //$node->getProperties();

    if($node == null)
    {
        http_response_code(404);
        $pb->setTemplate("Pages/node-views/404");
        $pb->render();
        exit();
    }
    else
    {
        $pb->setVariable("node", $node);
        switch($action)
        {
            case "@delete_confirm":
                if($jwt_validation_passed)
                {
                    if($node->extendsOrInstanceOf(URLHandling::GetURLForSchema(UserSchema::class)))
                    {
                        $pb->setTemplate("Pages/delete-views/generic");
                    }
                    elseif($node->extendsOrInstanceOf(URLHandling::GetURLForSchema(CaseSchema::class)))
                    {
                        $pb->setTemplate("Pages/delete-views/generic");
                    }
                    else
                    {
                        $pb->setTemplate("Pages/delete-views/generic");
                    }
                }
                else
                {
                    header("Location: /graph/" . $primary_string_path);
                    exit();
                }
                break;
            case "@delete":
                if($jwt_validation_passed)
                {
                    $node->delete();
                    $path = explode("/", $primary_string_path);
                    array_pop($path);
                    //echo implode("/", $path);
                    header("Location: /graph/" . implode("/", $path));
                    exit();
                }
                else
                {
                    header("Location: /graph/" . $primary_string_path);
                    exit();
                }
                break;
            case "@edit":
                if($jwt_validation_passed)
                {
                    //echo "EDIT";

                    if(isset($_POST["value"]))
                    {
                        $refs = $node->getReferences();
                        //echo "PEND: ".end($path_primary)." // ".implode("--", array_keys($refs));

                        $data = $_POST["value"];
                        $new_node = Auxilium\GraphDatabaseConnection::new_node($data, "text/plain");

                        foreach($refs as $ref_name => &$ref_nodes)
                        {
                            foreach($ref_nodes as &$ref_node)
                            {
                                $ref_node->addProperty($ref_name, $new_node, null, true);
                                //echo $ref_node->getId()." ==[".$ref_name."]=> ".$node->getId()."<br />";
                            }
                        }
                        $path = explode("/", $primary_string_path);
                        array_pop($path);
                        header("Location: /graph/" . implode("/", $path));
                        exit();
                        //$new_node = \auxilium\GraphDatabaseConnection::new_node($data, "text/plain");
                        //$query_result = $node->addProperty($_POST["name"], $return_node);
                    }
                    else
                    {
                        $pb->setTemplate("Pages/edit-views/text-plain");
                    }
                }
                else
                {
                    header("Location: /graph/" . $primary_string_path);
                    exit();
                }
                break;
            case "@unlink":
                if($jwt_validation_passed)
                {
                    //echo "Unlinking: ".$node->getId()." => ".$last_prop;
                    //exit();
                    if($url_metadata->getProperty("uln") != null)
                    {
                        //echo "Unlinking: ".$node->getId()." => ".$last_prop."<br />";
                        $prop = $node->getProperty($last_prop);
                        if($prop != null)
                        {
                            if($prop->getId() == $url_metadata->getProperty("uln"))
                            { // Make sure the property hasn't changed since when the link was generated - the user expects the thing they clicked to be removed, not some other random thing with the same path.
                                $node->unlinkProperty($last_prop);
                            }
                        }
                        //exit();
                        //$node->unlinkProperty($last_prop);
                        header("Location: /graph/" . $primary_string_path);
                        exit();
                    }
                }
                else
                {
                    header("Location: /graph/" . $primary_string_path);
                    exit();
                    //$action = "@view";
                }
                break;
            case "@new_property":
                if($jwt_validation_passed)
                {
                    if($url_metadata->getProperty("rcn") != null)
                    {
                        if(isset($_POST["name"]))
                        {
                            //echo $node->getId()." => ".$_POST["name"]." => ".\auxilium\URLMetadata::expand_crushed_uuid(\auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));

                            //exit();
                            $return_node_id = Auxilium\URLMetadata::expand_crushed_uuid(Auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));
                            $return_node = Auxilium\Node::from_id($return_node_id);
                            $query_result = $node->addProperty($_POST["name"], $return_node);
                            if($query_result !== false)
                            {
                                //var_dump($query_result);
                                //exit();
                                $ret_url = $url_metadata->popFromReturnStack();
                                if($ret_url == null)
                                {
                                    $ret_url = "/graph/" . $primary_string_path;
                                }
                                $url_metadata->setProperty("rcn", null);
                                header("Location: " . $ret_url . "?" . $url_metadata);
                                exit();
                            }
                            //echo "Could not link: ".$node->getId()." => ".$_POST["name"]." => ".\auxilium\URLMetadata::expand_crushed_uuid(\auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));
                            //exit();
                            $pb->setVariable("duplicate_property_name", $_POST["name"]);
                            $pb->setTemplate("Pages/node-views/name-new-property");
                        }
                        else
                        {
                            $pb->setTemplate("Pages/node-views/name-new-property");
                        }
                    }
                    else
                    {
                        $url_metadata->pushCurrentToReturnStack();

                        $form_list = file_get_contents(WEB_ROOT_DIRECTORY . "/property-forms.json");
                        $form_list = json_decode($form_list, true);

                        /* 
                        // Now handled in URLMetadata class
                        $url_metadata_with_tgn = clone $url_metadata;
                        $url_metadata_with_tgn->setProperty("tgn", \auxilium\EncodingTools::base64_encode_url_safe(\auxilium\URLMetadata::crush_uuid($node->getId())));
                        $pb->setVariable("url_metadata_with_tgn", $url_metadata_with_tgn);
                        */

                        $pb->setVariable("form_list", $form_list);

                        $pb->setTemplate("Pages/node-views/new-property");
                    }
                }
                else
                {
                    $pb->setTemplate("Pages/node-views/generic");
                }
                break;
            case "@search":
                $pb->setTemplate("Pages/node-views/search");
                break;
            case "@references":
                $pb->setTemplate("Pages/node-views/references");
                break;
            case "@ref_error":
                $pb->setVariable("top_error_message", "PATH_REFERENCE_MISMATCH");
            case "@view":
            default:
                if($node->extendsOrInstanceOf(URLHandling::GetURLForSchema(UserSchema::class)))
                {
                    $pb->setTemplate("Pages/node-views/user");
                    $pb->setVariable("hidden_props", ["cases", "messages", "documents"]);
                }
                elseif($node->extendsOrInstanceOf(URLHandling::GetURLForSchema(CaseSchema::class)))
                {
                    $pb->setTemplate("Pages/node-views/case");
                    $pb->setVariable("hidden_props", ["description", "clients", "messages", "documents"]);
                }
                else
                {
                    $pb->setTemplate("Pages/node-views/generic");
                }
        }
    }

    $pb->render();

}
catch(DatabaseConnectionException $e)
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
