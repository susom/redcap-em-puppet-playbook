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

$module::log($_POST);


if (!empty($_POST['refresh_puppet'])) {
    list($result, $message) = $module->refresh_playbook();

    if ($result) {
        echo "<div class='alert alert-success'>" . $message . "</div>";
    } else {
        echo "<div class='alert alder-danger'>" . $message . "</div>";
    }
}


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

    <form method="POST" id="form0" action="">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h4><?php echo $module->getModuleName() ?></h4>
            </div>
            <div class="panel-body">
                <div>
                    <p>This tool helps administer various versions of REDCap and maintain links within that version.

                    <ul>
                        <li>There is no confirmation - when you press encode it will replace any existing value.</li>
                        <li>The encoded versions are salt-specific so you cannot transfer the encoded value to a server with a different salt</li>
                    </ul>
                </div>

                <div class="alert <?php echo $success ? "alert-success" : "alert-danger"; ?>"><pre class="output"><?php echo $message ?></pre></div>


                <button class="btn btn-primary" name="refresh_puppet" value="1">Refresh Puppet</button>

                <button class="btn btn-primary" name="update_environment" value="1">Update Environment</button>

                <div class="input-group col-lg-5">
                </div>
            </div>
        </div>
    </form>

<style>
    .alert { border: 0 !important; }
    pre.output { font-size: 8pt; overflow-wrap: normal; }
</style>

<?php

require APP_PATH_DOCROOT . "ControlCenter/footer.php";


