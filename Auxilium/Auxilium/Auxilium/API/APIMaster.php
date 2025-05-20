<?php

namespace Auxilium\Auxilium\API;

use App\API\Controllers\IncidentEventController;
use App\Common\UUID;
use App\Enumerators\ItemPrepend;
use Auxilium\Auxilium\API\Controllers\IndexController;
use Auxilium\Auxilium\API\Controllers\JobRunnerController;
use Auxilium\Auxilium\API\Controllers\JobLookupController;
use Auxilium\Auxilium\API\Controllers\JobStatisticsController;
use Auxilium\Auxilium\API\Controllers\LFSController;
use Auxilium\Auxilium\API\Controllers\MessageController;
use Auxilium\Auxilium\API\Controllers\NodeController;
use Auxilium\Auxilium\API\Controllers\PDFController;
use Auxilium\Auxilium\API\Controllers\QueryController;
use Auxilium\Auxilium\API\Superclasses\APIController;
use Auxilium\Utilities\Logging;
use Composer\Pcre\UnexpectedNullMatchException;
use Exception;

class APIMaster
{


    protected static function MatchURI(string $regex): bool
    {
        $regex = str_replace(
            ["/", "?"],
            ["\\/", "\\?"],
            $regex
        );
        $regex = "/^$regex$/";

        // strip out any params
        $urlPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

        $temp = preg_match(pattern: $regex, subject: $urlPath);

        return $temp === 1;
    }

    public static function GetController(): ?APIController
    {
        $routes = [
            "/api/v2/lfs(/.+)"                      => LFSController::class,
            "/api/v2/nodes(/.+)"                    => NodeController::class,
            "/api/v2/outbound-oauth-login"          => null,
            "/api/v2/outbound-oauth-register"       => null,
            "/api/v2/query"                         => QueryController::class,
            "/api/v2/retrieve-rfc822-component"     => null,
            "/api/v2/jobs"                          => JobLookupController::class,
            "/api/v2/job-stats"                     => JobStatisticsController::class,
            "/api/v2/job-run"                       => JobRunnerController::class,
            "/api/v2/indexes(/.+)"                  => IndexController::class,
            "/api/v2/pdf(/.+)"                      => PDFController::class,
        ];

        foreach ($routes as $regex => $controllerClass)
        {
            if (self::MatchURI(regex: $regex))
            {
                return $controllerClass ? new $controllerClass() : null;
            }
        }

        http_response_code(response_code: 404);
        echo "{\"status\": 404}";
        die();
    }

    public static function Go(): array
    {
        $controller = self::GetController();
        if($controller == null)
        {
            throw new UnexpectedNullMatchException();
        }
        $controller->EnforceLogin();
        switch($_SERVER['REQUEST_METHOD'])
        {
            case "GET":
                $result = $controller->Get();
                break;
            case "POST":
                $result = $controller->Post();
                break;
            case "DELETE":
                $result = $controller->Delete();
                break;
        }
        return $result->ToAssocArray();
    }
}
