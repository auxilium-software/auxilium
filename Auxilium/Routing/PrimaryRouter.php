<?php

use Auxilium\Auxilium\API\APIMaster;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';


// Get the requested URI
$requestUri = $_SERVER['REQUEST_URI'];

// Remove query string if present
$path = parse_url($requestUri, PHP_URL_PATH);

// Base directory for your public files
$publicDir = __DIR__ . "/../Public";
$routedDir = __DIR__ . "/../RoutedPages";

// Map routes to corresponding files
$routes = [
    "/new"              => "$routedDir/new.php",
    "/graph"            => "$routedDir/graph.php",
    "/form"             => "$routedDir/form.php",
    "/chats/drafts"     => "$routedDir/chats/draft.php",
    "/message-centre"   => "$routedDir/message-centre.php",
    "/users"            => "$routedDir/user.php",

    "/assets/language-packs"    => "$routedDir/assets/get-language-pack.php",

    "/email-link"   => "$routedDir/email-link.php",
];

// check for an api endpoint
if(str_starts_with($path, "/api/v1"))
{
    http_response_code(299);
    echo "404";
    die();
}
if(str_starts_with($path, "/api/v2"))
{
    APIMaster::Go();
}

// Check if the request matches a predefined route
foreach($routes as $route => $file)
{
    if(str_starts_with($path, $route))
    {
        require_once $file;
        return true;
    }
}

// Check if the requested file exists with a `.php` extension
$file = $publicDir . $path . '.php';
if(file_exists($file))
{
    require_once $file;
    return true;
}

// Check if the requested file exists without an extension
$file = $publicDir . $path;
if(file_exists($file))
{
    return false;
}

// Return a 404 response for unmatched routes
http_response_code(404);
echo "404";
die();
