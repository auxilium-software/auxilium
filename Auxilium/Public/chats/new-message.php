<?php

use Auxilium\APITools;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

$at = APITools::get_instance();
$at->requireLogin();


$message_uuid = EncodingTools::GenerateNewUUID(); // We don't need to assign this to a table, this is just for convenience to get a unique file handle.
$message_draft_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/MessageDrafts/" . Session::get_current()->getUser()->getId() . "/" . $message_uuid . ".json";
if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/MessageDrafts/" . Session::get_current()->getUser()->getId() . "/"))
{
    mkdir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/MessageDrafts/" . Session::get_current()->getUser()->getId() . "/", 0700, true);
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
    NavigationUtilities::Redirect(target: "/chats/drafts/" . $message_uuid);
    exit();
}
