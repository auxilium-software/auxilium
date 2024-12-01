<?php

namespace Auxilium\SessionHandling;

class CookieHandling
{
    public static function GetBooleanCookie(string $cookieName, bool $default = false): bool
    {
        $cookieValue = self::GetCookieValue($cookieName);

        if (!$cookieValue)
            return $default;
        if ($cookieValue == "true")
            return true;
        return false;
    }

    public static function GetCookieValue(string $cookieName): bool|string
    {
        if (!isset($_COOKIE[$cookieName])) {
            switch ($cookieName) {
                case "lang":
                    return "en";
                case "progressive_load":
                    return "true";
            }
        }
        return $_COOKIE[$cookieName];
    }
}
