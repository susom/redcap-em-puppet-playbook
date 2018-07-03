<?php
namespace Stanford\Playbook;

/*
    Call API service to pull from git dev branch to server dev branch
*/

use \REDCap;
/** @var \Stanford\Playbook\Playbook $module **/

REDCap::logEvent(USERID . " is initiating a DEV repo pull from GIT at " . date("Y-m-d"));

// This EM is not associated with a project since it is a system utility.
// Save the git info in the System Settings.
$git_url = $module->getSystemSetting("puppet_url");
$token = $module->getSystemSetting("puppet_token");

// Not sure if this should have body before host_config_key but I'm guessing not.
$body = array("host_config_key" => $token);
$context_type = "application/json";
$timeout = null;    // is this seconds? not sure what to put

//$response =  http_post($git_url, $body, $timeout, $context_type);
$response = false;
if ($response == false) {
    $message = "There was a problem updating the server instance using the puppet playbook.";
    echo "<div>" . $message . "</div><br>";
    REDCap::logEvent($message);

    // Give an option to back to the Control Center
    $go_back = htmlspecialchars($_SERVER['HTTP_REFERER']);
    echo "<a href='$go_back'>Go Back to Control Center</a>";
} else {
    $message = "Server DEV instance was successfully sync'd using puppet playbook.";
    REDCap::logEvent($message);

    // Redirect back to Control Center if successful????
    $go_back = htmlspecialchars($_SERVER['HTTP_REFERER']);
    header("Location: " . $go_back);
}

exit;

?>
