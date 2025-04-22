<?php

use Auxilium\Auxilium\API\APITools2;
use Auxilium\Auxilium\API\Controllers\IndexController;
use Auxilium\Auxilium\API\Models\IndexModel;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\URIUtilities;
use Darksparrow\DeegraphInteractions\DataStructures\DataURL;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$controller = new IndexController();
$controller->GenerateIndex();
