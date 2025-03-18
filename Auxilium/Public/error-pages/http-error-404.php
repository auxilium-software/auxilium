<?php

use Auxilium\TwigHandling\PageBuilder2;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

PageBuilder2::Render(
    template : "ErrorPages/HTTPError.html.twig",
    variables: [
        "error_code" => 404,
    ],
);
