<?php
require_once "../environment.php";
$pb = \auxilium\PageBuilder::get_instance();
$pb->setVariable("error_code", 404);
$pb->setTemplate("http-error");
$pb->render();
?>