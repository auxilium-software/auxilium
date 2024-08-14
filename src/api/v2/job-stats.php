<?php
require_once "../../environment.php";

$at = \auxilium\APITools::get_instance();
//$at->requireInternalIpRange();
//$at->requireInternalApiKey();

$c_time = time();
$job_names = [];
$total_jobs = 0;

$jobs = scandir(LOCAL_EPHEMERAL_CREDENTIAL_STORE."jobs");
foreach ($jobs as &$job_name) {
    if (!in_array($job_name, [".", "..", "done", "failed"])) {
        $total_jobs++;
        $job_name = substr($job_name, 0, -5);
        $time = unpack("Jtime", hex2bin(substr($job_name, 0, 16)))["time"];
        $job_info = [
            "id" => $job_name,
            "created" => $time,
            "time_elapsed" => $c_time - $time
        ];
        array_push($job_names, $job_info);
    }
}

$at->setVariable("jobs", $job_names);
$at->output();
            
?>
