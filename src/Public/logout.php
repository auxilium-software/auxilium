<?php

use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';


// setcookie("session_key", null, time() - (3600 * 48), "/", null, true, true);

setcookie("session_key", "", time() - (3600 * 48), "/", "", true, true);

\Auxilium\TwigHandling\PageBuilder2::AutoRender([
    "current_user" => null,
]);
