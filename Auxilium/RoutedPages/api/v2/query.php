<?php

use Auxilium\Auxilium\API\APITools2;
use Auxilium\Auxilium\API\Controllers\QueryController;
use Auxilium\Auxilium\API\Models\QueryModel;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\Exceptions\DeegraphException;
use Auxilium\SessionHandling\Session;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$controller = new QueryController();
$controller->RunQuery();
