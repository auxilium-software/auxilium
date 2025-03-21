<?php

use Auxilium\EmailHandling\InternetMessageTransport;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$at = Auxilium\APITools::get_instance();
//$at->requireInternalIpRange();
$at->requireInternalApiKey();

//const EXEC_TIME_LIMIT = 5000000000; // stop after 5 seconds
const EXEC_TIME_LIMIT = 1000000000; // stop after 1000 msec
//const EXEC_TIME_LIMIT = 100000000; // stop after 100 msec

const REFRESH_RATE = 3;

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

$at->setVariable("completed_jobs", $completed_jobs);
$at->setVariable("attempted_jobs", $attempted_jobs);
$at->setVariable("remaining_jobs", $total_jobs - $completed_jobs);
$at->setVariable("elapsed_time_us", ceil((hrtime(true) - $time_pre) / 1000));
$at->setVariable("exec_time_limit_us", EXEC_TIME_LIMIT);
$at->output();
