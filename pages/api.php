<?php
namespace Stanford\Playbook;
/** @var \Stanford\Playbook\Playbook $module **/

/*
    Call API service to pull from git dev branch to server dev branch
*/

$module::log($_SERVER['REMOTE_ADDR'], "Call to Refresh Playbook from API");

$result = $module->refresh_playbook();

$module::log($result, "Result");
