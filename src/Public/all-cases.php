<?php
require_once "environment.php";


$pb = \Auxilium\TwigHandling\PageBuilder::get_instance();

try {
    $pb->requireLogin();
    if (in_array("ACT", Auxilium\GraphDatabaseConnection::get_instance_node()->getPermissions())) {
        $pb->setVariable("is_admin", true);
    }
    $pb->setTemplate("Pages/all-cases");
    $pb->render();
} catch (\Auxilium\Exceptions\DatabaseConnectionException $e) {
    $pb->setTemplate("ErrorPages/InternalSystemError");
    $pb->render();
}
