<?php
require_once "environment.php";


$pb = Auxilium\PageBuilder::get_instance();

try {
    $pb->requireLogin();
    if (in_array("ACT", Auxilium\GraphDatabaseConnection::get_instance_node()->getPermissions())) {
        $pb->setVariable("is_admin", true);
    }
    $pb->setTemplate("all-cases");
    $pb->render();
} catch (\Auxilium\Exceptions\DatabaseConnectionException $e) {
    $pb->setTemplate("internal-system-error");
    $pb->render();
}
