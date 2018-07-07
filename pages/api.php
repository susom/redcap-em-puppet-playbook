<?php
namespace Stanford\Playbook;
/** @var \Stanford\Playbook\Playbook $module **/

/*
    Call API service to pull from git dev branch to server dev branch
*/

$module::log($_SERVER['REMOTE_ADDR'], "Call to Refresh Playbook from API");

list($success,$message) = $module->refresh_playbook();

$module::log($success, $message);

if ($success) {
    echo $message;
} else {
    http_response_code(404);
    echo $message;
}

