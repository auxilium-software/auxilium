<?php

use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\Security;
use Auxilium\TwigHandling\PageBuilder2;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

Security::RequireLogin();

if(Security::IsAdmin())
{
    try
    {
        PageBuilder2::Render(
            template: '/Pages/System/index.html.twig'
        );
    }
    catch(DatabaseConnectionException $e)
    {
        PageBuilder2::RenderInternalSystemError($e);
    }
}
else
{
    PageBuilder2::RenderInternalSystemError(new Exception("Insufficient permissions"));
}
