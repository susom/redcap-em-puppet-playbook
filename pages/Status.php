<?php
namespace Stanford\Playbook;
/** @var \Stanford\Playbook\Playbook $module **/

use \REDCap;

if (isset($_POST['checkCode'])) {
    // Get the current code version
    // .git/refs/heads/$branch

    // Determine branch by server name
    switch ($_SERVER['SERVER_NAME']) {
        case "redcap-dev.stanford.edu":
            $branch = "dev";
            break;
        case "redcap.stanford.edu":
            $branch = "prod";
            break;
        case "redcap-demo.stanford.edu":
            $branch = "demo";
            break;
        default:
            $branch = "foo";
    }

    // See if file exists
    $path = dirname(APP_PATH_DOCROOT) . DS . ".git/refs/heads/$branch";
    if (file_exists($path)){
        $modified = filemtime($path);
        $time = time();
        $delta = $time-$modified;

        $date = date("Y-m-d H:i:s", $modified);

        $commit = trim(file_get_contents($path));
        // $module->emDebug("$path exists", $time, $contents);

    } else {
        $module->emDebug("$path does not exist");
        $commit = "$path not found";
        $date = "";
        $delta = "";
    }

    $result = array(
        "branch" => $branch,
        "commit" => $commit,
        "modified" => $date,
        "delta" => $delta
    );

    $module->emDebug($result);
    header("Content-type: application/json");
    echo json_encode($result);
    exit();
}


require APP_PATH_DOCROOT . "ControlCenter/header.php";
if (!SUPER_USER) {
    ?>
    <div class="jumbotron text-center">
        <h3><span class="glyphicon glyphicon-exclamation-sign"></span> This utility is only available for REDCap Administrators</h3>
    </div>
    <?php
    exit();
}

// Handle refreshing the puppet button
if (!empty($_POST['refresh_puppet'])) {
    list($result, $message) = $module->refresh_playbook();

    if ($result) {
        echo "<div class='alert alert-success'>" . $message . "</div>";
    } else {
        echo "<div class='alert alert-danger'>" . $message . "</div>";
    }
}


// Handle a forced update of the environment
if (!empty($_POST['update_environment'])) {
    // Do update
    list($success, $message) = $module->verifyEnvironment(false);
    $message = "Environment Updated - see results:\n\n" . $message;
} else {
    // Do dryrun
    list($success, $message) = $module->verifyEnvironment(true);
    $message = "Updating the environment will have the following changes:\n\n" . $message;
}

?>
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h4><?php echo $module->getModuleName() ?></h4>
            <hr>
        </div>

        <div class="panel-body">
            <h6>Git Status</h6>
            <div id="updateStatus" class="alert alert-warning">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Branch:</strong>
                    </div>
                    <div class="col-md-9">
                        <span id="branch"></span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <strong>Commit Hash:</strong>
                    </div>
                    <div class="col-md-9">
                        <span id="commit"></span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <strong>Last Modified:</strong>
                    </div>
                    <div class="col-md-9">
                        <span id="modified"></span>  (<span id="delta"></span> seconds ago)
                    </div>
                </div>
            </div>
            <hr>
            <form method="POST" id="form0" action="">

                <h4>Maintaining Server and Database Environments In-Sync</h4>
                <p>This tool helps update a database if it has been cloned from another environment.</p>
                <div class="alert <?php echo $success ? "alert-success" : "alert-danger"; ?>"><pre class="output"><?php echo $message ?></pre></div>
                <button class="btn btn-primary" name="update_environment" value="1">Update Environment</button>

                <hr>
                <h4>Calling RefreshPlaybook</h4>
                <p>
                    The playbook controls the currently deployed version of code on the server.  If there are updates to
                the linked repos, you can call an ansible script from this page that will cause puppet to re-pull or configure the defined REDCap instances on this Virtual Machine.
                </p>
                <?php if (! $module->is_playbook_configured()) { ?>
                    <div class="alert alert-danger">The playbook has not been configured.  Please see the external module configuration page.</div>
                <?php } else { ?>
                    <p>
                        Alternately, if you wish to automatically trigger the playbook from another external source, you can do so using this url provided it is configured and tested:
                    </p>
                    <pre><?php echo $module->get_refresh_playbook_url() ?></pre>
                    <button class="btn btn-primary" name="refresh_puppet" value="1">Refresh Puppet</button>
                <?php } ?>
            </form>
        </div>
    </div>

<style>
    .alert { border: 0 !important; }
    pre.output { font-size: 8pt; overflow-wrap: normal; }
</style>

<script>
    $(document).ready( function() {
        // window.setInterval(checkCode, 1000);
        window.setTimeout(checkCode, 1000);
    });

    function checkCode() {
        $.post(window.location.href,"checkCode",function(data){
            $('#commit').html(data.commit);
            $('#modified').html(data.modified);
            $('#delta').html(data.delta + " secs");
            $('#branch').html(data.branch);

            if (data.delta > 0 && data.delta < 30) {
                $('#updateStatus')
                    .removeClass(function (index, className) { return (className.match (/(^|\s)alert-\S+/g) || []).join(' '); })
                    .addClass('alert alert-success');
            } else {
                $('#updateStatus')
                    .removeClass(function (index, className) { return (className.match (/(^|\s)alert-\S+/g) || []).join(' '); })
                    .addClass('alert alert-secondary');
            }

            // Check a second later...
            window.setTimeout(checkCode, 1000);

        });
    }


</script>

<?php

require APP_PATH_DOCROOT . "ControlCenter/footer.php";


