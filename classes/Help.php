<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

class Help
{
    /** @var array map from help topic to help content */
    private static $help = [
        'dag-map' =>
             "<p>"
             . "This page lets you specify how DAGs (Data Access Groups) are transferred between the 2 projects."
             . " By default, DAGs in the source project are transferred to the destination project if the DAG"
             . " also exists in the destination project."
             . "</p>"
         ,
        'data-transfer-configurations' =>
            "<p>"
            . "This page lets you manage the Data Transfer configurations for the project. Data Transfer"
            . " configurations specify a data export from, or data import to, the project."
            . " The owner of a configuration is the user who created it."
            . "</p>"
            . "<p>"
            . " To create a new configuration, enter the name of the configuration in the text box"
            . " and click on the <b>Add</b> button. To edit a configuration, click on its "
            . " <b>Configure</b>"
            . ' <img alt="CONFIG" src="' . APP_PATH_IMAGES . 'gear.png">'
            . " button."
            . " If you are not the owner of a configuration (or a REDCap admin), the"
            . " Configuration button will appear as"
            . ' <i class="fa fa-eye" style="color: #999999;"></i>, and you will only be allowed'
            . " to view the configuration. However, you will not be able to view"
            . " the API token for the remote project, if there is one."
            . "</p>"
            . "<p>"
            . "Only the owner of a configuration can edit, rename, or delete it, with one exception."
            . " If the owner no longer has access to the project, then other users can"
            . " delete the configuration."
            . "</p>"
        ,
        'data-transfer-cron-jobs' =>
            '<p>'
            . 'This page contains information and configuration settings for scheduled data transfers that'
            . ' are processed by the Data Transfer cron job. You can:'
            . '</p>'
            . '<ul>'
            . '<li>'
            . 'See the last time the Data Transfer cron job ran and checked'
            . ' for scheduled data transfers to process.'
            . '</li>'
            . '<li>'
            . 'Configure the number of hour time slots per day that a user can schedule'
            . ' a single configuration to run.'
            . ' By default, this value is set to the maximum of 24. The minimum is'
            . ' 1, which would mean that users can only schedule a data transfer configuration to run once per day.'
            . '</li>'
            . '<li>'
            . 'Configure what times data transfers can be scheduled by users.'
            . ' If the box for a given day and time is checked, it means that users are'
            . ' allowed to schedule data transfers to run during that time.'
            . ' By default, all boxes are checked, so users are allowed to schedule'
            . ' data transfers for any time.'
            . '</li>'
            . '<li>'
            . 'See how many data transfers are scheduled for each time. If there are any data transfers scheduled'
            . ' for a time, then the number scheduled will appear to the right of the checkbox for that time.'
            . ' This number can be clicked on to see the details for the data transfers scheduled for that time.'
            . '</li>'
            . '</ul>'
        ,
        'field-map' =>
            '<p>'
            . 'The field map specifies how fields are mapped from the source project to the destination project.'
            . '</p>'
            . "<p>"
            . "<b>Wildcards.</b> Wildcard mapping specifications can be made by selecting <b>ALL</b> for the"
            . " source event, form, and/or field."
            . " For destination events and forms corresponding to a wildcard source event or form, use"
            . " <b>MATCHING</b>. For a destination field that corresponds to a wildcard source field, use"
            . " <b>EQUIVALENT</b> or <b>COMPATIBLE</b>. EQUIVALENT requires an exact name and type match between"
            . " the two fields. COMPATIBLE requires a name match, and that the destinations field's type"
            . " is compatible with the source field's type. For example, if the destination field has"
            . ' a validation type of "number", it would be compatible (but not equivalent) with a source field with'
            . ' a validation type of "integer".'
            . '</p>'
            . '<p>'
            . '<b>Checking the Field Map.</b> To check the field map, click on the <b>Field Mapping Detail</b>'
            . ' button. A new window or tab will open that lists all the individual field mappings after'
            . ' expanding and combining your specified field mappings. In addition, a list any field mappings'
            . ' that are incomplete or have errors will be listed.'
            . "</p>\n"
            . "<b>Checking a Single Field Mapping.</b> To check a single field mapping, click on its <b>Status</b>"
            . " button: "
            . '<i class="fa fa-circle-info status-field-mapping" style="color: blue;"></i>'
            . "<p>"
            . '<b>Reordering Field Mappings.</b> You can reorder field mappings by dragging and dropping individual'
            . ' field mappings to their new locations.'
            . '</p>'
        ,
        'manual-transfer' =>
            '<p>'
            . 'This page is used to manually transfer data from one project to another according to'
            . ' the selected data transfer configuration.'
            . ' This page also provides information about field mappings'
            . ' that are incomplete or have errors.'
            . '</p>'
        ,
        'processing-options' =>
             '<p>'
             . "<b>Record batch size</b> specifies how many records are processed at a time when transferring"
             . " multiple records. Processing in batches is supported, because transferring all"
             . " the records at once can cause REDCap's memory limit to be exceeded for very large"
             . " REDCap projects. And, transferring data one record at a time can cause the data"
             . " transfer to be very slow. Note that file fields, if transferred, are always processed one at a time"
             . " regardless of the record batch size setting."
             . '</p>'
         ,
         'record-changes' =>
             '<p>'
             . 'This section lets you set how records in the destination project are changed.'
             . '</p>'
             . '<p>'
             . '<span style="color: red; font-weight: bold;">WARNING:</span>'
             . ' selecting all of the following options will cause files in the destination:'
             . ' project to be deleted if the file to be transferred from'
             . ' the source project does not exist.'
             . '<ul>'
             . '<li>Update existing records</li>'
             . '<li>Overwrite existing values with blank values</li>'
             . '<li> Transfer file fields</li>'
             . '</ul>'
             . '</p>'
         ,
         'record-export-filter' =>
             '<p>'
             . 'The record export filter allows you to restrict the records that are exported'
             . ' from the source project. Only records matching the specified filter will'
             . ' be exported. For example, the following filter would only export the record'
             . ' with ID 1001:'
             . ' <pre> '
             . ' <code> '
             . ' [record_id] = 1001'
             . ' </code> '
             . ' </pre> '
             . '</p>'
         ,
         'record-matches' =>
             '<p>'
             . 'The record match configuration option lets you set what value'
             . ' is used from the source project to match records in the'
             . ' destination project.'
             . '</p>'
         ,
         'repeating-data' =>
             '<p>'
             . 'The repeating data section of the transfer options lets you configure how'
             . ' transfers from repeating data to non-repeating data, and non-repeating data'
             . ' to repeating data, are handled.'
             . '</p>'
             . '<p>'
             . 'Repeating data use instance numbers to distinguish between different instances.'
             . ' When transferring repeating data to non-repeating data, you need to configure'
             . ' which instance of the repeating data will be transferred.'
             . ' When transferring non-repeating data to repeating data, you need to configure'
             . ' which instance of the repeating data the non-repeating data will be transferred to.'
             . '</p>'
         ,
         'schedule' =>
             '<p>'
             . 'This page is used to schedule data transfers to run on a recurring basis.'
             . ' Check the boxes for the days and times when you want the data'
             . ' transfer for the selected configuration to run.'
             . ' Click the <b>Save</b> button to save your schedule.'
             . '</p>'
             . '<p>'
             . ' If a checkbox for a given day and hour cannot be checked, it means that'
             . ' this day and time has been disabled by an admin, and data transfers'
             . ' cannot be scheduled during this time.'
             . '</p>'
             . '<p>'
             . 'In addition, the REDCap admin can set a maximum number of times that a'
             . ' data transfer configuration can be scheduled to run in a day.'
             . ' If you try to exceed this limit, you will get an error.'
             . ' If the limit of times per day is reduced after you have already scheduled'
             . ' more than that number of times, then you will see a <b>"> max"</b> to the'
             . ' right of the checkboxes for times that exceed the limit, and these times will'
             . ' not be processed.'
             . '</p>'
         ,
         'transfer-activation' =>
             '<p>'
             . "For importing data into a project, the only transfer activation method supported is"
             . " manual transfer. For exporting data from a project, manual transfer and"
             . " transferring the data on form save are supported. For the transfer on form"
             . " save case, anytime a form is saved that has fields in the field map, the data"
             . " in the form will be transferred as specified in the data transfer"
             . " configuration."
             . '</p>'
         ,
         'transfer-project' =>
             '<p>'
             . "This page is for specifying:"
             . "</p>"
             . "<ul>"
             . "<li> the <b>transfer project</b>, which is the other project in the data transfer</li>"
             . "<li> the <b>direction</b> of the data transfer (import or export)</li>"
             . "<li> if the data transfer configuration is <b>enabled</b></li>"
             . "</ul>"
             . '</p>'
             . '<p>'
             . ' <b>Local projects</b> are projects that are on the same REDCap instance as the project'
             . ' containing the Data Transfer configuration.'
             . ' <b>Remote projects</b> are projects on a different REDCap instance, and they are accessed using'
             . ' a REDCap API (Application Programming Interface) token. It is also possible to access'
             . ' local projects using an API token.'
             . '</p>'
             . '<p>'
             . ' The transfer project needs to be configured first, '
             . ' before the other configuration tabs can be used.'
             . '</p>'
    ];

    public static function getTitle($topic)
    {
        # Change dashes to blanks and capitalize the first letter of each word
        $title = str_replace('-', ' ', $topic);
        $title = ucwords($title);

        # Make adjustments
        $title = str_replace('Post Processing', 'Post-Processing', $title);
        $title = str_replace('Api', 'API', $title);
        $title = str_replace('Email', 'E-mail', $title);
        $title = str_replace('Sql', 'SQL', $title);

        return $title;
    }


    public static function getHelp($topic)
    {
        $help = self::$help[$topic];
        return $help;
    }

    public static function getHelpWithPageLink($topic, $module)
    {
        $help = self::getHelp($topic);
        $help = '<a id="' . $topic . '-help-page" href="' . $module->getUrl('web/help.php?topic=' . $topic) . '"'
            . ' target="_blank" style="float: right;"'   // @codeCoverageIgnore
            . '>'                                        // @codeCoverageIgnore
            . 'View text on separate page</a>'           // @codeCoverageIgnore
            . '<div style="clear: both;"></div>'         // @codeCoverageIgnore
            . $help;
        return $help;
    }


    public static function getTopics()
    {
        return array_keys(self::$help);
    }

    /**
     * Indicates if the specified topic is a valid help topic.
     *
     * @return boolean true if the specified topic is a valid help topic, and false otherwise.
     */
    public static function isValidTopic($topic)
    {
        $isValid = false;
        $topics = array_keys(self::$help);
        if (in_array($topic, $topics)) {
            $isValid = true;
        }
        return $isValid;
    }
}
