<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

/**
 * Class for managing the storage and retrieval of external module settings stored in the REDCap database.
 */
class Settings
{
    public const CONFIGURATIONS_KEY       = 'configurations';    # project key

    public const ADMIN_CONFIG_KEY         = 'admin-config';

    public const PROJECT_INFO_KEY         = 'project-info';

    public const LAST_RUN_TIME_KEY        = 'last-run-time'; // for storing day and time of last run


    public const VERSION_KEY = 'version';


    private $module;

    /** @var RedCapDb $db REDCap database object. */
    private $db;

    public function __construct($module, $db)
    {
        $this->module = $module;
        $this->db     = $db;
    }

    /**
     * Gets the external module version number.
     */
    public function getVersion()
    {
        $version = $this->module->getSystemSetting(self::VERSION_KEY);
        return $version;
    }


    #----------------------------------------------------------
    # ProjectInfo settings methods
    #----------------------------------------------------------

    /*
    public function getProjectInfo($projectId = PROJECT_ID)
    {
        $key = self::PROJECT_INFO_KEY;
        $json = $this->module->getProjectSetting($key, $projectId);
        $projectInfo = new ProjectInfo();
        $projectInfo->fromJson($json);
        return $projectInfo;
    }
     */

    #-------------------------------------------------------------------
    # Admin Config methods
    #-------------------------------------------------------------------
    public function getAdminConfig()
    {
        $adminConfig = new AdminConfig();
        $setting = $this->module->getSystemSetting(self::ADMIN_CONFIG_KEY);
        $adminConfig->fromJson($setting);
        return $adminConfig;
    }

    public function setAdminConfig($adminConfig)
    {
        $json = $adminConfig->toJson();
        $this->module->setSystemSetting(self::ADMIN_CONFIG_KEY, $json);
    }


    #-------------------------------------------------------------------
    # Configurations methods
    #-------------------------------------------------------------------

    public function getConfigurations($projectId = PROJECT_ID)
    {
        $configurations = new Configurations();

        if (!empty($projectId)) {
            # $phpSerialization = $this->module->getProjectSetting(self::CONFIGURATIONS_KEY, $projectId);
            # if (!empty($phpSerialization)) {
            #     $configurations = $this->getConfigurationsFromSerialization($projectId, $phpSerialization);
            # }

            $json = $this->module->getProjectSetting(self::CONFIGURATIONS_KEY, $projectId);
            if (!empty($json)) {
                $configurations = $this->getConfigurationsFromJson($projectId, $json);
            }
        }

        return $configurations;
    }

    public function getConfigurationsFromJson($projectId, $json)
    {
        $configurations = new Configurations();
        $configurations->setFromJson($json);

        #-------------------------------------------------------------------
        # If the current project ID doesn't match the saved configurations
        # project ID, it indicates that this is a copied project, so:.
        #
        # - reset configuration project IDs
        #-------------------------------------------------------------------
        $pidUpdated = false;
        foreach ($configurations->getConfigurationMap() as $name => $configuration) {
            if ($configuration->getConfigProjectId() !== $projectId) {
                $configuration->setConfigProjectId($projectId);
                $pidUpdated = true;
            }
        }

        if ($pidUpdated) {
            $this->setConfigurations($configurations, $projectId);
        }

        return $configurations;
    }

#    public function getConfigurationsFromSerialization($projectId, $phpSerialization)
#    {
#        $configurations = unserialize($phpSerialization);
#
#        #-------------------------------------------------------------------
#        # If the current project ID doesn't match the saved configurations
#        # project ID, it indicates that this is a copied project, so:.
#        #
#        # - reset configuration project IDs
#        #-------------------------------------------------------------------
#        $pidUpdated = false;
#        foreach ($configurations->getConfigurationMap() as $name => $configuration) {
#            if ($configuration->getConfigProjectId() !== $projectId) {
#                $configuration->setConfigProjectId($projectId);
#                $pidUpdated = true;
#            }
#        }
#
#        if ($pidUpdated) {
#            $this->setConfigurations($configurations, $projectId);
#        }
#
#        return $configurations;
#    }

    public function getConfigurationNames($projectId = PROJECT_ID)
    {
        $configurationNames = array();
        $configurations = new Configurations();

        if (!empty($projectId)) {
            # $phpSerialization = $this->module->getProjectSetting(self::CONFIGURATIONS_KEY, $projectId);
            $configsJson = $this->module->getProjectSetting(self::CONFIGURATIONS_KEY, $projectId);
            #if (!empty($phpSerialization)) {
            if (!empty($configsJson)) {
                # $configurations = unserialize($phpSerialization);
                $configurations->setFromJson($configsJson);
                $configurationNames = $configurations->getConfigurationNames();
            }
        }

        return $configurationNames;
    }

    public function setConfigurations($configurations, $projectId = PROJECT_ID)
    {
        # $phpSerialization = serialize($configurations);
        # $this->module->setProjectSetting(self::CONFIGURATIONS_KEY, $phpSerialization, $projectId);
        $json = $configurations->toJson();
        $this->module->setProjectSetting(self::CONFIGURATIONS_KEY, $json, $projectId);
    }

    public function getConfiguration($name, $projectId = PROJECT_ID)
    {
        $configurations = $this->getConfigurations($projectId);
        $configuration = $configurations->getConfiguration($name);

        return $configuration;
    }

    public function setConfiguration($configuration, $username = USER_ID, $projectId = PROJECT_ID)
    {
        # NOTE: check now in configuration class
        # if ($username !== $configuration->getOwner()) {
        #     $message = "User \"{$username}\" does not have permission"
        #         . " to modify configuration \"{$configuration->getName()}\".";
        #     throw new \Exception($message);
        # }

        $this->db->startTransaction();

        try {
            $configurations = $this->getConfigurations($projectId);

            $projectUsers = \REDCap::getUsers();
            $isSuperUser = $this->module->isSuperUser($username);

            $configurations->setConfiguration(
                $configuration->getName(),
                $configuration,
                $username,
                $projectUsers,
                $isSuperUser
            );

            $this->setConfigurations($configurations, $projectId);

            $commit = true;
            $this->db->endTransaction($commit);
        } catch (\Exception $exception) {
            $commit = false;
            $this->db->endTransaction($commit);
            throw $exception;
        }
    }

    public function addConfiguration($name, $username = USER_ID, $projectId = PROJECT_ID)
    {
        $this->db->startTransaction();

        try {
            $configurations = $this->getConfigurations($projectId);

            $configuration = new Configuration();
            $configuration->setName($name);
            $configuration->setOwner($username);

            $configurations->addConfiguration($name, $configuration);
            $this->setConfigurations($configurations, $projectId);

            $commit = true;
            $this->db->endTransaction($commit);
        } catch (\Exception $exception) {
            $commit = false;
            $this->db->endTransaction($commit);
            throw $exception;
        }
    }



    /**
     * Copy configuration (only supports copying from/to same
     * user and project).
     */
    public function copyConfiguration($fromConfigName, $toConfigName, $username, $projectUsers, $transaction = true)
    {
        $commit = true;
        $errorMessage = '';

        if ($transaction) {
            $this->db->startTransaction();
        }

        try {
            $configurations = $this->getConfigurations();
            $configurations->copyConfiguration($fromConfigName, $toConfigName, $username, $projectUsers);
            $this->setConfigurations($configurations);
        } catch (\Exception $exception) {
            $commit = false;
            $this->db->endTransaction($commit);
            throw $exception;
        }

        if ($transaction) {
            $this->db->endTransaction($commit);
        }
    }

    /**
     * Rename configuration (only supports rename from/to same
     * user and project).
     */
    public function renameConfiguration($configName, $newConfigName, $username, $projectUsers, $transaction = true)
    {
        $commit = true;
        $errorMessage = '';

        if ($transaction) {
            $this->db->startTransaction();
        }

        try {
            $configurations = $this->getConfigurations();
            $isSuperUser = $this->module->isSuperUser();
            $configurations->renameConfiguration($configName, $newConfigName, $username, $projectUsers, $isSuperUser);
            $this->setConfigurations($configurations);
        } catch (\Exception $exception) {
            $commit = false;
            $this->db->endTransaction($commit);
            throw $exception;
        }

        if ($transaction) {
            $this->db->endTransaction($commit);
        }
    }

    public function deleteConfiguration($configName, $username, $projectUsers, $transaction = true)
    {
        $commit = true;
        $errorMessage = '';

        if ($transaction) {
            $this->db->startTransaction();
        }

        $configurations = $this->getConfigurations();

        $isSuperUser = $this->module->isSuperUser();

        $configurations->deleteConfiguration($configName, $username, $projectUsers, $isSuperUser);
        $this->setConfigurations($configurations);

        if ($transaction) {
            $this->db->endTransaction($commit);
        }
    }


    #-------------------------------------------------------------------
    # Cron job methods
    #-------------------------------------------------------------------

    /**
     * Gets the cron jobs for the specified day (0 = Sunday, 1 = Monday, ...)
     * and time (0 = 12am - 1am, 1 = 1am - 2am, ..., 23 = 11pm - 12am).
     */
    public function getCronJobs($day, $time, $transaction = true)
    {
        $commit = true;
        $errorMessage = '';

        if ($transaction) {
            $this->db->startTransaction();
        }

        $cronJobs = array();

        $allCronJobs = $this->getAllCronJobs($transaction);

        $cronJobs = $allCronJobs[$day][$time] ?? [];

        if ($transaction) {
            $this->db->endTransaction($commit);
        }

        return $cronJobs;
    }


    /**
     * Gets all the cron jobs (for all users and all projects).
     *
     * @return array Array where the first dimension is the day (0 = Sunday, 1 = Monday, etc.)
     *     and the second dimension is the time/hour (0 = 12:00am to 1:00am, 1 = 1:00am to 2:00am, etc.).
     *     The elements of the 2-dimenaional array are maps with the following keys: 'owner', 'projectId',
     *     'config' (which is the configuration name) and 'configInstance' (which is the instance number
     *     for a config for a project ID and day, starting at 1 for the first instance).
     *     The config instance is used to check for the maximum number of data transfers for a configuration
     *     being exceeded for a day.
     */
    public function getAllCronJobs($transaction = true)
    {
        $commit = true;
        $errorMessage = '';

        if ($transaction) {
            $this->db->startTransaction();
        }

        $cronJobs = array();
        foreach (range(0, 6) as $day) {
            $cronJobs[$day] = array();
            foreach (range(0, 23) as $hour) {
                $cronJobs[$day][$hour] = array();
            }
        }

        # Get all Data Transfer configuration settings
        $allDataTransferConfigSettings = $this->db->getDataTransferConfigurationsSettings($this->module);

        foreach ($allDataTransferConfigSettings as $dataTransferConfigSettings) {
            $projectId  = $dataTransferConfigSettings['project_id'];
            # $configPhpSerialization = $dataTransferConfigSettings['value'];
            $configJson = $dataTransferConfigSettings['value'];

            #$configurations = $this->getConfigurationsFromSerialization($projectId, $configPhpSerialization);
            $configurations = $this->getConfigurationsFromJson($projectId, $configJson);

            foreach ($configurations->getConfigurationMap() as $configName => $config) {
                if (isset($config)) {
                    $schedule  = $config->getSchedule();

                    for ($day = 0; $day < 7; $day++) {
                        $hours = $schedule[$day];

                        # Keep track of the instance of each config for each project ID for this day
                        $dayConfigInstance = [];

                        if (!empty($hours) && is_array($hours)) {
                            foreach ($hours as $hour) {
                                if (!array_key_exists($projectId, $dayConfigInstance)) {
                                    $dayConfigInstance[$projectId][$configName] = 1;
                                } elseif (!array_key_exists($configName, $dayConfigInstance[$projectId])) {
                                    $dayConfigInstance[$projectId][$configName] = 1;
                                } else {
                                    $dayConfigInstance[$projectId][$configName]++;
                                }
                                $configInstance = $dayConfigInstance[$projectId][$configName];

                                $cronJob = array(
                                    'owner'          => $config->getOwner(),
                                    'projectId'      => $projectId,
                                    'config'         => $configName,
                                    'configInstance' => $configInstance
                                );
                                array_push($cronJobs[$day][$hour], $cronJob);
                            }
                        }
                    }
                }
            }
        }

        if ($transaction) {
            $this->db->endTransaction($commit);
        }

        return $cronJobs;
    }



    #-------------------------------------------------------------------
    # Last run time methods
    #-------------------------------------------------------------------

    /**
     * Gets the last time that the REDCap-ETL cron jobs were run
     */
    public function getLastRunTime()
    {
        $lastRunTime = null;
        $dateAndTime = $this->module->getSystemSetting(self::LAST_RUN_TIME_KEY);
        if (!empty($dateAndTime)) {
            $lastRunTime = explode(',', $dateAndTime);
        }
        return $lastRunTime;
    }

    public function setLastRunTime($date, $hour, $minutes)
    {
        $lastRunTime = $date . ',' . $hour . ',' . $minutes;
        $this->module->setSystemSetting(self::LAST_RUN_TIME_KEY, $lastRunTime);
    }

    public function isLastRunTime($date, $hour)
    {
        $isLast = false;

        $lastRunTime = $this->getLastRunTime();

        if ($lastRunTime === null) {
            $isLast = false;
        } elseif ($lastRunTime[0] == $date && $lastRunTime[1] == $hour) {
            $isLast = true;
        }

        return $isLast;
    }


    #----------------------------------------------
    # Test method
    #----------------------------------------------

    public static function test()
    {
        return true;
    }
}
