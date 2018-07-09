<?php
namespace Stanford\Playbook;
/** @var \Stanford\Playbook\Playbook $module **/

use \REDCap;

require APP_PATH_DOCROOT . "ControlCenter/header.php";

if (!SUPER_USER) {
    ?>
    <div class="jumbotron text-center">
        <h3><span class="glyphicon glyphicon-exclamation-sign"></span> This utility is only available for REDCap Administrators</h3>
    </div>
    <?php
    exit();
}

// $module::log($_POST);


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
    $message = "Updating the environment will have the following changes:";
}

?>

    <form method="POST" id="form0" action="">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h4><?php echo $module->getModuleName() ?></h4>
            </div>
            <div class="panel-body">
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
            </div>
        </div>
    </form>


<style>
    .alert { border: 0 !important; }
    pre.output { font-size: 8pt; overflow-wrap: normal; }
</style>

<?php

require APP_PATH_DOCROOT . "ControlCenter/footer.php";


