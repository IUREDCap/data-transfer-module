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
use IU\DataTransfer\Project;
use IU\DataTransfer\Variable;
use IU\DataTransfer\Version;

$error   = '';
$warning = '';
$success = '';

try {
    #-----------------------------------------------------------
    # Check that the user has permission to access this page
    #-----------------------------------------------------------
    $module->checkUserPagePermission(USERID);

    $selfUrl        = $module->getUrl('web/diff.php');
    $configureUrl   = $module->getUrl('web/configure.php');

    $configName = $module->getRequestSessionVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');

    if (!empty($configName)) {
        $configuration = $module->getConfiguration($configName, PROJECT_ID);

        $sourceProject      = $configuration->getSourceProject($module);
        $destinationProject = $configuration->getDestinationProject($module);

        $sourceEvents      = $sourceProject->getEvents();
        $destinationEvents = $destinationProject->getEvents();

        $sourceArmNames      = $sourceProject->getDefinedArmNames();
        $destinationArmNames = $destinationProject->getDefinedArmNames();

        $sourceMetadata   = $sourceProject->getSortedMetadata();
        $destinationMetadata = $destinationProject->getSortedMetadata();
    }

    $instruments = \REDCap::getInstrumentNames();
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
$module->renderProjectPageConfigureContentHeader($configureUrl, $selfUrl, $error, $warning, $success);
?>

<?php
if (empty($configName) || $configuration === null) {
    echo "<p>No configuration selected</p>\n";
} elseif (!$configuration->isProjectComplete()) {
    echo "<p>The data transfer project information needs to be completed before this page can be used.</p>\n";
} else {
    $recordIdField = \REDCap::getRecordIdField();
    $dataDictionary = \REDCap::getDataDictionary(PROJECT_ID, 'array');
    $fieldNames = array_column($dataDictionary, 'field_name');
    ?>

<div style="margin-bottom: 22px;">
    <span style="border: 1px solid black; padding: 4px;">
        <b>Configuration Name:</b>
        <?php echo $configName; ?>
    </span>
</div>

<table class="dataTable" style="margin-bottom: 17px;">
    <thead>
        <tr>
            <th style="background-color: transparent; border-left: 0; border-top: 0;"> &nbsp; </th>
            <th> Source Project </th>
            <th> Desitnation Project </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th> Project Name </th>
            <td> <?php echo $sourceProject->getTitle(); ?>  </td>
            <td> <?php echo $destinationProject->getTitle(); ?>  </td>
        </tr>
        <tr>
            <th> Project ID </th>
            <td style="text-align: right;">
                <?php echo $sourceProject->getPid(); ?>
            </td>
            <td style="text-align: right;"> <?php echo $destinationProject->getPid(); ?>  </td>
        </tr>
        <tr>
            <?php
            if ($sourceProject->isLongitudinal() === $destinationProject->isLongitudinal()) {
                $cssClass = "data-match";
            } else {
                $cssClass = "data-mismatch";
            }
            ?>
            <th> Longitudinal </th>
            <td class="<?php echo $cssClass; ?>">
                 <?php echo $sourceProject->isLongitudinal() ? 'Yes' : 'No'; ?>
            </td>
            <td class="<?php echo $cssClass; ?>">
                <?php echo $destinationProject->isLongitudinal() ? 'Yes' : 'No'; ?>
            </td>
        </tr>
        <tr>
            <?php
            if ($sourceProject->surveysEnabled() === $destinationProject->surveysEnabled()) {
                $cssClass = "data-match";
            } else {
                $cssClass = "data-mismatch";
            }
            ?>
            <th> Surveys Enabled </th>
            <td class="<?php echo $cssClass; ?>">
                <?php echo $sourceProject->surveysEnabled() ? 'Yes' : 'No'; ?>
            </td>
            <td class="<?php echo $cssClass; ?>">
                <?php echo $destinationProject->surveysEnabled() ? 'Yes' : 'No'; ?>
            </td>
        </tr>
        <tr>
            <?php
            if (
                $sourceProject->hasRepeatingInstrumentsOrEvents()
                == $destinationProject->hasRepeatingInstrumentsOrEvents()
            ) {
                $cssClass = "data-match";
            } else {
                $cssClass = "data-mismatch";
            }
            ?>
            <th> Repeating Instruments or Events </th>
            <td class="<?php echo $cssClass; ?>">
                <?php echo $sourceProject->hasRepeatingInstrumentsOrEvents() ? 'Yes' : 'No'; ?>
            </td>
            <td class="<?php echo $cssClass; ?>">
                <?php echo $destinationProject->hasRepeatingInstrumentsOrEvents() ? 'Yes' : 'No'; ?>
            </td>
        </tr>
        <tr>
            <?php
            if ($sourceProject->recordAutoNumberingEnabled() === $destinationProject->recordAutoNumberingEnabled()) {
                $cssClass = "data-match";
            } else {
                $cssClass = "data-mismatch";
            }
            ?>
            <th> Record Autonumbering Enabled </th>
            <td class="<?php echo $cssClass; ?>">
                <?php echo $sourceProject->recordAutonumberingEnabled() ? 'Yes' : 'No'; ?>
            </td>
            <td class="<?php echo $cssClass; ?>">
                <?php echo $destinationProject->recordAutonumberingEnabled() ? 'Yes' : 'No'; ?>
            </td>
        </tr>

        <tr>
            <?php
            $mdcCssClass = Variable::compareMissingDataCodeLists(
                $sourceProject->getMissingDataCodes(),
                $destinationProject->getMissingDataCodes()
            );

            ?>
            <th>Missing Data Codes</th>
            <td class="<?php echo $mdcCssClass; ?>"> 
                <ul class="missing-data-codes">
                    <?php
                    foreach ($sourceProject->getMissingDataCodes() as $value => $label) {
                        echo "<li><b>{$value}:</b> {$label}</li>\n";
                    }
                    ?>
                </ul>
            </td>

            <td class="<?php echo $mdcCssClass; ?>">
                <ul class="missing-data-codes">
                    <?php
                    foreach ($destinationProject->getMissingDataCodes() as $value => $label) {
                        echo "<li><b>{$value}:</b> {$label}</li>\n";
                    }
                    ?>
                </ul>
            </td>
        </tr>
    </tbody>
</table>

<p>&nbsp;</p>

    <?php
    #---------------------------------------------------------------------------------------------------
    # Arms
    #---------------------------------------------------------------------------------------------------
    if ($sourceProject->isLongitudinal() || $destinationProject->isLongitudinal()) {
        ?>
<h5>Arms</h5>

<table class="dataTable">
    <thead>
        <tr>
            <th style="text-align: center;">Source Project</th>
            <th style="text-align: center;">Destination Project</th>
        </tr>
        <tr>
            <th>
                <?php echo $sourceProject->getTitle(); ?> [<?php echo $sourceProject->getPid(); ?>]
            </th>
            <th>
                <?php echo $destinationProject->getTitle(); ?> [<?php echo $destinationProject->getPid(); ?>]
            </th>
        </tr>
        <!--
        <tr>
            <th>Arm Name</th> <th>Arm Name</th>
        </tr>
        -->
    </thead>
    <tbody>
        <?php
        $sourceIndex = 0;
        $destIndex = 0;
        while ($sourceIndex < count($sourceArmNames) || $destIndex < count($destinationArmNames)) {
            echo "<tr>";
            if ($sourceIndex >= count($sourceArmNames)) {
                echo "<td>&nbsp;</td>";
                echo "<td class=\"data-mismatch\">{$destinationArmNames[$destIndex]}</td>";
                $destIndex++;
            } elseif ($destIndex >= count($destinationArmNames)) {
                echo "<td class=\"data-mismatch\">{$sourceArmNames[$sourceIndex]}</td>";
                echo "<td>&nbsp;</td>";
                $sourceIndex++;
            } else {
                $cmp = strcmp($sourceArmNames[$sourceIndex], $destinationArmNames[$destIndex]);
                if ($cmp < 0) {
                    echo "<td class=\"data-mismatch\">{$sourceArmNames[$sourceIndex]}</td>";
                    echo "<td>&nbsp;</td>";
                    $sourceIndex++;
                } elseif ($cmp == 0) {
                    echo "<td class=\"data-match\">{$sourceArmNames[$sourceIndex]}</td>";
                    echo "<td class=\"data-match\">{$destinationArmNames[$destIndex]}</td>";
                    $destIndex++;
                    $sourceIndex++;
                } elseif ($cmp > 0) {
                    echo "<td>&nbsp;</td>";
                    echo "<td class=\"data-mismatch\">{$destinationArmNames[$destIndex]}</td>";
                    $destIndex++;
                }
            }
            echo "</tr>\n";
        }
        ?>
    </tbody>
</table>

<p>&nbsp;</p>

        <?php
    }    // End Arms
    ?>


    <?php
    #---------------------------------------------------------------------------------------------------
    # Events
    #---------------------------------------------------------------------------------------------------
    if ($sourceProject->isLongitudinal() || $destinationProject->isLongitudinal()) {
        ?>
<h5>Events</h5>


<table class="dataTable">
    <thead>
        <tr>
            <th colspan="3">
                <?php echo $sourceProject->getTitle(); ?> [<?php echo $sourceProject->getPid(); ?>]
            </th>
            <th colspan="3" style="border-left: 2px solid #000;">
                <?php echo $destinationProject->getTitle(); ?> [<?php echo $destinationProject->getPid(); ?>]
            </th>
        </tr>
        <tr>
            <th>Event Unique Name</th>
            <th>Repeating?</th>
            <th>Repeating Forms?</th>

            <th style="border-left: 2px solid #000;">Event Unique Name</th>
            <th>Repeating?</th>
            <th>Repeating Forms?</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $uniqueEventNames = array_merge(
            $sourceProject->getDefinedUniqueEventNames(),
            $destinationProject->getDefinedUniqueEventNames()
        );
        sort($uniqueEventNames);
        $uniqueEventNames = array_unique($uniqueEventNames);

        foreach ($uniqueEventNames as $uniqueEventName) {
            #------------------------------------------
            # Set comparison display classes
            #------------------------------------------
            if (
                !$sourceProject->hasUniqueEventName($uniqueEventName)
                || !$destinationProject->hasUniqueEventName($uniqueEventName)
            ) {
                $nameClass           = Variable::TYPES_NOT_EQUAL;
                $repeatingClass      = Variable::TYPES_NOT_EQUAL;
                $repeatingFormsClass = Variable::TYPES_NOT_EQUAL;
            } else {
                $nameClass      = Variable::TYPES_EQUAL;

                if (
                    $sourceProject->isEventRepeating($uniqueEventName)
                    === $destinationProject->isEventRepeating($uniqueEventName)
                ) {
                    $repeatingClass = Variable::TYPES_EQUAL;
                } else {
                    $repeatingClass = Variable::TYPES_NOT_EQUAL;
                }

                if (
                    $sourceProject->areEventFormsRepeating($uniqueEventName)
                    === $destinationProject->areEventFormsRepeating($uniqueEventName)
                ) {
                    $repeatingFormsClass = Variable::TYPES_EQUAL;
                } else {
                    $repeatingFormsClass = Variable::TYPES_NOT_EQUAL;
                }
            }

            echo "<tr>";

            if ($sourceProject->hasUniqueEventName($uniqueEventName)) {
                echo "<td class=\"{$nameClass}\">{$uniqueEventName}</td>";

                if ($sourceProject->isEventRepeating($uniqueEventName)) {
                    echo "<td class=\"{$repeatingClass}\">Yes</td>";
                } else {
                    echo "<td class=\"{$repeatingClass}\">No</td>";
                }

                if ($sourceProject->areEventFormsRepeating($uniqueEventName)) {
                    echo "<td class=\"{$repeatingFormsClass}\">Yes</td>";
                } else {
                    echo "<td class=\"{$repeatingFormsClass}\">No</td>";
                }
            } else {
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
            }

            if ($destinationProject->hasUniqueEventName($uniqueEventName)) {
                echo "<td class=\"{$nameClass}\" style=\"border-left: 2px solid #000;\">{$uniqueEventName}</td>";

                if ($destinationProject->isEventRepeating($uniqueEventName)) {
                    echo "<td class=\"{$repeatingClass}\">Yes</td>";
                } else {
                    echo "<td class=\"{$repeatingClass}\">No</td>";
                }

                if ($destinationProject->areEventFormsRepeating($uniqueEventName)) {
                    echo "<td class=\"{$repeatingFormsClass}\">Yes</td>";
                } else {
                    echo "<td class=\"{$repeatingFormsClass}\">No</td>";
                }
            } else {
                echo "<td style=\"border-left: 2px solid #000;\">&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
            }

            echo "</tr>";
        }
        ?>
    </tbody>
</table>

<p>&nbsp;</p>
        <?php
    }    // End Events
    ?>


<!-- ===========================================================================================================
= FIELDS
=========================================================================================================== --!>
<h5>Fields</h5>

<table class="dataTable" style="font-size: 88%;">
    <thead>
        <tr>

        <th colspan="7" style="text-align: center;">
            <?php echo $sourceProject->getTitle(); ?> [<?php echo $sourceProject->getPid(); ?>]
        </th>

        <th colspan="7" style="text-align: center; border-left: 2px solid #000000;">
            <?php echo $destinationProject->getTitle(); ?> [<?php echo $destinationProject->getPid(); ?>]
        </th>

        </tr>

        <tr>
        <th>Field Name</th> <th>Field Type</th> <th>Validation Type</th>
        <th>Min</th> <th>Max</th>
        <th>Select Options</th>
        <th>Required</th>

        <th style="border-left: 2px solid #000000;">Field Name</th> <th>Field Type</th> <th>Vaidation Type</th>
        <th>Min</th> <th>Max</th>
        <th>Select Options</th>
        <th>Required</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sourceVariables      = Variable::createVariableMap(
            $sourceMetadata,
            $sourceProject->getMissingDataCodes(),
            $sourceProject->getActionTagsMap()
        );

        $destinationVariables = Variable::createVariableMap(
            $destinationMetadata,
            $destinationProject->getMissingDataCodes(),
            $destinationProject->getActionTagsMap()
        );

        $merge = array_merge($sourceVariables, $destinationVariables);
        $variables = array_keys($merge);
        sort($variables);

        foreach ($variables as $variable) {
            $sourceVar = $sourceVariables[$variable];
            $destVar   = $destinationVariables[$variable];

            echo "<tr>\n";

            #------------------------------------------
            # Set comparison display classes
            #------------------------------------------
            if (empty($sourceVar) || empty($destVar)) {
                $nameClass           = Variable::TYPES_NOT_EQUAL;
                $fieldTypeClass      = Variable::TYPES_NOT_EQUAL;
                $validationTypeClass = Variable::TYPES_NOT_EQUAL;
                $minClass            = Variable::TYPES_NOT_EQUAL;
                $maxClass            = Variable::TYPES_NOT_EQUAL;
                $selectOptionsClass  = Variable::TYPES_NOT_EQUAL;
                $requiredClass       = Variable::TYPES_NOT_EQUAL;
            } else {
                $nameClass           = Variable::TYPES_EQUAL;
                $fieldTypeClass      = $sourceVar->compareFieldType($destVar);
                $validationTypeClass = $sourceVar->compareValidationType($destVar);
                $minClass            = $sourceVar->compareMin($destVar);
                $maxClass            = $sourceVar->compareMax($destVar);
                $selectOptionsClass  = $sourceVar->compareSelectOptions($destVar);
                $requiredClass       = $sourceVar->compareRequired($destVar);
            }

            #------------------------------------------------
            # Display the source variable information
            #------------------------------------------------
            if (empty($sourceVar)) {
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
            } else {
                echo "<td class=\"{$nameClass}\">{$sourceVar->getName()}</td>";
                echo "<td class=\"{$fieldTypeClass}\">{$sourceVar->getFieldType()}</td>";
                echo "<td class=\"{$validationTypeClass}\">{$sourceVar->getValidationType()}</td>";
                echo "<td class=\"{$minClass}\">{$sourceVar->getMin()}</td>";
                echo "<td class=\"{$maxClass}\">{$sourceVar->getMax()}</td>";

                echo "<td class=\"{$selectOptionsClass}\" style=\"max-width: 14em;\">";
                echo "<ul class=\"select-options\">";
                foreach ($sourceVar->getSelectOptions() as $value => $label) {
                    echo "<li><b>{$value}:</b> {$label}</li>";
                }
                echo "</ul>";

                echo "<td class=\"{$requiredClass}\">" . ($sourceVar->isRequired() ? 'Yes' : 'No') . "</td>";

                echo "</td>";
            }

            #------------------------------------------------
            # Display the destination variable information
            #------------------------------------------------
            if (empty($destVar)) {
                echo "<td style=\"border-left: 2px solid #000000;\">&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
                echo "<td>&nbsp;</td>";
            } else {
                echo "<td class=\"{$nameClass}\" style=\"border-left: 2px solid #000000;\">{$destVar->getName()}</td>";
                echo "<td class=\"{$fieldTypeClass}\">{$destVar->getFieldType()}</td>";
                echo "<td class=\"{$validationTypeClass}\">{$destVar->getValidationType()}</td>";
                echo "<td class=\"{$minClass}\">{$destVar->getMin()}</td>";
                echo "<td class=\"{$maxClass}\">{$destVar->getMax()}</td>";

                echo "<td class=\"{$selectOptionsClass}\" style=\"max-width: 14em;\">";
                echo "<ul class=\"select-options\">";
                foreach ($destVar->getSelectOptions() as $value => $label) {
                    echo "<li><b>{$value}:</b> {$label}</li>";
                }
                echo "</ul>";

                echo "<td class=\"{$requiredClass}\">" . ($destVar->isRequired() ? 'Yes' : 'No') . "</td>";

                echo "</td>";
            }

            echo "</tr>\n";
        }

        ?>
    </tbody>
</table>

    <p>&nbsp;</p>

    <?php
} // else (for case where there is a configuration and the data transfer project information has been completed)
?>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>
