<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../environment.php';

/*
Cookie consent *not* required for "essential functionality"
https://ico.org.uk/for-organisations/guide-to-pecr/cookies-and-similar-technologies/
if (!isset($_COOKIE["cookie-consent"])) {
    echo $twig->render($twig_variables["selected_lang"]."/cookie-consent-required.html", $twig_variables);
    exit();
}
if ($_COOKIE["cookie-consent"] != "true") {
    echo $twig->render($twig_variables["selected_lang"]."/cookie-consent-required.html", $twig_variables);
    exit();
}
*/

if(isset($_GET["switch"]))
{
    switch($_GET["switch"])
    {
        case "cy":
            setcookie("lang", "cy", time() + (3600 * 24 * 30));
            break;
        case "en":
        default:
            setcookie("lang", "en", time() + (3600 * 24 * 30));
            break;
    }
}

if(isset($_SERVER["HTTP_REFERER"]))
{
    if(!str_contains($_SERVER["HTTP_REFERER"], "/lang"))
    {
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
}
header("Location: /");
exit();
