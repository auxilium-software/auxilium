<?php

require_once "../../environment.php";

$at = Auxilium\APITools::get_instance();
$at->requireLogin();

$file_id = null;
$file_hash = null;
$metadata = false;
$mime_type = null;
$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$last_uri_component = explode("?", end($uri_components));
$get_params = "";
if (count($last_uri_component) > 1) {
    $get_params = $last_uri_component[1];
}
$uri_components[count($uri_components) - 1] = $last_uri_component[0];

if (count($uri_components) > 4) {
    $spl = explode("+",$uri_components[4]);
    $file_id = strtolower($spl[0]);
    if (count($spl) > 1) {
        $file_hash = $spl[1];
    }
    if (count($spl) > 2) {
        $mime_type = str_replace(":", "/", urldecode($spl[2]));
    }
}

if ($mime_type == null) {
    $mime_type = "application/octet-stream";
}

$desired_components = explode(",", strtolower($get_params));

$lfsobj = new Auxilium\AuxiliumLFSObject("auxlfs://".INSTANCE_CREDENTIAL_DDS_HOST."/".$file_id."+".$file_hash."+".urlencode($mime_type));

if (!$lfsobj->canRead()) {
    $at->setErrorText("Missing read permission");
    $at->output();
    exit();
}

$message_headers = [];
$full_message = \ZBateson\MailMimeParser\Message::from($lfsobj->getData(), false);

foreach ($full_message->getAllHeaders() as $header) {
    $parts = $header->getParts();
    if (count($parts) > 1) {
        $res = [];
        foreach ($parts as &$part) {
            array_push($res, $part->getValue());
        }
        $message_headers[strtolower($header->getName())] = $res;
    } else {
        if (count($parts) > 0) {
            $message_headers[strtolower($header->getName())] = $parts[0]->getValue();
        }
    }
}

foreach($desired_components as &$desired_component) {
    switch(trim($desired_component)) {
        case "subject":
        case "from":
        case "to":
        case "reply-to":
        case "delivered-to":
        case "return-path":
        case "x-auxilium-message-version":
        case "cc":
        case "bcc":
            $at->setVariable($desired_component, isset($message_headers[$desired_component]) ? $message_headers[$desired_component] : null);
            break;
        case "*":
            foreach($message_headers as $header_name=>&$header) {
                $at->setVariable($header_name, $header);
            }
            break;
        case "text-content":
            $at->setVariable("text-content", $full_message->getTextContent());
            break;
        default:
            $at->setVariable($desired_component, null);
    }
}

//$at->setVariable("uri", strval($lfsobj));
//$at->setVariable("data", $lfsobj->getData());
$at->output();
