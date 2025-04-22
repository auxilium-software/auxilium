<?php

use Auxilium\APITools;
use Auxilium\Auxilium\API\Controllers\PDFController;
use Auxilium\Helpers\PDF\PDFGeneration;
use Auxilium\Utilities\URIUtilities;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$controller = new PDFController();
$controller->GeneratePDF();
