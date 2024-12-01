<?php

use Auxilium\SessionHandling\Session;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../environment.php';

$at = Auxilium\APITools::get_instance();
$at->requireLogin();


$message_uuid = Auxilium\EncodingTools::generate_new_uuid(); // We don't need to assign this to a table, this is just for convenience to get a unique file handle.
$message_draft_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "message-drafts/" . Session::get_current()->getUser()->getUuid() . "/" . $message_uuid . ".json";
if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "message-drafts/" . Session::get_current()->getUser()->getUuid() . "/"))
{
    mkdir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "message-drafts/" . Session::get_current()->getUser()->getUuid() . "/", 0700, true);
}
$new_message_template = [
    "body" => "",
    "recipient" => null,
    "subject" => null
];
if(isset($_GET["subject"]))
{
    $new_message_template["subject"] = $_GET["subject"];
}
if(isset($_GET["attached_to"]))
{
    $new_message_template["attached_to"] = $_GET["attached_to"];
}
if(isset($_GET["to"]))
{
    $new_message_template["recipients"] = ["{" . $_GET["to"] . "}"];
}
$bytes_written = file_put_contents($message_draft_path, json_encode($new_message_template));
if($bytes_written === FALSE)
{
    http_response_code(500);
    echo json_encode([
            "status" => "ERROR",
            "error_message" => "Failed to write new message to RAM disk",
            "error_code" => http_response_code()
        ]
    );
    exit();
}
else
{
    header("Location: /chats/drafts/" . $message_uuid);
    exit();
}
