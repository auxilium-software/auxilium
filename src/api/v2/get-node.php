<?php
require_once "../../environment.php";

$at = \auxilium\APITools::get_instance();
$at->requireLogin();

try {
    $uri_components = explode("/", $_SERVER["REQUEST_URI"]);
    $last_uri_component = explode("?", end($uri_components));
    $get_params = "";
    if (count($last_uri_component) > 1) {
        $get_params = $last_uri_component[1];
    }
    $uri_components[count($uri_components) - 1] = $last_uri_component[0];

    $node_id = $uri_components[4];

    if (!preg_match("/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/", $node_id)) {
        $at->setErrorText("Formatting error");
        $at->setResponseCode(400);
        $at->output();
        exit();
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case "DELETE":
            $query = "DELETE {".$node_id."}";
            \auxilium\GraphDatabaseConnection::query(\auxilium\Session::get_current()->getUser(), $query);
            $at->output();
            break;
        case "GET":
        default:
            $node_info = \auxilium\GraphDatabaseConnection::get_raw_node_info(\auxilium\Session::get_current()->getUser(), $node_id);
            $at->setVariable("result", $node_info);
            $at->setVariable("request", $node_id);
            $at->output();
            break;
    }
} catch (\auxilium\DeegraphException $e) {
    $at->setErrorText("Database error");
    $at->setVariable("stack_trace", $e->getInnerTrace());
    $at->output();
} 

?>
