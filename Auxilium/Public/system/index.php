<?php

use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

$pb = PageBuilder::get_instance();
$pb->requireLogin();

if(in_array("ACT", \Auxilium\DatabaseInteractions\GraphDatabaseConnection::get_instance_node()->getPermissions()))
{
    try
    {
        $pb->setTemplate("Pages/system/index");
        $pb->render();
    }
    catch(DatabaseConnectionException $e)
    {
        $pb->setDefaultVariables();
        $pb->setTemplate("ErrorPages/InternalSystemError");
        $technical_details = "Exception Type:\n    " . get_class($e);
        $technical_details .= "\nMessage:\n    " . $e->getMessage();
        $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
        $pb->setVariable("technical_details", $technical_details);
        http_response_code(500);
        $pb->render();
    }
}
else
{
    $pb->setDefaultVariables();
    $pb->setTemplate("ErrorPages/InternalSystemError");
    $technical_details = "Exception Type:\n    InsufficientPermissions";
    $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    $pb->setVariable("technical_details", $technical_details);
    http_response_code(403);
    $pb->render();
}
