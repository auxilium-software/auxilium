<?php

use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

$pb = PageBuilder::get_instance();
$pb->setVariable("error_code", 418);
$pb->setTemplate("ErrorPages/HTTPError");
$pb->render();
