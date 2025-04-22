<?php

namespace Auxilium\Auxilium\API\Models;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "Node",
    title: "Node",
    description: "Stores information about a Deegraph Node.",
    required: [
        "Status",
        "ResponseCode",
    ],
)]
class NodeModel
{
    #[Property(
        property: "Status",
        description: "The status of the API request.",
        type: "string",
        nullable: false,
    )]
    public APIResponseStatus $Status;
    #[Property(
        property: "ResponseCode",
        description: "The HTTP Status code.",
        type: "int",
        nullable: false,
    )]
    public int $ResponseCode;
    #[Property(
        property: "ErrorText",
        description: "If an error occurs, the message will be placed here.",
        type: "string",
        nullable: false,
    )]
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
