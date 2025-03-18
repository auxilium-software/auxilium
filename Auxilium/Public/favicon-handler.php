<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';


switch($_GET["type"])
{
    case "svg":
        echo file_get_contents(__DIR__ . "/Static/Favicons/White.png");
        die();
    case "png":
    default:
        echo file_get_contents(__DIR__ . "/Static/Favicons/White.png");
        die();
}
