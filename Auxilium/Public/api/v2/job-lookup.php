<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$at = Auxilium\APITools::get_instance();
$at->requireLogin();

$draft_content = null;
$put_data = false;
$draft_id = null;
$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$last_uri_component = explode("?", end($uri_components));
$get_params = "";
if(count($last_uri_component) > 1)
{
    $get_params = $last_uri_component[1];
}
$uri_components[count($uri_components) - 1] = $last_uri_component[0];

$job_id = $uri_components[4];
$action = "access";
if(count($uri_components) > 5)
{
    $action = strtolower($uri_components[5]);
}
$job_key = null;

if(!preg_match("/^[0-9a-f]{16}\\.[0-9a-zA-Z_-]{32}$/", $job_id))
{
    if(preg_match("/^[0-9a-f]{16}\\.[0-9a-zA-Z_-]{32}\\.[0-9a-zA-Z_-]{64}$/", $job_id))
    {
        $id_cmps = explode(".", $job_id);
        $job_id = $id_cmps[0] . "." . $id_cmps[1];
        $job_key = $id_cmps[2];
    }
    else
    {
        $at->setErrorText("Malformed job_id");
        $at->setVariable("job_id", $job_id);
        $at->output();
    }
}

$job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/" . $job_id . ".json";
$at->setVariable("status", "PENDING");
if(!file_exists($job_path))
{
    $at->setVariable("status", "DONE");
    $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Complete/" . $job_id . ".json";
}
if(!file_exists($job_path))
{
    $at->setVariable("status", "FAILED");
    $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/" . $job_id . ".json";
}
if(!file_exists($job_path))
{
    $at->setErrorText("Invalid job id");
    $at->output();
}
$job_content = json_decode(file_get_contents($job_path), true);
$key_authed = true;
if(isset($job_content["job_key"]))
{
    $key_authed = false;
    if($job_key == $job_content["job_key"])
    {
        $key_authed = true;
    }
    else
    {
        if($job_key != null)
        {
            $at->setErrorText("Invalid job key");
            $at->output();
        }
    }
}

if($action == "access")
{
    $at->setVariable("job_id", $job_id);
    if($key_authed)
    {
        $at->setVariable("content", $job_content);
    }
    else
    {
        $at->setVariable("note", "KEY_REQUIRED_TO_VIEW_CONTENT");
    }
    $at->output();
}
else
{
    $at->setErrorText("Invalid action");
    $at->output();
}
