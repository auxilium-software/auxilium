<?php

namespace Auxilium\Auxilium\API\Controllers;

use Auxilium\Auxilium\API\APITools2;
use Auxilium\Auxilium\API\Enumerators\JobStatus;
use Auxilium\Auxilium\API\Models\JobInQueueModel;
use Auxilium\Auxilium\API\Models\JobLookupModel;
use Auxilium\Auxilium\API\Models\JobStatsModel;
use Auxilium\Auxilium\API\Models\QueryModel;
use Auxilium\EmailHandling\InternetMessageTransport;
use Auxilium\Utilities\URIUtilities;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;

class JobController
{

    //const EXEC_TIME_LIMIT = 5000000000; // stop after 5 seconds
    const EXEC_TIME_LIMIT = 1000000000; // stop after 1000 msec
    //const EXEC_TIME_LIMIT = 100000000; // stop after 100 msec

    const REFRESH_RATE = 3;



    private JobInQueueModel|JobLookupModel|JobStatsModel $Model;
    private APITools2 $APITools;
    private URIUtilities $URIUtilities;

    public function __construct()
    {
        $this->URIUtilities = new URIUtilities();

    }



    #[NoReturn]
    #[Get(
        path: "/api/v2/job-stats",
        operationId: "[GET]/api/v2/job-stats",
        description: "",
        summary: "Job statistics",
        tags: [
            "Jobs",
        ],
        responses: [
            new Response(
                response: 200,
                description: "",
                content: new JsonContent(
                    ref: "#/components/schemas/JobStatsModel"
                )
            )
        ],
        deprecated: false,
    )]
    public function GetJobStats()
    {
        $this->Model = new JobStatsModel();
        $this->APITools = new APITools2($this->Model);
        $this->APITools->requireLogin();


        $c_time = time();
        $job_names = [];
        $total_jobs = 0;

        $jobs = scandir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/");
        foreach($jobs as &$job_name)
        {
            if(!in_array($job_name, [".", "..", "Completed", "Failed"]))
            {
                $total_jobs++;
                $job_name = substr($job_name, 0, -5);
                var_dump($job_name);
                $time = unpack(
                    format: "Jtime",
                    string: hex2bin(
                        string: substr(
                            string: $job_name,
                            offset: 0,
                            length: 16
                        )
                    )
                )["time"];
                $job_info = [
                    "id" => $job_name,
                    "created" => $time,
                    "time_elapsed" => $c_time - $time
                ];
                $job_names[] = $job_info;
            }
        }

        $this->Model->Jobs = $job_names;
        $this->APITools->output();

    }



    #[NoReturn]
    #[Get(
        path: "/api/v2/jobs",
        operationId: "[GET]/api/v2/jobs",
        description: "",
        summary: "Jobs",
        tags: [
            "Jobs",
        ],
        responses: [
            new Response(
                response: 200,
                description: "",
                content: new JsonContent(
                    ref: "#/components/schemas/JobLookupModel"
                )
            )
        ],
        deprecated: false,
    )]
    public function JobLookup()
    {
        $this->Model = new JobLookupModel();
        $this->APITools = new APITools2($this->Model);
        $this->APITools->requireLogin();


        $job_id = $this->URIUtilities->getURIComponents()[4];
        $action = "access";
        if(count($this->URIUtilities->getURIComponents()) > 5)
        {
            $action = strtolower($this->URIUtilities->getURIComponents()[5]);
        }
        $job_key = null;

        if(!preg_match("/^[0-9a-f]{16}\\.[0-9a-zA-Z_-]{32}$/", $job_id))
        {
            if(preg_match("/^[0-9a-f]{16}\\.[0-9a-zA-Z_-]{32}\\.[0-9a-zA-Z_-]{64}$/", $job_id))
            {
                $id_cmps = explode(".", $job_id);
                $job_id = $id_cmps[0] . "." . $id_cmps[1];
                $job_key = $id_cmps[2];
            }
            else
            {
                $this->APITools->setErrorText("Malformed job_id");
                $this->Model->JobID = $job_id;
                $this->APITools->output();
            }
        }

        $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_id . ".json";
        $this->Model->JobStatus = JobStatus::PENDING;
        if(!file_exists($job_path))
        {
            $this->Model->JobStatus = JobStatus::DONE;
            $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Completed/" . $job_id . ".json";
        }
        if(!file_exists($job_path))
        {
            $this->Model->JobStatus = JobStatus::FAILED;
            $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/" . $job_id . ".json";
        }
        if(!file_exists($job_path))
        {
            $this->APITools->setErrorText("Invalid job id");
            $this->APITools->output();
        }
        $job_content = json_decode(file_get_contents($job_path), true);
        $key_authed = true;
        if(isset($job_content["job_key"]))
        {
            $key_authed = false;
            if($job_key == $job_content["job_key"])
            {
                $key_authed = true;
            }
            else
            {
                if($job_key != null)
                {
                    $this->APITools->setErrorText("Invalid job key");
                    $this->APITools->output();
                }
            }
        }

        if($action == "access")
        {
            $this->Model->JobID = $job_id;
            if($key_authed)
            {
                $this->Model->Content = $job_content;
            }
            else
            {
                $this->Model->Note = "KEY_REQUIRED_TO_VIEW_CONTENT";
            }
            $this->APITools->output();
        }
        else
        {
            $this->APITools->setErrorText("Invalid action");
            $this->APITools->output();
        }
    }



    #[NoReturn]
    #[Get(
        path: "/api/v2/job-run",
        operationId: "[GET]/api/v2/job-run",
        description: "",
        summary: "Jobs",
        tags: [
            "Jobs",
        ],
        responses: [
            new Response(
                response: 200,
                description: "",
                content: new JsonContent(
                    ref: "#/components/schemas/JobInQueueModel"
                )
            )
        ],
        deprecated: false,
    )]
    public function HandleJob()
    {
        $this->Model = new JobInQueueModel();
        $this->APITools = new APITools2($this->Model);
        $this->APITools->requireInternalApiKey();



        $time_pre = hrtime(true);
        $completed_jobs = 0;
        $attempted_jobs = 0;
        $total_jobs = 0;

        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/LastJobRun"))
        {
            file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/LastJobRun", time());
        }
        $last_run = intval(file_get_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/LastJobRun"));
        $this_run = time();
        $run_diff = $this_run - $last_run;
        file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/LastJobRun", $this_run);

        // create directories
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/"))
            mkdir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/", 0700, true);
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Completed/"))
            mkdir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Completed/", 0700, true);
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/"))
            mkdir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/", 0700, true);

        if($run_diff > REFRESH_RATE)
        {
            $job_name = "cron-" . $this_run;
            $job_payload = [
                "type" => "SCAN_INBOXES",
                "tries" => 0,
                "max_tries" => 1
            ];
            file_put_contents(
                LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name,
                json_encode($job_payload, JSON_PRETTY_PRINT)
            );
        }

        $jobs = scandir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/");
        foreach($jobs as &$job_name)
        {
            if(!in_array($job_name, [".", "..", "Completed", "Failed"]))
            {
                $total_jobs++;
            }
        }

        foreach($jobs as &$job_name)
        {
            if(!in_array($job_name, [".", "..", "Completed", "Failed"]))
            {
                $job_payload = json_decode(file_get_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name), true);
                $success = false;
                $error_message = null;
                $exception = null;
                $job_payload["tries"]++;

                try
                {
                    switch($job_payload["type"])
                    {
                        case "SEND_EMAIL":
                            $success = InternetMessageTransport::send_now($job_payload["content"]);
                            break;
                        case "SCAN_INBOXES":
                            $success = InternetMessageTransport::scan_inboxes();
                            break;
                        case "INGEST_S3_EMAIL":
                            //$success = \auxilium\InternetMessageTransport::ingest_s3_object($job_payload["key"]);
                            break;
                    }
                }
                catch(Exception $e)
                {
                    $success = false;
                    $exception = $e;
                }

                if($success !== true)
                {
                    $error_message = [
                        "try" => ($job_payload["tries"] - 1)
                    ];
                    if($exception != null)
                    {
                        $error_message["exception"] = $exception->getMessage();
                    }
                    if($success !== false)
                    {
                        $error_message["return_object"] = $success;
                    }
                    $success = false;
                }


                if($error_message != null)
                {
                    if(!array_key_exists("errors", $job_payload))
                    {
                        $job_payload["errors"] = [];
                    }
                    $job_payload["errors"][] = $error_message;
                }

                if($success)
                {
                    unlink(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name);
                    file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Completed/" . $job_name, json_encode($job_payload, JSON_PRETTY_PRINT));
                }
                else
                {
                    if($job_payload["tries"] < $job_payload["max_tries"])
                    {
                        file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name, json_encode($job_payload, JSON_PRETTY_PRINT));
                    }
                    else
                    {
                        unlink(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name);
                        file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/" . $job_name, json_encode($job_payload, JSON_PRETTY_PRINT));
                    }
                }
                if((hrtime(true) - $time_pre) > EXEC_TIME_LIMIT)
                {
                    break;
                }
            }
        }

        $this->Model->CompletedJobs = $completed_jobs;
        $this->Model->AttemptedJobs = $attempted_jobs;
        $this->Model->RemainingJobs = $total_jobs - $completed_jobs;
        $this->Model->ElapsedTimeUS = ceil((hrtime(true) - $time_pre) / 1000);
        $this->Model->ExecTimeLimitUS = EXEC_TIME_LIMIT;

        $this->APITools->output();
    }
}
