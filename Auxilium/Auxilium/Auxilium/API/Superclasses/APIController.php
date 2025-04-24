<?php

namespace Auxilium\Auxilium\API\Superclasses;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\URIUtilities;

class APIController
{
    /*
    private int $ResponseCode;
    private ?string $ErrorText;
    private APIResponseStatus $Status;
    */

    public APIModel $Model;
    public URIUtilities $URIUtilities;


    public function __construct()
    {
        $this->URIUtilities = new URIUtilities();
        $this->EnforceLogin();
    }


    public function Render()
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

    public function EnforceLogin(): void
    {
        if(!Session::get_current()?->sessionAuthenticated())
        {
            $this->Model = new APIModel();
            $this->Model->Status = APIResponseStatus::UNAUTHORISED;
            $this->Model->ErrorText = "Login required for this API. Check session token.";
            $this->Model->ResponseCode = 401;

            $this->Render();
        }
    }
}