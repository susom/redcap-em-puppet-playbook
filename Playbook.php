<?php

namespace Stanford\Playbook;
/** @var \Stanford\Playbook\Playbook $module **/

/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 6/1/2018
 * Time: 9:58 AM
 */

use \REDCap;

class Playbook extends \ExternalModules\AbstractExternalModule
{
    static $server_defs = array(
        "dev" => array(
            "db" => "redcap_dev",
            "username" => "redcap_dev",
            "hostname" => "redcap-db-d03.stanford.edu",
            "redcap_base_url" => "https://redcap-dev-gen2.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "auto_fix" => true
        ),
        "prod" => array(
            "db" => "redcap",
            "username" => "redcap_webapp",
            "hostname" => "redcap-db-p01.stanford.edu",
            "redcap_base_url" => "https://redcap-gen2.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "auto_fix" => false
        ),
        "restore" => array(
            "db" => "redcap",
            "username" => "redcap_webapp",
            "hostname" => "redcap-db-d03.stanford.edu",
            "redcap_base_url" => "https://redcap-restore.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "auto_fix" => true
        ),
        "test" => array(
            "db" => "redcap_test",
            "username" => "redcap_webapp",
            "hostname" => "redcap-db-d03.stanford.edu",
            "redcap_base_url" => "https://redcap-test.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "auto_fix" => true
        ),
        "local" => array(
            "db" => "redcap_local",
            "hostname" => "localhost",
            "username" => "redcap_user",
            "redcap_base_url" => "http://localhost/",
            "auto_fix" => false
        )
    );




    public function __construct()
    {
        parent::__construct();
    }


    public function refresh_playbook() {
        // This EM is not associated with a project since it is a system utility.
        // Save the git info in the System Settings.
        $url = $this->getSystemSetting("puppet_url");
        $token = $this->getSystemSetting("puppet_token");

        // Not sure if this should have body before host_config_key but I'm guessing not.
        $body = array("host_config_key" => $token);
        $context_type = "application/json";
        $timeout = 60;    // is this seconds? not sure what to put

        $errors = array();

        $response =  http_post($url, $body, $timeout, $context_type);
        if ($response == false) {
            $message = "There was a problem updating the server instance using the puppet playbook.";
            $result = false;
            //REDCap::logEvent($message);

        } else {
            $message = "Playbook initiated - please check the redcap-operations channel in slack for details.";
            $result = true;
            // REDCap::logEvent($message);
        }

        return array($result,$message);
        // empty($errors) ? array(true, "Playbook Refresh initiated...") : array(false, $errors);
    }


    /**
     * This is a cron-executed method to verify that the database and filesystem are in sync.  After a refresh of
     * the database, it may be necessary to modify settings in the database to reflect the new environment
     */
    public function cron_db_sync() {
        $this::log("In dbFileSync");

        list($success, $message) = $this->verifyEnvironment();
        $this::log($success, $message);
    }

    public function verifyEnvironment($dryrun = null) {
        global $db, $username, $hostname, $redcap_base_url;

        // Loop through server definitions to make sure all settings match
        $success = false;
        $message = "";

        foreach ($this::$server_defs as $environment => $params) {
            if ($db == $params['db'] && $username == $params['username'] && $hostname == $params['hostname']) {
                $server = $environment;

                if ($redcap_base_url == $params['redcap_base_url']) {
                    // All is good
                    $success = true;
                    $message = "Environment is $environment";
                } else {
                    $this::log("Database is reporting " . $redcap_base_url . " but server environment should be " . $params['redcap_base_url']);
                    // Generate update queries:

                    if ($dryrun == null) $dryrun = $params['auto_fix'];
                    list($success, $message) = $this::updateDbInstance($redcap_base_url, $params['redcap_base_url'], $dryrun);
                }

                break;
            } else {
                $this::log("This is not the $environment server");
            }
        }

        if (empty($server)) {
            // No match was made
            // TODO: error handler
            $success = false;
            $message = "Unable to match current environment to any defined server";
        }

        $this::log($success, $message);

        return array($success, $message);
    }



    public static function updateDbInstance($old_uri, $new_uri, $dryrun = false) {
        self::log("Updating db from $old_uri to $new_uri");

        $success = true;
        $results = array();

        // $errors = array();
        //
        // $old_uri = @self::$db_to_uri_map[$old_instance];
        // $new_uri = @self::$db_to_uri_map[$new_instance];
        // $old_uri = "old"; //@self::$db_to_uri_map[$old_instance];
        // $new_uri = "new"; //@self::$db_to_uri_map[$new_instance];
        //
        // if (empty($old_uri)) $errors[] = "No URI defined for $old_instance";
        // if (empty($new_uri)) $errors[] = "No URI defined for $new_instance";
        //
        //

        $sql = "update redcap_config set value = '$new_uri' where field_name = 'redcap_base_url' limit 1;";
        $results[] = "Updating redcap_base_url to $new_uri: " . self::doTransaction($sql,$dryrun);


        $sql = "update redcap_external_links set link_url = replace(link_url, '$old_uri', '$new_uri') where instr(link_url, '$old_uri') > 0";
        $results[] = "Updating External Links: " . self::doTransaction($sql, $dryrun);


        $sql = "update redcap_projects set data_entry_trigger_url = replace(data_entry_trigger_url, '$old_uri', '$new_uri') where instr(data_entry_trigger_url, '$old_uri') > 0";
        $results[] = "Updating DET Urls: " . self::doTransaction($sql, $dryrun);


        return array($success, implode("\n", $results));
    }

    public static function doTransaction($sql, $dryrun) {

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        db_query($sql);
        $rows = "Rows affected: " . db_affected_rows();

        self::log($sql, "SQL" . ($dryrun ? " (dryrun)":"") . " => " . $rows . " rows affected");

        if ($dryrun) {
            db_query("ROLLBACK");
        } else {
            db_query("COMMIT");
        }
        db_query("SET AUTOCOMMIT=1");

        return $rows;
    }



    public static function log($obj = "Here", $detail = null, $type = "INFO") {
        self::writeLog($obj, $detail, $type);
    }

    public static function debug($obj = "Here", $detail = null, $type = "DEBUG") {
        self::writeLog($obj, $detail, $type);
    }

    public static function error($obj = "Here", $detail = null, $type = "ERROR") {
        self::writeLog($obj, $detail, $type);
        //TODO: BUBBLE UP ERRORS FOR REVIEW!
    }

    public static function writeLog($obj, $detail, $type) {
        $plugin_log_file = ""; //"/var/log/redcap/puppet_playbook.log";

        // Get calling file using php backtrace to help label where the log entry is coming from
        $bt = debug_backtrace();
        $calling_file = $bt[1]['file'];
        $calling_line = $bt[1]['line'];
        $calling_function = $bt[3]['function'];
        if (empty($calling_function)) $calling_function = $bt[2]['function'];
        if (empty($calling_function)) $calling_function = $bt[1]['function'];
        // if (empty($calling_function)) $calling_function = $bt[0]['function'];

        // Convert arrays/objects into string for logging
        if (is_array($obj)) {
            $msg = "(array): " . print_r($obj,true);
        } elseif (is_object($obj)) {
            $msg = "(object): " . print_r($obj,true);
        } elseif (is_string($obj) || is_numeric($obj)) {
            $msg = $obj;
        } elseif (is_bool($obj)) {
            $msg = "(boolean): " . ($obj ? "true" : "false");
        } else {
            $msg = "(unknown): " . print_r($obj,true);
        }

        // Prepend prefix
        if ($detail) $msg = "[$detail] " . $msg;

        // Build log row
        $output = array(
            date( 'Y-m-d H:i:s' ),
            empty($project_id) ? "-" : $project_id,
            basename($calling_file, '.php'),
            $calling_line,
            $calling_function,
            $type,
            $msg
        );

        // Output to plugin log if defined, else use error_log
        if (!empty($plugin_log_file)) {
            file_put_contents(
                $plugin_log_file,
                implode("\t",$output) . "\n",
                FILE_APPEND
            );
        }
        if (!file_exists($plugin_log_file)) {
            // Output to error log
            error_log(implode("\t",$output));
        }
    }

}