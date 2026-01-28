<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

/** @var \IU\DataTransfer\DataTransfer $module */

require_once __DIR__ . '/../vendor/autoload.php';

use IU\DataTransfer\Authorization;
use IU\DataTransfer\Configuration;
use IU\DataTransfer\Help;
use IU\DataTransfer\Filter;
use IU\DataTransfer\Version;

try {
    # [DELETE THIS] $module->fieldWithActionTag = "Value to Pass";
    #-----------------------------------------------------------
    # Check that the user has permission to access this page
    #-----------------------------------------------------------
    $module->checkUserPagePermission(USERID);

    $user = USERID;

    $projectUsers = REDCap::getUsers();

    #-----------------------------------------------------------------
    # Process form submissions (configuration add/copy/delete/rename)
    #-----------------------------------------------------------------
    $submitValue = Filter::sanitizeButtonLabel($_POST['submitValue']);
    if (strcasecmp($submitValue, 'add') === 0) {
        #--------------------------------------
        # Add configuration
        #--------------------------------------
        if (!array_key_exists('configurationName', $_POST) || empty($_POST['configurationName'])) {
            $error = 'ERROR: No configuration name was specified.';
        } else {
            $configurationName = Filter::stripTags($_POST['configurationName']);

            # Want to make sure config name is validated before it is used
            #### Configuration::validateName($configurationName);

            # Add configuration; an exception should be thrown if the configuration
            # already exists
            $module->addConfiguration($configurationName);
        }
    } elseif (strcasecmp($submitValue, 'copy') === 0) {
        #--------------------------------------------
        # Copy configuration
        #--------------------------------------------
        $copyFromConfigName = Filter::stripTags($_POST['copyFromConfigName']);
        $copyToConfigName   = Filter::stripTags($_POST['copyToConfigName']);
        if (!empty($copyFromConfigName) && !empty($copyToConfigName)) {
            # Want to make sure config names are validated before it is used
            Configuration::validateName($copyFromConfigName);
            Configuration::validateName($copyToConfigName);

            $module->copyConfiguration($copyFromConfigName, $copyToConfigName, $user, $projectUsers);
        }
    } elseif (strcasecmp($submitValue, 'delete') === 0) {
        #---------------------------------------------
        # Delete configuration
        #---------------------------------------------
        $deleteConfigName = Filter::stripTags($_POST['deleteConfigName']);
        if (!empty($deleteConfigName)) {
            # Want to make sure config name is validated before it is used
            Configuration::validateName($deleteConfigName);

            $module->deleteConfiguration($deleteConfigName, $user, $projectUsers);
        }
    } elseif (strcasecmp($submitValue, 'rename') === 0) {
        #----------------------------------------------
        # Rename configuration
        #----------------------------------------------
        $renameConfigName    = Filter::stripTags($_POST['renameConfigName']);
        $renameNewConfigName = Filter::stripTags($_POST['renameNewConfigName']);
        if (!empty($renameConfigName) && !empty($renameNewConfigName)) {
            # Want to make sure config names are validated before it is used
            Configuration::validateName($renameConfigName);
            Configuration::validateName($renameNewConfigName);

            $module->renameConfiguration($renameConfigName, $renameNewConfigName, $user, $projectUsers);
        }
    }

    $selfUrl   = $module->getUrl('web/index.php');
    $configUrl = $module->getUrl('web/configure.php');
    $schedUrl  = $module->getUrl('web/schedule.php');

    $projectId = $module->getProjectId();

    $configurations = $module->getConfigurations(PROJECT_ID);
    $configurationNames = $configurations->getConfigurationNames();
} catch (\Exception $exception) {
    $error = 'ERROR: ' . $exception->getMessage();
}

#---------------------------------------------
# Add custom files to head section of page
#---------------------------------------------
ob_start();
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$buffer = ob_get_clean();
$cssFile = $module->getUrl('resources/data-transfer.css');
$link = '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" media="all">';
$buffer = str_replace('</head>', "    " . $link . "\n</head>", $buffer);
echo $buffer;
?>

<div class="projhdr">
<span class="fas fa-arrow-right-arrow-left" style="padding: 2px; border: solid 2px;"></span> Data Transfer
<span style="float: right; font-size: 84%;"><?php echo "Version " . Version::RELEASE_NUMBER; ?></span>
</div>



<?php

$module->renderProjectPageContentHeader($selfUrl, $error, $warning, $success);

?>

<?php
#------------------------------------------------------------
# Add configuration form
#------------------------------------------------------------
?>
<form action="<?=$selfUrl;?>" method="post" style="margin-bottom: 12px;">
    <label for="configurationName">Data Transfer configuration name:</label>
    <input name="configurationName" id="configurationName" type="text" size="40" />
    <input type="submit" name="submitValue" value="Add" />

    <span style="font-size: 140%; margin-left: 1em;" title="help">
        <i id="data-transfer-configurations-help-link" class="fa fa-question-circle" style="color: blue;"></i>
    </span>

    <div id="data-transfer-configurations-help" title="Data Transfer Configurations" style="display: none;">
        <?php echo Help::getHelpWithPageLink('data-transfer-configurations', $module); ?>
    </div>
    
    <input type="hidden" name="redcap_csrf_token" value="<?php echo $redcapCsrfToken; ?>"/>
</form>



<table class="dataTable">
<thead>
<tr class="hrd">

    <th>Configuration Name</th>
    <th>Enabled</th>
    <th>Owner</th>
    <th>Configure</th>
    <th>Schedule</th>
    <th>Copy</th>
    <th>Rename</th>
    <th>Delete</th>

</tr>
</thead>


<tbody>
<?php

#------------------------------------------------------------------
# Displays rows of table of user's Data Trasnfer configurations
#------------------------------------------------------------------
$row = 1;

if (!empty($configurations)) {
    foreach ($configurations->getConfigurationMap() as $configurationName => $configuration) {
        if ($row % 2 === 0) {
            echo '<tr class="even">' . "\n";
        } else {
            echo '<tr class="odd">' . "\n";
        }

        $configureUrl = $configUrl . '&configName=' . Filter::escapeForUrlParameter($configurationName);
        $scheduleUrl  = $schedUrl . '&configName=' . Filter::escapeForUrlParameter($configurationName);

        #-------------------------------------------------------------------------------------
        # CONFIGURATION NAME
        #-------------------------------------------------------------------------------------
        echo "<td>"
            # Note: link for configuration name causes problems for automated tests delete configuration
            # . '<a href="' . $configureUrl
            # . '" id="' . Filter::escapeForHtmlAttribute('configure-' . $configurationName) . '">'
            . Filter::escapeForHtml($configurationName)
            # . '</a>'
            . "</td>\n";

        #-------------------------------------------------------------------------------------
        # ENABLED
        #-------------------------------------------------------------------------------------
        echo '<td style="text-align: center;">';
        if ($configuration->isEnabled()) {
            echo '<img src="' . APP_PATH_IMAGES . 'tick.png" alt="Yes">';
        } else {
            echo "&nbsp;"; //    echo '<img src="' . APP_PATH_IMAGES . 'cross.png" alt="No">';
        }
        echo "</td>\n";

        #-------------------------------------------------------------------------------------
        # OWNER
        #-------------------------------------------------------------------------------------
        echo '<td>';
        echo $configuration->getOwner();
        echo "</td>\n";

        #-------------------------------------------------------------------------------------
        # CONFIGURE BUTTON - disable if user does not have permission to access configuration
        #-------------------------------------------------------------------------------------
        if ($configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
            echo '<td style="text-align:center;">'
                . '<a href="' . $configureUrl
                . '" id="' . Filter::escapeForHtmlAttribute('configure-' . $configurationName) . '">'
                . '<img alt="CONFIG" src="' . APP_PATH_IMAGES . 'gear.png">'
                . '</a>'
                . "</td>\n";
        } else {
            echo '<td style="text-align:center;">'
                . '<a href="' . $configureUrl
                . '" id="' . Filter::escapeForHtmlAttribute('configure-' . $configurationName) . '">'
                . '<i class="fa fa-eye" style="font-size: 100%; color: #999999;"></i>'
                . '</a>'
                . "</td>\n";
        }

        #-------------------------------------------------------------------------------------
        # SCHEDULE BUTTON - disable if user does not have permission to access configuration
        #-------------------------------------------------------------------------------------
        if ($configuration->hasSchedule()) {
            echo '<td style="text-align:center;">'
                . '<a href="' . $scheduleUrl
                . '" id="' . Filter::escapeForHtmlAttribute('schedule-' . $configurationName) . '">'
                . '<img alt="SCHEDULE" src="' . APP_PATH_IMAGES . 'calendar_pencil.png">'
                . '</a>'
                . "</td>\n";
        } else {
            if ($configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
                echo '<td style="text-align:center;">'
                    . '<a href="' . $scheduleUrl
                    . '" id="' . Filter::escapeForHtmlAttribute('schedule-' . $configurationName) . '">'
                    . '<img alt="SCHEDULE" src="' . APP_PATH_IMAGES . 'plus2.png">'
                    . '</a>'
                    . "</td>\n";
            } else {
                echo '<td style="text-align:center;">'
                    . '<img alt="SCHEDULE" class="disabled" src="' . APP_PATH_IMAGES . 'plus2.png">'
                    . "</td>\n";
            }
        }


        #-----------------------------------------------------------
        # COPY BUTTON
        #-----------------------------------------------------------
        echo '<td style="text-align:center;">'
            . '<input type="image" src="' . APP_PATH_IMAGES . 'page_copy.png" alt="COPY"'
            . ' class="copyConfig" style="cursor: pointer;"'
            . ' id="copyConfig' . $row . '"/>'
            . "</td>\n";

        #-----------------------------------------------------------
        # RENAME BUTTON - disable if user does not have the needed
        # data export permission to access the configuration
        #-----------------------------------------------------------
        if ($configuration->mayBeRenamedByUser($user, $projectUsers, $module->isSuperUser())) {
            echo '<td style="text-align:center;">'
                . '<input type="image" src="' . APP_PATH_IMAGES . 'page_white_edit.png" alt="RENAME"'
                . ' class="renameConfig" style="cursor: pointer;"'
                . ' id="renameConfig' . $row . '"/>'
                . "</td>\n";
        } else {
            echo '<td style="text-align:center;">'
                . '<img src="' . APP_PATH_IMAGES . 'page_white_edit.png" alt="RENAME" class="disabled" />'
                . "</td>\n";
        }

        #-----------------------------------------------------------
        # DELETE BUTTON - disable if user does not have the needed
        # data export permission to access the configuration
        #-----------------------------------------------------------
        if ($configuration->mayBeDeletedByUser($user, $projectUsers, $module->isSuperUser())) {
            echo '<td style="text-align:center;">'
                . '<input type="image" src="' . APP_PATH_IMAGES . 'delete.png" alt="DELETE"'
                . ' class="deleteConfig" style="cursor: pointer;"'
                . ' id="deleteConfig' . $row . '"/>'
                . "</td>\n";
        } else {
            echo '<td style="text-align:center;">'
                . '<img src="' . APP_PATH_IMAGES . 'delete.png" alt="DELETE" class="disabled"/>'
                . "</td>\n";
        }

        echo "</tr>\n";
        $row++;
    }
}

?>
</tbody>
</table>


<script>
    // Help dialog events
    $(document).ready(function() {

        // const module = '<?=$module->getJavascriptModuleObjectName()?>'

        // console.log("Hello from JS-file: " + module);
        // console.log("Hello from JS-file: <?=$module->fieldWithActionTag?>");


        $('#data-transfer-configurations-help-link').click(function () {
            //$('#data-transfer-configurations-help')
            //    .dialog({dialogClass: 'data-transfer-help', width: 540, maxHeight: 440})
            //    .dialog('widget').position({my: 'left top', at: 'right+50 top+90', of: $(this)})
            //    ;
            let dialogDiv = '<div id="data-transfer-configurations-help" title="Data Transfer Configurations"'
                + ' style="display: none;">'
                + '<?php echo Help::getHelpWithPageLink("data-transfer-configurations", $module); ?>';
                + '</div>';
            // helpHtml = '<?php echo Help::getHelpWithPageLink("data-transfer-configurations", $module); ?>';
            // alert(helpHtml);
            // $(dialogDiv).html(helpHtml);
            // alert(dialogDiv);
            $(dialogDiv).dialog({dialogClass: 'data-transfer-help', width: 520, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+70 top+50', of: $(this)})
            ;
            return false;
        });
    });
</script>

<?php
#--------------------------------------
# Copy config dialog
#--------------------------------------
?>
<script>
$(function() {
    copyForm = $("#copyForm").dialog({
        autoOpen: false,
        height: 220,
        width: 400,
        modal: true,
        buttons: {
            Cancel: function() {$(this).dialog("close");},
            "Copy configuration": function() {copyForm.submit(); $(this).dialog("close");}
        },
        title: "Copy configuration"
    });
    
    <?php
    # Set up click event handlers for the Copy Configuration  buttons
    $row = 1;
    foreach ($configurationNames as $configurationName) {
        echo '$("#copyConfig' . $row . '").click({fromConfig: "'
            . Filter::escapeForJavaScriptInDoubleQuotes($configurationName)
            . '"}, copyConfig);' . "\n";
        $row++;
    }
    ?>
    
    function copyConfig(event) {
        var configName = event.data.fromConfig;
        $("#configToCopy").text('"'+configName+'"');
        $('#copyToConfigName').val(configName);
        $('#copyFromConfigName').val(configName);
        $("#copyForm").dialog("open");
    }
});
</script>
<div id="copyDialog"
    title="Configuration Copy"
    style="display: none;"
    >
    <form id="copyForm" action="<?php echo $selfUrl;?>" method="post">
    To copy the configuration <span id="configToCopy" style="font-weight: bold;"></span>,
    enter the name of the new configuration below, and click on the
    <span style="font-weight: bold;">Copy configuration</span> button.
    <p>
    <span style="font-weight: bold;">New configuration name:</span>
    <input type="text" name="copyToConfigName" id="copyToConfigName">
    </p>
    <input type="hidden" name="copyFromConfigName" id="copyFromConfigName" value="">
    <input type="hidden" name="submitValue" value="copy">
    <input type="hidden" name="redcap_csrf_token" value="<?php echo $redcapCsrfToken; ?>"/>
    </form>
</div>

<?php
#--------------------------------------
# Rename config dialog
#--------------------------------------
?>
<script>
$(function() {
    // Rename Data Transfer configuration form
    renameForm = $("#renameForm").dialog({
        autoOpen: false,
        height: 220,
        width: 400,
        modal: true,
        buttons: {
            Cancel: function() {$(this).dialog("close");},
            "Rename configuration": function() {renameForm.submit();}
        },
        title: "Rename configuration"
    });

    <?php
    # Set up click event handlers for the Rename Configuration  buttons
    $row = 1;
    foreach ($configurationNames as $configurationName) {
        echo '$("#renameConfig' . $row . '").click({'
            . 'configName: "'
            . Filter::escapeForJavaScriptInDoubleQuotes($configurationName)
            . '"'
            . '}, renameConfig);' . "\n";
        $row++;
    }
    ?>
    
    function renameConfig(event) {
        var configName = event.data.configName;
        $("#configToRename").text('"'+configName+'"');
        $('#renameConfigName').val(configName);
        $('#renameNewConfigName').val(configName);
        $("#renameForm").dialog("open");
    }
});
</script>
<div id="renameDialog"
    title="Configuration Rename"
    style="display: none;"
    >
    <form id="renameForm" action="<?php echo $selfUrl;?>" method="post">
    To rename the configuration <span id="configToRename" style="font-weight: bold;"></span>,
    enter the new name for the new configuration below, and click on the
    <span style="font-weight: bold;">Rename configuration</span> button.
    <p>
    <span style="font-weight: bold;">New configuration name:</span>
    <input type="text" name="renameNewConfigName" id="renameNewConfigName">
    </p>
    <input type="hidden" name="renameConfigName" id="renameConfigName" value="">
    <input type="hidden" name="submitValue" value="rename">
    <input type="hidden" name="redcap_csrf_token" value="<?php echo $redcapCsrfToken; ?>"/>
    </form>
</div>


<?php
#--------------------------------------
# Delete config dialog
#--------------------------------------
?>
<script>
$(function() {
    // Delete Data Transfer configuration form
    deleteForm = $("#deleteForm").dialog({
        autoOpen: false,
        height: 170,
        width: 400,
        modal: true,
        buttons: {
            Cancel: function() {$(this).dialog("close");},
            "Delete configuration": function() {deleteForm.submit();}
        },
        title: "Delete configuration"
    });
  
    <?php
    # Set up click event handlers for the Delete Configuration  buttons
    $row = 1;
    foreach ($configurationNames as $configurationName) {
        echo '$("#deleteConfig' . $row . '").click({configName: "'
           . Filter::escapeForJavaScriptInDoubleQuotes($configurationName)
           . '"}, deleteConfig);' . "\n";
        $row++;
    }
    ?>
    
    function deleteConfig(event) {
        var configName = event.data.configName;
        $("#configToDelete").text('"'+configName+'"');
        $('#deleteConfigName').val(configName);
        $("#deleteForm").dialog("open");
    }
});
</script>
<div id="deleteDialog"
    title="Configuration Delete"
    style="display: none;"
    >
    <form id="deleteForm" action="<?php echo $selfUrl;?>" method="post">
        To delete the Data Transfer configuration <span id="configToDelete" style="font-weight: bold;"></span>,
        click on the <span style="font-weight: bold;">Delete configuration</span> button.
        <input type="hidden" name="deleteConfigName" id="deleteConfigName" value="">
        <input type="hidden" name="submitValue" value="delete">
        <input type="hidden" name="redcap_csrf_token" value="<?php echo $redcapCsrfToken; ?>"/>
    </form>
</div>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>


