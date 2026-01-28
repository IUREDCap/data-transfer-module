<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

# This is required for cron jobs
// phpcs:disable
require_once(__DIR__.'/vendor/autoload.php');
// phpcs:enable

define('DATA_TRANSFER_MODULE', 1);

use ExternalModules\AbstractExternalModule;

/**
 * Main Data Transfer external module class.
 */
class DataTransfer extends AbstractExternalModule
{
    public const ADMIN_HOME_PAGE       = 'web/admin/config.php';
    public const ADMIN_INFO_PAGE       = 'web/admin/info.php';
    public const SCHEDULE_DETAIL_PAGE  = 'web/admin/schedule_detail.php';

    public const USERS_PAGE         = 'web/admin/users.php';
    public const USER_CONFIG_PAGE   = 'web/admin/user_config.php';

    public const DATA_TRANSFER_CONFIG_PAGE = 'web/configure.php';

    public const LOG_PAGE           = 'web/admin/log.php';



    public const CONFIGURE_PAGE = 'web/configure.php';

    # Event log constants
    public const RUN_LOG_ACTION    = 'Data Transfer Export';
    public const CHANGE_LOG_ACTION = 'Data Transfer Change';
    public const LOG_EVENT         = -1;



    # Access/permission errors
    public const CSRF_ERROR                  = 1;   # (Possible) Cross-Site Request Forgery Error
    public const USER_RIGHTS_ERROR           = 2;
    public const NO_DT_PROJECT_PERMISSION    = 3;
    public const NO_CONFIGURATION_PERMISSION = 4;

    private $db;
    private $settings;

    private $changeLogAction;

    /**
     * Method called by Data Transfer cron to process scheduled data transfers.
     */
    public function cron()
    {
        $now = new \DateTime();
        $day     = $now->format('w');  // 0-6 (day of week; Sunday = 0)
        $hour    = $now->format('G');  // 0-23 (24-hour format without leading zeroes)
        $minutes = $now->format('i');
        $date    = $now->format('Y-m-d');

        if ($this->isLastRunTime($date, $hour)) {
            ; # Cron jobs for this time were already processed
        } else {
            # Set the last run time processed to this time, so that it won't be processed again
            $this->setLastRunTime($date, $hour, $minutes);

            $this->runCronJobs($day, $hour);
        }
    }

    public function runCronJobs($day, $hour)
    {
        $adminConfig = $this->getAdminConfig();

        if ($adminConfig->isAllowedCronTime($day, $hour)) {
            $cronJobs = $this->getCronJobs($day, $hour);

            foreach ($cronJobs as $cronJob) {
                try {
                    $projectId  = $cronJob['projectId'] ?? '';
                    $logParameters = ['project_id' => $projectId];

                    $configInstance = $cronJob['configInstance'];
                    if ($configInstance > $adminConfig->getMaxScheduleHours()) {
                        $message = "The maximum number of data transfers per day for a configuration"
                            . " ({$adminConfig->getMaxScheduleHours()})"
                            . " was exceeded.";
                        throw new \Exception($message);
                    }

                    $owner      = $cronJob['owner'] ?? '';
                    $configName = $cronJob['config'] ?? '';

                    $configuration = $this->getConfiguration($configName, $projectId);

                    #----------------------------------------------------------------
                    # Need to set the PROJECT_ID constant, since this is a cron
                    # job and is not associated with a project
                    #----------------------------------------------------------------
                    if (!defined('PROJECT_ID')) {
                        define('PROJECT_ID', $projectId);
                    }

                    $dataTransferer = new DataTransferer($this, $configuration);

                    $username = $owner; # For cron jobs, run as owner, since only the onwner can schedule data transfers
                    $transferType = DataTransferer::SCHEDULED_TRANSFER;

                    $dataTransferer->transferData($username, $transferType);

                    #-----------------------------------------------
                    # Send e-mail, if configured
                    #-----------------------------------------------
                    if ($configuration->getEmailSchedulingCompletions()) {
                        $owner = $configuration->getOwner();
                        if (!empty($owner)) {
                            $ownerUserObject = $this->getUser($owner);
                            if (!empty($ownerUserObject) && method_exists($ownerUserObject, 'getEmail')) {
                                $ownerEmail = $ownerUserObject->getEmail();
                                if (!empty($ownerEmail)) {
                                    $to = $ownerEmail;

                                    // phpcs:disable
                                    global $from_email;
                                    $from = $from_email;
                                    // phpcs:enable

                                    $subject = 'REDCap Data Transfer EM transfer completion';
                                    # $subject = 'REDCap Data Transfer';

                                    $sourceProject = $dataTransferer->getSourceProject();
                                    $destinationProject = $dataTransferer->getDestinationProject();

                                    $message = "The REDCap Data Transfer external module has successfully completed a"
                                        . " scheduled data transfer from project"
                                        . " {$sourceProject->getProjectIdentifier()}"
                                        . " to project {$destinationProject->getProjectIdentifier()}"
                                        . " using configuration \"{$configuration->getName()}\".\n"
                                        ;

                                    \REDCap::email($to, $from, $subject, $message);
                                }
                            }
                        }
                    }
                } catch (\Throwable $throwable) {
                    $message = "ERROR."
                        . " Data Transfer scheduled run using configuration \"{$configName}\": "
                        . $throwable->getMessage();
                    $this->log($message, $logParameters);

                    if (!empty($configuration)) {
                        if ($configuration->getEmailSchedulingErrors()) {
                            $owner = $configuration->getOwner();
                            if (!empty($owner)) {
                                $ownerUserObject = $this->getUser($owner);
                                if (!empty($ownerUserObject) && method_exists($ownerUserObject, 'getEmail')) {
                                    $ownerEmail = $ownerUserObject->getEmail();
                                    if (!empty($ownerEmail)) {
                                        $to = $ownerEmail;

                                        // phpcs:disable
                                        global $from_email;
                                        $from = $from_email;
                                        // phpcs:enable

                                        $subject = 'REDCap Data Transfer EM scheduling error';
                                        # $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

                                        \REDCap::email($to, $from, $subject, $message);
                                    }
                                }
                            }
                        }
                    } // If the configuration is not empty
                }
            }
        }
    }


    /**
     * Hook for when a record is saved; for exporting data when a record is saved
     * From the documentation: "Allows custom actions to be performed immediately
     * after a record has been saved on a data entry form or survey page."
     */
// phpcs:disable
    function redcap_save_record(
// phpcs:enable
        $projectId,
        $record,
        $instrument,
        $eventId,
        $groupId = null,
        $surveyHash = null,
        $responseIid = null,
        $repeatInstance = 1
    ) {
        try {
            $logFile = __DIR__ . '/record-save.log';

            $sourceForm = '';

            $configurations = $this->getConfigurations($projectId);

            if (!empty($configurations)) {
                $formConfigs = $configurations->getExportOnFormSaveConfigurations($this, $instrument, $eventId);

                foreach ($formConfigs->getConfigurationMap() as $configName => $configuration) {
                    $sourceProject = $configuration->getSourceProject($this);
                    $uniqueEventName = null;

                    if ($sourceProject->isLongitudinal()) {
                        $uniqueEventName = $sourceProject->getUniqueEventNameFromEventId($eventId);
                    }

                    $dataTransferer = new DataTransferer($this, $configuration);
                    $username   = $configuration->getOwner();
                    $sourceForm = $instrument;
                    $sourceEvent = $uniqueEventName;

                    $dataTransferer->transferData(
                        $username,
                        DataTransferer::EXPORT_ON_FORM_SAVE_TRANSFER,
                        $record,
                        $sourceForm,
                        $sourceEvent
                    );

                    # echo "<script>alert('This is a test.');</script>";
                }
            }
        } catch (\Throwable $throwable) {
            # E-mail export on form save data transfer errors to owner of configuration
            # (might want to set configuration to inactive if owner is no longer a REDCap user???)
            if (!empty($configuration)) {
                $owner = $configuration->getOwner();
                if (!empty($owner)) {
                    $ownerUserObject = $this->getUser($owner);
                    if (!empty($ownerUserObject) && method_exists($ownerUserObject, 'getEmail')) {
                        $ownerEmail = $ownerUserObject->getEmail();
                        if (!empty($ownerEmail)) {
                            $subject = 'REDCap Data Transfer external module transfer on form save error';
                            $message = 'An error occurred when trying to transfer data on form save';

                            if (!empty($sourceForm)) {
                                $message .= ' for form "' . $sourceForm . '"';
                            }

                            $message .= ' for project';
                            if (!empty($sourceProject) && method_exists($sourceProject, 'getTitle')) {
                                $message .= ' "' . $sourceProject->getTitle() . '"';
                            }
                            $message .= '(pid = ' . $projectId . ')';
                            $message .=  ".\n";

                            $message .= "\nERROR: {$throwable->getMessage()}\n";
                        }

                        $to = $ownerEmail;

                        // phpcs:disable
                        global $from_email;
                        $from = $from_email;
                        // phpcs:enable

                        \REDCap::email($to, $from, $subject, $message);
                        //  [, string $cc [, string $bcc [, string $fromName [, array $attachments ]]]] )
                    }
                }
            }
        }
    }

// phpcs:disable
    function redcap_data_entry_form_top(
// phpcs:enable
        $projectId,
        $record,
        $instrument,
        $eventId,
        $groupId,
        $repeatInstance
    ) {
        $configurations = $this->getConfigurations($projectId);

        if (!empty($configurations)) {
            $formConfigs = $configurations->getExportOnFormSaveConfigurations($this, $instrument, $eventId);

            if (!empty($formConfigs) && !empty($formConfigs->getConfigurationMap())) {
                include_once(__DIR__ . '/web/export_on_form_include.php');
            }
        }

        # Only list button if there is at least one import configuration
        # that specifies the current instrument
        ?>
        <script>
            //$(document).ready(function() {
                //alert('test123');
                // Add the data pull button
                //$('table#questiontable>tbody').prepend(
                //    '<tr style="border-top: 1px solid #DDDDDD;">'
                //    + '<td style="text-align: center; padding: 6px;" colspan="2">'
                //    + '<button id="dataTransferFormImport">Import data</button> using data transfer configuration: '
                //    + '<select>'
                //    + '</select>'
                //    + '</td>'
                //    + '</tr>'
                //    + '<tr>'
                //    + "<td>Form</td><td><?php echo $instrument; ?></td>"
                //    + '<tr>'
                //    + '</tr>'
                //    + "<td>Event</td><td><?php echo $eventId;?></td>"
                //    + '</tr>'
                //);
            //});
        </script>
        <?php
        # echo '<div>Import data from project: <button>import</button></div>' . "\n";
    }

    /**
     * Returns REDCap user rights for the current project.
     *
     * @return array a map from username to an map of rights for the current project.
     */
    public function getUserRights()
    {
        $userId = USERID;
        $rights = \REDCap::getUserRights($userId)[$userId];
        return $rights;
    }


    /**
     * Gets the external module's version number.
     */
    public function getVersion()
    {
        return $this->getSettings()->getVersion();
    }

    /**
     * Gets the settings for this module and initializes settings if they are unset.
     * This method should always be called (outside of this method) instead of
     * accessing settings directly.
     *
     * @return Settings the settings for this module.
     */
    public function getSettings()
    {
        if (!isset($this->settings)) {
            $this->db = new RedCapDb($this);
            $this->settings = new Settings($this, $this->db);
        }

        return $this->settings;
    }

    /**
     * Gets the db (database connection) for this module and initializes db if it is unset.
     * This method should always be called (outside of this method) instead of
     * accessing db directly.
     *
     * @return Settings the settings for this module.
     */
    public function getDb()
    {
        if (!isset($this->db)) {
            $this->db = new RedCapDb($this);
        }

        return $this->db;
    }


    public function isSuperUser($username = USERID)
    {
        $isSuperUser = false;
        $user = $this->getUser($username);

        if ($user === null) {
            $isSuperUser = false;
        } else {
            $isSuperUser = $user->isSuperUser();
        }

        return $isSuperUser;
    }


    #-------------------------------------------------------------------
    # Configuration methods
    #-------------------------------------------------------------------

    /**
     * Gets the specified configuration for the current user.
     *
     * @param string $name the name of the configuration to get.
     * @return Configuration the specified configuration.
     */
    public function getConfiguration($name, $projectId = PROJECT_ID)
    {
        return $this->getSettings()->getConfiguration($name, $projectId);
    }

    /**
     * @param Configuration $configuration
     * @param string $username
     * @param string $projectId
     */
    public function setConfiguration($configuration, $username = USERID, $projectId = PROJECT_ID)
    {
        $this->getSettings()->setConfiguration($configuration, $username, $projectId);
        $details = 'Data Transfer configuration "' . $configuration->getName() . '"'
            . ' updated.';
        # \REDCap::logEvent(self::CHANGE_LOG_ACTION, $details, null, null, self::LOG_EVENT);

        $parameters = [];
        $this->log($details);
    }

    public function addConfiguration($name, $username = USERID, $projectId = PROJECT_ID)
    {
        $this->getSettings()->addConfiguration($name, $username, $projectId);
        $details = 'Data Transfer configuration "' . $name . '" created.';

        # \REDCap::logEvent(self::CHANGE_LOG_ACTION, $details, null, null, self::LOG_EVENT);
        $this->log($details);
    }


    /**
     * Copy configuration (only supports copying from/to same
     * user and project).
     */
    public function copyConfiguration($fromConfigName, $toConfigName, $username = USERID, $projectUsers = [])
    {
        $this->getSettings()->copyConfiguration($fromConfigName, $toConfigName, $username, $projectUsers);
        $details = 'Data Transfer configuration "' . $fromConfigName . '" copied to "'
            . $toConfigName . '".';

        # \REDCap::logEvent(self::CHANGE_LOG_ACTION, $details, null, null, self::LOG_EVENT);
        $this->log($details);
    }

    /**
     * Renames configuration (only supports rename from/to same project).
     *
     * @param string $configName the name of the configuration to be renamed.
     * @param string $newConfigName the new name for the configuration being renamed.
     */
    public function renameConfiguration($configName, $newConfigName, $username = USERID, $projectUsers = [])
    {
        $this->getSettings()->renameConfiguration($configName, $newConfigName, $username, $projectUsers);
        $details = 'Data Transfer configuration "' . $configName . '" renamed to "'
            . $newConfigName . '".';

        # \REDCap::logEvent(self::CHANGE_LOG_ACTION, $details, null, null, self::LOG_EVENT);
        $this->log($details);
    }

    public function deleteConfiguration($configName, $username = USERID, $projectUsers = [])
    {
        $this->getSettings()->deleteConfiguration($configName, $username, $projectUsers);
        $details = 'Data Transfer onfiguration "' . $configName . '" deleted.';

        # \REDCap::logEvent(self::CHANGE_LOG_ACTION, $details, null, null, self::LOG_EVENT);
        $this->log($details);
    }

    public function getArmEventFormInfo($projectId = PROJECT_ID)
    {
        $info = $this->getDb()->getArmEventFormInfo($projectId);
        return $info;
    }


    #==================================================================================
    # Project methods
    #==================================================================================

    public function getProjectInfo($projectId = PROJECT_ID)
    {
        $proj = new \Project($projectId);
        $projectInfo = $this->getDb()->getProjectInfo($projectId);
        $projectInfo['has_repeating_instruments_or_events'] = $proj->hasRepeatingFormsEvents();

        return $projectInfo;
    }

    public function getProjectUserRights($projectId = PROJECT_ID, $username = null)
    {
        $proj = new \Project($projectId);
        $userRights = $this->getDb()->getProjectUserRights($projectId, $username);

        return $userRights;
    }

    public function getArms($projectId = PROJECT_ID)
    {
        $arms = $this->getDb()->getArms($projectId);
        return $arms;
    }

    public function getEvents($projectId = PROJECT_ID)
    {
        $events = $this->getDb()->getEvents($projectId);
        return $events;
    }

    public function deleteFile($projectId, $recordId, $field, $event = null, $instance = null)
    {
        $status = $this->getDb()->deleteFile($projectId, $recordId, $field, $event, $instance);
        return $status;
    }

    #==================================================================================
    # User methods
    #==================================================================================

    public function getUserProjects($username = USERID)
    {
        $userProjects = $this->getDb()->getUserProjects($username);
        return $userProjects;
    }


    #==================================================================================
    # Configurations methods
    #==================================================================================
    public function getConfigurations($projectId = PROJECT_ID)
    {
        $configurations = $this->getSettings()->getConfigurations($projectId);
        return $configurations;
    }

    public function getConfigurationNames($projectId = PROJECT_ID)
    {
        $configurationNames = $this->getSettings()->getConfigurationNames($projectId);
        return $configurationNames;
    }

    /**
     * Render sub-tabs for the admin Data Transfer configuration pages.
     */
    public function renderConfigureSubTabs($activeUrl = '')
    {
        $configureUrl = $this->getUrl(self::CONFIGURE_PAGE);
        $configureLabel = '<span class="fas fa-file-import"></span>'
           . ' Transfer Project';

        $transferOptionsUrl = $this->getUrl('web/transfer_options.php');
        $transferOptionsLabel = '<span class="fas fa-file-lines"></span>'
           . ' Transfer Options';

        $fieldMapUrl = $this->getUrl('web/field_map.php');
        $fieldMapLabel = '<span class="fas fa-map"></span>'
           . ' Field Map';

        $dagMapUrl = $this->getUrl('web/dag_map.php');
        $dagMapLabel = '<span class="fas fa-map"></span>'
           . ' DAG Map';

        $diffMapUrl = $this->getUrl('web/diff.php');
        $diffMapLabel = '<span class="fas fa-not-equal"></span>'
           . ' Project Comparison';

        # $manualTransferMapUrl = $this->getUrl('web/transfer.php');
        # $manualTransferMapLabel = '<span class="fas fa-arrow-right-arrow-left"></span>'
        #    . ' Manual Transfer';

        $tabs = array();

        $tabs[$configureUrl]         = $configureLabel;
        $tabs[$transferOptionsUrl]   = $transferOptionsLabel;
        $tabs[$fieldMapUrl]          = $fieldMapLabel;
        $tabs[$dagMapUrl]            = $dagMapLabel;
        $tabs[$diffMapUrl]           = $diffMapLabel;
        # $tabs[$manualTransferMapUrl] = $manualTransferMapLabel;

        $this->renderSubTabs($tabs, $activeUrl);
    }


    /**
     * Renders the top-level tabs for the user interface.
     */
    public function renderUserTabs($activeUrl = '')
    {
        $listUrl = $this->getUrl('web/index.php');
        $listLabel = '<span class="fas fa-list"></span>'
           . ' Data Transfer Configurations';

        $configUrl = $this->getUrl(self::CONFIGURE_PAGE);
        $configLabel = '<span style="color: #808080;" class="fas fa-cog"></span>'
           . ' Configure';

        $manualTransferUrl = $this->getUrl('web/manual_transfer.php');
        $manualTransferLabel =
            '<i class="fas fa-arrow-right-arrow-left"></i>'
            . ' Manual Transfer';

        $scheduleUrl = $this->getUrl('web/schedule.php');
        $scheduleLabel =
            '<i class="fas fa-clock"></i>'
            . ' Schedule';

        $userManualUrl = $this->getUrl('web/user_manual.php');
        $userManualLabel =
            '<i class="fas fa-book"></i>'
            . ' User Manual';


        # Map for tabs from URL to the label for the URL
        $tabs = array();

        $tabs[$listUrl]           = $listLabel;
        $tabs[$configUrl]         = $configLabel;
        $tabs[$manualTransferUrl] = $manualTransferLabel;
        $tabs[$scheduleUrl]       = $scheduleLabel;
        $tabs[$userManualUrl]     = $userManualLabel;

        $this->renderTabs($tabs, $activeUrl);
    }


    /**
     * Renders tabs using built-in REDCap styles.
     *
     * @param array $tabs map from URL to tab label.
     * @param string $activeUrl the URL that should be marked as active.
     */
    public function renderTabs($tabs = array(), $activeUrl = '')
    {
        echo '<div id="sub-nav" style="margin:5px 0 20px;">' . "\n";
        echo '<ul>' . "\n";
        foreach ($tabs as $tabUrl => $tabLabel) {
            // Check for Enabled tab
            $isEnabled = false;
            $class = '';
            if (strcasecmp($tabUrl, $activeUrl) === 0) {
                $class = ' class="active"';
                $isEnabled = true;
            }
            echo '<li ' . $class . '>' . "\n";
            # Note: URLs created with the getUrl method, so they should already be escaped
            echo '<a href="' . $tabUrl . '" style="font-size:13px;color:#393733;padding:6px 9px 5px 10px;">';
            # Note: labels are static values in code, and not based on user input
            echo $tabLabel . '</a>' . "\n";
        }
        echo '</li>' . "\n";
        echo '</ul>' . "\n";
        echo '</div>' . "\n";
        echo '<div class="clear"></div>' . "\n";
    }

    /**
     * Renders sub-tabs (second-level tabs) on the page.
     *
     * @param array $tabs map from URL to tab label.
     * @param string $activeUrl the URL that should be marked as active.
     */
    public function renderSubTabs($tabs = array(), $activeUrl = '')
    {
        //echo '<div style="text-align:right; margin-bottom: 17px; margin-top: 0px;">';
        echo '<div style="text-align: left; margin-bottom: 27px; margin-top: 0px; padding-top: 0px;">';
        $isFirst = true;
        foreach ($tabs as $url => $label) {
            $style = '';
            if (strcasecmp($url, $activeUrl) === 0) {
                $style = ' style="padding: 1px; text-decoration: none; border-bottom: 2px solid black;" ';
            } else {
                $style = ' style="padding: 1px; text-decoration: none;" ';
            }

            if ($isFirst) {
                $isFirst = false;
            } else {
                echo "&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;";
            }
            echo '<a href="' . $url . '" ' . $style . '>' . "{$label}</a>";
        }
        // echo "&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "<hr/>\n";
        echo "</div>\n";
    }


    public function renderSuccessMessageDiv($message)
    {
        if (!empty($message)) {
            echo '<div align="center" class="darkgreen" style="margin: 20px 0;">' . "\n";
            echo '<img src="' . (APP_PATH_IMAGES . 'accept.png') . '" alt="">';
            echo '&nbsp;' . Filter::escapeForHtml($message) . "\n";
            echo "</div>\n";
        }
    }

    public function renderWarningMessageDiv($message)
    {
        if (!empty($message)) {
            echo '<div align="center" class="yellow" style="margin: 20px 0;">' . "\n";
            echo '<img src="' . (APP_PATH_IMAGES . 'warning.png') . '"  alt="" width="16px">';
            echo '&nbsp;' . Filter::escapeForHtml($message) . "\n";
            echo "</div>\n";
        }
    }

    public function renderErrorMessageDiv($message)
    {
        if (!empty($message)) {
            echo '<div align="center" class="red" style="margin: 20px 0;">' . "\n";
            echo '<img src="' . (APP_PATH_IMAGES . 'exclamation.png') . '" alt="">';
            echo '&nbsp;' . Filter::escapeForHtml($message) . "\n";
            echo "</div>\n";
        }
    }


    public function renderAdminPageContentHeader($selfUrl, $errorMessage, $warningMessage, $successMessage)
    {
        $this->renderAdminTabs($selfUrl);
        $this->renderErrorMessageDiv($errorMessage);
        $this->renderWarningMessageDiv($warningMessage);
        $this->renderSuccessMessageDiv($successMessage);
    }

    /**
     * Renders the page content tabs for REDCap-ETL admin pages.
     */
    public function renderAdminTabs($activeUrl = '')
    {

        $infoUrl = $this->getUrl(self::ADMIN_INFO_PAGE);
        $infoLabel = '&nbsp;<span class="fa fa-info-circle"></span>&nbsp;Info';

        $adminUrl = $this->getUrl(self::ADMIN_HOME_PAGE);
        $adminLabel = '<span class="fas fa-cog"></span>'
           . ' Config';

        $scheduleDetailUrl = $this->getUrl(self::SCHEDULE_DETAIL_PAGE);
        $scheduleDetailLabel = '<span class="fas fa-clock"></span>'
           . ' Schedule Detail';

        # $usersUrl = $this->getUrl(self::USERS_PAGE);
        # $usersLabel = '<span class="fas fa-user"></span>'
        #    . ' Users</span>';

        # $logUrl = $this->getUrl(self::LOG_PAGE);
        # $logLabel = '<span class="fas fa-file-alt"></span>'
        #    . ' Log';

        $tabs = array();

        $tabs[$infoUrl]           = $infoLabel;
        $tabs[$adminUrl]          = $adminLabel;
        $tabs[$scheduleDetailUrl] = $scheduleDetailLabel;

        # $tabs[$usersUrl]         = $usersLabel;
        # $tabs[$logUrl]  = $logLabel;

        $this->renderTabs($tabs, $activeUrl);
    }



    /**
     * Renders the page content header for a project page.
     *
     * @param string $selfUrl the URL of the page where the content header is to rendered.
     * @param string $errorMessage the error message to print (if any).
     * @param string $successMessage the success message to print (if any).
     */
    public function renderProjectPageContentHeader($selfUrl, $errorMessage, $warningMessage, $successMessage)
    {
        $this->renderUserTabs($selfUrl);
        $this->renderErrorMessageDiv($errorMessage);
        $this->renderWarningMessageDiv($warningMessage);
        $this->renderSuccessMessageDiv($successMessage);
    }

    public function renderProjectPageConfigureContentHeader(
        $tabUrl,
        $subtabUrl,
        $errorMessage,
        $warningMessage,
        $successMessage
    ) {
        $this->renderUserTabs($tabUrl);
        $this->renderConfigureSubTabs($subtabUrl);
        $this->renderErrorMessageDiv($errorMessage);
        $this->renderWarningMessageDiv($warningMessage);
        $this->renderSuccessMessageDiv($successMessage);
    }


    public function renderMessages($errorMessage, $warningMessage, $successMessage)
    {
        $this->renderErrorMessageDiv($errorMessage);
        $this->renderWarningMessageDiv($warningMessage);
        $this->renderSuccessMessageDiv($successMessage);
    }


    /**
     * Checks admin page access and exits if there is an issue.
     */
    public function checkAdminPagePermission()
    {
        if (!$this->isSuperUser()) {
            exit("Only super users can access this page!");
        }
    }


    /**
     * Checks if the user has permission to a user (non-admin) page, and
     * returns the configuration corresponding to the configuration name in
     * the request, if any. If the user does NOT have permission, the
     * request will be routed to an error page.
     *
     * @return Configuration if a configuration name was specified in the request, the
     *     configuration for that configuration name.
     */
    public function checkUserPagePermission(
        $username = USERID,
        $configCheck = false,
        $runCheck = false,
        $scheduleCheck = false
    ) {
        $configuration = null;

        #if (!Csrf::isValidRequest()) {
        #    # CSRF (Cross-Site Request Forgery) check failed; this should mean that either the
        #    # request is a CSRF attack or the user's session expired
        #    $accessUrl = $this->getUrl('web/access.php?accessError=' . self::CSRF_ERROR);
        #    header('Location: ' . $accessUrl);
        #    exit();
        #} else
        if (!Authorization::hasRedCapUserRightsForDataTransfer($this)) {
            # User does not have REDCap user rights to use Data Transfer for this project
            $accessUrl = $this->getUrl('web/access.php?accessError=' . self::USER_RIGHTS_ERROR);
            header('Location: ' . $accessUrl);
            exit();
        }

        return $configuration;
    }

    public function getRequestVar($name, $filterFunction)
    {
        $var = call_user_func($filterFunction, $_POST[$name]);
        if (empty($var)) {
            $var = call_user_func($filterFunction, $_GET[$name]);
        }

        return $var;
    }

    /**
     * Gets the named request variable by checking values in the following order: POST, GET, SESSION.
     * In addition, if a value for the variable is found, then that value is saved to
     * the user's SESSION.
     *
     * @param string $name the name of the variable to retrieve.
     * @param function $filterFunction the function used to filter the variables value.
     */
    public function getRequestSessionVar($name, $filterFunction)
    {
        $var = '';

        if (array_key_exists($name, $_POST)) {
            $var = call_user_func($filterFunction, $_POST[$name]);
        } elseif (array_key_exists($name, $_GET)) {
            $var = call_user_func($filterFunction, $_GET[$name]);
        } elseif (array_key_exists($name, $_SESSION)) {
            $var = call_user_func($filterFunction, $_SESSION[$name]);
        }

        if (!empty($var)) {
            $_SESSION[$name] = $var;
        }

        return $var;
    }

    #-------------------------------------------------------------------
    # Cron job methods
    #-------------------------------------------------------------------

    /**
     * Gets all the cron jobs (for all users and all projects).
     */
    public function getAllCronJobs()
    {
        return $this->getSettings()->getAllCronJobs();
    }

    public function getCronJobs($day, $time)
    {
         return $this->getSettings()->getCronJobs($day, $time);
    }


    #==================================================================================
    # Last run time methods
    #==================================================================================

    public function getLastRunTime()
    {
        return $this->getSettings()->getLastRunTime();
    }

    public function setLastRunTime($date, $hour, $minutes)
    {
        $this->getSettings()->setLastRunTime($date, $hour, $minutes);
        # Don't log this because it is an internal event
    }

    public function isLastRunTime($date, $hour)
    {
        return $this->getSettings()->isLastRunTime($date, $hour);
    }


    #==================================================================================
    # Admin Config methods
    #==================================================================================

    public function getAdminConfig()
    {
        return $this->getSettings()->getAdminConfig();
    }

    public function setAdminConfig($adminConfig)
    {
        $this->getSettings()->setAdminConfig($adminConfig);
        $details = 'REDCap-ETL admin configuration "' . $configName . '" modified.';
        \REDCap::logEvent(self::CHANGE_LOG_ACTION, $details, null, null, self::LOG_EVENT);
    }
}
