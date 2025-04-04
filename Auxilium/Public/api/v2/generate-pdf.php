<?php

use Auxilium\APITools;
use Auxilium\Helpers\PDF\PDFGeneration;
use Auxilium\Utilities\URIUtilities;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$at = APITools::get_instance();
$at->requireLogin();

$uri = new URIUtilities();
$type = $uri->getURIComponents()[4];
$uuid = $uri->getURIComponents()[5];

switch($type)
{
    case "case":
        PDFGeneration::GenerateCaseOverviewPage(caseID: "{{$uuid}}")->Render();
}
