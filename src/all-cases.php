<?php
require_once "environment.php";


$pb = \auxilium\PageBuilder::get_instance();

try {
    $pb->requireLogin();
    if (in_array("ACT", \auxilium\GraphDatabaseConnection::get_instance_node()->getPermissions())) {
        $pb->setVariable("is_admin", true);
    }
    $pb->setTemplate("all-cases");
    $pb->render();
} catch (\auxilium\DatabaseConnectionException $e) {
    $pb->setTemplate("internal-system-error");
    $pb->render();
}


?>
