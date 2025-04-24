<?php

namespace Auxilium\Auxilium\API\Models;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "DraftModel",
    title: "Draft",
    description: "Stores information about an email draft.",
    required: [
        "Status",
        "ResponseCode",
    ],
)]
class DraftModel
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



    public mixed $DraftID = null;
    public ?int $BytesWritten = null;
    public ?array $Content = null;
    public ?string $JobReference = null;
    public ?array $AttachFailures = null;
    public ?array $AttachedTo = null;
    public ?string $MessageNodeID = null;



    public function ToAssocArray(): array
    {
        return [
            "response_code"     => $this->ResponseCode,
            "status"            => $this->Status->value,
            "error_message"     => $this->ErrorText,

            "draft_id"          => $this->DraftID,
            "bytes_written"     => $this->BytesWritten,
            "content"           => $this->Content,
            "job_reference"     => $this->JobReference,
            "attach_failures"   => $this->AttachFailures,
            "attached_to"       => $this->AttachedTo,
            "message_node_id"   => $this->MessageNodeID,
        ];
    }
}