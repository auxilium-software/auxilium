<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\GraphDatabaseConnection;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\SessionHandling\Security;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Wrappers\ICMPWrapper;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

try
{
    try
    {
        Security::RequireLogin();
        ICMPWrapper::RequireSchemaRepo();


        PageBuilder2::AutoRender(variables: [
            "progressive_load" => CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false),
            "is_admin" => (
            in_array(
                needle  : "ACT",
                haystack: GraphDatabaseConnection::get_instance_node()->getPermissions()
            )
            ),
        ]
        );
    }
    catch(DatabaseConnectionException $e)
    {
        PageBuilder2::RenderInternalSystemError($e);
    }
}
catch(Exception $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}
