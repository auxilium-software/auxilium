<?php
require_once "environment.php";

$pb = \auxilium\PageBuilder::get_instance();
try {
    try {
        $pb->requireLogin();
        
        $pb->setVariable("progressive_load", false);
        if(isset($_COOKIE["progressiveload"])) {
            if ($_COOKIE["progressiveload"] == "true") {
                $pb->setVariable("progressive_load", true);
            }
        }

        $uri_components = explode("/", $_SERVER["REQUEST_URI"]);
        $last_uri_component = explode("?", end($uri_components));
        $get_params = "";
        if (count($last_uri_component) > 1) {
            $get_params = $last_uri_component[1];
        }
        $uri_components[count($uri_components) - 1] = $last_uri_component[0];

        array_shift($uri_components);
        $method = array_shift($uri_components);
        if (count($uri_components) > 0) {
            if (mb_strlen(end($uri_components)) == 0) {
                array_pop($uri_components);
            }
        }
        if (count($uri_components) > 0) {
            if (mb_strlen($uri_components[0]) == 0) {
                array_shift($uri_components);
            }
        }
        if (count($uri_components) == 0) {
            header("Location: /graph/~".\auxilium\Session::get_current()->getUser()->getId());
            exit();
        }

        $path_primary = [];
        $action = "@view";
        $path_secondary = [];
        $sec_toggle = false;
        foreach ($uri_components as &$uri_component) {
            if ($sec_toggle) {
                array_push($path_secondary, $uri_component);
            } else {
                if (substr($uri_component, 0, 1) === "@") {
                    if (mb_strtolower($uri_component) == "@creator") {
                        array_push($path_primary, $uri_component);
                    } else {
                        $action = mb_strtolower($uri_component);
                    }
                } else {
                    array_push($path_primary, $uri_component);
                }
            }
        }

        $last_prop = null;
        if ($action == "@unlink") { // Remove the last element of the url
            $last_prop = array_pop($path_primary);
        }

        $primary_string_path = implode("/", $path_primary);
        $pb->setVariable("primary_string_path", $primary_string_path);

        $path_parsed = [];
        for ($i = 0; $i < count($path_primary); $i++) {
            if (strpos(urldecode($path_primary[$i]), "~") === 0) {
                $path_parsed[$i] = "{".strtoupper(substr(urldecode($path_primary[$i]), 1))."}";
            } else {
                $path_parsed[$i] = urldecode($path_primary[$i]);
            }
        }

        $deegraph_path = implode("/", $path_parsed);
        $pb->setVariable("deegraph_path", $deegraph_path);

        if ($pb->getVariable("progressive_load")) {
            $primary_node_path_order = [];
            $primary_node_deegraph_paths = [];
            $absolute_path = "";
            for ($i = 0; $i < count($path_primary); $i++) {
                $np = implode("/", array_slice($path_primary, 0, $i + 1));
                array_push($primary_node_path_order, $np);
                $pth_prim = $path_primary[$i];
                $absolute_path = $absolute_path."/".$path_primary[$i];
                
                if ((strpos($pth_prim, "~") === 0) || preg_match('/^[0-9]*$/', $pth_prim)) {
                    $absolute_path = implode("/", array_slice($path_parsed, 0, $i + 1));
                }
                $primary_node_deegraph_paths[$np] = $absolute_path;
            }
            $pb->setVariable("primary_node_path_order", $primary_node_path_order);
            $pb->setVariable("primary_node_deegraph_paths", $primary_node_deegraph_paths);
        } else {
            $primary_node_path_order = [];
            $primary_node_path_names = [];
            $primary_node_path_nodes = [];
            for ($i = 0; $i < count($path_primary); $i++) {
                $np = implode("/", array_slice($path_primary, 0, $i + 1));
                array_push($primary_node_path_order, $np);
                $pth_prim = $path_primary[$i];
                
                if ((strpos($pth_prim, "~") === 0) || preg_match('/^[0-9]*$/', $pth_prim)) {
                    $absolute_path = implode("/", array_slice($path_parsed, 0, $i + 1));
                    $primary_node_path_nodes[$np] = \auxilium\Node::from_path($absolute_path);
                    if ($primary_node_path_nodes[$np] != null) {
                        if ($primary_node_path_nodes[$np]->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/user.json")) {
                            if ($primary_node_path_nodes[$np]->is(\auxilium\Session::get_current()->getUser())) {
                                $primary_node_path_names[$np] = "::auxpckstr:ui_heading/my_account::";
                                $pb->setVariable("is_own_account", true);
                            } else {
                                $primary_node_path_names[$np] = $primary_node_path_nodes[$np]->getProperty("name");
                            }
                        } elseif ($primary_node_path_nodes[$np]->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/case.json")) {
                            $primary_node_path_names[$np] = $primary_node_path_nodes[$np]->getProperty("title");
                        } elseif ($primary_node_path_nodes[$np]->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/message.json")) {
                            $primary_node_path_names[$np] = "Message";
                        } elseif ($primary_node_path_nodes[$np]->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/organisation.json")) {
                            $primary_node_path_names[$np] = $primary_node_path_nodes[$np]->getProperty("name");
                        }
                    }
                } else {
                    if (\auxilium\MicroTemplate::does_template_exist("data_types/".$pth_prim)) {
                        $primary_node_path_names[$np] = "::auxpckstr:data_types/".$pth_prim."::";
                    } else {
                        $primary_node_path_names[$np] = str_replace("_", " ", $pth_prim);
                    }
                }
            }
            $pb->setVariable("primary_node_path_order", $primary_node_path_order);
            $pb->setVariable("primary_node_path_names", $primary_node_path_names);
            $pb->setVariable("primary_node_path_nodes", $primary_node_path_nodes);
            $pb->setVariable("primary_node_path_name", end($primary_node_path_names));
        }

        $node = \auxilium\Node::from_path($primary_string_path);

        $jwt_validation_passed = false; // This is used to make sure that a user has clicked a link that Auxilium has generated. 
        //This is not the current state of the url_metadata, rather the state it was in when we received the request

        $url_metadata = \auxilium\URLMetadata::from_jwt($get_params);
        if ($url_metadata == null) {
            $url_metadata = new \auxilium\URLMetadata();
            $url_metadata->setPath($primary_string_path);
        } else {
            if ($url_metadata->checkPath($primary_string_path)) { // Both path parts match -> this was likely a clicked or history link
                if ($url_metadata->checkNode($node)) { // Check the end result is the node we expected - otherwise throw error - the database has changed what we're looking at!
                    $jwt_validation_passed = $url_metadata->isSecureMatch(); // We don't just want to check validity - we want to use this as a CSRF token for a particular user
                    if (!$jwt_validation_passed) {
                        $url_metadata = new \auxilium\URLMetadata();
                        $url_metadata->setPath($primary_string_path);
                    }
                } else {
                    if ($node != null) {
                        header("Location: /graph/~".$node->getId()."/@ref_error");
                        exit();
                    }
                    $url_metadata = new \auxilium\URLMetadata();
                    $url_metadata->setPath($primary_string_path);
                }
            } else {
                $url_metadata = new \auxilium\URLMetadata();
                $url_metadata->setPath($primary_string_path);
            }
        }
        $pb->setVariable("url_metadata", $url_metadata);
        $pb->setVariable("root_url_metadata", new \auxilium\URLMetadata());
        $pb->setVariable("jwt_validation_passed", $jwt_validation_passed);

        //$node->getProperties();

        if ($node == null) {
            http_response_code(404);
            $pb->setTemplate("node-views/404");
            $pb->render();
            exit();
        } else {
            $pb->setVariable("node", $node);
            switch ($action) {
                case "@delete_confirm":
                    if ($jwt_validation_passed) {
                        if ($node->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/user.json")) {
                            $pb->setTemplate("delete-views/generic");
                        } elseif ($node->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/case.json")) {
                            $pb->setTemplate("delete-views/generic");
                        } else {
                            $pb->setTemplate("delete-views/generic");
                        }
                    } else {
                        header("Location: /graph/".$primary_string_path);
                        exit();
                    }
                    break;
                case "@delete":
                    if ($jwt_validation_passed) {
                        $node->delete();
                        $path = explode("/", $primary_string_path);
                        array_pop($path);
                        //echo implode("/", $path);
                        header("Location: /graph/".implode("/", $path));
                        exit();
                    } else {
                        header("Location: /graph/".$primary_string_path);
                        exit();
                    }
                    break;
                case "@edit":
                    if ($jwt_validation_passed) {
                        //echo "EDIT";
                        
                        if (isset($_POST["value"])) {
                            $refs = $node->getReferences();
                            //echo "PEND: ".end($path_primary)." // ".implode("--", array_keys($refs));
                            
                            $data = $_POST["value"];
                            $new_node = \auxilium\GraphDatabaseConnection::new_node($data, "text/plain");
                            
                            foreach ($refs as $ref_name => &$ref_nodes) {
                                foreach ($ref_nodes as &$ref_node) {
                                    $ref_node->addProperty($ref_name, $new_node, null, true);
                                    //echo $ref_node->getId()." ==[".$ref_name."]=> ".$node->getId()."<br />";
                                }
                            }
                            $path = explode("/", $primary_string_path);
                            array_pop($path);
                            header("Location: /graph/".implode("/", $path));
                            exit();
                            //$new_node = \auxilium\GraphDatabaseConnection::new_node($data, "text/plain");
                            //$query_result = $node->addProperty($_POST["name"], $return_node);
                        } else {
                            $pb->setTemplate("edit-views/text-plain");
                        }
                    } else {
                        header("Location: /graph/".$primary_string_path);
                        exit();
                    }
                    break;
                case "@unlink":
                    if ($jwt_validation_passed) {
                        //echo "Unlinking: ".$node->getId()." => ".$last_prop;
                        //exit();
                        if ($url_metadata->getProperty("uln") != null) {
                            //echo "Unlinking: ".$node->getId()." => ".$last_prop."<br />";
                            $prop = $node->getProperty($last_prop);
                            if ($prop != null) {
                                if ($prop->getId() == $url_metadata->getProperty("uln")) { // Make sure the property hasn't changed since when the link was generated - the user expects the thing they clicked to be removed, not some other random thing with the same path.
                                    $node->unlinkProperty($last_prop);
                                }
                            }
                            //exit();
                            //$node->unlinkProperty($last_prop);
                            header("Location: /graph/".$primary_string_path);
                            exit();
                        }
                    } else {
                        header("Location: /graph/".$primary_string_path);
                        exit();
                        //$action = "@view";
                    }
                    break;
                case "@new_property":
                    if ($jwt_validation_passed) {
                        if ($url_metadata->getProperty("rcn") != null) {
                            if (isset($_POST["name"])) {
                                //echo $node->getId()." => ".$_POST["name"]." => ".\auxilium\URLMetadata::expand_crushed_uuid(\auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));
                                
                                //exit();
                                $return_node_id = \auxilium\URLMetadata::expand_crushed_uuid(\auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));
                                $return_node = \auxilium\Node::from_id($return_node_id);
                                $query_result = $node->addProperty($_POST["name"], $return_node);
                                if ($query_result !== false) {
                                    //var_dump($query_result);
                                    //exit();
                                    $ret_url = $url_metadata->popFromReturnStack();
                                    if ($ret_url == null) {
                                        $ret_url = "/graph/".$primary_string_path;
                                    }
                                    $url_metadata->setProperty("rcn", null);
                                    header("Location: ".$ret_url."?".$url_metadata);
                                    exit();
                                }
                                //echo "Could not link: ".$node->getId()." => ".$_POST["name"]." => ".\auxilium\URLMetadata::expand_crushed_uuid(\auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));
                                //exit();
                                $pb->setVariable("duplicate_property_name", $_POST["name"]);
                                $pb->setTemplate("node-views/name-new-property");
                            } else {
                                $pb->setTemplate("node-views/name-new-property");
                            }
                        } else {
                            $url_metadata->pushCurrentToReturnStack();
                            
                            $form_list = file_get_contents(WEB_ROOT_DIRECTORY."/property-forms.json");
                            $form_list = json_decode($form_list, true);
                            
                            /* 
                            // Now handled in URLMetadata class
                            $url_metadata_with_tgn = clone $url_metadata;
                            $url_metadata_with_tgn->setProperty("tgn", \auxilium\EncodingTools::base64_encode_url_safe(\auxilium\URLMetadata::crush_uuid($node->getId())));
                            $pb->setVariable("url_metadata_with_tgn", $url_metadata_with_tgn);
                            */
                            
                            $pb->setVariable("form_list", $form_list);
                            
                            $pb->setTemplate("node-views/new-property");
                        }
                    } else {
                        $pb->setTemplate("node-views/generic");
                    }
                    break;
                case "@search":
                    $pb->setTemplate("node-views/search");
                    break;
                case "@references":
                    $pb->setTemplate("node-views/references");
                    break;
                case "@ref_error":
                    $pb->setVariable("top_error_message", "PATH_REFERENCE_MISMATCH");
                case "@view":
                default:
                    if ($node->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/user.json")) {
                        $login_methods = [];
                        $bind_variables = [
                            "user_uuid" => $node->getId(),
                        ];
                        $sql = "SELECT email_address, user_uuid FROM standard_logins WHERE user_uuid=:user_uuid";
                        $statement = \auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($bind_variables);
                        $user_data = $statement->fetch();
                        if ($user_data != null) {
                            array_push($login_methods, [
                                "type" => "classic"
                            ]);
                        }
                        
                        $bind_variables = [
                            "user_uuid" => $node->getId()
                        ];
                        $sql = "SELECT unique_sub, user_uuid FROM oauth_logins WHERE user_uuid=:user_uuid";
                        $statement = \auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($bind_variables);
                        $returned_data = $statement->fetch();
                        while ($returned_data != null) {
                            array_push($login_methods, [
                                "type" => "oauth",
                                "vendor" => explode("/", $returned_data["unique_sub"])[0]
                            ]);
                            $returned_data = $statement->fetch();
                        }
                        
                        if ($node->getId() == \auxilium\Session::get_current()->getUser()->getId()) {
                            $pb->setVariable("is_own_account", true);
                        }
                        
                        $pb->setVariable("login_methods", $login_methods);
                            //[
                            //    "type" => "oauth",
                            //    "vendor" => "microsoft"
                            //]
                        //$pb->setVariable("permissions", true);
                        $pb->setTemplate("node-views/user");
                        $pb->setVariable("hidden_props", ["cases", "messages", "documents"]);
                        
                        //$pb->setVariable("traditional_login_method", []);
                    } elseif ($node->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/case.json")) {
                        $pb->setTemplate("node-views/case");
                        $pb->setVariable("hidden_props", ["description", "clients", "messages", "documents", "todos", "timeline", "workers"]);
                    } elseif ($node->extendsOrInstanceOf("https://schemas.auxiliumsoftware.co.uk/v1/organisation.json")) {
                        $pb->setTemplate("node-views/group");
                        $pb->setVariable("hidden_props", ["departments", "cases", "staff"]);
                    } else {
                        $pb->setTemplate("node-views/generic");
                    }
            }
        }

        $pb->render();

    } catch (\auxilium\DatabaseConnectionException $e) {
        $pb->setDefaultVariables();
        $pb->setTemplate("internal-system-error");
        $technical_details = "Exception Type:\n    ".get_class($e);
        $technical_details .= "\nMessage:\n    ".$e->getMessage();
        $technical_details .= "\nURI:\n    ".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
        $pb->setVariable("technical_details", $technical_details);
        http_response_code(500);
        $pb->render();
    }
} catch (\Exception $e) {
    $pb->setDefaultVariables();
    $pb->setTemplate("internal-system-error");
    $technical_details = "Exception Type:\n    ".get_class($e);
    $technical_details .= "\nURI:\n    ".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
    $pb->setVariable("technical_details", $technical_details);
    http_response_code(500);
    $pb->render();
}
?>
