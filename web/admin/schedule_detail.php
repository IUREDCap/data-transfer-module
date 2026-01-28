<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

/** @var \IU\DataTransfer\DataTransfer $module */

# FOR STAND-ALONE TASKS

#---------------------------------------------
# Check that the user has access permission
#---------------------------------------------
$module->checkAdminPagePermission();


require_once __DIR__ . '/../../vendor/autoload.php';

use IU\DataTransfer\AdminConfig;
use IU\DataTransfer\Filter;
use IU\DataTransfer\DataTransfer;

$selfUrl         = $module->getUrl(DataTransfer::SCHEDULE_DETAIL_PAGE);
$userUrl         = $module->getURL(DataTransfer::USER_CONFIG_PAGE);

$adminConfig = $module->getAdminConfig();

$selectedDay = Filter::sanitizeInt($_POST['selectedDay']);
if (empty($selectedDay)) {
    $selectedDay = Filter::sanitizeInt($_GET['selectedDay']);
    if (empty($selectedDay)) {
        $selectedDay = 0;
    }
}

$selectedTime = Filter::sanitizeInt($_POST['selectedTime']);
if (empty($selectedTime)) {
    $selectedTime = Filter::sanitizeInt($_GET['selectedTime']);
    if (empty($selectedTime)) {
        $selectedTime = 0;
    }
}

$submitValue = Filter::sanitizeButtonLabel($_POST['submitValue']);


/*
if ($submitValue === 'Run') {
    try {
        $module->runCronJobs($selectedDay, $selectedTime);
        $success = "Cron jobs were run for: day={$selectedDay} hour={$selectedTime}\n\n";
    } catch (\Exception $exception) {
        $error = $exception->getMessage();
    }
}
*/

?>


<?php #require_once APP_PATH_DOCROOT . 'ControlCenter/header.php'; ?>

<?php
#--------------------------------------------
# Include REDCap's project page header
#--------------------------------------------
ob_start();
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$buffer = ob_get_clean();
$cssFile = $module->getUrl('resources/data-transfer.css');
$link = '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" media="all">';
$buffer = str_replace('</head>', "    " . $link . "\n</head>", $buffer);
echo $buffer;
?>

<h4><img style="margin-right: 7px;" src="<?php echo APP_PATH_IMAGES ?>table_gear.png" alt="">Data Transfer Admin</h4>


<?php

$module->renderAdminPageContentHeader($selfUrl, $error, $warning, $success);

?>

<?php

#---------------------------------
# Day and time selection form
#---------------------------------
$days = AdminConfig::DAY_LABELS;
$times = $adminConfig->getTimeLabels();
?>
<h5 style="margin-top: 10px;">Data Transfers Scheduled for:</h5>
<form action="<?php echo $selfUrl;?>" method="post"
      style="padding: 4px; margin-bottom: 12px; border: 1px solid #ccc; background-color: #ccc;">
    <span style="font-weight: bold;">Day:</span>
    <select name="selectedDay" onchange="this.form.submit()">
    <?php
    foreach ($days as $value => $label) {
        if (strcmp($value, $selectedDay) === 0) {
            echo '<option value="' . $value . '" selected>' . $label . "</option>\n";
        } else {
            echo '<option value="' . $value . '">' . $label . "</option>\n";
        }
    }
    ?>
    </select>
    
    <span style="font-weight: bold; margin-left: 1em;">Time:</span>
    <select name="selectedTime" onchange="this.form.submit()">
    <?php
    foreach ($times as $value => $label) {
        if (strcmp($value, $selectedTime) === 0) {
            echo '<option value="' . $value . '" selected>' . $label . "</option>\n";
        } else {
            echo '<option value="' . $value . '">' . $label . "</option>\n";
        }
    }
    ?>
    </select>
</form>

<table class="dataTable">
    <thead>
        <tr> <th>Configuration Name</th> <th>Owner</th> <th>Project ID</th> </tr>
    </thead>
    <tbody>
        <?php
        $row = 1;

        $cronJobs = $module->getCronJobs($selectedDay, $selectedTime);

        #-----------------------------------------------------
        # Task cron jobs
        #-----------------------------------------------------
        foreach ($cronJobs as $cronJob) {
            $owner     = $cronJob['owner'];
            $projectId = $cronJob['projectId'];
            $config    = $cronJob['config'];

            $ownerUrl = APP_PATH_WEBROOT . "ControlCenter/view_users.php?username=" . urlencode($owner) . '"';

            $configUrl = $module->getURL(
                DataTransfer::DATA_TRANSFER_CONFIG_PAGE
                . '?pid=' . Filter::escapeForUrlParameter($projectId)
                . '&configName=' . Filter::escapeForUrlParameter($config)
            );

            $pidLinks = '<a href="' . APP_PATH_WEBROOT . 'index.php'
                . '?pid=' . (int)$projectId . '"'
                . ' target="_blank" rel="noopener noreferrer"'
                . '>'
                . (int)$projectId
                . '</a>';

            if ($row % 2 === 0) {
                echo '<tr class="even">' . "\n";
            } else {
                echo '<tr class="odd">' . "\n";
            }

            # echo "<td><pre>" . print_r($cronJob, true) . "</pre></td>\n";   # Configuration Name

            echo "<td><a href=\"{$configUrl}\" target=\"_blank\" rel=\"noopener noreferrer\" >{$config}</a></td>\n";
            echo "<td><a href=\"{$ownerUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$owner}</a></td>\n";

            echo "<td>" . $pidLinks . '</a>' . "</td>\n";

            #echo "<td>{$cronJob['configInstance']}</td>\n";

            echo "</tr>\n";
            $row++;
        }
        ?>
    </tbody>
</table>

<?php
# print "<pre>\n";
# print "SELECTED DAY: {$selectedDay}\n";
# print "SELECTED TIME: {$selectedTime}\n";
# print_r($cronJobs);
# print "</pre>\n";
?>

<?php
if (!empty($workflowNote)) {
    echo "<p style=\"margin-top: 24px;\"><sup>{$workflowSuperscript}</sup>{$workflowNote}</p>\n";
}
?>

<!--
<form action="<?php #echo $selfUrl;?>" method="post" style="margin-top: 12px;">
    <input type="hidden" name="selectedDay" value="<?php #echo $selectedDay; ?>">
    <input type="hidden" name="selectedTime" value="<?php #echo $selectedTime; ?>">
    <input type="submit" id="runButton" name="submitValue" value="Run"
       onclick='$("#runButton").css("cursor", "progress"); $("body").css("cursor", "progress");'/>
-->
    <?php # Csrf::generateFormToken(); ?>
<!-- </form>
-->

<div id="popup" style="display: none;"></div>


<script>
$(function() {
$('#popup').dialog({
    autoOpen: false,
    open: function(event, ui) {
        $('#popup').load(
            "<?php echo $module->getURL(
                "config_dialog.php?config={$config}&username={$username}"
                . "&projectId={$projectId}"
            ) ?>",
            function() {}
        );
    },
  modal: true,
  minHeight: 600,
  minWidth: 800,
  buttons: {
    'Save Changes': function(){
        $(this).dialog('close');
    },
    'Discard & Exit' : function(){
      $(this).dialog('close');
    }
  }
});
    $(".copyConfig").click(function(){
        var id = this.id;
        var configName = id.substring(4);
        $("#configToCopy").text('"'+configName+'"');
        $('#copyFromConfigName').val(configName);
        $("#popup").dialog("open");
    });
});
</script>


<?php require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php'; ?>
