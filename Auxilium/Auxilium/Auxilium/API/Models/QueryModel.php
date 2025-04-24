<?php

namespace Auxilium\Auxilium\API\Models;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "QueryModel",
    title: "Query",
    description: "Stores information about a Deegraph Query.",
    required: [
        "Status",
        "ResponseCode",
    ],
)]
class QueryModel
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
    public mixed $Query = null;



    public ?array $Results = null;
    public ?array $Queries = null;



    public mixed $ResultSlice = null;
    public float|int|null $StartIndex = null;
    public ?int $Page = null;



    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status"        => $this->Status->value,
            "error_message" => $this->ErrorText,

            "result"        => $this->Result,
            "query"         => $this->Query,

            "results"       => $this->Results,
            "queries"       => $this->Queries,

            "result_slice"  => $this->ResultSlice,
            "start_index"   => $this->StartIndex,
            "page"          => $this->Page,
        ];
    }
}
