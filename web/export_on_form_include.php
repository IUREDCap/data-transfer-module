<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

#-------------------------------------------------------------------------
# Only allow this page to be included, and not accessed directly
#-------------------------------------------------------------------------
if (!defined('DATA_TRANSFER_MODULE')) {
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    exit;
}

# Inputs:
# $formConfigs - Configurations object containing configurations where data
#                is exported on form save for the current form.

?>

<?php
if (!empty($formConfigs)) {
    ?>

    <!-- Dialog with export form data on save information -->
    <div id="export-on-form-save-dialog" title="Export Form Data on Save" style="display: none;" >
        <?php
        $isFirstConfig = true;
        foreach ($formConfigs->getConfigurationMap() as $configName => $config) {
            if ($isFirstConfig) {
                $isFirstConfig = false;
            } else {
                echo "<hr/>\n";
            }


            $sourceProject  = $config->getSourceProject($this);
            $sourceForm = $instrument;
            $sourceEvent = null;
            if ($sourceProject->isLongitudinal()) {
                $sourceEvent = $sourceProject->getUniqueEventNameFromEventId($eventId);
            }

            $transferProject = $config->getTransferProject($this);

            $fieldMap = $config->getFieldMapObject();
            $fieldMap = $fieldMap->simplify($this, $config);

            $fieldMap->filterMappings($sourceForm, $sourceEvent);

            echo '<p>' . "\n";
            if ($transferProject->isApiProject()) {
                echo "Form data will be transferred using API to project <b>\"{$transferProject->getTitle()}\"</b>";
                echo " at {$transferProject->getApiUrl()}";
                echo " [pid = {$transferProject->getPid()}]<br/>\n";
            } else {
                echo "Form data will be transferred to project <b>\"{$transferProject->getTitle()}\"</b>";
                echo " [pid = {$transferProject->getPid()}]<br/>\n";
            }
            echo "</p>\n";

            echo '<p style="margin-left: 2em;">' . "\n";
            echo "Data Transfer Configuration: <b>{$configName}</b>"
               . " (owner: {$config->getOwner()})<br/>\n";
            echo "</p>\n";

            $th = '<th style="border: 1px solid #222222; padding: 2px 4px; background-color: #EEEEEE;">';
            $td = '<td style="border: 1px solid #222222; padding: 2px 4px;">';

            # echo "source form: {$sourceForm}<br/>\n";
            # echo "source eventi: {$sourceEvent}<br/>\n";
            echo '<table style="margin-left: 2em; border: 1px solid #222222;">' . "\n";
            echo '<thead>';
            # echo '<table style="margin-left: 2em; border: 1px solid #222222; border-collapse: collapse;">' . "\n";
            echo "<tr>";
            echo $th . 'Form Field</th>';
            echo $th . '&nbsp;</th>';

            if ($transferProject->isLongitudinal()) {
                echo $th . 'Destination Event</th>';
            }

            echo $th . 'Destination Form</th>';
            echo $th . 'Destination Field</th>';
            echo "</tr>\n";
            echo '</thead>';

            echo '<tbody>';
            foreach ($fieldMap->getMappings() as $mapping) {
                echo "<tr>";

                echo $td;
                echo $mapping->getSourceField();
                echo "</td>";

                echo $td;
                echo '&nbsp;<i class="fas fa-arrow-right"></i>&nbsp;';
                echo "</td>";

                if ($transferProject->isLongitudinal()) {
                    echo $td;
                    echo $mapping->getDestinationEvent();
                    echo "</td>";
                }

                echo $td;
                echo $mapping->getDestinationForm();
                echo "</td>";

                echo $td;
                echo $mapping->getDestinationField();
                echo "</td>";

                echo "</tr\n>";
            }
            echo '</tbody>';
            echo "</table>\n";
        }
        ?>
    </div>

    <script>
        $(document).ready(function() {

            $( "#export-on-form-save-dialog" ).dialog({
                autoOpen: false,
                buttons: {
                   OK: function() {$(this).dialog("close");}
                },
                title: "Export Form Data on Save",
                width: 820,
                height: 500 //,
                //position: {
                //   my: "left center",
                //   at: "left center"
                //}
            });

            $( 'body' ).on("click", '.opener-2', function() {
                $( "#export-on-form-save-dialog" ).dialog( "open" );
                return false;
            });

            $('table#questiontable>tbody').prepend(
                '<tr style="border-top: 1px solid #DDDDDD; background-color: #EFBFDF;">'
                // '<tr style="border-top: 1px solid #DDDDDD; background-color: #F2E2FA;">'
                + '<td style="text-align: center; padding: 6px;" colspan="2">'
                + '<b>Data Transfer.</b> The Data Transfer external module will transfer data from this'
                + ' form when it is saved.'
                + ' <button class="opener-2"'
                + ' style="border: 1px solid #AC2B81#; margin-left: 4px; text-align: center; border-radius: 6px;'
                + ' background-color: #d558ab; color: #FFFFFF; font-weight: bold;">'
                + 'details'
                + '</button>'
                + '</td>'
                + '</tr>'
            );
        });
    </script>

    <?php
}
?>


