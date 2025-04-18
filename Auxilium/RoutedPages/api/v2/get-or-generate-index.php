<?php

use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\URIUtilities;
use Darksparrow\DeegraphInteractions\DataStructures\DataURL;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$at = Auxilium\APITools::get_instance();
$at->requireLogin();

$uri = new URIUtilities();
$index_id = $uri->getURIComponents()[count($uri->getURIComponents()) - 1];
$index_id = explode(".", $index_id)[0];

if(!preg_match("/^[0-9a-z_-]+$/", $index_id))
{
    $at->setErrorText("Malformed index name");
    $at->output();
}


$regenerate_index = false;
$index_list = json_decode(file_get_contents(__DIR__ . "/../../../indexes.json"), true);

if(!array_key_exists($index_id, $index_list))
{
    $index_id = "global";
    if(!array_key_exists("global", $index_list))
    {
        $at->setErrorText("Broken indexes.json file. Contact system administrator.");
        $at->output();
        die();
    }
}

$index_store_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Indexes/" . Session::get_current()->getUser()->getId() . "/" . $index_id . ".json";
if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Indexes/" . Session::get_current()->getUser()->getId() . "/"))
{
    mkdir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Indexes/" . Session::get_current()->getUser()->getId() . "/", 0700, true);
}
$old_index = ["created" => "1970-01-01T00:00:00Z"];
if(file_exists($index_store_path))
{
    $old_index = json_decode(file_get_contents($index_store_path), true);
}
else
{
    $regenerate_index = true;
}
$new_index = ["created" => date("c", time())];

$max_age = 3600;
if(array_key_exists("max_age", $index_list[$index_id]))
{
    $max_age = $index_list[$index_id]["max_age"];
}
$index_age = time() - strtotime($old_index["created"]);
$at->setVariable("age", $index_age);
$at->setVariable("max_age", $max_age);
if((time() - strtotime($old_index["created"])) > $max_age)
{
    $regenerate_index = true;
    $at->setVariable("age", 0);
}

if($regenerate_index)
{
    $queries = $index_list[$index_id]["index_queries"];
    $new_index["lookup_table"] = [];

    foreach($queries as &$query)
    {
        $results = GraphDatabaseConnection::query(Session::get_current()->getUser(), $query)["@rows"];
        foreach($results as &$row)
        {
            foreach($row as $column_name => &$cell)
            {
                foreach($cell as $path => $value)
                {
                    $value = mb_strtolower((new DataURL($value))->getData());
                    if(!array_key_exists($value, $new_index["lookup_table"]))
                    {
                        $new_index["lookup_table"][$value] = [];
                    }
                    array_push($new_index["lookup_table"][$value], $path);
                }
            }

        }
    }

    file_put_contents($index_store_path, json_encode($new_index, JSON_PRETTY_PRINT));
}
else
{
    $new_index = $old_index;
}

$at->setVariable("index", $new_index);
$at->output();
