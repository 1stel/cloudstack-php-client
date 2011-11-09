CloudStack PHP Client
=====================

PHP client library for the CloudStack User API v2.2 (Reference : http://download.cloud.com/releases/2.2.0/api_2.2.12/TOC_Global_Admin.html)

Examples
--------

Initialization

    $cloudstack = new CloudStackClient(API_ENDPOINT, API_KEY, SECRET_KEY);
   
Lists

    $vms = $cloudstack->listVirtualMachines();
    foreach ($vms as $vm) {
        echo("{$vm->id} : {$vm->name} {$vm->state}<br>");
    }
   
Asynchronous tasks

    $job = $cloudstack->deployVirtualMachine(array(
        'serviceofferingid' => 1,
        'templateid'        => 259,
        'zoneid'            => 1
    ));
    echo("VM being deployed. Job id = {$job->jobid}<br>");
    
    echo("All jobs :<br>");
    foreach ($cloudstack->listAsyncJobs() as $job) {
        echo("{$job->jobid} : {$job->cmd}, status = {$job->jobstatus}<br>");
    }
