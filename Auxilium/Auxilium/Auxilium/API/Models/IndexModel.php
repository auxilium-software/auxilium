<?php

namespace Auxilium\Auxilium\API\Models;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use Auxilium\Auxilium\API\Superclasses\APIModel;
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
class IndexModel extends APIModel
{



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