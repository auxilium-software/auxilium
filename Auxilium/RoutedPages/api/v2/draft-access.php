<?php

use Auxilium\Auxilium\API\APITools2;
use Auxilium\Auxilium\API\Models\DraftModel;
use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\EmailHandling\InternetMessageTransport;
use Auxilium\Schemas\CollectionSchema;
use Auxilium\Schemas\MessageSchema;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\Security;
use Auxilium\Utilities\URIUtilities;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$model = new DraftModel();
$at = new APITools2($model);
$at->requireLogin();
