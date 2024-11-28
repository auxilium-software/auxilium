<?php

use Auxilium\SessionHandling\CookieHandling;
use Auxilium\SessionHandling\Security;
use Auxilium\TwigHandling\PageBuilder2;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';


try
{
    try
    {
        Security::RequireLogin();
        PageBuilder2::AutoRender(variables: [
            "progressive_load" => CookieHandling::GetBooleanCookie("progressive_load", false),
            "is_admin" => (
                in_array(
                    "ACT",
                    Auxilium\GraphDatabaseConnection::get_instance_node()->getPermissions()
                )
            ),
        ]);
    }
    catch (\Auxilium\Exceptions\DatabaseConnectionException $e)
    {
        PageBuilder2::RenderInternalSystemError($e);
    }
}
catch (\Exception $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}
