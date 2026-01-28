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
use IU\DataTransfer\Filter;
use IU\DataTransfer\Help;
use IU\DataTransfer\Version;

$error   = '';
$warning = '';
$success = '';

try {
    #-----------------------------------------------------------
    # Check that the user has permission to access this page
    #-----------------------------------------------------------
    $module->checkUserPagePermission(USERID);

    $user = USERID;

    $projectUsers = \REDCap::getUsers();

    $projectId = $module->getProjectId();

    $selfUrl      = $module->getUrl('web/field_map.php');
    $configureUrl = $module->getUrl('web/configure.php');

    $fieldMappingStatusUrl = $module->getUrl('web/field_mapping_status.php');
    $fieldMappingDetailUrl = $module->getUrl('web/field_mapping_detail.php');

    $configName = $module->getRequestSessionVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');

    $fieldMap = '';

    if (!empty($configName)) {
        $configuration = $module->getConfiguration($configName);

        if (!empty($configuration) && $configuration->isProjectComplete()) {
            #--------------------------------------------------------------------
            # Process form submissions - save transfer options to configuration
            #--------------------------------------------------------------------
            $submitValue = Filter::sanitizeButtonLabel($_POST['submitValue']);
            $buttonValue = Filter::sanitizeButtonLabel($_POST['buttonValue']);

            // if ($submitValue === 'Save') {

            if ($buttonValue === 'saveFieldMap') {
                $parameters = $_POST;
                # error_log(print_r($_POST, true), 3, __DIR__ . '/../field-map.log');
                $configuration->setFieldMapFromProperties($parameters, $user, $projectUsers, $module->isSuperUser());

                $module->setConfiguration($configuration, USERID, PROJECT_ID);
                $success = 'Saved.';
            }

            #--------------------------------------------------------------------
            # Get the information (after the configuration is possibly updated)
            #--------------------------------------------------------------------
            $sourceProject      = $configuration->getSourceProject($module);
            $destinationProject = $configuration->getDestinationProject($module);

            $fieldMap = $configuration->getFieldMap();
        }
    }
} catch (Exception $exception) {
    $error = 'ERROR: ' . $exception->getMessage();
}


#--------------------------------------------
# Include REDCap's project page header
#--------------------------------------------
ob_start();
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$buffer = ob_get_clean();
$cssFile = $module->getUrl('resources/data-transfer.css');
$link = '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" media="all"/>';
$jsInclude = '<script type="text/javascript" src="' . ($module->getUrl('resources/fieldMapper.js')) . '"></script>';

$buffer = str_replace('</head>', "    " . $link . "\n" . $jsInclude . "\n</head>", $buffer);
echo $buffer;
?>

<div class="projhdr">
<span class="fas fa-arrow-right-arrow-left" style="padding: 2px; border: solid 2px;"></span> Data Transfer
<span style="float: right; font-size: 84%;"><?php echo "Version " . Version::RELEASE_NUMBER; ?></span>
</div>


<?php
$module->renderProjectPageConfigureContentHeader($configureUrl, $selfUrl, $error, $warning, $success);
# $module->renderProjectPageContentHeader($configureUrl, $error, $warning, $success);
# $module->renderConfigureSubTabs($selfUrl);
?>

<?php
if (empty($configName) || $configuration === null) {
    echo "<p>No configuration selected</p>\n";
} elseif (!$configuration->isProjectComplete()) {
    echo "<p>The data transfer project information needs to be completed before this page can be used.</p>\n";
} else {
    ?>
    <div style="margin-bottom: 22px;">
        <span style="border: 1px solid black; padding: 4px;">
            <b>Configuration Name:</b>
            <?php echo $configName; ?>
        </span>

        <?php
        if (!$configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
            echo '<span style="font-weight: bold; margin-left: 1em; color: #F70000;">[VIEW ONLY MODE]</span>' . "\n";
        }
        ?>

        <!-- Help for field map -->
        <span style="font-size: 140%; margin-left: 1em;" title="help">
            <i id="field-map-help-link" class="fa fa-question-circle" style="color: blue;"></i>
        </span>

        <!-- Field map help div -->
        <div id="field-map-help" title="Field Map" style="display: none;">
            <?php echo Help::getHelpWithPageLink('field-map', $module); ?>
        </div>

    </div>
 
    <h5>Field Map</h5>

    <?php
    $inert = '';
    if (!$configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
        $inert = 'inert';
    }
    ?>

    <form action="<?=$selfUrl;?>" method="post" style="margin-bottom: 12px;" <?php echo $inert; ?>>

        <div style="margin-bottom: 14px;">
            <?php if ($configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) { ?>
            <button id="saveFieldMapButton" name="saveFieldMapButton" value="submitted" style="margin-right: 2em;">
                <i class="fa fa-save" style="color: green; font-size: 110%;"></i>
                <span style="font-weight: bold; color: green; font-size: 110%;">Save</span>
            </button>
            <?php } ?>

            <button id="fieldMappingStatusButton">
                <i class="fa fa-rectangle-list" style="color: green; font-size: 110%;"></i>
                <span style="font-weight: bold; color: #3E72A8; font-size: 110%;">
                Field Mapping Detail
                </span>
            </button>

        </div>


        <input type="hidden" id="buttonValue" name="buttonValue" value="saveFieldMap"></input>

        <!-- Checboxes from other configuration tabs that need to be included -->
        <input type="hidden" name="<?php echo Configuration::IS_ENABLED; ?>"
               value="<?php echo $configuration->isEnabled(); ?>"/>

        <input type="hidden" name="<?php echo Configuration::TRANSFER_FILES; ?>"
               value="<?php echo $configuration->getTransferFiles(); ?>"/>

        <input type="hidden" name="<?php echo Configuration::OVERWRITE_WITH_BLANKS; ?>"
               value="<?php echo $configuration->getOverwriteWithBlanks(); ?>"/>


        <div id="fieldMapper"
             style="border: 1px solid black; border-radius: 10px; padding: 10px; font-size: 90%; margin-right: 14px;">
        </div>

        <!-- JSON representation of field map ??? -->
        <input type="hidden" id="fieldMap" name="fieldMap"></input>

        <!-- place to store field map value for JavaScript to use -->
        <div id="fieldMapDiv" hidden>
            <pre><?php echo Filter::escapeForHtml($fieldMap); ?></pre>
        </div>

    </form>


    <form method="post" id="fieldMappingDetailForm" action="<?=$fieldMappingDetailUrl;?>" target="_blank">
        <input type="hidden" id="fieldMapDetailJson" name="fieldMapJson"/>
        <input type="hidden" name="configName" value="<?php echo $configName; ?>"/>
    </form>


    <!-- Hidden div for storing the source project info JSON for use by JavaScript-->
    <div id="sourceProjectJsonDiv" hidden>
        <pre><?php echo Filter::escapeForHtml($sourceProject->getInfoJson()); ?></pre>
    </div>

    <!-- Hidden div for storing the destination project info JSON for use by JavaScript-->
    <div id="destinationProjectJsonDiv" hidden>
        <pre><?php echo Filter::escapeForHtml($destinationProject->getInfoJson()); ?></pre>
    </div>

    <script>
    $(document).ready(function() {

        // alert( "FIELD MAP DIV TEXT: " + $("#fieldMapDiv").text() );

        DataTransferModule.createFieldMapper(
            <?php echo $projectId; ?>,
            "<?php echo $configName; ?>",
            "<?php echo $fieldMappingStatusUrl; ?>",
            "<?php echo $module->getCSRFToken(); ?>",
            $("#fieldMapper"),
            $("#sourceProjectJsonDiv").text(),
            $("#destinationProjectJsonDiv").text(),
            $("#fieldMapDiv").text()
        );

        $("#saveFieldMapButton").click(function() {
            var fieldMap = DataTransferModule.toFormattedJson();
            // alert("save field map: " + fieldMap);
            $("#fieldMap").val(fieldMap);
        });


        $("#fieldMappingStatusButton").click(function() {
            var fieldMap = DataTransferModule.toJson();
            // alert("status field map: " + fieldMap);
            $("#fieldMapDetailJson").val(fieldMap);
            $("#fieldMappingDetailForm").submit();
            return false;
        });

        /* */
        var options = ["one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten", "eleven"];
        $("#test-select").autocomplete({
            source: options,
            minLength: 0,
            select: function( event, ui ) {
                // Unfocus after selection
                $(this).blur();
            },
            focus: function(event, ui) {
                // Prevent the default behavior of selecting the focused item
                return false;
            }
        }).focus(function() {
            $(this).autocomplete("search", ""); 
        });
        /* */

        $('#field-map-help-link').click(function () {
            $('#field-map-help')
                .dialog({dialogClass: 'data-transfer-help', width: 640, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+50 top-90', of: $(this)})
                ;
            return false;
        });

    });
    </script>

    <p>&nbsp;</p>


    <!-- -->
    <!-- <input id="test-select"/> -->
    <!-- -->

    <!--
    <select id="test-select">
        <option value="1">one</option>
        <option value="2">two</option>
        <option value="3">three</option>
        <option value="4">four</option>
    </select>
    -->

    <!--
    <p>&nbsp;</p>

    <div style="height: 17px; width: 200px; border: 1px solid black;" class="progress-bar-25 progress-bar-green">
        <div style="height: 12px;"></div>
    </div>

    <p>&nbsp;</p>
    -->


    <?php
}   // End of else
?>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>
