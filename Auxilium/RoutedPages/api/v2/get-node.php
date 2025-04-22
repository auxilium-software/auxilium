<?php


use Auxilium\Auxilium\API\Controllers\NodeController;
use Auxilium\Exceptions\DeegraphException;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';


$controller = new NodeController();

try
{
    $controller->GetNode();
}
catch(DeegraphException $e)
{
    throw $e;
    $at->setErrorText("Database error");
    $at->setVariable("stack_trace", $e->getInnerTrace());
    $at->output();
}
