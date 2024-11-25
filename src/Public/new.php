<?php
require_once "environment.php";

$pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
$pb->requireLogin();

$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$last_uri_component = explode("?", end($uri_components));
$get_params = "";
if (count($last_uri_component) > 1) {
    $get_params = $last_uri_component[1];
}
$uri_components[count($uri_components) - 1] = $last_uri_component[0];

$jwt_validation_passed = false; // This is used to make sure that a user has clicked a link that Auxilium has generated. 
//This is not the current state of the url_metadata, rather the state it was in when we received the request

$url_metadata = Auxilium\URLMetadata::from_jwt($get_params);
if ($url_metadata == null) {
    $url_metadata = new Auxilium\URLMetadata();
    $url_metadata->setPath($primary_string_path);
} else {
    $jwt_validation_passed = $url_metadata->isSecureMatch(); // We don't just want to check validity - we want to use this as a CSRF token for a particular user
    if (!$jwt_validation_passed) {
        $url_metadata = new Auxilium\URLMetadata();
        $url_metadata->setPath($primary_string_path);
    }
}

$pb->setVariable("url_metadata", $url_metadata);
$pb->setVariable("root_url_metadata", new Auxilium\URLMetadata());
$pb->setVariable("jwt_validation_passed", $jwt_validation_passed);

$action = null;
if (count($uri_components) > 2) {
    $action = $uri_components[2];
}

switch ($action) {
    case "collection":
        $new_node = Auxilium\GraphDatabaseConnection::new_node(null, null, "https://schemas.auxiliumsoftware.co.uk/v1/collection.json");
        $ret_url = $url_metadata->popFromReturnStack();
        $url_metadata->setProperty("rcn", Auxilium\EncodingTools::base64_encode_url_safe(Auxilium\URLMetadata::crush_uuid($new_node->getId())));
        //echo $ret_url."?".$url_metadata;
        header("Location: ".$ret_url."?".$url_metadata);
        exit();
        break;
    case "file":
        $pb->setTemplate("generic");
        break;
    case "text":
        $pb->setTemplate("new-node-text");
        if (isset($_POST["text"])) {
            $data = trim($_POST["text"]);
            $new_node = Auxilium\GraphDatabaseConnection::new_node($data, "text/plain");
            $ret_url = $url_metadata->popFromReturnStack();
            $url_metadata->setProperty("rcn", Auxilium\EncodingTools::base64_encode_url_safe(Auxilium\URLMetadata::crush_uuid($new_node->getId())));
            //echo $ret_url."?".$url_metadata;
            header("Location: ".$ret_url."?".$url_metadata);
            exit();
        }
        break;
    default:
        $pb->setTemplate("generic");
}

$pb->render();
