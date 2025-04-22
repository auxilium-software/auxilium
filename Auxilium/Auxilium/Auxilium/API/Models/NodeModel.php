<?php

namespace Auxilium\Auxilium\API\Models;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;

class NodeModel
{
    public APIResponseStatus $Status;
    public int $ResponseCode;
    public ?string $ErrorText = null;



    public mixed $Result = null;
    public mixed $Request = null;



    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status"        => $this->Status->value,
            "error_message" => $this->ErrorText,

            "result"        => $this->Result,
            "request"       => $this->Request,
        ];
    }
}
