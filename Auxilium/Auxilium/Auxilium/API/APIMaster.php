<?php

namespace Auxilium\Auxilium\API;

use Auxilium\Auxilium\API\Controllers\IndexController;
use Auxilium\Auxilium\API\Controllers\JobRunnerController;
use Auxilium\Auxilium\API\Controllers\JobLookupController;
use Auxilium\Auxilium\API\Controllers\JobStatisticsController;
use Auxilium\Auxilium\API\Controllers\MessageController;
use Auxilium\Auxilium\API\Controllers\NodeController;
use Auxilium\Auxilium\API\Controllers\PDFController;
use Auxilium\Auxilium\API\Controllers\QueryController;
use Composer\Pcre\UnexpectedNullMatchException;

class APIMaster
{
    public static function GetController()
    {
        $endpoint = $_SERVER['REQUEST_URI'];
        switch($endpoint)
        {
            case "/api/v2/lfs":
                return null;

            case "/api/v2/nodes":
                return new NodeController();

            case "/api/v2/outbound-oauth-login":
                return null;

            case "/api/v2/outbound-oauth-register":
                return null;

            case "/api/v2/query":
                return new QueryController();

            case "/api/v2/retrieve-rfc822-component":
                return null;

            case "/api/v2/drafts":
                return new MessageController();

            case "/api/v2/jobs":
                return new JobLookupController();

            case "/api/v2/job-stats":
                return new JobStatisticsController();

            case "/api/v2/job-run":
                return new JobRunnerController();

            case "/api/v2/indexes":
                return new IndexController();

            case "/api/v2/pdf":
                return new PDFController();

            default:
                return null;
        }
    }

    public static function Get(): array
    {
        $controller = self::GetController();
        if($controller == null)
        {
            throw new UnexpectedNullMatchException();
        }
        $controller->EnforceLogin();
        $result = $controller->Get();
        return $result->ToAssocArray();
    }
}
