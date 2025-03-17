<?php

use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\Exceptions\DeegraphException;
use Auxilium\SessionHandling\Session;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$at = Auxilium\APITools::get_instance();
$at->requireLogin();

function debug_to_console($data)
{
    $output = $data;
    if(is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('" . $output . "');</script>";
}


try
{
    $uri_components = explode("/", $_SERVER["REQUEST_URI"]);
    $last_uri_component = explode("?", end($uri_components));
    $get_params = "";
    if(count($last_uri_component) > 1)
    {
        $get_params = $last_uri_component[1];
    }
    $uri_components[count($uri_components) - 1] = $last_uri_component[0];

    $node_id = $uri_components[4];

    if(!preg_match("/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/", $node_id))
    {
        $at->setErrorText("Formatting error");
        $at->setResponseCode(400);
        $at->output();
        exit();
    }

    switch($_SERVER['REQUEST_METHOD'])
    {
        case "DELETE":
            $query = "DELETE {" . $node_id . "}";
            GraphDatabaseConnection::query(Session::get_current()->getUser(), $query);
            $at->output();
            break;
        case "GET":
        default:
            $node_info = GraphDatabaseConnection::get_raw_node_info(
                actor: Session::get_current()->getUser(),
                uuid : new UUID($node_id),
            );
            $at->setVariable("result", $node_info);
            $at->setVariable("request", $node_id);
            $at->output();
            break;
    }
}
catch(DeegraphException $e)
{
    throw $e;
    $at->setErrorText("Database error");
    $at->setVariable("stack_trace", $e->getInnerTrace());
    $at->output();
}

