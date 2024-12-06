<?php

namespace Auxilium\SessionHandling;

use Auxilium\Enumerators\CookieKey;
use Auxilium\Enumerators\Language;

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

    private static function GetCookieTTL(CookieKey $targetCookie): int
    {
        switch($targetCookie)
        {
            case CookieKey::SESSION_KEY:
                return (3600 * 48);
            case CookieKey::PROGRESSIVE_LOAD:
            //     return 0;
            case CookieKey::STYLE:
            case CookieKey::LANGUAGE:
                return (3600 * 24 * 30);
        }
        return 0;
    }

    public static function SetCookie(CookieKey $targetCookie, string $value): bool
    {
        $success = setcookie(
            $targetCookie->value,
            $value,
            time() + self::GetCookieTTL($targetCookie),
            "/",
            null,
            true,
            true
        );
        return $success;
    }

    public static function DeleteCookie(CookieKey $targetCookie): bool
    {
        return setcookie(
            $targetCookie->value, // name
            "", // value
            time() - (3600 * 48), // ttl
            "/", //
            "", // domain
            true, //
            true //
        );
    }



    public static function SetSessionKey(string $sessionKey): void
    {
        self::SetCookie(CookieKey::SESSION_KEY, $sessionKey);
    }
    public static function SetProgressiveLoad(bool $progressiveLoad): void
    {
        if($progressiveLoad) self::SetCookie(CookieKey::SESSION_KEY, "true");
        self::SetCookie(CookieKey::SESSION_KEY, "false");
    }
    public static function SetLanguage(Language $language): void
    {
        self::SetCookie(CookieKey::LANGUAGE, $language->value);
    }
    public static function SetStyle(Language $language): void
    {
        self::SetCookie(CookieKey::LANGUAGE, $language->value);
    }
}
