<?php

namespace Auxilium\Auxilium\API;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use Auxilium\Auxilium\API\Models\APIResponsePayload;
use Auxilium\Auxilium\API\Models\DraftModel;
use Auxilium\Auxilium\API\Models\IndexModel;
use Auxilium\Auxilium\API\Models\JobInQueueModel;
use Auxilium\Auxilium\API\Models\JobLookupModel;
use Auxilium\Auxilium\API\Models\JobStatsModel;
use Auxilium\Auxilium\API\Models\NodeModel;
use Auxilium\Auxilium\API\Models\QueryModel;
use Auxilium\SessionHandling\Session;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Info;
use OpenApi\Attributes\SecurityScheme;
use OpenApi\Attributes\Server;

#[Info(
    version: "2.0-alpha",
    title: "Auxilium API",
)]
class APITools2
{
    private QueryModel|NodeModel|DraftModel|IndexModel|JobInQueueModel|JobLookupModel|JobStatsModel $Model;



    public function __construct(QueryModel|NodeModel|DraftModel|IndexModel|JobInQueueModel|JobLookupModel|JobStatsModel $model)
    {
        $this->Model = $model;
        $this->clearReturnData();
    }



    public function clearReturnData(): void
    {
        $this->Model->ResponseCode = http_response_code();
    }
    public function setStatus(APIResponseStatus $status): void
    {
        $this->Model->Status = $status;
    }
    public function setErrorText(string $message): void
    {
        $this->Model->ErrorText = $message;
    }
    public function setResponseCode($responseCode = 200): void
    {
        http_response_code($responseCode);
    }






    #[NoReturn] public function output(): void
    {
        header("Content-Type: application/json; charset=utf-8");
        $this->Model->ResponseCode = http_response_code();
        if(!isset($this->returnData["status"]))
        {
            if(http_response_code() === 200)
            {
                $this->Model->Status = APIResponseStatus::OK;
            }
            else
            {
                $this->Model->Status = APIResponseStatus::ERROR;
            }
        }
        echo json_encode(
            $this->Model->ToAssocArray(),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
        );
        echo "\n";
        exit();
    }







    public function RequireLogin()
    {
        if(!Session::get_current()?->sessionAuthenticated())
        {
            $this->clearReturnData();
            $this->setStatus(APIResponseStatus::UNAUTHORISED);
            $this->setErrorText("Login required for this API. Check session token.");
            $this->setResponseCode(401);
            $this->output();
        }
    }
}