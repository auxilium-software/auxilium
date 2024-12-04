<?php

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBServerConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBTable;
use Auxilium\DatabaseInteractions\MariaDB\SQLQueryBuilderWrapper;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\Schemas\CaseSchema;
use Auxilium\Schemas\MessageSchema;
use Auxilium\Schemas\OrganisationSchema;
use Auxilium\Schemas\UserSchema;
use Auxilium\SessionHandling\Security;
use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\URLMetadata;
use Auxilium\Utilities\NavigationUtilities;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';


try
{
    try
    {
        Security::RequireLogin();


        PageBuilder2::AddVariable("progressive_load", false);
        if(isset($_COOKIE["progressiveload"]))
        {
            if($_COOKIE["progressiveload"] == "true")
            {
                PageBuilder2::AddVariable("progressive_load", true);
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
            NavigationUtilities::Redirect(target: "/graph/~" . Session::get_current()->getUser()->GetNodeID());
        }

        $path_primary = [];
        $action = "@view";
        $path_secondary = [];
        $sec_toggle = false;
        foreach($uri_components as &$uri_component)
        {
            if($sec_toggle)
            {
                $path_secondary[] = $uri_component;
            }
            else
            {
                if(str_starts_with($uri_component, "@"))
                {
                    if(mb_strtolower($uri_component) == "@creator")
                    {
                        $path_primary[] = $uri_component;
                    }
                    else
                    {
                        $action = mb_strtolower($uri_component);
                    }
                }
                else
                {
                    $path_primary[] = $uri_component;
                }
            }
        }

        $last_prop = null;
        if($action == "@unlink")
        { // Remove the last element of the url
            $last_prop = array_pop($path_primary);
        }

        $primary_string_path = implode("/", $path_primary);
        PageBuilder2::AddVariable("primary_string_path", $primary_string_path);

        $path_parsed = [];
        for($i = 0; $i < count($path_primary); $i++)
        {
            if(str_starts_with(urldecode($path_primary[$i]), "~"))
            {
                $path_parsed[$i] = "{" . strtoupper(substr(urldecode($path_primary[$i]), 1)) . "}";
            }
            else
            {
                $path_parsed[$i] = urldecode($path_primary[$i]);
            }
        }

        $deegraph_path = implode("/", $path_parsed);
        PageBuilder2::AddVariable("deegraph_path", $deegraph_path);

        if(PageBuilder2::GetVariable("progressive_load"))
        {
            $primary_node_path_order = [];
            $primary_node_deegraph_paths = [];
            $absolute_path = "";
            for($i = 0; $i < count($path_primary); $i++)
            {
                $np = implode("/", array_slice($path_primary, 0, $i + 1));
                $primary_node_path_order[] = $np;
                $pth_prim = $path_primary[$i];
                $absolute_path = $absolute_path . "/" . $path_primary[$i];

                if((str_starts_with($pth_prim, "~")) || preg_match('/^[0-9]*$/', $pth_prim))
                {
                    $absolute_path = implode("/", array_slice($path_parsed, 0, $i + 1));
                }
                $primary_node_deegraph_paths[$np] = $absolute_path;
            }
            PageBuilder2::AddVariable("primary_node_path_order", $primary_node_path_order);
            PageBuilder2::AddVariable("primary_node_deegraph_paths", $primary_node_deegraph_paths);
        }
        else
        {
            $primary_node_path_order = [];
            $primary_node_path_names = [];
            $primary_node_path_nodes = [];
            for($i = 0; $i < count($path_primary); $i++)
            {
                $np = implode("/", array_slice($path_primary, 0, $i + 1));
                $primary_node_path_order[] = $np;
                $pth_prim = $path_primary[$i];

                if((str_starts_with($pth_prim, "~")) || preg_match('/^[0-9]*$/', $pth_prim))
                {
                    $absolute_path = implode("/", array_slice($path_parsed, 0, $i + 1));
                    $primary_node_path_nodes[$np] = DeegraphNode::FromPath($absolute_path);
                    if($primary_node_path_nodes[$np] != null)
                    {
                        if($primary_node_path_nodes[$np]->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(UserSchema::class)))
                        {
                            if($primary_node_path_nodes[$np]->Is(Session::get_current()->getUser()))
                            {
                                $primary_node_path_names[$np] = "::auxpckstr:ui_heading/my_account::";
                                PageBuilder2::AddVariable("is_own_account", true);
                            }
                            else
                            {
                                $primary_node_path_names[$np] = $primary_node_path_nodes[$np]->GetProperty("name");
                            }
                        }
                        elseif($primary_node_path_nodes[$np]->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(CaseSchema::class)))
                        {
                            $primary_node_path_names[$np] = $primary_node_path_nodes[$np]->GetProperty("title");
                        }
                        elseif($primary_node_path_nodes[$np]->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(MessageSchema::class)))
                        {
                            $primary_node_path_names[$np] = "Message";
                        }
                        elseif($primary_node_path_nodes[$np]->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(OrganisationSchema::class)))
                        {
                            $primary_node_path_names[$np] = $primary_node_path_nodes[$np]->GetProperty("name");
                        }
                    }
                }
                else
                {
                    if(Auxilium\MicroTemplate::does_template_exist("data_types/" . $pth_prim))
                    {
                        $primary_node_path_names[$np] = "::auxpckstr:data_types/" . $pth_prim . "::";
                    }
                    else
                    {
                        $primary_node_path_names[$np] = str_replace("_", " ", $pth_prim);
                    }
                }
            }
            PageBuilder2::AddVariable("primary_node_path_order", $primary_node_path_order);
            PageBuilder2::AddVariable("primary_node_path_names", $primary_node_path_names);
            PageBuilder2::AddVariable("primary_node_path_nodes", $primary_node_path_nodes);
            PageBuilder2::AddVariable("primary_node_path_name", end($primary_node_path_names));
        }

        $node = DeegraphNode::FromPath($primary_string_path);

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
            if($url_metadata->checkPath($primary_string_path))
            { // Both path parts match -> this was likely a clicked or history link
                if($url_metadata->checkNode($node))
                { // Check the end result is the node we expected - otherwise throw error - the database has changed what we're looking at!
                    $jwt_validation_passed = $url_metadata->isSecureMatch(); // We don't just want to check validity - we want to use this as a CSRF token for a particular user
                    if(!$jwt_validation_passed)
                    {
                        $url_metadata = new Auxilium\URLMetadata();
                        $url_metadata->setPath($primary_string_path);
                    }
                }
                else
                {
                    if($node != null)
                    {
                        NavigationUtilities::Redirect(target: "/graph/~" . $node->GetNodeID() . "/@ref_error");
                    }
                    $url_metadata = new Auxilium\URLMetadata();
                    $url_metadata->setPath($primary_string_path);
                }
            }
            else
            {
                $url_metadata = new Auxilium\URLMetadata();
                $url_metadata->setPath($primary_string_path);
            }
        }
        PageBuilder2::AddVariable("url_metadata", $url_metadata);
        PageBuilder2::AddVariable("root_url_metadata", new URLMetadata());
        PageBuilder2::AddVariable("jwt_validation_passed", $jwt_validation_passed);

        //$node->getProperties();


        if($node == null)
        {
            PageBuilder2::Render404();
        }


        PageBuilder2::AddVariable("node", $node);
        switch($action)
        {
            case "@delete_confirm":
                if(!$jwt_validation_passed) NavigationUtilities::Redirect(target: "/graph/" . $primary_string_path);

                if($node->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(UserSchema::class)))
                {
                    PageBuilder2::Render(
                        template : "Pages/delete-views/generic.html.twig",
                        variables: []
                    );
                }

                if($node->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(CaseSchema::class)))
                {
                    PageBuilder2::Render(
                        template : "Pages/delete-views/generic.html.twig",
                        variables: []
                    );
                }

                PageBuilder2::Render(
                    template : "Pages/delete-views/generic.html.twig",
                    variables: []
                );
            case "@delete":
                if(!$jwt_validation_passed) NavigationUtilities::Redirect(target: "/graph/" . $primary_string_path);

                $node->Delete();
                $path = explode("/", $primary_string_path);
                array_pop($path);
                //echo implode("/", $path);
                NavigationUtilities::Redirect(target: "/graph/" . implode("/", $path));
            case "@edit":
                if(!$jwt_validation_passed) NavigationUtilities::Redirect(target: "/graph/" . $primary_string_path);

                //echo "EDIT";

                if(isset($_POST["value"]))
                {
                    $refs = $node->GetReferences();
                    //echo "PEND: ".end($path_primary)." // ".implode("--", array_keys($refs));

                    $data = $_POST["value"];
                    $new_node = Auxilium\GraphDatabaseConnection::new_node($data, "text/plain");

                    foreach($refs as $ref_name => &$ref_nodes)
                    {
                        foreach($ref_nodes as &$ref_node)
                        {
                            $ref_node->addProperty($ref_name, $new_node, null, true);
                            //echo $ref_node->GetNodeID()." ==[".$ref_name."]=> ".$node->GetNodeID()."<br />";
                        }
                    }
                    $path = explode("/", $primary_string_path);
                    array_pop($path);
                    NavigationUtilities::Redirect(target: "/graph/" . implode("/", $path));
                    //$new_node = \auxilium\GraphDatabaseConnection::new_node($data, "text/plain");
                    //$query_result = $node->addProperty($_POST["name"], $return_node);
                }

                PageBuilder2::Render(
                    template : "Pages/edit-views/text-plain.html.twig",
                    variables: []
                );
            case "@unlink":
                if(!$jwt_validation_passed) NavigationUtilities::Redirect(target: "/graph/" . $primary_string_path);

                //echo "Unlinking: ".$node->GetNodeID()." => ".$last_prop;
                //exit();
                if($url_metadata->getProperty("uln") != null)
                {
                    //echo "Unlinking: ".$node->GetNodeID()." => ".$last_prop."<br />";
                    $prop = $node->GetProperty($last_prop);
                    if($prop != null)
                    {
                        if($prop->GetNodeID() == $url_metadata->getProperty("uln"))
                        { // Make sure the property hasn't changed since when the link was generated - the user expects the thing they clicked to be removed, not some other random thing with the same path.
                            $node->UnlinkProperty($last_prop);
                        }
                    }
                    //exit();
                    //$node->unlinkProperty($last_prop);
                    NavigationUtilities::Redirect(target: "/graph/" . $primary_string_path);
                }
                break;
            case "@new_property":

                if(!$jwt_validation_passed)
                {
                    PageBuilder2::Render(
                        template : "Pages/node-views/generic.html.twig",
                        variables: []
                    );
                }
                if($url_metadata->getProperty("rcn") != null)
                {
                    if(isset($_POST["name"]))
                    {
                        //echo $node->GetNodeID()." => ".$_POST["name"]." => ".\auxilium\URLMetadata::expand_crushed_uuid(\auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));

                        //exit();
                        $return_node_id = Auxilium\URLMetadata::expand_crushed_uuid(Auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));
                        $return_node = DeegraphNode::FromID($return_node_id);
                        $query_result = $node->AddProperty($_POST["name"], $return_node);
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
                            NavigationUtilities::Redirect(target: $ret_url . "?" . $url_metadata);
                        }
                        //echo "Could not link: ".$node->GetNodeID()." => ".$_POST["name"]." => ".\auxilium\URLMetadata::expand_crushed_uuid(\auxilium\EncodingTools::base64_decode_url_safe($url_metadata->getProperty("rcn")));
                        //exit();
                        PageBuilder2::Render(
                            template : "Pages/node-views/name-new-property.html.twig",
                            variables: [
                                "duplicate_property_name" => $_POST["name"],
                            ]
                        );
                    }
                    PageBuilder2::Render(
                        template : "Pages/node-views/name-new-property.html.twig",
                        variables: []
                    );
                }
                $url_metadata->pushCurrentToReturnStack();

                $form_list = file_get_contents(WEB_ROOT_DIRECTORY . "/property-forms.json");
                $form_list = json_decode($form_list, true);

                /*
                // Now handled in URLMetadata class
                $url_metadata_with_tgn = clone $url_metadata;
                $url_metadata_with_tgn->setProperty("tgn", \auxilium\EncodingTools::base64_encode_url_safe(\auxilium\URLMetadata::crush_uuid($node->GetNodeID())));
                PageBuilder2::AddVariable("url_metadata_with_tgn", $url_metadata_with_tgn);
                */

                PageBuilder2::Render(
                    template : "Pages/node-views/new-property.html.twig",
                    variables: [
                        "form_list" => $form_list,
                    ]
                );
            case "@search":
                PageBuilder2::Render(
                    template : "Pages/node-views/search.html.twig",
                    variables: []
                );
            case "@references":
                PageBuilder2::Render(
                    template : "Pages/node-views/references.html.twig",
                    variables: []
                );


            case "@ref_error":
                PageBuilder2::AddVariable("top_error_message", "PATH_REFERENCE_MISMATCH");
            case "@view":
            default:
                if($node->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(UserSchema::class)))
                {
                    $login_methods = [];
                    /*
                    $bind_variables = [
                        "user_uuid" => $node->GetNodeID(),
                    ];
                    $sql = "SELECT email_address, user_uuid FROM standard_logins WHERE user_uuid=:user_uuid";
                    $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($bind_variables);
                    $user_data = $statement->fetch();
                    */
                    $user_data = MariaDBServerConnection::RunOneRowSelect(
                        SQLQueryBuilderWrapper::SELECT(MariaDBTable::STANDARD_LOGINS)
                            ->cols(cols: [
                                "email_address",
                                "user_uuid",
                            ]
                            )
                            ->where(cond: "user_uuid=:user_uuid")
                            ->bindValue(name: "user_uuid", value: $node->GetNodeID())
                    );

                    if($user_data != null)
                    {
                        $login_methods[] = [
                            "type" => "classic"
                        ];
                    }


                    $queryResponse = MariaDBServerConnection::RunSelect(
                        SQLQueryBuilderWrapper::SELECT(MariaDBTable::OAUTH_LOGINS)
                            ->cols(cols: [
                                "unique_sub",
                                "user_uuid",
                            ]
                            )
                            ->where(cond: "user_uuid=:user_uuid")
                            ->bindValue(name: "user_uuid", value: $node->GetNodeID())
                    );
                    foreach($queryResponse as $loginDetails)
                    {
                        $login_methods[] = [
                            "type" => "oauth",
                            "vendor" => explode("/", $loginDetails["unique_sub"])[0]
                        ];
                    }

                    /*
                    $bind_variables = [
                        "user_uuid" => $node->GetNodeID()
                    ];
                    $sql = "SELECT unique_sub, user_uuid FROM oauth_logins WHERE user_uuid=:user_uuid";
                    $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($bind_variables);
                    $returned_data = $statement->fetch();
                    while($returned_data != null)
                    {
                        $login_methods[] = [
                            "type" => "oauth",
                            "vendor" => explode("/", $returned_data["unique_sub"])[0]
                        ];
                        $returned_data = $statement->fetch();
                    }
                    */

                    if($node->GetNodeID() == Session::get_current()->getUser()->GetNodeID())
                    {
                        PageBuilder2::AddVariable("is_own_account", true);
                    }

                    //[
                    //    "type" => "oauth",
                    //    "vendor" => "microsoft"
                    //]
                    //PageBuilder2::AddVariable("permissions", true);
                    PageBuilder2::AddVariable("hidden_props", ["cases", "messages", "documents"]);
                    PageBuilder2::Render(
                        template : "Pages/node-views/user.html.twig",
                        variables: [
                            "login_methods" => $login_methods
                        ]
                    );

                    //PageBuilder2::AddVariable("traditional_login_method", []);
                }
                elseif($node->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(CaseSchema::class)))
                {
                    PageBuilder2::Render(
                        template : "Pages/node-views/case.html.twig",
                        variables: [
                            "hidden_props" => ["description", "clients", "messages", "documents", "todos", "timeline", "workers"],
                        ]
                    );
                }
                elseif($node->ExtendsOrInstanceOf(URLHandling::GetURLForSchema(OrganisationSchema::class)))
                {
                    PageBuilder2::Render(
                        template : "Pages/node-views/group.html.twig",
                        variables: [
                            "hidden_props" => ["departments", "cases", "staff"],
                        ]
                    );
                }
                else
                {
                    PageBuilder2::Render(
                        template : "Pages/node-views/generic.html.twig",
                        variables: []
                    );
                }
        }

        var_dump("!!graph switching error!!");
        die();

    }
    catch(DatabaseConnectionException $e)
    {
        PageBuilder2::RenderInternalSystemError($e);
    }
}
catch(Exception $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}
