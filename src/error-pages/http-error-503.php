<?php
require_once "../environment.php";
$pb = \Auxilium\PageBuilder::get_instance();
$pb->setVariable("error_code", 503);
$pb->setTemplate("http-error");
$pb->render();
?>
