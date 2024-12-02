<?php

use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

$pb = PageBuilder::get_instance();
$pb->requireLogin();

$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$message_uuid = null;
if(preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", explode("?", $uri_components[3])[0]))
{
    $message_uuid = explode("?", $uri_components[3])[0];
}
else
{
    $pb->setVariable("draft_path", $message_draft_path);
    $pb->setTemplate("Pages/chats/draft-corrupted");
    $pb->render();
    http_response_code(400);
    exit();
}
$ret_url = Auxilium\EncodingTools::base64_encode_url_safe(explode("?", $_SERVER["REQUEST_URI"])[0]);
$pb->setVariable("encoded_return_url", $ret_url);

$message_draft_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "message-drafts/" . Session::get_current()->getUser()->getUuid() . "/" . $message_uuid . ".json";
if(!file_exists($message_draft_path))
{ // Hmmm , not a draft message then, let's try finding it in the database
    $pb->setVariable("draft_path", $message_draft_path);
    $pb->setTemplate("Pages/chats/draft-corrupted");
    $pb->render();
    http_response_code(400);
    exit();
}

$message_draft = json_decode(file_get_contents($message_draft_path), true);

$pb->setVariable("raw_message_draft", $message_draft);
$pb->setVariable("draft_id", $message_uuid);
$pb->setTemplate("Pages/chats/draft");
$pb->render();
