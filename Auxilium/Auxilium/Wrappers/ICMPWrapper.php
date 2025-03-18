<?php

namespace Auxilium\Wrappers;

use Auxilium\TwigHandling\PageBuilder2;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;
use JJG\Ping;

class ICMPWrapper
{
    public static function RequireSchemaRepo(): bool
    {
        return true;
        $temp = parse_url(URLHandling::$URLBase);

        $success = self::CheckUp(target: $temp['host']);
        if($success)
            return true;

        http_response_code(500);
        PageBuilder2::Render(
            template : "ErrorPages/InternalSystemError.html.twig",
            variables: [
                "technical_details" => "A required service (" . $temp['host'] . ") is down/unavailable.",
            ]
        );
    }

    public static function CheckUp(string $target): bool
    {
        if((!ip2long($target)) && dns_get_record($target) == [])
            return false;

        $ping = new Ping(
            host   : $target,
            ttl    : 255,
            timeout: 0.5,
        );
        $latency = $ping->ping();
        if($latency !== false)
            return true;
        return false;
    }
}
