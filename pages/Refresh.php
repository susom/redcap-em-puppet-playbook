<?php
namespace Stanford\GIT;

use \REDCap;
global $module;

REDCap::logEvent(USERID . " is initiating a DEV repo pull from GIT at " . date("Y-m-d"));

// Call API service to pull from git dev branch to server dev branch

// This EM is not associated with a project since it is a system utility.  It looks like
// project_id is a signed value so maybe I use a negative project_id

$git_url = $module->getSystemSetting("git_url");
$token = $module->getSystemSetting("git_token");

$body = array("data host_config_key" => $token);
$context_type = "application/json";

$response = false;
//$response = http_post($git_url, $body, null ,$context_type);
if ($response == false) {
    echo "<p>There was a problem updating the server dev instance from the GIT DEV repo.</p>";
    REDCap::logEvent("There was a problem updating the server dev instance from the GIT DEV repo.");
} else {
    echo "<p>Server DEV instance was successfully sync'd with DEV GIT repo.</p>";
    REDCap::logEvent("Server DEV instance was successfully sync'd with DEV GIT repo.");
}

// Redirect back to Control Center if successful????
return;

?>


