<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

/** @var \IU\DataTransfer\DataTransfer $module */

require_once __DIR__ . '/../vendor/autoload.php';

use IU\DataTransfer\Authorization;
use IU\DataTransfer\Configuration;
use IU\DataTransfer\DataTransfer;
use IU\DataTransfer\DataTransferer;
use IU\DataTransfer\Filter;
use IU\DataTransfer\Help;
use IU\DataTransfer\Version;

$error   = '';
$warning = '';
$success = '';

try {
    $module->checkUserPagePermission(USERID);

    $selfUrl   = $module->getUrl('web/manual_transfer.php');

    $configurations = $module->getConfigurations(PROJECT_ID);
    $configurationNames = $configurations->getConfigurationNames();

    $configName = $module->getRequestSessionVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');
    if (!empty($configName)) {
        $configuration = $module->getConfiguration($configName, PROJECT_ID);

        if ($configuration !== null && $configuration->isProjectComplete()) {
            $sourceProject      = $configuration->getSourceProject($module);
            $destinationProject = $configuration->getDestinationProject($module);

            #-----------------------------------
            # Check field map
            #-----------------------------------
            $fieldMap           = $configuration->getFieldMap();
            $fieldMapObject     = $configuration->getFieldMapObject();

            if ($fieldMapObject->getNumberOfMappings() === 0) {
                $warning = "WARNING: Configuration \"{$configName}\" contains no field mappings;"
                   . " no data will be transferred.";
            } else {
                $numberOfIncompleteMappings
                    = count($fieldMapObject->getIncompleteMappings($sourceProject, $destinationProject));

                if ($numberOfIncompleteMappings > 0) {
                    $fieldMapWarning = "WARNING: Configuration \"{$configName}\" has {$numberOfIncompleteMappings}"
                        . " incomplete field mapping";
                    if ($numberOfIncompleteMappings > 1) {
                        $fieldMapWarning .= 's';
                    }
                    $fieldMapWarning .= ', which will be ignored.';
                }

                $numberOfErrorMappings = count($fieldMapObject->getErrorMappings($sourceProject, $destinationProject));
                if ($numberOfErrorMappings > 0) {
                    $fieldMapError = "ERROR: Configuration \"{$configName}\" has {$numberOfErrorMappings}"
                        . " field mapping";
                    if ($numberOfErrorMappings > 1) {
                        $fieldMapError .= 's';
                        $fieldMapError .= ' with errors. These field mappings will be ignored.';
                    } else {
                        $fieldMapError .= ' with errors. This field mapping will be ignored.';
                    }
                }
            }


            #-----------------------------------------------------------------
            # Process form submissions for transfer of data
            #-----------------------------------------------------------------
            $submitValue = Filter::sanitizeButtonLabel($_POST['submitValue']);

            if ($submitValue === 'Transfer') {
                $dataTransferer = new DataTransferer($module, $configuration);
                $dataTransferer->transferData(USERID, DataTransferer::MANUAL_TRANSFER);

                $success = 'Data transferred from project "' . $sourceProject->getTitle() . '"'
                    . ' to project "' . $destinationProject->getTitle() . '".';
            }
        }
    }
} catch (\Exception $exception) {
    $error = 'ERROR: ' . $exception->getMessage()
        # . "\n" . $exception->getTraceAsString()
        ;
}


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
<span class="fas fa-arrow-right-arrow-left" style="padding: 2px; border: solid 2px;"></span> Data Transfer
<span style="float: right; font-size: 84%;"><?php echo "Version " . Version::RELEASE_NUMBER; ?></span>
</div>


<?php
$module->renderProjectPageContentHeader($selfUrl, $error, $warning, $success);
?>

<div>
    <span style="font-weight: bold;">Configurations:</span>
    <select id="dtConfigSelect">
        <?php
        echo '<option value=""></option>';
        foreach ($configurationNames as $configurationName) {
            $selected = '';
            if ($configName === $configurationName) {
                $selected = ' selected ';
            }
            echo '<option value="' . $configurationName . '"' . $selected . '>' . $configurationName . '</option>';
        }
        ?>
    </select>

    <!-- Help for manual transfer -->
    <span style="font-size: 140%; margin-left: 1em;" title="help">
        <i id="manual-transfer-help-link" class="fa fa-question-circle" style="color: blue;"></i>
    </span>

    <!-- Manual transfer help div -->
    <div id="manual-transfer-help" title="Manual Transfer" style="display: none;">
        <?php echo Help::getHelpWithPageLink('manual-transfer', $module); ?>
    </div>

</div>

<?php

if (empty($configName) || $configuration === null) {
    echo "<p>No configuration selected.</p>\n";
} elseif (!$configuration->isProjectComplete()) {
    echo "<p>The data transfer project information for configuration \"{$configName}\" needs to be"
       . " completed before this page can be used.</p>\n";
} elseif (!$configuration->isEnabled()) {
    echo "<p>This configuration has not been enabled.</p>\n";
} elseif (!$configuration->getManualTransferEnabled()) {
    echo "<p>Manual data transfer has not been enabled for this configuration.</p>\n";
} else { ?>
    <p>
    Transfer data from project
    <span style="font-weight: bold;">
        <?php echo $sourceProject->getProjectIdentifier(); ?>
    </span>
    to project
    <span style="font-weight: bold;">
        <?php echo $destinationProject->getProjectIdentifier(); ?>
    </span>
    </p>

    <?php

    if (!empty($fieldMapWarning)) {
        echo '<p style="margin-left: 2em; margin-bottom: 17px;">' . "\n";
        echo '<img style="width: 2em;" src="' . APP_PATH_IMAGES . 'warning.png" alt="WARNING">';
        echo "&nbsp{$fieldMapWarning}";
        echo "</p>\n";
    }

    if (!empty($fieldMapError)) {
        echo '<p style="margin-left: 2em; margin-bottom: 17px;">' . "\n";
        echo '<img style="width: 2em;" src="' . APP_PATH_IMAGES . 'exclamation.png" alt="ERROR">';
        echo "&nbsp;{$fieldMapError}";
        echo "</p>\n";
    }

    ?>

    <form action="<?=$selfUrl;?>" method="post" style="margin-bottom: 12px;">
        <input type="submit" name="submitValue" value="Transfer"
               style="color: green; font-weight: bold; font-size: 110%;"/>
        <input type="hidden" name="redcap_csrf_token" value="<?php echo $module->getCsrfToken(); ?>"/>
    </form>


<?php } ?>

<script>
    $(document).ready(function () {
        $("#dtConfigSelect").change(function () {
            let option = $(this).find(':selected').val();
            let url = '<?php echo $selfUrl; ?>';
            url += '&' + 'configName=' + option;
            // console.log(url);
            window.location.href = url;
        });

        // Help dialog event
        $('#manual-transfer-help-link').click(function () {
            $('#manual-transfer-help')
                .dialog({dialogClass: 'data-transfer-help', width: 540, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+50 top+90', of: $(this)})
                ;
            return false;
        });

    });
</script>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>
