<?php

use Auxilium\Exceptions\DeegraphException;
use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

$pb = PageBuilder::get_instance();
$pb->requireLogin();
$pb->setTemplate("Pages/console");
if(isset($_POST["query"]))
{
    $query = trim($_POST["query"]);
    try
    {
        $result = \Auxilium\DatabaseInteractions\GraphDatabaseConnection::query(Session::get_current()->getUser(), $query);
        if(isset($_POST["return_format"]))
        {
            if(strtoupper($_POST["return_format"]) == "RAW")
            {
                header("Content-Type: application/json; charset=utf-8");
                $json_out = json_encode($result, JSON_PRETTY_PRINT);
                $json_out = explode("\n", $json_out);
                foreach($json_out as &$line)
                {
                    echo $line . "\n";
                }
                //echo $json_out;
                //print($json_out);
                echo "\n";
                ob_flush();
                flush();
                exit();
            }
        }
        $pb->setVariable("result", json_encode($result, JSON_PRETTY_PRINT));
        $pb->setVariable("query", $query);
    }
    catch(DeegraphException $e)
    {
        if(strtoupper($_POST["return_format"]) == "RAW")
        {
            header("Content-Type: application/json; charset=utf-8");
            $json_out = json_encode($e->getInnerTrace(), JSON_PRETTY_PRINT);
            $json_out = explode("\n", $json_out);
            foreach($json_out as &$line)
            {
                echo $line . "\n";
            }
            //echo $json_out;
            //print($json_out);
            echo "\n";
            ob_flush();
            flush();
            exit();
        }
    }
}
$pb->render();
