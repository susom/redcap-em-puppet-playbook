<?php
namespace Stanford\Playbook;
/** @var \Stanford\Playbook\Playbook $module **/

/*
    Call API service to pull from git dev branch to server dev branch
*/


list($success,$message) = $module->refresh_playbook();

if ($success) {
    echo $message;
} else {
    http_response_code(404);
    echo $message;
}

$module->emLog($_SERVER['REMOTE_ADDR'], "Call to Refresh Playbook from API", $success, $message);
