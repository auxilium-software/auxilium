<?php

namespace Auxilium\Auxilium\API\Models;

use Auxilium\Auxilium\API\Enumerators\APIResponseStatus;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "JobInQueueModel",
    title: "JobInQueueModel",
    description: "Stores information about a Job in Queue.",
    required: [
        "Status",
        "ResponseCode",
    ],
)]
class JobInQueueModel
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



    public ?int $CompletedJobs = null;
    public ?int $AttemptedJobs = null;
    public ?int $RemainingJobs = null;
    public ?float $ElapsedTimeUS = null;
    public ?float $ExecTimeLimitUS = null;


    public function ToAssocArray(): array
    {
        return [
            "response_code"         => $this->ResponseCode,
            "status"                => $this->Status->value,
            "error_message"         => $this->ErrorText,

            "completed_jobs"        => $this->CompletedJobs,
            "attempted_jobs"        => $this->AttemptedJobs,
            "remaining_jobs"        => $this->RemainingJobs,
            "elapsed_time_us"       => $this->ElapsedTimeUS,
            "exec_time_limit_us"    => $this->ExecTimeLimitUS,
        ];
    }
}
