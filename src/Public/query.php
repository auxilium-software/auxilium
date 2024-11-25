<?php
require_once "environment.php";

$pb = Auxilium\PageBuilder::get_instance();
$pb->requireLogin();
$pb->setTemplate("query");
if (isset($_POST["query"])) {
    $query = trim($_POST["query"]);
    $result = Auxilium\GraphDatabaseConnection::query(Auxilium\Session::get_current()->getUser(), $query);
    $pb->setVariable("result", json_encode($result, JSON_PRETTY_PRINT));
    $pb->setVariable("query", $query, JSON_PRETTY_PRINT);
}
$pb->render();
