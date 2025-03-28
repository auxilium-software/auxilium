<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$at = Auxilium\APITools::get_instance();
//$at->requireInternalIpRange();
//$at->requireInternalApiKey();

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

$at->setVariable("jobs", $job_names);
$at->output();
