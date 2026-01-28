<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

#------------------------------------------------------------
# Page for displaying the status of a field mapping
#------------------------------------------------------------

/** @var \IU\DataTransferModule\AutoNotifyModule $module */

require_once __DIR__ . '/../vendor/autoload.php';

use IU\DataTransfer\DataTransfer;
use IU\DataTransfer\Authorization;
use IU\DataTransfer\Configuration;
use IU\DataTransfer\FieldMap;
use IU\DataTransfer\Filter;

$error   = '';
$warning = '';
$success = '';

#---------------------------------------------
# Check that the user has access permission
#---------------------------------------------
try {
    $module->checkUserPagePermission(USERID);

    $selfUrl   = $module->getUrl('web/field_mapping_status.php');

    $fieldMapJson = $module->getRequestVar('fieldMapJson', '\IU\DataTransfer\Filter::sanitizeJson');
    $fieldMap = new FieldMap();
    $fieldMap->setFromJson($fieldMapJson);

    $configName   = $module->getRequestVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');
    if (empty($configName)) {
        throw new \Exception("No configuration specified.");
    }

    $configuration = $module->getConfiguration($configName);
    if (empty($configuration)) {
        throw new \Exception("Invalid configuration \"{$configName}\" specified.");
    }

    $sourceProject      = $configuration->getSourceProject($module);
    $destinationProject = $configuration->getDestinationProject($module);

    if (defined('PROJECT_ID')) {
        $projectId = PROJECT_ID;
        if (empty($projectId)) {
            throw new \Exception("Project ID is not set.");
        }
    } else {
        throw new \Exception("Project ID is not set.");
    }
} catch (Exception $exception) {
    $error = 'ERROR: ' . $exception->getMessage();
}

?>


<?php
#--------------------------------------------
# Include REDCap's project page header
#--------------------------------------------
ob_start();

$htmlPage = new HtmlPage();
$htmlPage->PrintHeaderExt();
include APP_PATH_VIEWS . 'HomeTabs.php';

# require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$buffer = ob_get_clean();

$cssFile = $module->getUrl('resources/data-transfer.css');
$link = '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" media="all">';

$buffer = str_replace('</head>', "    {$link}\n</head>", $buffer);

echo $buffer;
?>

<div style="margin-top: 3em;">
&nbsp;
</div>

<?php
$module->renderMessages($error, $warning, $success);
?>

<?php

if (empty($error) && !empty($configuration)) {
    echo "<h4>Field Mapping Detail for Configuration \"{$configName}\"</h4>\n";

    $mappingHeader =
        '<tr>'
        //. '<th>Event</th><th>Form</th><th>Field</th><th>Instance</th>'
        . '<th>Event</th><th>Form</th><th>Field</th>'
        . '<th>&nbsp;</th>'
        //. '<th>Event</th><th>Form</th><th>Field</th><th>Instance</th>'
        . '<th>Event</th><th>Form</th><th>Field</th>'
        . '</tr>'
        ;

    $isFirst = true;

    foreach ($fieldMap->getMappings() as $mapping) {
        if ($isFirst) {
            $isFirst = false;
        } else {
            echo "<hr/>\n";
        }

        $fieldMappingStatus = $mapping->check($sourceProject, $destinationProject);

        echo "<p><span style=\"font-weight: bold;\">Status:</span> ";
        if ($fieldMappingStatus->isOk()) {
            echo '<span style="color: green;">OK</span>';
        } elseif ($fieldMappingStatus->isIncomplete()) {
            echo '<span style="color: blue;">Incomplete</span>';
        } elseif ($fieldMappingStatus->isError()) {
            echo '<span style="color: red;">Error</span>';
        }
        echo "</p>\n";

        if ($fieldMappingStatus->isError()) {
            echo "<div style=\"margin-left: 4em; border: 1px solid #222222; padding: 4px;\">\n";
            echo "Errors:\n";
            echo "<ul>\n";
            foreach ($fieldMappingStatus->getErrors() as $error) {
                echo "<li>{$error}</li>\n";
            }
            echo "</ul>\n";
            echo "</div>\n";
        }


        echo '<p style="font-weight: bold; color: #3E72A8;">Specified Mapping:</p>' . "\n";
        echo "<table class=\"dataTable\">\n";
        echo $mappingHeader;
        echo "<tr>\n";
        echo "<td>{$mapping->getSourceEvent()}</td>\n";
        echo "<td>{$mapping->getSourceForm()}</td>\n";
        echo "<td>{$mapping->getSourceField()}</td>\n";
        // echo "<td>{$mapping->getSourceInstance()}</td>\n";
        echo '<td><i class="fa fa-arrow-right-long" style="font-size: 140%;"></i></td>' . "\n";
        echo "<td>{$mapping->getDestinationEvent()}</td>\n";
        echo "<td>{$mapping->getDestinationForm()}</td>\n";
        echo "<td>{$mapping->getDestinationField()}</td>\n";
        // echo "<td>{$mapping->getDestinationInstance()}</td>\n";
        echo "</tr>\n";
        echo "</table>\n";

        echo '<div style="margin-left: 4em;">' . "\n";
        echo '<p style="font-weight: bold; color: #3E72A8;">Expanded Mapping:</p>' . "\n";
        $expandedMappings = $mapping->expand($module, $configuration);
        echo "<table class=\"dataTable\">\n";
        echo $mappingHeader;

        foreach ($expandedMappings as $expandedMapping) {
            echo "<tr>\n";
            echo "<td>{$expandedMapping->getSourceEvent()}</td>\n";
            echo "<td>{$expandedMapping->getSourceForm()}</td>\n";
            echo "<td>{$expandedMapping->getSourceField()}</td>\n";
            // echo "<td>{$expandedMapping->getSourceInstance()}</td>\n";
            echo '<td><i class="fa fa-arrow-right-long" style="font-size: 140%;"></i></td>' . "\n";
            echo "<td>{$expandedMapping->getDestinationEvent()}</td>\n";
            echo "<td>{$expandedMapping->getDestinationForm()}</td>\n";
            echo "<td>{$expandedMapping->getDestinationField()}</td>\n";
            // echo "<td>{$expandedMapping->getDestinationInstance()}</td>\n";
            echo "</tr>\n";
        }

        echo "</table>\n";
        echo "</div>\n";
    }
}

?>

<div>&nbsp;</div>



<!-- START OF FOOTER -->

<?php $htmlPage->PrintFooterExt(); ?>
