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

    $projectUsers = REDCap::getUsers();

    $selfUrl        = $module->getUrl('web/transfer_options.php');
    $configureUrl   = $module->getUrl('web/configure.php');

    $configName = $module->getRequestSessionVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');

    $owner = null;
    if (!empty($configName)) {
        $configuration = $module->getConfiguration($configName, PROJECT_ID);

        if (!empty($configuration)) {
            $owner = $configuration->getOwner();

            if ($configuration->isProjectComplete()) {
                $sourceProject      = $configuration->getSourceProject($module);
                $destinationProject = $configuration->getDestinationProject($module);
            }
        }
    }

    $instruments = REDCap::getInstrumentNames();

    #--------------------------------------------------------------------
    # Process form submissions - save transfer options to configuration
    #--------------------------------------------------------------------
    $submitValue = Filter::sanitizeButtonLabel($_POST['submitValue']);

    if ($submitValue === 'Save') {
        $parameters = $_POST;
        $configuration->setTransferOptions($parameters, $user, $projectUsers, $module->isSuperUser());

        $module->setConfiguration($configuration, USERID, PROJECT_ID);
        $success = 'Saved.';
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
$link = '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" media="all">';
$buffer = str_replace('</head>', "    " . $link . "\n</head>", $buffer);
echo $buffer;
?>

<div class="projhdr">
<span class="fas fa-arrow-right-arrow-left" style="padding: 2px; border: solid 2px;"></span> Data Transfer
<span style="float: right; font-size: 84%;"><?php echo "Version " . Version::RELEASE_NUMBER; ?></span>
</div>


<?php
# $module->renderProjectPageContentHeader($configureUrl, $error, $warning, $success);
# $module->renderConfigureSubTabs($selfUrl);
$module->renderProjectPageConfigureContentHeader($configureUrl, $selfUrl, $error, $warning, $success);

?>

<?php
if (empty($configName) || $configuration === null) {
    echo "<p>No configuration selected</p>\n";
} elseif (!$configuration->isProjectComplete()) {
    echo "<p>The data transfer project information needs to be completed before this page can be used.</p>\n";
} elseif (empty($error)) {
    $sourceRecordIdField      = $sourceProject->getRecordIdField();
    $sourceSecondaryUniqueId  = $sourceProject->getSecondaryUniqueField();

    $destinationRecordIdField     = $destinationProject->getRecordIdField();
    $destinationSecondaryUniqueId = $destinationProject->getSecondaryUniqueField();

    $destinationRecordAutonumberingEnabled = $destinationProject->recordAutonumberingEnabled();
    ?>

    <div style="margin-bottom: 12px;">
        <span style="border: 1px solid black; padding: 4px;">
            <b>Configuration Name:</b>
            <?php echo $configName; ?>
        </span>
        <?php
        if (!$configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
            echo '<span style="font-weight: bold; margin-left: 1em; color: #F70000;">[VIEW ONLY MODE]</span>' . "\n";
        }
        ?>
    </div>

    <?php if ($configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) { ?>
        <div style="margin-bottom: 7px; margin-top: 14px;">
            <button id="saveTransferOptionButton" name="saveTransferOptionButton" value="submitted"
                    style="margin-right: 2em;">
                <i class="fa fa-save" style="color: green; font-size: 110%;"></i>
                <span style="font-weight: bold; color: green; font-size: 110%;">Save</span>
            </button>
        </div>
    <?php } ?>


    <?php
    $inert = '';
    if (!$configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
        $inert = 'inert';
    }
    ?>

    <form id="transferOptionsForm" action="<?=$selfUrl;?>" method="post"
          style="margin-bottom: 12px;" <?php echo $inert; ?>>

        <input type="hidden" name="<?php echo Configuration::IS_ENABLED; ?>"
               value="<?php echo $configuration->isEnabled(); ?>"/>

        <div id="column-container" style="font-size: 90%;">

            <div id="column1" style="float: left;">

                <!-- =================== -->
                <!-- Transfer Activation -->
                <!-- =================== -->
                <fieldset class="config">
                    <legend>Transfer Activation</legend>

                    <div>
                        <?php
                        $checked = '';
                        if ($configuration->getManualTransferEnabled()) {
                            $checked = ' checked';
                        }
                        ?>
                        <input type="checkbox"
                               name="<?php echo Configuration::MANUAL_TRANSFER_ENABLED; ?>"
                               <?php echo $checked; ?>/>
                        <span>Manual</span>

                        <!-- Help link for transfer activation -->
                        <span style="font-size: 140%; margin-right: 1em; float: right;"
                              title="Transfer Activation Help">
                            <i id="transfer-activation-help-link" class="fa fa-question-circle"
                               style="color: blue;"></i>
                        </span>

                        <div style="clear: both;"></div>

                        <!-- Help div for transfer activation -->
                        <div id="transfer-activation-help" title="Transfer Activation" style="display: none;">
                            <?php echo Help::getHelpWithPageLink('transfer-activation', $module); ?>
                        </div>

                        <?php
                        if ($configuration->getDirection() === Configuration::DIRECTION_IMPORT) {
                            ?>
                        
                            <?php
                        } elseif ($configuration->getDirection() === Configuration::DIRECTION_EXPORT) {
                            $checked = '';
                            if ($configuration->getExportOnFormSave()) {
                                $checked = ' checked';
                            }

                            $emailChecked = '';
                            if ($configuration->getEmailFormSaveErrors()) {
                                $emailChecked = ' checked';
                            }
                            ?>
                                <input name="<?php echo Configuration::EXPORT_ON_FORM_SAVE; ?>"
                                       type="checkbox" <?php echo $checked; ?>/>
                                On form save 
                                <ul style="list-style-type: none; padding: 4px; margin-left: 2em; margin-bottom: 0">
                                    <li>
                                        <input name="<?php echo Configuration::EMAIL_FORM_SAVE_ERRORS; ?>"
                                               style="margin-left: 4px;"
                                               type="checkbox" <?php echo $emailChecked; ?>/>
                                        E-mail form save data transfer error notifications
                                    </li>
                                </ul>
                            <?php
                        }
                        ?>
                    </div>
                </fieldset>

                <!-- ===================================================================
                = Record Matches
                ==================================================================== -->
                <fieldset class="config">
                    <legend>Record Matches</legend>

                    <div style="margin-bottom: 7px;">
                    Source project value to match and use as record ID in the destination project:
                    </div>



                    <!-- Record ID option -->
                    <?php
                    $checked = '';
                    if ($configuration->getRecordMatch() === Configuration::MATCH_RECORD_ID) {
                        $checked = ' checked ';
                    }
                    ?>

                    <div style="float: left;">
                    <input type="radio" name="<?php echo Configuration::RECORD_MATCH; ?>"
                           value="<?php echo Configuration::MATCH_RECORD_ID; ?>"
                           <?php echo $checked; ?>
                           />
                        Record ID field:
                        <?php echo "<b>{$sourceRecordIdField}</b>"; ?>
                    </div>

                    <!-- Help link for record match -->
                    <span style="font-size: 140%; margin-right: 1em; float: right;" title="Record Matches Help">
                        <i id="record-matches-help-link" class="fa fa-question-circle" style="color: blue;"></i>
                    </span>

                    <div style="clear: both;"></div>

                    <!-- Help div for record match -->
                    <div id="record-matches-help" title="Record Matches" style="display: none;">
                        <?php echo Help::getHelpWithPageLink('record-matches', $module); ?>
                    </div>

                    <!-- Secondary Unique ID option -->
                    <?php
                    $checked = '';
                    $secondaryDisabled = '';
                    if (empty($sourceSecondaryUniqueId)) {
                        $secondaryDisabled = ' disabled ';
                    } elseif ($configuration->getRecordMatch() === Configuration::MATCH_SECONDARY_ID) {
                        $checked = ' checked ';
                    }

                    ?>

                    <div>
                    <input id="sourceSecondaryRadio" type="radio"  name="<?php echo Configuration::RECORD_MATCH; ?>"
                           value="<?php echo Configuration::MATCH_SECONDARY_ID; ?>"
                           <?php echo $checked; ?>
                           <?php echo $secondaryDisabled; ?>/>
                        <label for="sourceSecondaryRadio">Secondary unique field:</label>
                        <?php
                        if (empty($sourceSecondaryUniqueId)) {
                            echo '<span style="color: #FF0000; font-weight: bold;">(not avaible)</span>';
                        } else {
                            echo "<b>{$sourceSecondaryUniqueId}</b>";
                        }
                        ?>
                    </div>

                    <!-- Logic option -->
                    <?php
                    $checked = '';
                    if ($configuration->getRecordMatch() === Configuration::MATCH_LOGIC) {
                        $checked = ' checked ';
                    }
                    ?>
                    <!--
                    <div>
                    <input type="radio"  name="<?php echo Configuration::RECORD_MATCH; ?>"
                           value="<?php echo Configuration::MATCH_LOGIC; ?>"
                           <?php echo $checked; ?> />
                        Record ID calculation:
                        <input type="text" size="60"></input>
                    </div>
                    -->
                </fieldset>

                <!-- ===================================================================
                = Repeating Data
                ==================================================================== -->
                <fieldset class="config" style="padding-top: 0;">
                    <legend>Repeating Data</legend>

                    <!-- Help link for repeating data -->
                    <span style="font-size: 140%; margin-right: 1em; float: right;" title="Repeating Data Help">
                        <i id="repeating-data-help-link" class="fa fa-question-circle" style="color: blue;"></i>
                    </span>

                    <div style="clear: both;"></div>

                    <!-- Help div for repeating data -->
                    <div id="repeating-data-help" title="Repeating Data" style="display: none;">
                        <?php echo Help::getHelpWithPageLink('repeating-data', $module); ?>
                    </div>

                    <table class="dataTable" style="background-color: #ffffff; margin-top: 7px;">
                        <tr>
                            <th>Source Data</th> <th>Destination Data</th> <th>Data Transfer</th>
                        </tr>
                        <tr>
                            <td>Repeating</td> <td>Non-Repeating</td>
                            <td>
                                <?php
                                $selectedFirst = '';
                                $selectedLast = '';
                                if ($configuration->getRepeatingToNonRepeating() === Configuration::FROM_FIRST) {
                                    $selectedFirst = 'selected';
                                } elseif ($configuration->getRepeatingToNonRepeating() === Configuration::FROM_LAST) {
                                    $selectedLast = 'selected';
                                }
                                ?>
                                <select name="<?php echo Configuration::REPEATING_TO_NON_REPEATING; ?>">
                                <option value="<?php echo Configuration::FROM_FIRST; ?>" <?php echo $selectedFirst; ?>>
                                        Transfer from first source instance
                                    </option>
                                    <option value="<?php echo Configuration::FROM_LAST; ?>"
                                            <?php echo $selectedLast; ?>>
                                        Transfer from last source instance
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>Non-Repeating</td> <td>Repeating</td>
                            <td>
                                <?php
                                $selected1 = '';
                                $selectedFirst = '';
                                $selectedLast = '';
                                $selectedNew = '';
                                if ($configuration->getNonRepeatingToRepeating() === Configuration::TO_1) {
                                    $selected1 = 'selected';
                                } elseif ($configuration->getNonRepeatingToRepeating() === Configuration::TO_FIRST) {
                                    $selectedFirst = 'selected';
                                } elseif ($configuration->getNonRepeatingToRepeating() === Configuration::TO_LAST) {
                                    $selectedLast = 'selected';
                                } elseif ($configuration->getNonRepeatingToRepeating() === Configuration::TO_NEW) {
                                    $selectedNew = 'selected';
                                }
                                ?>
                                <select name="<?php echo Configuration::NON_REPEATING_TO_REPEATING; ?>">
                                    <option value="<?php echo Configuration::TO_1; ?>" <?php echo $selected1; ?>>
                                        Transfer to destination instance 1
                                    </option>
                                    <option value="<?php echo Configuration::TO_FIRST; ?>"
                                            <?php echo $selectedFirst; ?>>
                                        Transfer to first destination instance
                                    </option>
                                    <option value="<?php echo Configuration::TO_LAST; ?>" <?php echo $selectedLast; ?>>
                                        Transfer to last destination instance
                                    </option>
                                    <option value="<?php echo Configuration::TO_NEW; ?>" <?php echo $selectedNew; ?>>
                                        Transfer to new destination instance
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>Repeating</td> <td>Repeating</td> <td>Transfer to matching instance</td>
                        </tr>
                    </table>
                </fieldset>
            </div> <!-- end of column 1 -->


            <!-- ===================================================================
            = COLUMN 2
            ==================================================================== -->
            <div id="column2" style="float: left;">

                <!-- ===================================================================
                = Record Export Filter
                ==================================================================== -->
                <fieldset class="config">
                    <legend>Record Export Filter</legend>

                    <div style="margin-bottom: 4px;">
                    Only export records from the source project that meet following condition:
                    </div>

                    <input type="text" name="<?php echo Configuration::SOURCE_FILTER_LOGIC; ?>"
                           value="<?php echo $configuration->getSourceFilterLogic(); ?>" size="40"/>

                    <!-- Help link for record export filter -->
                    <span style="font-size: 140%; margin-right: 1em; float: right;" title="Record Export Filter Help">
                        <i id="record-export-filter-help-link" class="fa fa-question-circle" style="color: blue;"></i>
                    </span>

                    <div style="clear: both;"></div>

                    <!-- Help div for record export filter -->
                    <div id="record-export-filter-help" title="Record Export Filter" style="display: none;">
                        <?php echo Help::getHelpWithPageLink('record-export-filter', $module); ?>
                    </div>
                </fieldset>

                <!-- ===================================================================
                = Record Changes
                ==================================================================== -->
                <fieldset class="config">
                    <legend>Record Changes</legend>

                    <div style="margin-top: 0; padding-top: 0;">
                        <span style="font-weight: bold;">Record Updates:</span>

                        <!-- Help link for record updates -->
                        <span style="font-size: 140%; margin-right: 1em; float: right;" title="Record Changes Help">
                            <i id="record-changes-help-link" class="fa fa-question-circle" style="color: blue;"></i>
                        </span>

                        <div style="clear: both;"></div>

                        <!-- Help div for record updates -->
                        <div id="record-changes-help" title="Record Changes" style="display: none;">
                            <?php echo Help::getHelpWithPageLink('record-changes', $module); ?>
                        </div>


                        <?php
                        $updateChecked = '';
                        $noUpdateChecked = '';
                        if ($configuration->getUpdateRecords()) {
                            $updateChecked = ' checked ';
                        } else {
                            $noUpdateChecked = ' checked ';
                        }
                        ?>
                        <ul style="list-style-type: none; padding: 4px; margin: 0;">
                            <li>
                                <input name="<?php echo Configuration::UPDATE_RECORDS; ?>" type="radio"
                                       value="true" <?php echo $updateChecked; ?>/>
                                Update existing records
                                <ul style="list-style-type: none; padding: 4px; margin-left: 2em;">
                                    <li>
                                        <?php
                                        $checked = '';
                                        if ($configuration->getOverwriteWithBlanks()) {
                                            $checked = ' checked ';
                                        }
                                        ?>
                                        <input type="checkbox"
                                               name="<?php echo Configuration::OVERWRITE_WITH_BLANKS; ?>"
                                               <?php echo $checked; ?>/>
                                        Overwrite existing values with blank values
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <input name="<?php echo Configuration::UPDATE_RECORDS; ?>" type="radio"
                                       value="false" <?php echo $noUpdateChecked?>/>
                                Do not update existing records
                            </li>
                        </ul>
                    </div>

                    <hr style="margin-top: 7px; margin-bottom: 7px;"/>

                    <div style="margin-top: 0; padding-top: 0;">
                        <b>Record Creation:</b>
                        <?php
                        $recordsAndInstancesChecked = '';
                        $instanceChecked = '';
                        $noneChecked = '';
                        if ($configuration->getRecordCreation() === Configuration::ADD_RECORDS_AND_INSTANCES) {
                            $recordsAndInstancesChecked = ' checked ';
                        } elseif ($configuration->getRecordCreation() === Configuration::ADD_INSTANCES) {
                            $instancesChecked = ' checked ';
                        } elseif ($configuration->getRecordCreation() === Configuration::ADD_NONE) {
                            $noneChecked = ' checked ';
                        }
                        ?>
                        <ul style="list-style-type: none; padding: 4px; margin: 0;">
                            <li>
                            <input type="radio" name="<?php echo Configuration::RECORD_CREATION; ?>"
                                   value="<?php echo Configuration::ADD_RECORDS_AND_INSTANCES; ?>"
                                   <?php echo $recordsAndInstancesChecked; ?>
                            />
                                Add new records and record instances (for repeating events and/or instruments)
                                to the destination project
                            </li>
                            <li>
                                <input type="radio" name="<?php echo Configuration::RECORD_CREATION; ?>"
                                       value="<?php echo Configuration::ADD_INSTANCES; ?>"
                                       <?php echo $instancesChecked; ?>
                                />
                                Add new record instances to the destination project, but not new records
                            </li>
                            <li>
                                <input type="radio" name="<?php echo Configuration::RECORD_CREATION; ?>"
                                       value="<?php echo Configuration::ADD_NONE; ?>"
                                       <?php echo $noneChecked; ?>
                                />
                                Do not add new records or record instances to the destination project
                            </li>
                        </ul>
                    </div>

                    <hr style="margin-top: 7px; margin-bottom: 7px;"/>

                    <div style="margin-top: 0; padding-top: 0;">
                        <b>File Fields:</b> <br/>
                        <?php
                        $checked = '';
                        if ($configuration->getTransferFiles()) {
                            $checked = ' checked ';
                        }
                        ?>
                        <input type="checkbox" name="<?php echo Configuration::TRANSFER_FILES; ?>"
                               <?php echo $checked; ?>/>
                        Transfer file fields
                    </div>

                    <!--
                    <hr/>
                    -->
        
                    <!-- ===================== -->
                    <!-- Add Autonumber option -->
                    <!-- ===================== -->
                    <!--
                    <hr/>
                    <div>
                    <b>Always Add as Auto-Numbered Record.</b> Always add a new
                    record with an auto-numbered record ID
                    for each record transfered to the destination project, even
                    if the record ID of the transfered record
                    already exists in the project.
                    </div>
                    -->
                </fieldset>

                <!-- ================== -->
                <!-- Processing options -->
                <!-- ================== -->
                <fieldset class="config">
                    <legend>Processing</legend>

                    <div>
                        Record batch size: 
                        <input type="text" name="<?php echo Configuration::BATCH_SIZE; ?>"
                               style="text-align: right;"
                               value="<?php echo $configuration->getBatchSize(); ?>" size="5"></input>

                        <!-- Help link for processing options -->
                        <span style="font-size: 140%; margin-right: 1em; float: right;" title="Processing Options Help">
                            <i id="processing-options-help-link" class="fa fa-question-circle" style="color: blue;"></i>
                        </span>

                        <div style="clear: both;"></div>

                        <!-- Help div for processing options -->
                        <div id="processing-options-help" title="Procssing Options" style="display: none;">
                            <?php echo Help::getHelpWithPageLink('processing-options', $module); ?>
                        </div>

                    </div>
                </fieldset>

            </div> <!-- end of column 2 -->

        </div>

        <div style="clear: both;"></div>





    <!--
    <fieldset class="config">
        <legend>Events</legend>
        <div style="margin-bottom: 12px;">
            <input type="radio" name="eventMatchType"> Exact match on Arm/Event names
        </div>

        <div>
            <input type="radio" name="eventMatchType"> Match based on selections below:
        </div>
    </fieldset>
    -->

    <!--
    <fieldset class="config">
        <legend>Fields</legend>

        <p>
        Only transfer fields meeting the following conditions:
        </p>

        <div style="margin-bottom: 12px;">
            <input type="radio" name="fieldMatchType"/> Exact match Field Name and Field Type
        </div>
        <div style="margin-bottom: 12px;">
            <input type="radio" name="fieldMatchType"/> Exact match Field Name and compatible Field Type
        </div>
    </fieldset>
    -->

    <input type="hidden" name="submitValue" value="Save" />
    <input type="hidden" name="redcap_csrf_token" value="<?php echo $module->getCsrfToken(); ?>"/>
</form>

<script>
    // Help dialog events
    $(document).ready(function() {

        $("#saveTransferOptionButton").click(function() {
            $("#transferOptionsForm").submit();
            return false;
        });

        $('#record-changes-help-link').click(function () {
            $('#record-changes-help')
                .dialog({dialogClass: 'record-changes-help', width: 640, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+40 top-277', of: $(this)})
                ;
            return false;
        });

        $('#record-export-filter-help-link').click(function () {
            $('#record-export-filter-help')
                .dialog({dialogClass: 'record-export-filter-help', width: 570, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right-10 top-267', of: $(this)})
                ;
            return false;
        });

        $('#record-matches-help-link').click(function () {
            $('#record-matches-help')
                .dialog({dialogClass: 'record-matches-help', width: 540, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+40 top-57', of: $(this)})
                ;
            return false;
        });

        $('#repeating-data-help-link').click(function () {
            $('#repeating-data-help')
                .dialog({dialogClass: 'data-transfer-help', width: 540, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+40 top-24', of: $(this)})
                ;
            return false;
        });

        $('#processing-options-help-link').click(function () {
            $('#processing-options-help')
                .dialog({dialogClass: 'data-transfer-help', width: 540, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+64 top-240', of: $(this)})
                ;
            return false;
        });

        $('#transfer-activation-help-link').click(function () {
            $('#transfer-activation-help')
                .dialog({dialogClass: 'data-transfer-help', width: 540, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+64 top-240', of: $(this)})
                ;
            return false;
        });
    });
</script>


    <?php
} // else (for case where there is a configuration and the data transfer project information has been completed)
?>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>
