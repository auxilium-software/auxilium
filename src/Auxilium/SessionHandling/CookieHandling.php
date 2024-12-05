<?php

namespace Auxilium\SessionHandling;

use Auxilium\Enumerators\CookieKey;

class CookieHandling
{
    public static function GetBooleanCookie(CookieKey $targetCookie, bool $default = false): bool
    {
        $cookieValue = self::GetCookieValue($targetCookie, "false");

        if(!$cookieValue)
            return $default;
        if($cookieValue == "true")
            return true;
        return false;
    }

    public static function GetCookieValue(CookieKey $targetCookie, string $default = ""): bool|string
    {
        if(!isset($_COOKIE[$targetCookie->value]))
        {
            /*
            switch($targetCookie)
            {
                case CookieKey::LANGUAGE:
                    return "en";
                case CookieKey::PROGRESSIVE_LOAD:
                    return "true";
            }
            */
            return $default;
        }
        return $_COOKIE[$targetCookie->value];
    }
}
