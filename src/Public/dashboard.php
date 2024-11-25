<?php
require_once "environment.php";

$pb = Auxilium\PageBuilder::get_instance();
try {
    try {
        $pb->requireLogin();
        $pb->setVariable("progressive_load", false);
        if(isset($_COOKIE["progressiveload"])) {
            if ($_COOKIE["progressiveload"] == "true") {
                $pb->setVariable("progressive_load", true);
            }
        }
        if (in_array("ACT", Auxilium\GraphDatabaseConnection::get_instance_node()->getPermissions())) {
            $pb->setVariable("is_admin", true);
        }
        $pb->setTemplate("dashboard");
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
} catch (\Exception $e) {
    $pb->setDefaultVariables();
    $pb->setTemplate("internal-system-error");
    $technical_details = "Exception Type:\n    ".get_class($e);
    $technical_details .= "\nURI:\n    ".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
    $technical_details .= "\nStack Trace:\n\n".$e->getTraceAsString();
    if ($e->getInnerTrace() != null) {
        $technical_details .= "\nInner Trace:\n\n".implode("\n", $e->getInnerTrace());
    }
    $pb->setVariable("technical_details", $technical_details);
    http_response_code(500);
    $pb->render();
}
