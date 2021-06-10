<?php
namespace Stanford\Playbook;
/** @var \Stanford\Playbook\Playbook $module **/

require_once "emLoggerTrait.php";


class Playbook extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;
    static $server_defs = array(
        "dev" => array(
            "db" => "redcap_dev",
            "username" => "redcap_dev",
            "hostname" => "redcap-db-d03.stanford.edu",
            "redcap_base_url" => "https://redcap-dev.stanford.edu/",
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
            "redcap_base_url" => "https://redcap.stanford.edu/",
            "hook_functions_file" => "/var/www/html/hooks/framework/redcap_hooks.php",
            "plugin_log_file" => "/var/log/redcap/plugin_log.log",
            "edoc_path" => "/edocs/",
            "force_ssl" => true,
            "auto_fix" => false
        ),
        "restore" => array(
            "db" => "redcap",
            "username" => "redcap_gen2",
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
            "redcap_base_url" => "http://localhost/",
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
            $this->emLog($msg);
            return array(FALSE, $msg);
        }


        // Not sure if this should have body before host_config_key but I'm guessing not.
        $body = array("host_config_key" => $token);
//        $context_type = "application/json";
//        $timeout = 60;
//
//        // test commit
//        try {
//
//
//	        $client = new \GuzzleHttp\Client([
//		        'verify' => false,
//		        'base_uri' => $url
//	        ]);
//
//
//	        $guzzle_response = $client->post($url, [
//                \GuzzleHttp\RequestOptions::JSON => $body
//            ]);
//	        // $response = http_post($url, $body, $timeout, $context_type);
//	        $response = $guzzle_response->getBody();
//			$this->emDebug("Guzzle Response:", $response);
//        } catch (\Exception $e) {
//        	$this->emError("Error in guzzle client: ", $e->getMessage());
//			$response = false;
//	    }


        $curl = curl_init();
        $data_string = json_encode($body);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data_string,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
        }
        curl_close($curl);
        $this->emLog("error");
        $this->emLog($error_msg);
        $this->emLog("response");
        $this->emLog($response);
        if ($response === false) {
            $message = "There was a problem updating the server instance using the puppet playbook.";
            $result = false;
        } else {
            $message = "Playbook initiated - please check the redcap-operations channel in slack for details.";
            $result = true;
        }
        return array($result, $message);
    }


    /**
     * This is a cron-executed method to verify that the database and filesystem are in sync.  After a refresh of
     * the database, it may be necessary to modify settings in the database to reflect the new environment
     */
    public function cron_db_sync() {
        list($success, $message) = $this->verifyEnvironment("CRON");
        $this->emDebug("cron_db_sync", $success, $message);
    }

    /**
     * This verifies the database matches the server environment
     *
     * @param true $dryrun
     * @return array
     * @throws \Exception
     */
    public function verifyEnvironment($dryrun = true) {
        global $db, $username, $hostname, $redcap_base_url;

        // Loop through server definitions to make sure all settings match
        $success = true;
        $message = "";

        foreach ($this::$server_defs as $environment => $params) {

            if ($db == $params['db'] && $username == $params['username'] && $hostname == $params['hostname']) {

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
                        $this->emDebug("Setting dryrun to " . ($dryrun ? "TRUE" : "FALSE"));
                    } else {
                        // NOT A CRON
                        $this->emDebug("Dryrun value is:", $dryrun);
                    }

                    $this->emLog("Database is reporting " . $redcap_base_url . " but server environment should be " . $params['redcap_base_url'] . ($dryrun ? " (dryrun)":""));

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
                // if ($db !== $params['db']) $this::log("db");
                // if ($username !== $params['username']) $this::log("username");
                // if ($hostname !== $params['hostname']) $this::log("hostname");
            }
        }

        if (empty($server)) {
            // No match was made
            $success = false;
            $results = array();
            $results[] = "Unable to match current environment to any defined server";
            $results[] = "db: $db";
            $results[] = "username: $username";
            $results[] = "hostname: $hostname";
            $results[] = "redcap_base_url: $redcap_base_url";
            $results[] = "-------------------------------";
            $results[] = var_export($this::$server_defs,true);
            $message = implode("\n\t",$results);
        }
        // $this::log($success, $message);
        return array($success, $message);
    }


    /**
     * Update redcap config
     * @param $field_name
     * @param $new_value
     * @param $dryrun
     * @return string
     */
    public static function updateConfig($field_name, $new_value, $dryrun) {
        // self::log("Updating db: setting field $field_name to $new_value");
        $sql = "update redcap_config set value = '$new_value' where field_name = '$field_name' limit 1;";
        return "Update of $field_name to $new_value: " . self::doTransaction($sql,$dryrun);
    }

    /**
     * Update a bunch of url references
     * @param $old_uri
     * @param $new_uri
     * @param $dryrun
     * @return string
     */
    public static function updateSql($old_uri, $new_uri, $dryrun) {
        // self::log("Updating db from $old_uri to $new_uri");

        $results = array();

        $sql = "update redcap_external_links set link_url = replace(link_url, '$old_uri', '$new_uri') where instr(link_url, '$old_uri') > 0";
        $results[] = "Update of External Links: " . self::doTransaction($sql, $dryrun);

        $sql = "update redcap_config set value = replace(value, '$old_uri', '$new_uri') where instr(value, '$old_uri') > 0";
        $results[] = "Update of REDCap Config Links: " . self::doTransaction($sql, $dryrun);

        $sql = "update redcap_projects set data_entry_trigger_url = replace(data_entry_trigger_url, '$old_uri', '$new_uri') where instr(data_entry_trigger_url, '$old_uri') > 0";
        $results[] = "Update of DET Urls: " . self::doTransaction($sql, $dryrun);

        $sql = "update redcap_projects set data_entry_trigger_url = REGEXP_REPLACE (data_entry_trigger_url, '^https://redcap[\\-a-zA-Z0-9]*.stanford.edu/', '$new_uri') where data_entry_trigger_url REGEXP 'https://redcap[\\-a-zA-Z0-9]*.stanford.edu.*' > 0";
        $results[] = "Update of DET Using RegEx Url: " . self::doTransaction($sql, $dryrun);

        return implode("\n", $results);
    }


    /**
     * Execute SQL as part of a transaction
     * @param $sql
     * @param $dryrun
     * @return string
     */
    public static function doTransaction($sql, $dryrun) {

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        db_query($sql);
        $rows = "Rows affected: " . db_affected_rows();

        //self::log($sql, "SQL" . ($dryrun ? " (dryrun)":"") . " => " . $rows . " rows affected");

        if ($dryrun) {
            db_query("ROLLBACK");
        } else {
            db_query("COMMIT");
        }
        db_query("SET AUTOCOMMIT=1");

        return $rows;
    }

}