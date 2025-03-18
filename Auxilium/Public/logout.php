<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder2;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

CookieHandling::DeleteCookie(targetCookie: CookieKey::SESSION_KEY);
PageBuilder2::AutoRender([
        "current_user" => null,
    ]
);
