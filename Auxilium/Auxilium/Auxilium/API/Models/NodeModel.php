<?php

namespace Auxilium\Auxilium\API\Models;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use Auxilium\Auxilium\API\Superclasses\APIModel;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "NodeModel",
    title: "Node",
    description: "Stores information about a Deegraph Node.",
    required: [
        "Status",
        "ResponseCode",
    ],
)]
class NodeModel extends APIModel
{



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
