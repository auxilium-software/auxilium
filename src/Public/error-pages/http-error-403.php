<?php

use Auxilium\TwigHandling\PageBuilder;

require_once "../environment.php";
$pb = PageBuilder::get_instance();
$pb->setVariable("error_code", 403);
$pb->setTemplate("ErrorPages/HTTPError");
$pb->render();
