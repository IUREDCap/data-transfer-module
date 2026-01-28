<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

/** @var \IU\DataTransfer\DataTransfer $module */

require_once __DIR__ . '/../vendor/autoload.php';

use IU\DataTransfer\Authorization;
use IU\DataTransfer\Configuration;
use IU\DataTransfer\Help;
use IU\DataTransfer\Filter;
use IU\DataTransfer\Version;


try {
    #-----------------------------------------------------------
    # Check that the user has permission to access this page
    #-----------------------------------------------------------
    $module->checkUserPagePermission(USERID);

    $user = USERID;

    $projectUsers = \REDCap::getUsers();

    $configurations = $module->getConfigurations();
    $configNames = $configurations->getConfigurationNames();

    #------------------------------------------------------------------
    # Get the configuration name (if any) and associated configuration
    #------------------------------------------------------------------
    $configName = $module->getRequestSessionVar('configName', '\IU\DataTransfer\Filter::sanitizeLabel');
    if (!empty($configName)) {
        $storedConfiguration = $module->getConfiguration($configName, PROJECT_ID);
        if ($storedConfiguration === null) {
            $configuration = new Configuration();
            $configuration->setOwner($user);
        } else {
            $configuration = $storedConfiguration;
        }
    } else {
        $configuration = new Configuration();
        $configuration->setOwner($user);
    }

    $owner = $configuration->getOwner();

    $userProjects = $module->getUserProjects($owner);

    $isOwner = false;
    if ($user === $owner) {
        $isOwner = true;
    }

    #-----------------------------------------------------------------
    # Process form submissions (configuration add/copy/delete/rename)
    #-----------------------------------------------------------------
    $submitValue = Filter::sanitizeButtonLabel($_POST['submitValue']);

    if ($submitValue === 'Save') {
        $parameters = $_POST;
        $configuration->setTransferProject($parameters, $user, $projectUsers, $module->isSuperUser());
        $module->setConfiguration($configuration, USERID, PROJECT_ID);
        $success = 'Saved.';
    }
} catch (\Exception $exception) {
    $error = 'ERROR: ' . $exception->getMessage();
}

#---------------------------------------------
# Add custom files to head section of page
#---------------------------------------------
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

$configurationNames = $module->getConfigurationNames();

$selfUrl      = $module->getUrl('web/configure.php');
$configureUrl = $module->getUrl('web/configure.php');

$projectId = $module->getProjectId();

?>

<?php

$module->renderProjectPageConfigureContentHeader($configureUrl, $selfUrl, $error, $warning, $success);

# $module->renderProjectPageContentHeader($selfUrl, $error, $warning, $success);
# $module->renderConfigureSubTabs($selfUrl);

?>

<?php

if (empty($configName) || $configuration === null) {
    if (count($configNames) < 1) {
        echo "<p>No data transfer configurations have been created for this project.</p>\n";
    } else {
        echo "<p>No configuration selected.</p>\n";
        echo "<p>Select a configuration:</p>\n";
        echo '<form id="configSelectForm" action="' . $selfUrl . '" method="post">' . "\n";
        echo '<select id="configSelect" name="configName">' . "\n";
        array_unshift($configNames, '');
        foreach ($configNames as $name) {
            echo "<option>{$name}</option>\n";
        }
        echo "</select>\n";
        echo "</form>\n";
    }
} else {
    #------------------------------------------------------------
    # Configure form
    #------------------------------------------------------------
    ?>

    <div style="margin-bottom: 22px;">
        <span style="border: 1px solid black; padding: 4px;">
            <b>Configuration Name:</b>
            <?php echo $configName; ?>
        </span>

        <!-- Help for transfer project -->
        <span style="font-size: 140%; margin-left: 1em;" title="help">
            <i id="transfer-project-help-link" class="fa fa-question-circle" style="color: blue;"></i>
        </span>

        <!-- Transfer project help div -->
        <div id="transfer-project-help" title="Transfer Project" style="display: none;">
            <?php echo Help::getHelpWithPageLink('transfer-project', $module); ?>
        </div>

        <?php
        if (!$configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
            echo '<span style="font-weight: bold; margin-left: 1em; color: #F70000;">[VIEW ONLY MODE]</span>' . "\n";
        }
        ?>
    </div>

    <?php
    $inert = '';
    if (!$configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) {
        $inert = 'inert';
    }
    ?>

    <form id="transferProjectForm" action="<?=$selfUrl;?>" method="post"
           style="margin-bottom: 12px;" <?php echo $inert; ?>>
        <input type="hidden" name="configName" value="<?php echo $configName; ?>"/>

        <input type="hidden" name="<?php echo Configuration::CONFIG_PROJECT_ID; ?>" value="<?php echo PROJECT_ID;?>"/>

        <!-- Checkboxes from other configuration tabs that need to be included -->
        <input type="hidden" name="<?php echo Configuration::TRANSFER_FILES; ?>"
               value="<?php echo $configuration->getTransferFiles(); ?>"/>

        <input type="hidden" name="<?php echo Configuration::OVERWRITE_WITH_BLANKS; ?>"
               value="<?php echo $configuration->getOverwriteWithBlanks(); ?>"/>

        <?php
        $checked = '';
        if ($configuration->isEnabled()) {
            $checked = 'checked';
        }
        ?>

        <input type="checkbox" name="isEnabled" <?php echo $checked;?>/> Enabled <br/>
        <br/>

        <div style="clear: both;"></div>

        <fieldset class="config" style="float: left;">
            <legend>Data Transfer Direction</legend>
            <?php
            $importChecked = '';
            $exportChecked = '';
            if ($configuration->getDirection() === 'import') {
                $importChecked = 'checked';
            } elseif ($configuration->getDirection() === 'export') {
                $exportChecked = 'checked';
            }
            ?>

            <input type="radio" name="direction" value="import" <?php echo $importChecked; ?>/>
            Import from
            <span class="fas fa-arrow-left" style="padding: 2px;"></span>
            <br/>
            <input type="radio" name="direction" value="export" <?php echo $exportChecked; ?>/>
            Export to
            <span class="fas fa-arrow-right" style="padding: 2px;"></span>
            <!--
            <select name="direction">
                <option value="import">Import from</option>
                <option value="export">Export to</option>
            </select>
            -->
        </fieldset>

        <fieldset class="config" style="float: left; margin-left: 2em;">
            <legend>Transfer Project</legend>

            <?php
            $localChecked = '';
            $remoteChecked = '';
            if ($configuration->getLocation() === 'local') {
                $localChecked = 'checked';
            } elseif ($configuration->getLocation() === 'remote') {
                $remoteChecked = 'checked';
            }
            ?>
            <input type="radio" name="location" value="local" <?php echo $localChecked; ?>/> local project

            <!-- Local transfer project select -->
            <select id="projectIdSelect" name="projectId">
            <option value="0"></option>

            <?php
            $configProjectId = $configuration->getProjectId();
            foreach ($userProjects as $projectId => $projectTitle) {
                if ($projectId == $configProjectId) {
                    echo "<option value=\"{$projectId}\" selected>{$projectTitle} [pid={$projectId}]</option>\n";
                } else {
                    echo "<option value=\"{$projectId}\">{$projectTitle} [pid={$projectId}]</option>\n";
                }
            }
            ?>
            </select>


            <br/>

            <?php
            if ($configuration->userMayViewApiToken($user)) {
                $apiTokenValue = Filter::escapeForHtml($configuration->getApiToken());
            } else {
                $apiTokenValue = Configuration::API_TOKEN_MASK;
            }
            ?>
            <div style="margin-top: 7px;">
                <input type="radio" name="location" value="remote" <?php echo $remoteChecked; ?>/> remote project with
                API URL: <input type="text" name="apiUrl"
                                value="<?php echo Filter::escapeForHtml($configuration->getApiUrl()); ?>" size="27">
                API Token: <input type="text" name="apiToken" size="33"
                                  value="<?php echo $apiTokenValue; ?>">
            </div>
        </fieldset>

        <div style="clear: both;"></div>

        <?php if ($configuration->mayBeModifiedByUser($user, $projectUsers, $module->isSuperUser())) { ?>
            <input type="submit" style="font-weight: bold; font-size: 110%;" name="submitValue" value="Save" />
        <?php } ?>

        <!--
        <div style="margin-bottom: 14px;">
            <button id="saveTransferProjectButton" name="saveFieldMapButton" value="submitted">
                <i class="fa fa-save" style="color: green; font-size: 110%;"></i>
                <span style="font-weight: bold; color: green; font-size: 110%;">Save</span>
            </button>
        </div>
        -->

        <input type="hidden" name="redcap_csrf_token" value="<?php echo $redcapCsrfToken; ?>"/>
        <!--
        <input type="hidden" name="submitValue" value="Save" />
        -->
        <input type="hidden" name="redcap_csrf_token" value="<?php echo $module->getCsrfToken(); ?>"/>
    </form>

    <?php
} // else
?>

<script>
    $(document).ready(function() {

        // Help dialog events
        $('#transfer-project-help-link').click(function () {
            $('#transfer-project-help')
                .dialog({dialogClass: 'data-transfer-help', width: 540, maxHeight: 440})
                .dialog('widget').position({my: 'left top', at: 'right+50 top+10', of: $(this)})
                ;
            return false;
        });

        $('#configSelect').change(function () {
            $('#configSelectForm').submit();
            return false;
        });

        //$("#saveTransferProjectButton").click(function() {
        //    $("#transferProjectForm").submit();
        ////});

        $("#projectIdSelect").select2();
    });
</script>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; ?>
