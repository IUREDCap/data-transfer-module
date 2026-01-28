<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

/** @var \IU\DataTransfer\DataTransfer $module */

require_once __DIR__ . '/../vendor/autoload.php';

use IU\DataTransfer\AdminConfig;
use IU\DataTransfer\Configuration;
use IU\DataTransfer\Filter;
use IU\DataTransfer\Help;
use IU\DataTransfer\RedCapDb;

$error   = '';
$warning = '';
$success = '';

$pid = PROJECT_ID;
$username = USERID;

try {
    #-----------------------------------------------------------
    # Check that the user has permission to access this page
    #-----------------------------------------------------------
    $configCheck = true;
    $configuration = $module->checkUserPagePermission(USERID, $configCheck);

    $projectUsers = \REDCap::getUsers();

    $selfUrl  = $module->getUrl("web/schedule.php");

    $adminConfig = $module->getAdminConfig();
    $maxScheduleHours = $adminConfig->getMaxScheduleHours();

    $isFileDownload = false;  // cannot schedule file downloads, so this has to be loading data to a database
    // $servers  = $module->getUserAllowedServersBasedOnAccessLevel(USERID, $isFileDownload);
    $isScheduled = true;

    #------------------------------------------
    # Get request variables
    #------------------------------------------
    $configName   = $module->getRequestSessionVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');

    if (!empty($configName)) {
        $configuration = $module->getConfiguration($configName);
        if (!empty($configuration)) {
            $schedule = $configuration->getSchedule();
        }
    }

    $configurations = $module->getConfigurations(PROJECT_ID);
    $configurationNames = $configurations->getConfigurationNames();


    #-------------------------
    # Set the submit value
    #-------------------------
    $submit = '';
    if (array_key_exists('submit', $_POST)) {
        $submit = Filter::sanitizeButtonLabel($_POST['submit']);
    }

    #-----------------------------------------
    # Process a submit
    #-----------------------------------------
    $submitValue = '';
    if (array_key_exists('submitValue', $_POST)) {
        $submitValue = Filter::sanitizeButtonLabel($_POST['submitValue']);
    }

    if (strcasecmp($submitValue, 'Save') === 0) {
        # Saving the schedule values
        if (empty($configuration)) {
            throw new \Exception("No configuration specified.");
        }

        $properties = $_POST;

        $configuration->setScheduleFromProperties($properties, $username, $projectUsers, $module->isSuperUser());
        $module->setConfiguration($configuration, USERID, PROJECT_ID);

        $schedule = $configuration->getSchedule();
        $success = 'Saved.';
    }
} catch (\Throwable $throwable) {
    $errar = 'ERROR: ' . $throwable->getMessage();
}
?>


<?php
#--------------------------------------------
# Include REDCap's project page header
#--------------------------------------------
ob_start();
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$buffer = ob_get_clean();
$cssFile = $module->getUrl('resources/data-transfer.css');
$link = '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" media="all">';
$buffer = str_replace('</head>', "    " . $link . "\n</head>", $buffer);
echo $buffer;
?>

<div class="projhdr"> 
    <img style="margin-right: 7px;" src="<?php echo APP_PATH_IMAGES ?>database_table.png" alt="">Data Transfer
    <?php
    if ($testMode) {
        echo '<span style="color: blue;">[TEST MODE]</span>';
    }
    ?>
</div>


<?php
$module->renderProjectPageContentHeader($selfUrl, $error, $warning, $success);

?>



<?php
#-------------------------------------
# Configuration selection form
#-------------------------------------
?>
<form action="<?php echo $selfUrl;?>" method="post" 
    style="padding: 12px; margin-bottom: 0px; margin-right: 1em; border-radius: 10px; border: 1px solid #ccc;">

    <div id="input-container" style="margin-bottom: 17px;">
        <!-- CONFIGURATION SELECT -->
        <div style="float: left;">
            <span style="font-weight: bold;">Configurations:</span>
            <select id="dtConfigSelect" name="configName">
                <?php
                echo '<option value=""></option>';
                foreach ($configurationNames as $configurationName) {
                    $selected = '';
                    if ($configName === $configurationName) {
                        $selected = ' selected ';
                    }
                    echo '<option value="' . $configurationName . '"' . $selected . '>'
                        . $configurationName . '</option>';
                }
                ?>
            </select>
        </div>

        <!-- HELP -->
        <div style="float: left; margin-left: 6em;">

            <!--
            <a href="#" id="schedule-help-link" class="etl-help" title="help">?</a>
            -->
            <div id="schedule-help" title="Schedule Data Transfer" style="display: none; width: 100%;">
                <?php echo Help::getHelpWithPageLink('schedule', $module); ?>
            </div>

            <span style="font-size: 140%; margin-left: 1em;" title="help">
                <i id="schedule-help-link" class="fa fa-question-circle" style="color: blue;"></i>
            </span>

        </div>

        <div style="clear: both;"></div>

    </div>

    <?php
    if (empty($configName) || $configuration === null) {
        echo "<p>No configuration selected</p>\n";
    } elseif (!$configuration->isProjectComplete()) {
        echo "<p>The data transfer project information"
            . " in configuration \"{$configName}\""
            . " needs to be completed"
            . " before it can be scheduled to run.</p>\n";
    } else {
        ?>
        <?php
        $inert = '';
        if (!$configuration->mayBeModifiedByUser($username, $projectUsers, $module->isSuperUser())) {
            $inert = 'inert';
        }
        ?>


        <!-- SAVE BUTTON -->
        <div style="margin-top: 17px;" <?php echo $inert; ?>>
            <input type="submit" name="submitValue" value="Save"
                   style="padding: 0em 2em; font-weight: bold; color: rgb(45, 107, 161);">

            <!-- E-mail scheduling errors -->
            <?php
            $checked = '';
            if (!empty($configuration) && $configuration->getEmailSchedulingErrors()) {
                $checked = ' checked ';
            }
            ?>

            <input type="checkbox" name="<?php echo Configuration::EMAIL_SCHEDULING_ERRORS; ?>"
                   style="margin-left: 4em;" <?php echo $checked; ?>>
            Send e-mail when a scheduled data transfer fails

            <!-- E-mail scheduling completions -->
            <?php
            $checked = '';
            if (!empty($configuration) && $configuration->getEmailSchedulingCompletions()) {
                $checked = ' checked ';
            }
            ?>

            <input type="checkbox" name="<?php echo Configuration::EMAIL_SCHEDULING_COMPLETIONS; ?>"
                   style="margin-left: 1em;" <?php echo $checked; ?>>
            Send e-mail when a scheduled data transfer succeeds
        </div>

        <table class="cron-schedule" style="margin-top: 17px;" <?php echo $inert; ?>>
            <thead>
                <tr>
                    <th>&nbsp;</th>
                    <?php
                    foreach (AdminConfig::DAY_LABELS as $key => $label) {
                        echo "<th class=\"day\">{$label}</th>\n";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $row = 1;
                foreach ($adminConfig->getTimes() as $time) {
                    if ($row % 2 === 0) {
                        echo '<tr class="even-row">';
                    } else {
                        echo '<tr>';
                    }
                    echo '<td class="time-range">' . ($adminConfig->getHtmlTimeLabel($time)) . "</td>";

                    foreach (AdminConfig::DAY_LABELS as $day => $label) {
                        $checkboxName = $label;
                        $value = $time;
                        $checked = '';

                        if (isset($schedule[$day]) && $schedule[$day] != '' && in_array($value, $schedule[$day])) {
                            $checked = ' checked ';
                        }

                        if ($adminConfig->isAllowedCronTime($day, $time)) {
                            echo '<td class="day" >';
                            echo '<input type="checkbox" name="' . $checkboxName . '[]"'
                                . ' class="scheduleTimeCheckbox"'
                                . ' value="' . $value . '" ' . $checked . '>';
                            echo '<span style="float: right"></span>' . "\n";
                            echo '<span style="clear: both"></span>' . "\n";
                            echo '</td>' . "\n";
                        } else {
                            echo '<td class="day" >'
                                . '<input type="checkbox" name="' . $checkboxName . '[]"'
                                . ' value="' . $value . '" disabled>'
                                . '<span style="float: right"></span>'
                                . '<span style="clear: both"></span>'
                                . '</td>' . "\n";
                        }
                    }
                    echo "</tr>\n";
                    $row++;
                }
                ?>
            </tbody>
        </table>
        <?php
    }
    ?>

  <!-- </fieldset> -->
</form>

<div id="maxHoursErrorDialog" title="Error" style="display: none;">
    <p>
    The maximum number of hours per day of <?php echo $maxScheduleHours; ?>
    that a data transfer can be scheduled was exceeded.
    </p>
</div>

<script type="text/javascript">

$(document).ready(function() {
    checkSchedule();  // Initial check after loading

    function checkSchedule() {
        // alert('checkSchedule');
        let days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        let maxHours = <?php echo $maxScheduleHours; ?>;

        for (const day of days) {
            let checkboxes = $(`input[type="checkbox"][class="scheduleTimeCheckbox"][name="${day}[]"]`);
            let count = 0;
            for (const checkbox of checkboxes) {
                let span = $(checkbox).next();

                if ($(checkbox).is(":checked")) {
                    count++;
                    let td = $(checkbox).closest('td');

                    if (count > maxHours) {
                        $(span).text('> max');
                    }
                    else {
                        $(span).text('');
                    }
                }
                else {
                    $(span).text('');
                }
            }
        }
    }

    // Data Transfer configuration select change
    $("#dtConfigSelect").change(function () {
        let option = $(this).find(':selected').val();
        let url = '<?php echo $selfUrl; ?>';
        url += '&' + 'configName=' + option;
        // console.log(url);
        window.location.href = url;
    });

    // Help dialog events
    $('#schedule-help-link').click(function () {
        $('#schedule-help').dialog({dialogClass: 'data-transfer-help', width: 540, maxHeight: 440})
            .dialog('widget').position({my: 'left top', at: 'right+50 top+90', of: $(this)})
            ;
        return false;
    });

    $("input.scheduleTimeCheckbox").change(function(){
        let name = $(this).attr('name');
        let count = $(`input[type="checkbox"][name="${name}"]:checked`).length;

        if ($(this).is(":checked") && count > <?php echo $maxScheduleHours; ?>) {
            //alert("Max checkbox number exceeded.");
            $(this).prop("checked", false);
            $("#maxHoursErrorDialog").dialog("open");
        } 
        checkSchedule();
    });

    $("#maxHoursErrorDialog").dialog({
        autoOpen: false,
        modal: true,
        width: 400,
        resizable: false,
        buttons: {
            "Ok": function() {
                $(this).dialog("close");
            }
        //,
        // ialogClass: "max-hours-error"
        }
    });
});

</script>


<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>
