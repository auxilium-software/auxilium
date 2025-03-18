<?php

use Auxilium\Schemas\CaseSchema;
use Auxilium\TwigHandling\PageBuilder;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

$pb = PageBuilder::get_instance();
$pb->requireLogin();
$pb->setTemplate("Pages/system/import");

if(isset($_POST["submit"]))
{
    //var_dump($_FILES);
    ini_set("max_execution_time", "300");

    $raw_json = file_get_contents($_FILES["dump"]["tmp_name"], true);
    $dump_object = json_decode($raw_json, true);
    echo "<span>Users: " . count($dump_object["users"]) . "</span><br />";
    echo "<span>Structural Nodes: " . count($dump_object["nodes"]) . "</span><br />";
    echo "<span>Cases: " . count($dump_object["cases"]) . "</span><br />";

    $import_id_deegraph_id_map = [];

    foreach($dump_object["nodes"] as &$structural_node)
    {
        $import_id = null;
        $data = null;
        $original_creation_date = null;
        $schema = null;
        $props = [];
        //var_dump($structural_node);
        foreach($structural_node as $key => &$value)
        {
            switch($key)
            {
                case "@import_id":
                    $import_id = $value;
                    break;
                case "@data":
                    $data = $value;
                    break;
                case "@schema":
                    $schema = $value;
                    break;
                default:
                    if(substr($key, 0, 1) == "+")
                    {
                        $props[substr($key, 1)] = \Auxilium\DatabaseInteractions\GraphDatabaseConnection::new_node($value, "text/plain");
                    }
            }
        }
        $node = \Auxilium\DatabaseInteractions\GraphDatabaseConnection::new_node_raw($data, $schema, null);
        foreach($props as $key => &$value)
        {
            $node->addProperty($key, $value, null, false);
        }
        echo "SN:" . $node->getId();
        if($import_id != null)
        {
            $import_id_deegraph_id_map[$import_id] = $node;
            echo "; II:" . $import_id;
        }
        echo "<br />";
    }

    foreach($dump_object["users"] as &$structural_node)
    {
        $import_id = null;
        $data = null;
        $original_creation_date = null;
        $schema = URLHandling::GetURLForSchema(UserSchema::class);
        $props = [];
        //var_dump($structural_node);
        foreach($structural_node as $key => &$value)
        {
            switch($key)
            {
                case "@import_id":
                    $import_id = $value;
                    break;
                case "@data":
                    $data = $value;
                    break;
                case "@schema":
                    $schema = $value;
                    break;
                default:
                    if(substr($key, 0, 1) == "+")
                    {
                        $props[substr($key, 1)] = \Auxilium\DatabaseInteractions\GraphDatabaseConnection::new_node($value, "text/plain");
                    }
                    elseif(substr($key, 0, 1) == "@")
                    {

                    }
                    else
                    {
                        $props[$key] = $import_id_deegraph_id_map[$value];
                    }
            }
        }
        $node = \Auxilium\DatabaseInteractions\GraphDatabaseConnection::new_node_raw($data, $schema, null);
        foreach($props as $key => &$value)
        {
            $node->addProperty($key, $value, null, false);
        }
        echo "UN:" . $node->getId();
        if($import_id != null)
        {
            $import_id_deegraph_id_map[$import_id] = $node;
            echo "; II:" . $import_id;
        }
        echo "<br />";
    }

    foreach($dump_object["cases"] as &$structural_node)
    {
        $import_id = null;
        $data = null;
        $original_creation_date = null;
        $schema = URLHandling::GetURLForSchema(CaseSchema::class);
        $props = [];
        //var_dump($structural_node);
        foreach($structural_node as $key => &$value)
        {
            switch($key)
            {
                case "@import_id":
                    $import_id = $value;
                    break;
                case "@data":
                    $data = $value;
                    break;
                case "@schema":
                    $schema = $value;
                    break;
                default:
                    if(substr($key, 0, 1) == "+")
                    {
                        $props[substr($key, 1)] = \Auxilium\DatabaseInteractions\GraphDatabaseConnection::new_node($value, "text/plain");
                    }
                    elseif(substr($key, 0, 1) == "@")
                    {

                    }
                    else
                    {
                        $props[$key] = $import_id_deegraph_id_map[$value];
                    }
            }
        }
        $node = \Auxilium\DatabaseInteractions\GraphDatabaseConnection::new_node_raw($data, $schema, null);
        foreach($props as $key => &$value)
        {
            $node->addProperty($key, $value, null, false);
        }
        echo "CN:" . $node->getId();
        if($import_id != null)
        {
            $import_id_deegraph_id_map[$import_id] = $node;
            echo "; II:" . $import_id;
        }
        echo "<br />";
    }

    die();
    /*$query = trim($_POST["query"]);
    $result = \auxilium\GraphDatabaseConnection::query(\auxilium\Session::get_current()->getUser(), $query);
    if (isset($_POST["return_format"])) {
        if (strtoupper($_POST["return_format"]) == "RAW") {
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;
        }
    }
    $pb->setVariable("result", json_encode($result, JSON_PRETTY_PRINT));
    $pb->setVariable("query", $query, JSON_PRETTY_PRINT);*/
}
$pb->render();
