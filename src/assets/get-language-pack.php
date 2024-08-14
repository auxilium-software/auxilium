<?php
require_once "../environment.php";

$at = \auxilium\APITools::get_instance();

$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$last_uri_component = explode("?", end($uri_components));
$get_params = "";
if (count($last_uri_component) > 1) {
    $get_params = $last_uri_component[1];
}
$uri_components[count($uri_components) - 1] = $last_uri_component[0];

array_shift($uri_components);
array_shift($uri_components);
array_shift($uri_components);
$lang = array_shift($uri_components);
if ($lang == null) {
    $lang = "en";
} elseif (strlen($lang) == 0) {
    $lang = "en";
}

$pack = [];

$merged_language_pack = file_get_contents("../localised-strings.json");

function extract_lang($from, $lang) {
    $keys = array_keys($from);
    $output = [];
    foreach ($keys as &$key) {
        if (is_array($from[$key])) {
            $output[$key] = extract_lang($from[$key], $lang);
        } else {
            if ($key == $lang) {
                $output = $from[$key];
            }
        }
    }
    if (is_array($output)) {
        if (count($output) > 0) {
            return $output;
        } else {
            return null;
        }
    }
    return $output;
}

$pack = extract_lang(json_decode($merged_language_pack, true), strtolower($lang));

$at->setVariable("language", $lang);
$at->setVariable("pack", $pack);
$at->output();

?>
