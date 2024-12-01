<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';

$pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
$pb->requireLogin();
$pb->setTemplate("Pages/query");
if(isset($_POST["query"]))
{
    $query = trim($_POST["query"]);
    $result = Auxilium\GraphDatabaseConnection::query(\Auxilium\SessionHandling\Session::get_current()->getUser(), $query);
    $pb->setVariable("result", json_encode($result, JSON_PRETTY_PRINT));
    $pb->setVariable("query", $query, JSON_PRETTY_PRINT);
}
$pb->render();
