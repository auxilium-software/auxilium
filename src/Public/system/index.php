<?php
require_once "../environment.php";

$pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
$pb->requireLogin();

if (in_array("ACT", Auxilium\GraphDatabaseConnection::get_instance_node()->getPermissions())) {
    try {
        $pb->setTemplate("system/index");
        $pb->render();
    } catch (\Auxilium\Exceptions\DatabaseConnectionException $e) {
        $pb->setDefaultVariables();
        $pb->setTemplate("internal-system-error");
        $technical_details = "Exception Type:\n    ".get_class($e);
        $technical_details .= "\nMessage:\n    ".$e->getMessage();
        $technical_details .= "\nURI:\n    ".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
        $pb->setVariable("technical_details", $technical_details);
        http_response_code(500);
        $pb->render();
    }
} else {
    $pb->setDefaultVariables();
    $pb->setTemplate("internal-system-error");
    $technical_details = "Exception Type:\n    InsufficientPermissions";
    $technical_details .= "\nURI:\n    ".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
    $pb->setVariable("technical_details", $technical_details);
    http_response_code(403);
    $pb->render();
}
