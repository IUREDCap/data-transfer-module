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

    $selfUrl      = $module->getUrl('web/dag_map.php');
    $configureUrl = $module->getUrl('web/configure.php');

    $configName = $module->getRequestSessionVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');

    if (!empty($configName)) {
        $configuration = $module->getConfiguration($configName);

        if (!empty($configuration)) {
            $sourceProject      = $configuration->getSourceProject($module);
            $destinationProject = $configuration->getDestinationProject($module);
        }

        if (array_key_exists('saveDagMapButton', $_POST)) {
            # Save
            $properties = $_POST;
            $configuration->setDagMapFromProperties($properties, $user, $projectUsers, $module->isSuperUser());

            $module->setConfiguration($configuration, USERID, PROJECT_ID);
            $success = 'Saved.';
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

# $module->renderProjectPageContentHeader($configureUrl, $error, $warning, $success);
# $module->renderConfigureSubTabs($selfUrl);
?>

<?php
if (empty($configName) || $configuration === null) {
    echo "<p>No configuration selected</p>\n";
} elseif (!$configuration->isProjectComplete()) {
    echo "<p>The data transfer project information needs to be completed before this page can be used.</p>\n";
} elseif (empty($error)) {
    $sourceDags      = array_merge([''], $sourceProject->getDagUniqueGroupNames());
    $destinationDags = array_merge([''], $destinationProject->getDagUniqueGroupNames());

    ?>
    <div style="margin-bottom: 22px;">
        <span style="border: 1px solid black; padding: 4px;">
            <b>Configuration Name:</b>
            <?php echo $configName; ?>
        </span>

        <!-- Help for DAG map -->
        <span style="font-size: 140%; margin-left: 1em;" title="help">
            <i id="dag-map-help-link" class="fa fa-question-circle" style="color: blue;"></i>
        </span>

        <!-- DAG map help div -->
        <div id="dag-map-help" title="DAG Map" style="display: none;">
            <?php echo Help::getHelpWithPageLink('dag-map', $module); ?>
        </div>


        <?php
        if (!$configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
            echo '<span style="font-weight: bold; margin-left: 1em; color: #F70000;">[VIEW ONLY MODE]</span>' . "\n";
        }
        ?>

    </div>
 
    <p>
    This page lets you specify how DAGs (Data Access Groups) are
    transferred from the source project to the destination project.
    </p>

    <?php
    $inert = '';
    if (!$configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
        $inert = 'inert';
    }
    ?>

    <form action="<?=$selfUrl;?>" method="post" style="margin-bottom: 12px;" <?php echo $inert; ?>>

        <div style="margin-bottom: 17px;">
            <?php if ($configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) { ?>
            <button id="saveDagMapButton" name="saveDagMapButton" value="submitted">
                <i class="fa fa-save" style="color: green; font-size: 110%;"></i>
                <span style="font-weight: bold; color: green; font-size: 110%;">Save</span>
            </button>
            <?php } ?>
        </div>

        <fieldset class="config">

            <div>
                <?php
                $checked = '';
                if ($configuration->getDagOption() === Configuration::DAG_NO_TRANSFER) {
                    $checked = 'checked';
                }
                ?>
                <input type="radio" name="<?php echo Configuration::DAG_OPTION; ?>"
                                    value="<?php echo Configuration::DAG_NO_TRANSFER; ?>"
                                    <?php echo $checked; ?>
                >
                <b>Do NOT Transfer.</b>
                Do not transfer DAGs (if any) from source project.
            </div>
        
            <hr/>

            <div>
                <?php
                $checked = '';
                if ($configuration->getDagOption() === Configuration::DAG_TRANSFER) {
                    $checked = 'checked';
                }
                ?>
                <input type="radio" name="<?php echo Configuration::DAG_OPTION; ?>"
                                    value="<?php echo Configuration::DAG_TRANSFER; ?>"
                                    <?php echo $checked; ?>
                >
                <b>Transfer.</b>
                Transfer DAGs from source project that exist in the destination project.
            </div>

            <hr/>

            <div>
                <?php
                $checked = '';
                if ($configuration->getDagOption() === Configuration::DAG_MAPPING) {
                    $checked = 'checked';
                }
                ?>
                <input type="radio" name="<?php echo Configuration::DAG_OPTION; ?>"
                                    value="<?php echo Configuration::DAG_MAPPING; ?>"
                                    <?php echo $checked; ?>
                >
                <b>Map.</b>
                Transfer DAGs from source project to destination project as follows:<br/>
            </div>


        <?php
        $dagMap = $configuration->getDagMap();
        ?>
        <table class="dataTable" id="dagMapTable"
               style="margin-top: 27px; margin-left: 4em; background-color: #FFFFFF;">
            <thead>
                <tr>
                    <th>For Source Project DAG:</th>
                    <!-- <th> Transfer Option</th> -->
                    <th> Exclude? </th>
                    <th>Set Destination Project DAG to:</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($sourceDags as $sourceDag) {
                    echo "<tr>";
                    echo "<td>{$sourceDag}</td>";

                    echo '<td style="text-align: center">';
                    if (array_key_exists($sourceDag, $configuration->getDagExclude())) {
                        echo "<input type=\"checkbox\" name=\"dag-exclude-{$sourceDag}\" checked>";
                    } else {
                        echo "<input type=\"checkbox\" name=\"dag-exclude-{$sourceDag}\">";
                    }
                    echo "</td>";

                    #echo "<td>";
                    #echo "<select name=\"dag-transfer-{$sourceDag}\">";

                    #echo '<option value="' . '">';
                    #echo "Do NOT transfer</option>";

                    #echo "<option value=\"2\">Transfer to new and existing records</option>";
                    #echo "<option value=\"3\">Transfer to new records</option>";
                    #echo "<option value=\"4\">Transfer to existing records</option>";
                    #echo "</select>\n";
                    #echo "</td>";

                    echo "<td>";
                    echo "<select name=\"dag-map-{$sourceDag}\">";
                    foreach ($destinationDags as $destinationDag) {
                        $value = '';
                        if (!empty($dagMap) && array_key_exists($sourceDag, $dagMap)) {
                            $value = $dagMap[$sourceDag];
                        } elseif (in_array($sourceDag, $destinationDags)) {
                            $value = $sourceDag;
                        }

                        if ($destinationDag === $value) {
                            echo "<option value=\"{$destinationDag}\" selected>{$destinationDag}</option>";
                        } else {
                            echo "<option value=\"{$destinationDag}\">{$destinationDag}</option>";
                        }
                    }
                    echo "</select>";

                    echo "</td>";
                    echo "</tr>\n";
                }
                ?>
            </tbody>
        </table>

        </fieldset>
    </form>

    <?php
}   // End of else
?>

<script>
    $(document).ready(function() {

        $('#dag-map-help-link').click(function () {
            $('#dag-map-help')
                .dialog({dialogClass: 'data-transfer-help', width: 640, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+50 top-90', of: $(this)})
                ;
            return false;
        });

    });
</script>



<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>
