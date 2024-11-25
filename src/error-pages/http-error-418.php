<?php
require_once "../environment.php";
$pb = \Auxilium\PageBuilder::get_instance();
$pb->setVariable("error_code", 418);
$pb->setTemplate("http-error");
$pb->render();
