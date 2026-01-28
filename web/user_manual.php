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
use IU\DataTransfer\Version;

$error   = '';
$warning = '';
$success = '';

try {
    $selfUrl   = $module->getUrl('web/user_manual.php');
    $configCreationImage = $module->getUrl('resources/config-creation.jpg');
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
$module->renderProjectPageContentHeader($selfUrl, $error, $warning, $success);
?>


<h4 style="font-weight: bold;">Overview</h4>

<p>
The Data Transfer external module is used to transfer data between REDCap projects. To specify
a data transfer, a configuration needs to be created in one of the projects involved in the
data transfer. The project that contains the configuration is referred to as the
<b>configuration project</b>. The data transfer configuration will specify the other
project involved in the data transfer, and that project is referred to as the
<b>transfer project</b>.
</p>

<p>The Data Transfer external module supports the export from or import to the configuration
project. The project from which data is transferred is referred to as the <b>source project</b>.
The project to which data is transferred is referred to as the <b>destination project</b>.
A single configuration can only import or export data (not both), but you can create multiple
configurations per project.
</p>

<img src="<?php echo $module->getUrl('resources/data-transfer-projects.jpg'); ?>" alt="Data Transfer Projects">

<p>
Data Transfer is able to transfer data to and from remote projects (projects in a different
REDCap instance), but it needs a REDCap API token for the remote project.
</p>

<hr/>

<h4 style="font-weight: bold;">Setting up a Data Transfer</h4>

<p>
A <b>data transfer configuration</b> is required to transfer data between 2 projects using
the Data Transfer external module. A single data transfer configuration specifies a
one-way transfer of data from one project to another. No limit is set by the
external module on the number of data transfer configurations per project.
</p>

<h5>
Steps for settings up a data transfer:
</h5>

<ol>
    <li style="margin-right: 2em;">
        <b>Choose the Configuration Project.</b> The first step is to decide which of the 2 projects
        of the data transfer will contain the configuration. In general, you
        would put the configuration in the project where you want to control the data
        transfer. In addition, if you are transferring data between 2 projects on different
        REDCap instances, and you only have an API token for one of those projects, the configuration
        would need to go in the project for which you do not have an API token (i.e., the project
        that is able to access the other project using an API token).
    </li>

    <li style="margin-right: 2em;">
        <b>Create a Configuration.</b>
        To create a configuration, on the <b>Data Transfer Configurations</b> tab:
        <ol>
            <li>enter the configuration name</li>
            <li>click on the <b>Add</b> button</li>
        </ol>
        <img src="<?php echo $module->getUrl('resources/config-creation.jpg'); ?>"
             style="border: 1px solid #222222; margin: 14px;"
             alt="Configuration Creation">
    </li>

    <li style="margin-right: 2em;">
    <b>Complete the Configuration.</b> The main steps for completing a data transfer configuration
    are shown in the list below.
        <ol>
            <li>
                <b>Specify the Transfer Project.</b> The first thing that needs to be
                configured is whether data is being imported or exported, and the
                other project that data will be imported from or exported to.
            </li>
            <li>
                <b>Specify the Transfer Options.</b> Read through the transfer options
                carefully  and change the default values that are not what you want.
                Click on the help links for more information.
            </li>
            <li>
                <b>Specify the Field Map.</b> The field map specifies how fields are
                mapped from the source project to the destination project.
                See the "Field Map" section below for more information.
            </li>
            <li>
                <b>Specify the DAG (Data Access Group) Mapping.</b> If the destination
                project does not have any DAGs, then the default value can be used.
            </li>
            <li>
                <b>Enable the Configuration.</b> In the <b>Transfer Project</b> subtab,
                click the <b>Enable</b> checkbox and then click the <b>Save</b>
                button.
            </li>
        </ol>
    </li>
</ol>

<h5>Field Map</h5>
<p>
The field map specifies which fields from the source project
are transferred to the destination project.
The field mappings are processed in the order listed, so that later mappings override
earlier mappings.
</p>

<p>
There are 3 basic types of mappings that can be specified:
</p>
<ol>
    <li style="margin-right: 2em;">
        <b>Individual Field Mapping:</b> a mapping from one field in the source
        project to one field in the destination project.
        <br/>
        <img src="<?php echo $module->getUrl('resources/individual-mapping.jpg'); ?>"
             style="margin-left: 1em;"
             alt="Individual Mapping">
        </li>
    <li style="margin-right: 2em;">
        <b>Wildcard Mapping:</b> a mapping that specifies a transfer from
        multiple fields in the source project to matching fields in the
        destination project. The example below says to transfer all fields
        of all forms in the source project to all matching forms and fields
        in the destination project. Destination events and forms use <b>MATCHING</b>
        for matching wildcard source events and forms (specified with <b>ALL</B>).
        For fields, either <b>EQUIVALENT</b>
        of <b>COMPATIBLE</b> is used. <b>EQUIVALENT</b> means a field thats name and type
        match exactly. <b>COMPATIBLE</b> means a field thats name matches exactly and
        thats type is compatible (but might not match exactly). For example, a destination
        field with validation
        type "number" is considered to be compatible (but not equivalent) to a source
        field with validation type "integer".
        <br/>
        <img src="<?php echo $module->getUrl('resources/wildcard-mapping.jpg'); ?>"
             style="margin-left: 1em;"
             alt="Wildcard Mapping">
        </li>
    </li>
    <li style="margin-right: 2em;">
        <b>Exclusion:</b> a specification of one or multiple destination fields
        that are excluded from the data transfer. The example below specifies that
        all the fields of the demographics form in the source project should be
        transferred to matching fields in the destination project, except for
        the <b>telephone</b> and <b>email</b> fields, which are excluded. Note
        that the order of the mappings is important, because mappings listed
        later (lower) override rules listed earlier.
        <br/>
        <img src="<?php echo $module->getUrl('resources/exclude-mapping.jpg'); ?>"
             style="margin-left: 1em;"
             alt="Wildcard Mapping">
    </li>

</ol>

<p>
To see the actual fields that will be transferred after wildcards are expanded,
and rule overrides have been applied, click on the <b>Field Mapping Detail</b>
button. If you want to see the wildcard expansions, or check for errors, for
a single field mapping, click on its <b>Status</b> button.
</p>

<p><b>Reordering Field Mappings.</b> Field mappings can be reordered by
dragging and dropping them.

<h5>Equivalent and Compatible Fields</h5>

<p>
Data Transfer uses the following classifications for mapped source and destination fields in a field map:
<ul>
    <li>
        <b>Equivalent.</b> All field attributes considered by Data Transfer are equivalent between
        the source and destination field. Note that Equivalent fields are also considered to be Compatible
        fields.
    </li>
    <li>
        <b>Compatible.</b> All field attributes considered by Data Transfer are compatible between
        the source and destination field.</li>
    <li>
        <b>Incompatible.</b> At least one element of field attributes is not considered compatible.
        A mapping of incompatible fields will result in a field mapping error, and the mapping
        between the fields will not occur if a data transfer is made.
    </li>
</ul>
</p>

<p>
Data Transfer considers the following REDCap field attributes when comparing fields:
<ul>
    <li><b>Field Type.</b>. For example, "Text Box", "Notes Box", "Checkboxes", "Yes - No".</li>
    <li><b>Required.</b> If the field is required or not.
    <li>
        <b>Validation.</b>. For text boxes, the validation specified,
        for example, "Email", "Integer", "Number".
    </li>
    <li>
        <b>Choices.</b>. For multiple-choice fields (drop-down lists, radio buttons and checkboxes),
        the values and labels for the choices.
    </li>
    <li>
        <b>Minimum Value.</b> For text box fields that have a numeric or date/time validation type,
        the minimum value allowed.
    </li>
    <li>
        <b>Maximum Value.</b> For text box fields that have a numeric or date/time validation type,
        the maximum value allowed.
    </li>
    <li>
        <b>Missing Data Codes.</b> The missing data codes for the field (if any). Note that Data Transfer does
        take into account @NOMISSING action tags.
    </li>
    <li>
        <b>Calculations.</b> For calculated field, the calculations that are performed.
    </li>
</ul>
</p>

<h6>Field Attribute Requirements for Equivalent and Compatible Fields</h6>

<p>
Data Transfer's rules for field equivalence and compatibility are as follows:
</p>
<ul style="width: 60%;">
    <li>
    For two fields to be considered equivalent, all of their field attributes in the table below
    must meet the condition for being equivalent.
    </li>
    <li>
    For two fields to be considered compatible, all of their field attributes in the table below must meet
    one of the conditions for being compatible.
    </li>
    <li>
    Fields that are equivalent are also considered to be compatible.
    </li>
    <li>
    For wildcard mappings, the field name attribute must also match exactly for two fields to be considered
    equivalent or compatible. When wildcards are used for the source field, Data Transfer uses the field name
    to search for the possibly equivalent or compatible field in the destination project. The field label
    attribute is ignored.
    </li>
</ul>

<table class="dataTable" style="margin-left: 2em; margin-bottom: 27px;">
    <thead>
        <tr>
            <th> Field Attribute </th>
            <th> Equivalent </th>
            <th> Compatible </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Field Type</td>
            <td>Exact match</td>
            <td>
                <ul style="margin-bottom: 0px;">
                    <li>Exact match</li>
                    <li>Source field is "dropdown" and destination field is "radio"</li>
                    <li>Source field is "radio" and destination field is "dropdown"</li>
                </ul>
            </td>
        </tr>

        <tr>
            <td>Required</td>
            <td>Exact match</td>
            <td>
                <ul style="margin-bottom: 0px;">
                    <li>Exact match</li>
                    <li>Source field is required and destination field is not required</li>
                </ul>
            </td>
        </tr>

        <tr>
            <td>Validation</td>
            <td>Exact match</td>
            <td>
                <ul style="margin-bottom: 0px;">
                    <li>Exact match</li>
                    <li>Source field is "Integer" and destination field is "Number"</li>
                    <li>Source field is a date and destination field is a date (even if date formats differ)</li>
                    <li>Source field is a datetime and destination field is a datetime
                    (even if date formats differ)</li>
                    <li>Source field is a datetime with seconds and destination field is a datetime
                    with seconds (even if date formats differ)</li>
                </ul>
            </td>
        </tr>

        <tr>
            <td>Choices</td>
            <td>Exact match</td>
            <td>
                <ul style="margin-bottom: 0px;">
                    <li>Exact match</li>
                    <li>Source field's choice values and labels are a proper subset
                    of the destination field's choice values and label</li>
                </ul>
            </td>
        </tr>

        <tr>
            <td>Minimum Value</td>
            <td>Exact match</td>
            <td>
                <ul style="margin-bottom: 0px;">
                    <li>Exact match</li>
                    <li>Source minimum value greater than or equal to destination minimum value</li>
                </ul>
            </td>
        </tr>

        <tr>
            <td>Maximum Value</td>
            <td>Exact match</td>
            <td>
                <ul style="margin-bottom: 0px;">
                    <li>Exact match</li>
                    <li>Source maximum value less than or equal to destination maximum value</li>
                </ul>
            </td>
        </tr>

        <tr>
            <td>Calculations</td>
            <td>Exact match</td>
            <td>
                <ul style="margin-bottom: 0px;">
                    <li>Exact match</li>
                </ul>
            </td>
        </tr>

    </tbody>
</table>

<hr/>

<h4 style="font-weight: bold;">Running a Data Transfer</h4>

There are 3 ways to run a data transfer once a configuration is set up:

<ol>
   <li>
   <b>Manual.</b> Use the <b>Manual Transfer</b> page to run a data transfer manually,
   </li>

   <li>
   <b>On Form Save.</b> Data transfers can be set up to run automatically when forms are saved by
   setting the <b>On form save</b> option on the <b>Transfer Options</b> subtab on the <b>Configure</b>
   page. Data will be transferred from any form that has a valid field mapping in the configuration.
   </li>

   <li>
   <b>Scheduled.</b> Data transfers can be scheduled to run on a regular basis using the <b>Schedule</b>
   page. A given data transfer configuration can be run at most once per hour, however, the REDCap admin
   may place restrictions on scheduling. Both the hour time periods and the maximum time periods a
   configuration can run per day can be restricted.
   </li>
</ol>

<hr/>

<h4 style="font-weight: bold;">User Permissions for Data Transfers</h4>
<p>
Users need the following permissions for data transfers:

<div>
    <b>Local Source Project</b>
    <ul>
        <li>"Project Design and Setup" privileges</li>
        <li>Access to all records; unrestricted by a DAG (Data Access Group)</li>
        <li>"Full Data Set" "Data Export Rights" for all forms</li>
    </ul>
</div>

<div>
    <b>Remote Source Project</b>
    <ul>
        <li>API token with export privilege</li>
        <li>The user of the API token should have:
            <ul>
                <li>Access to all records; unrestricted by a DAG (Data Access Group)</li>
                <li>"Full Data Set" "Data Export Rights" for all forms</li>
                <li>"Data Access Group" privileges</li>
            </ul>
        </li>
    </ul>
</div>


<div>
    <b>Local Destination Project</b>
    <ul>
        <li>Access to all records; unrestricted by a DAG (Data Access Group)</li>
        <li>"View &amp; Edit Data" "Viewing Rights" for all forms</li>
        <li>"Full Data Set" "Data Export Rights" for all forms</li>
        <li>Create Records rights</li>
    </ul>
</div>

<div>
    <b>Remote Destination Project</b>
    <ul>
        <li>API token with import/update and export privileges</li>
        <li>The user of the API token should have:
            <ul>
                <li>Access to all records; unrestricted by a DAG (Data Access Group)</li>
                <li>"Full Data Set" "Data Export Rights" for all forms</li>
                <li>"Data Access Group" privileges</li>
            </ul>
        </li>
    </ul>
</div>

<hr/>

<h4 style="font-weight: bold;">User Permissions for Data Transfer Configuration</h4>

<p>
The user who created a data transfer configuration is considered to be
the <b>owner</b> of the configuration.
</p>

<p>
The owner of a configuration has permission to edit, rename, copy, and delete
the configuration, and transfer data using the configuration. The owner
can also create and modify a schedule for the configuration of when
it should be automatically run.
</p>

<p>
Users of a project who are not the owner of the configuration have
permission to:
</p>

<ul>
    <li> View the configuration, except for the transfer project's API token (if any) </li>
    <li> View the schedule (if any) for the configuration </li>
    <li> Copy the configuration, except for the transfer project's API token (if any) </li>
    <li> Transfer data using the configuration (but also need appropriate permission
         for the projects involved in the transfer)</li>
    <li> Delete the configuration only if the owner no longer has access to the project </li>
</ul>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>
