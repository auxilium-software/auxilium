<?php

use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';


$pb = PageBuilder::get_instance();

try
{
    $pb->requireLogin();
    if(in_array("ACT", Auxilium\GraphDatabaseConnection::get_instance_node()->getPermissions()))
    {
        $pb->setVariable("is_admin", true);
    }
    $pb->setTemplate("Pages/all-cases");
    $pb->render();
} catch(DatabaseConnectionException $e)
{
    $pb->setTemplate("ErrorPages/InternalSystemError");
    $pb->render();
}
