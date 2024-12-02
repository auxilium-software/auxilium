<?php

use Auxilium\TwigHandling\PageBuilder;

require_once "../Environment.php";
$pb = PageBuilder::get_instance();
$pb->setVariable("error_code", 503);
$pb->setTemplate("ErrorPages/HTTPError");
$pb->render();
