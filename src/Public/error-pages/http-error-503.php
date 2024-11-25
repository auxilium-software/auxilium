<?php
require_once "../environment.php";
$pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
$pb->setVariable("error_code", 503);
$pb->setTemplate("http-error");
$pb->render();
