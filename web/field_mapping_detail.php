<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

#------------------------------------------------------------
# Page for displaying the details of field mappings
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

    $selfUrl   = $module->getUrl('web/field_mapping_detail.php');

    $fieldMapJson = $module->getRequestVar('fieldMapJson', '\IU\DataTransfer\Filter::sanitizeJson');
    $fieldMap = new FieldMap();
    $fieldMap->setFromJson($fieldMapJson);

    $configName   = $module->getRequestVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');
    if (empty($configName)) {
        throw new \Exception("No configuration specified.");
    }

    $configuration = $module->getConfiguration($configName);

    if (empty($configuration)) {
        throw new \Exception("Configuration \"{$configName}\" not found.");
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

    $simplifiedFieldMap = $fieldMap->simplify($module, $configuration);
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

    $mappingExcludeHeader =
        '<tr>'
        //. '<th>Event</th><th>Form</th><th>Field</th><th>Instance</th>'
        . '<th>Event</th><th>Form</th><th>Field</th>'
        . '<th>&nbsp;</th>'
        //. '<th>Event</th><th>Form</th><th>Field</th><th>Instance</th>'
        . '<th>Event</th><th>Form</th><th>Field</th>'
        . '<th>Exclude</th>'
        . '</tr>'
        ;

    $mappingExcludeErrorsHeader =
        '<tr>'
        //. '<th>Event</th><th>Form</th><th>Field</th><th>Instance</th>'
        . '<th>Event</th><th>Form</th><th>Field</th>'
        . '<th>&nbsp;</th>'
        //. '<th>Event</th><th>Form</th><th>Field</th><th>Instance</th>'
        . '<th>Event</th><th>Form</th><th>Field</th>'
        . '<th>Exclude</th>'
        . '<th>Errors</th>'
        . '</tr>'
        ;

    echo "<table id=\"fieldMappingDetailTable\" class=\"dataTable\">\n";
    echo $mappingHeader;

    foreach ($simplifiedFieldMap->getMappings() as $mapping) {
        echo "<tr>\n";
        echo "<td>{$mapping->getSourceEvent()}</td>\n";
        echo "<td>{$mapping->getSourceForm()}</td>\n";
        echo "<td>{$mapping->getSourceField()}</td>\n";
        echo '<td><i class="fa fa-arrow-right-long" style="font-size: 140%;"></i></td>' . "\n";
        echo "<td>{$mapping->getDestinationEvent()}</td>\n";
        echo "<td>{$mapping->getDestinationForm()}</td>\n";
        echo "<td>{$mapping->getDestinationField()}</td>\n";
        echo "</tr>\n";
    }

    echo "</table>\n";

    #-----------------------------------------------------------------
    # Incomplete field mappings
    #-----------------------------------------------------------------
    $incompleteMappings = $fieldMap->getIncompleteMappings($sourceProject, $destinationProject);

    if (!empty($incompleteMappings)) {
        echo "<h4 style=\"margin-top: 32px;\">";
        echo '<i class="fa fa-circle-half-stroke" style="color: blue;"></i>';
        echo "&nbsp;Incomplete Field Mappings for Configuration \"{$configName}\"</h4>\n";
        echo "<table id=\"incomplete-field-mappings-table\" class=\"dataTable\">\n";
        echo $mappingExcludeHeader;

        foreach ($incompleteMappings as $mapping) {
            echo "<tr>\n";
            if ($mapping->getExcludeDestination()) {
                echo "<td>&nbsp;</td>\n";
                echo "<td>&nbsp;</td>\n";
                echo "<td>&nbsp;</td>\n";
                echo '<td><i class="fa fa-ban" style="font-size: 140%; color: red;"></i></td>';
            } else {
                echo "<td>{$mapping->getSourceEvent()}</td>\n";
                echo "<td>{$mapping->getSourceForm()}</td>\n";
                echo "<td>{$mapping->getSourceField()}</td>\n";
                echo '<td><i class="fa fa-arrow-right-long" style="font-size: 140%;"></i></td>' . "\n";
            }
            echo "<td>{$mapping->getDestinationEvent()}</td>\n";
            echo "<td>{$mapping->getDestinationForm()}</td>\n";
            echo "<td>{$mapping->getDestinationField()}</td>\n";
            $checked = '';
            if ($mapping->getExcludeDestination()) {
                $checked = 'checked';
            }
            echo "<td style=\"text-align: center;\"><input type=\"checkbox\" disabled $checked/></td>\n";
            echo "</tr>\n";
        }

        echo "</table>\n";
    }

    #-----------------------------------------------------------------
    # Error field mappings
    #-----------------------------------------------------------------
    $errorMappings = $fieldMap->getErrorMappings($sourceProject, $destinationProject);

    if (!empty($errorMappings)) {
        echo "<h4 style=\"margin-top: 32px;\">";
        echo '<i class="fa fa-circle-xmark" style="color: red;"></i>';
        echo "&nbsp;Error Field Mappings for Configuration \"{$configName}\"";
        echo "</h4>\n";
        echo "<table class=\"dataTable\">\n";
        echo $mappingExcludeErrorsHeader;

        foreach ($errorMappings as $mapping) {
            $fieldMappingStatus = $mapping->check($sourceProject, $destinationProject);

            echo "<tr>\n";
            if ($mapping->getExcludeDestination()) {
                echo "<td>&nbsp;</td>\n";
                echo "<td>&nbsp;</td>\n";
                echo "<td>&nbsp;</td>\n";
                echo '<td><i class="fa fa-ban" style="font-size: 140%; color: red;"></i></td>';
            } else {
                echo "<td>{$mapping->getSourceEvent()}</td>\n";
                echo "<td>{$mapping->getSourceForm()}</td>\n";
                echo "<td>{$mapping->getSourceField()}</td>\n";
                echo '<td><i class="fa fa-arrow-right-long" style="font-size: 140%;"></i></td>' . "\n";
            }
            echo "<td>{$mapping->getDestinationEvent()}</td>\n";
            echo "<td>{$mapping->getDestinationForm()}</td>\n";
            echo "<td>{$mapping->getDestinationField()}</td>\n";
            $checked = '';
            if ($mapping->getExcludeDestination()) {
                $checked = 'checked';
            }
            echo "<td style=\"text-align: center;\"><input type=\"checkbox\" disabled $checked/></td>\n";

            $errors = $fieldMappingStatus->getErrors();
            $errorsHtml = '<ul>';
            foreach ($errors as $error) {
                $errorsHtml .= "<li>{$error}</li>";
            }
            $errorsHtml .= '</ul>';
            echo "<td>{$errorsHtml}</td>\n";
            echo "</tr>\n";
        }

        echo "</table>\n";
    }
}

?>

<div>&nbsp;</div>


<!-- START OF FOOTER -->

<?php $htmlPage->PrintFooterExt(); ?>
