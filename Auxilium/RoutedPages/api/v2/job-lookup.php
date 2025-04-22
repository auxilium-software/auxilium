<?php

use Auxilium\Auxilium\API\APITools2;
use Auxilium\Auxilium\API\Controllers\JobController;
use Auxilium\Auxilium\API\Models\JobStatsModel;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$controller = new JobController();
$controller->JobLookup();
