<?php
namespace Stanford\Playbook;
/** @var \Stanford\Playbook\Playbook $module **/

class Playbook extends \ExternalModules\AbstractExternalModule
{
    static $server_defs = array(
        "dev" => array(
            "db" => "redcap_dev",
            "username" => "redcap_dev",
            "hostname" => "redcap-db-d03.stanford.edu",
            "redcap_base_url" => "https://redcap-dev-gen2.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "plugin_log_file" => "/var/log/redcap/plugin_log_dev.log",
            "edoc_path" => "/edocs/",
            "force_ssl" => true,
            "auto_fix" => true          // If called by cron, should we automatically commit changes
        ),
        "prod" => array(
            "db" => "redcap",
            "username" => "redcap_webapp",
            "hostname" => "redcap-db-p01.stanford.edu",
            "redcap_base_url" => "https://redcap-gen2.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "plugin_log_file" => "/var/log/redcap/plugin_log.log",
            "edoc_path" => "/edocs/",
            "force_ssl" => true,
            "auto_fix" => false
        ),
        "prod_r1" => array(
            "db" => "redcap",
            "username" => "redcap_gen2",
            "hostname" => "cci-mysql-rc-02.stanford.edu",
            "redcap_base_url" => "https://redcap-gen2.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "plugin_log_file" => "/var/log/redcap/plugin_log.log",
            "edoc_path" => "/edocs/",
            "force_ssl" => true,
            "auto_fix" => false
        ),
        "restore" => array(
            "db" => "redcap",
            "username" => "redcap_webapp",
            "hostname" => "redcap-db-d03.stanford.edu",
            "redcap_base_url" => "https://redcap-restore.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "plugin_log_file" => "/var/log/redcap/plugin_log_restore.log",
            "edoc_path" => "/edocs/",
            "force_ssl" => true,
            "auto_fix" => true
        ),
        "test" => array(
            "db" => "redcap_test",
            "username" => "redcap_webapp",
            "hostname" => "redcap-db-d03.stanford.edu",
            "redcap_base_url" => "https://redcap-test.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "plugin_log_file" => "/var/log/redcap/plugin_log_test.log",
            "edoc_path" => "/edocs/",
            "force_ssl" => true,
            "auto_fix" => true
        ),
        "local" => array(
            "db" => "redcap_local",
            "hostname" => "localhost",
            "username" => "redcap_user",
            "redcap_base_url" => "http://localhost-abc/",
            "plugin_log_file" => "/tmp/plugin_log.log",
            "force_ssl" => false,
            "auto_fix" => false
        ),
        "local_jae" => array(
            "db" => "redcap",
            "hostname" => "localhost",
            "username" => "redcap",
            "redcap_base_url" => "http://localhost/",
            "hook_functions_file" =>"github/web/hooks/framework/redcap_hooks.php",
            "plugin_log_file" => "/tmp/plugin_log.log",
            "force_ssl" => false,
            "auto_fix" => false
        )
    );


    public function __construct()
    {
        parent::__construct();
    }


    public function get_refresh_playbook_url() {
        return $this->getUrl("pages/api",true,true);
    }


    public function is_playbook_configured() {
        $url = $this->getSystemSetting("puppet_url");
        $token = $this->getSystemSetting("puppet_token");
        if (empty($url) || empty($token)) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * This method calls ansible which triggers a puppet refresh of the current VM (host) including all docker instances on that server
     * @return array
     */
    public function refresh_playbook() {
        // This EM is not associated with a project since it is a system utility.
        // Save the git info in the System Settings.
        $url = $this->getSystemSetting("puppet_url");
        $token = $this->getSystemSetting("puppet_token");

        if (empty($url) || empty($token)) {
            $msg = "Unable to refresh playbook - required system parameters missing!";
            $this::log($msg);
            return array (FALSE, $msg);
        }


        // Not sure if this should have body before host_config_key but I'm guessing not.
        $body = array("host_config_key" => $token);
        $context_type = "application/json";
        $timeout = 60;
        $response = http_post($url, $body, $timeout, $context_type);

        if ($response === false) {
            $message = "There was a problem updating the server instance using the puppet playbook.";
            $result = false;
        } else {
            $message = "Playbook initiated - please check the redcap-operations channel in slack for details.";
            $result = true;
        }
        return array($result,$message);
    }


    /**
     * This is a cron-executed method to verify that the database and filesystem are in sync.  After a refresh of
     * the database, it may be necessary to modify settings in the database to reflect the new environment
     */
    public function cron_db_sync() {
        // $this::log("In cron_db_sync for " . __CLASS__);
        list($success, $message) = $this->verifyEnvironment("CRON");
    }

    /**
     * This verifies the database matches the server environment
     * @param true $dryrun
     * @return array
     */
    public function verifyEnvironment($dryrun = true) {
        global $db, $username, $hostname, $redcap_base_url;

        // Loop through server definitions to make sure all settings match
        $success = true;
        $message = "";

        foreach ($this::$server_defs as $environment => $params) {

            if ($db == $params['db'] &&
                $username == $params['username'] &&
                $hostname == $params['hostname']) {

                $server = $environment;

                $results = array();

                $results[] = "Settings for $server:\n" . var_export($params, true) . "\n-------------------";


                if ($redcap_base_url == $params['redcap_base_url'] && $dryrun !== false) {
                    // All is good
                    $results[] = "Environment matches database for $environment";
                } else {
                    // Generate update queries:

                    // Set default commit based on auto_fix if called from cron
                    if ($dryrun === "CRON") {
                        $dryrun = !$params['auto_fix'];
                        $this::log("Setting dryrun to " . ($dryrun ? "TRUE" : "FALSE"));
                        // $this::log($dryrun, "Dryrun Was Not Null");
                    } else {
                        // $this::log($dryrun, "Dryrun Is Not Null");
                    }

                    $this::log("Database is reporting " . $redcap_base_url . " but server environment should be " . $params['redcap_base_url'] . ($dryrun ? " (dryrun)":""));

                    $results[] = "Updating Database for " . $params['redcap_base_url'] . ($dryrun ? " (dryrun)":"");

                    // Update base_url
                    $results[] = $this::updateConfig('redcap_base_url', $params['redcap_base_url'], $dryrun);

                    // Update plugin logs
                    if (!empty($params['plugin_log'])) $results[] = $this::updateConfig('plugin_log', $params['plugin_log'], $dryrun);

                    // Update hook_functions_file
                    if (!empty($params['hook_functions_file'])) $results[] = $this::updateConfig('hook_functions_file', $params['hook_functions_file'], $dryrun);

                    // Update edoc_path
                    if (!empty($params['edoc_path'])) $results[] = $this::updateConfig('edoc_path', $params['edoc_path'], $dryrun);

                    // Update other more complex queries queries
                    $results[] = $this::updateSql($redcap_base_url, $params['redcap_base_url'], $dryrun);

                    if ($params['force_ssl'] === true) {
                        $http_base_url = str_replace("https:","http:",$redcap_base_url);
                        $results[] = "Re-running to move any non-SSL urls to the new url:";
                        $results[] = $this::updateSql($http_base_url, $params['redcap_base_url'], $dryrun);
                    }
                }

                $message = implode("\n", $results);

                break;
            } else {
                // $this::log("This is not the $environment server");
            }
        }

        if (empty($server)) {
            // No match was made
            $success = false;
            $message = "Unable to match current environment to any defined server";
        }
        // $this::log($success, $message);
        return array($success, $message);
    }


    public static function updateConfig($field_name, $new_value, $dryrun) {
        // self::log("Updating db: setting field $field_name to $new_value");
        $sql = "update redcap_config set value = '$new_value' where field_name = '$field_name' limit 1;";
        return "Update of $field_name to $new_value: " . self::doTransaction($sql,$dryrun);
    }

    public static function updateSql($old_uri, $new_uri, $dryrun) {
        // self::log("Updating db from $old_uri to $new_uri");

        $results = array();

        $sql = "update redcap_external_links set link_url = replace(link_url, '$old_uri', '$new_uri') where instr(link_url, '$old_uri') > 0";
        $results[] = "Update of External Links: " . self::doTransaction($sql, $dryrun);

        $sql = "update redcap_projects set data_entry_trigger_url = replace(data_entry_trigger_url, '$old_uri', '$new_uri') where instr(data_entry_trigger_url, '$old_uri') > 0";
        $results[] = "Update of DET Urls: " . self::doTransaction($sql, $dryrun);

        return implode("\n", $results);
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
        global $plugin_log_file;
        //$plugin_log_file = ""; //"/var/log/redcap/puppet_playbook.log";

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