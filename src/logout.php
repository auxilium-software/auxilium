<?php
require_once "environment.php";

$pb = Auxilium\PageBuilder::get_instance();

setcookie("session_key", null, time() - (3600 * 48), "/", null, true, true);
//$twig_variables["user_info"] = null;
//echo $twig->render($twig_variables["selected_lang"]."/logout.html", $twig_variables);
$pb->setVariable("current_user", null);
$pb->setTemplate("logout");
$pb->render();

?>
