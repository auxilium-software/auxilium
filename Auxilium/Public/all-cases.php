<?php

use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\Security;
use Auxilium\TwigHandling\PageBuilder2;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';


try
{
    Security::RequireLogin();
    $isAdmin = false;
    if(in_array("ACT", \Auxilium\DatabaseInteractions\GraphDatabaseConnection::get_instance_node()->getPermissions()))
    {
        $isAdmin = true;
    }
    PageBuilder2::Render(
        template : 'Pages/all-cases.html.twig',
        variables: [
            "is_admin" => $isAdmin,
        ]
    );
}
catch(DatabaseConnectionException $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}
