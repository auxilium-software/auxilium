<?php

use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\Security;
use Auxilium\TwigHandling\PageBuilder2;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';


try
{
    Security::RequireLogin();
    PageBuilder2::Render(
        template : 'Pages/all-cases.html.twig',
        variables: [
            "is_admin" => Security::IsAdmin(),
        ]
    );
}
catch(DatabaseConnectionException $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}
