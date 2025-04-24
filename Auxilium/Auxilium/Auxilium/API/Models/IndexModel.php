<?php

namespace Auxilium\Auxilium\API\Models;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;


#[Schema(
    schema: "IndexModel",
    title: "Index",
    description: "Stores information about an Auxilium Index.",
    required: [
        "Status",
        "ResponseCode",
    ],
)]
class IndexModel
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
        nullable: true,
    )]
    public int $ResponseCode;
    #[Property(
        property: "ErrorText",
        description: "If an error occurs, the message will be placed here.",
        type: "string",
        nullable: true,
    )]
    public ?string $ErrorText = null;



    public ?int $Age = null;
    public mixed $MaxAge = null;
    public mixed $Index = null;



    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status"        => $this->Status->value,
            "error_message" => $this->ErrorText,

            "age"           => $this->Age,
            "max_age"       => $this->MaxAge,
            "index"         => $this->Index,
        ];
    }
}