<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

/** @var \IU\DataTransfer\DataTransfer $module */

#---------------------------------------------
# Check that the user has access permission
#---------------------------------------------
$module->checkAdminPagePermission();

require_once __DIR__ . '/../../vendor/autoload.php';

use IU\DataTransfer\AdminConfig;
use IU\DataTransfer\Filter;
use IU\DataTransfer\Help;
use IU\DataTransfer\DataTransfer;

try {
    $selfUrl     = $module->getUrl(DataTransfer::ADMIN_HOME_PAGE);
    $cronInfoUrl = $module->getUrl(DataTransfer::SCHEDULE_DETAIL_PAGE);

    $adminConfig = $module->getAdminConfig();

    $submitValue = Filter::sanitizeButtonLabel($_POST['submitValue']);
    $cronJobs = $module->getAllCronJobs();

    if (strcasecmp($submitValue, 'Save') === 0) {
        $adminConfig->set(Filter::stripTagsArrayRecursive($_POST));

        $module->setAdminConfig($adminConfig);
        $success = "Admin configuration saved.";
    }
} catch (Exception $exception) {
    $error = 'ERROR: ' . $exception->getMessage();
    $error .= "\n\n" . $exception->getTraceAsString();
}

?>

<?php #require_once APP_PATH_DOCROOT . 'ControlCenter/header.php'; ?>

<?php
#---------------------------------------------
# Include REDCap's control center page header
#---------------------------------------------
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


<form action="<?php echo $selfUrl;?>" method="post">

    <div>
    <input type="submit" name="submitValue" value="Save" style="font-weight: bold; font-size: 110%; color: green;"">
    </div>

    <!--
    <p>
    Version: <?php # echo Filter::escapeForHtml($module->getVersion()); ?>
    </p>
    -->
    
    <?php
    #--------------------------------------------------
    # Allow On Demand
    #--------------------------------------------------
    #$checked = '';
    #if ($adminConfig->getAllowOnDemand()) {
    #    $checked = 'checked';
    #}
    ?>
    <!--
    <input type="checkbox" name="<?php # echo AdminConfig::ALLOW_ON_DEMAND;?>" <?php # echo $checked;?>>
    Allow Data Transfer processes to be run interactively? <br />
    -->
    
    <?php
    #------------------------------------------------
    # Allow Cron (Scheduled) Jobs
    #------------------------------------------------
    #$checked = '';
    #if ($adminConfig->getAllowCron()) {
    #    $checked = 'checked';
    #}
    ?>
    <!--
    <input type="checkbox" name="<?php # echo AdminConfig::ALLOW_CRON;?>" <?php # echo $checked;?>>
    Allow user scheduled Data Transfer cron jobs? <br />
    -->

    <?php
    #---------------------------------------
    # Last cron run time
    #---------------------------------------
    $cronTime = $module->getLastRunTime();
    if (!isset($cronTime) || !is_array($cronTime)) {
        $cronTime = '';
    } else {
        $date    = $cronTime[0];
        $hour    = $cronTime[1];
        $minutes = $cronTime[2];
        if (strlen($hour) === 1) {
            $hour = '0' . $hour;
        }
        $cronTime = "{$date} {$hour}:{$minutes}";
    }
    ?>
    <p>
    Last Data Transfer cron run time: <?php echo $cronTime; ?><br />
    </p>
    
    <p>
    Maximum number of hours a single configuration can be scheduled to run in a day:  
    <select name="<?php echo AdminConfig::MAX_SCHEDULE_HOURS; ?>">
        <?php
        foreach (range(1, 24) as $hour) {
            $selected = '';
            if ($hour == $adminConfig->getMaxScheduleHours()) {
                $selected = ' selected';
            }

            echo "<option value=\"{$hour}\"{$selected}>{$hour}</option>\n";
        }
        ?>
    </select>
    </p>

    <?php
    #----------------------------------------------------------------
    # Allowed cron times table
    #----------------------------------------------------------------
    ?>
    <p style="text-align: center; margin-top: 14px;">Allowed Data Transfer cron job times
    and number of scheduled jobs per time

    <span style="font-size: 140%; margin-left: 1em;" title="help">
        <i id="data-transfer-cron-jobs-help-link" class="fa fa-question-circle" style="color: blue;"></i>
    </span>

    <div id="data-transfer-cron-jobs-help" title="Data Transfer Cron Jobs" style="display: none;">
        <?php echo Help::getHelpWithPageLink('data-transfer-cron-jobs', $module); ?>
    </div>

    </p>
    
    <table class="cron-schedule admin-cron-schedule">
      <thead>
        <tr>
          <th>&nbsp;</th>
            <?php
            foreach (AdminConfig::DAY_LABELS as $dayLabel) {
                echo '<th class="day">' . $dayLabel . "</th>\n";
            }
            ?>
        </tr>
      </thead>
    <tbody>
        
    <?php
    #---------------------------------------------------
    # Allowed and schedule cron jobs
    #---------------------------------------------------
    $row = 1;
    foreach (range(0, 23) as $time) {
        if ($row % 2 === 0) {
            echo '<tr class="even-row">' . "\n";
        } else {
            echo '<tr>' . "\n";
        }
        $row++;
        $label = $adminConfig->getHtmlTimeLabel($time);
        ?>

        <td class="time-range"><?php echo $label;?></td>
        
        <?php
        foreach (range(0, 6) as $day) {
            $name = AdminConfig::ALLOWED_CRON_TIMES . '[' . $day . '][' . $time . ']';
            $count = count($cronJobs[$day][$time]);

            $jobsUrl = $cronInfoUrl . '&selectedDay=' . $day . '&selectedTime=' . $time;

            $checked = '';
            if ($adminConfig->isAllowedCronTime($day, $time)) {
                $checked = ' checked ';
            }
            echo '<td class="day" style="position: relative;">' . "\n";
            echo '<input type="checkbox" name="' . $name . '" ' . $checked . '>';
            if ($count > 0) {
                echo '<a href="' . $jobsUrl . '"'
                    . ' style="position: absolute; top: 1px; right: 4px;'
                    . ' text-decoration: underline; font-weight: bold;">'
                    . $count . '</a>';
            }
            echo '</td>' . "\n";
        }
        ?>
      </tr>
        <?php
    }
    ?>
    </tbody>
  </table>

</form>

<?php
#print "<pre>\n"; print_r($cronJobs); print "</pre>\n";
?>

<?php require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php'; ?>

<script>
    $(document).ready(function() {
        $( function() {
            $('#data-transfer-cron-jobs-help-link').click(function () {
                $('#data-transfer-cron-jobs-help').dialog(
                    {dialogClass: 'data-transfer-help', width: 540, maxHeight: 440}
                )
                    .dialog('widget').position({my: 'left top', at: 'right-284 top+80', of: $(this)})
                    ;
                return false;
            });
        });
    });
</script>

