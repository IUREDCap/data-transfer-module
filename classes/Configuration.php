<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use IU\PHPCap\RedCapProject;

/**
 * Class for data transfer configuration
 */
class Configuration
{
    public const DIRECTION_IMPORT = 'import';
    public const DIRECTION_EXPORT = 'export';

    public const LOCATION_LOCAL = 'local';
    public const LOCATION_REMOTE = 'remote';

    public const SUNDAY    = 'Sunday';
    public const MONDAY    = 'Monday';
    public const TUESDAY   = 'Tuesday';
    public const WEDNESDAY = 'Wednesday';
    public const THURSDAY  = 'Thursday';
    public const FRIDAY    = 'Friday';
    public const SATURDAY  = 'Saturday';

    public const DAY_LABELS = [
        self::SUNDAY, self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::THURSDAY, self::FRIDAY, self::SATURDAY
    ];

    private $name;

    private $owner; // Username of person who created the configuration
                    // (can only be changed by the owner or by an admin)

    public const CONFIG_PROJECT_ID = 'configProjectId';
    private $configProjectId; // ID of the project that contains the configuration

    public const IS_ENABLED = 'isEnabled';
    private $isEnabled;

    public const DIRECTION = 'direction';
    private $direction;

    public const LOCATION = 'location';
    private $location;

    // The project ID for the transfer project
    public const TRANSFER_PROJECT_ID = 'projectId';
    private $projectId; // If local project

    // API information for the transfer project
    public const API_URL = 'apiUrl';
    private $apiUrl;  // If remote project

    public const API_TOKEN = 'apiToken';
    private $apiToken;  // If remote project

    public const API_TOKEN_MASK = "********************************";


    private $recordLogic;

    // Record ID source project value to match destination project record ID
    public const MATCH_RECORD_ID    = 'matchRecordId';
    public const MATCH_SECONDARY_ID = 'matchSecondaryId';
    public const MATCH_LOGIC        = 'matchLogic';

    public const RECORD_MATCH = 'recordMatch';
    private $recordMatch;

    public const UPDATE_RECORDS = 'updateRecords';
    /** @var boolean indicates if existing records should be updated. */
    private $updateRecords;


    // Record creation
    public const RECORD_CREATION = 'recordCreation';
    private $recordCreation;

    public const ADD_RECORDS_AND_INSTANCES = 'addRecordsAndInstances';
    public const ADD_INSTANCES             = 'addInstances';
    public const ADD_NONE                  = 'addNone';

    // File updates
    public const TRANSFER_FILES = 'transferFiles';
    private $transferFiles;

    // Record overwrtiing
    public const OVERWRITE_WITH_BLANKS = 'overwriteWithBlanks';
    private $overwriteWithBlanks;

    // Logic that filters records from the source project, e.g., "[record_id] < 1000" would only retrieve records
    // from the source project that have an ID less than 1000.
    public const SOURCE_FILTER_LOGIC = 'sourceFilterLogic';
    private $sourceFilterLogic;

    # Batch size, which determines how many records are processed at a time
    public const BATCH_SIZE = 'batchSize';
    public const DEFAULT_BATCH_SIZE = 100;
    private $batchSize;

    public const MATCH_EQUIVALENT_FIELDS = 'matchEquivalentFields';
    public const MATCH_COMPATIBLE_FIELDS = 'matchCompatibleFields';
    public const MATCH_NO_FIELDS         = 'matchNoFields';

    // field map represented as JSON string
    public const FIELD_MAP = 'fieldMap';
    private $fieldMap;

    // DAGs (Data Access Groups)
    public const DAG_OPTION = 'dagOption';
    private $dagOption;

    public const DAG_NO_TRANSFER = 'dagNoTransfer';
    public const DAG_TRANSFER    = 'dagTransfer';
    public const DAG_MAPPING     = 'dagMapping';

    public const DAG_MAP = 'dagMap';
    private $dagMap;

    public const DAG_MAP_EXLCUDE = 'dagMap';
    private $dagExclude;

    // Repeating data transfer
    public const REPEATING_TO_NON_REPEATING = 'repeatingToNonRepeating';
    private $repeatingToNonRepeating;

    public const FROM_FIRST = 'fromFirst';
    public const FROM_LAST  = 'fromLast';

    public const NON_REPEATING_TO_REPEATING = 'nonRepeatingToRepeating';
    private $nonRepeatingToRepeating;

    public const TO_1     = 'to1';
    public const TO_FIRST = 'toFirst';
    public const TO_LAST  = 'toLast';
    public const TO_NEW   = 'toNew';

    // Transfer activation:
    // manual, on form save
    public const EXPORT_ON_FORM_SAVE = 'exportOnFormSave';
    private $exportOnFormSave;

    public const MANUAL_TRANSFER_ENABLED = 'manualTransferEnabled';
    private $manualTransferEnabled;

    public const EMAIL_FORM_SAVE_ERRORS = 'emailFormSaveErrors';
    private $emailFormSaveErrors;

    public const IMPORT_FROM_FORM = 'importFromForm';
    private $importFromForm;

    public const SCHEDULE = 'schedule';
    /** @var array Two-dimensional array, where the first dimension is the day of the week:
     *      0 (for Sunday), 1 (for Monday)', ... and the second dimension is the time (range):
     *      0 (for 12:00am to 1:00am), 1 (for 1:00am to 2:00am), ..., 23 (for 11:00pm to 12:00am).
     */
    private $schedule;

    public const EMAIL_SCHEDULING_ERRORS = 'emailSchedulingErrors';
    private $emailSchedulingErrors;

    public const EMAIL_SCHEDULING_COMPLETIONS = 'emailSchedulingCompletions';
    private $emailSchedulingCompletions;

    public function __construct()
    {
        $this->isEnabled = false;

        $this->configProjectId = null;

        $this->recordMatch = self::MATCH_RECORD_ID;

        $this->updateRecords = true;

        $this->recordCreation = self::ADD_RECORDS_AND_INSTANCES;

        $this->transferFiles = true;

        $this->overwriteWithBlanks = false;

        $this->sourceFilterLogic = '';

        $this->repeatingToNonRepeating = self::FROM_LAST;
        $this->nonRepeatingToRepeating = self::TO_LAST;

        $this->batchSize = self::DEFAULT_BATCH_SIZE;

        $this->fieldMap = '';

        $this->dagOption = self::DAG_TRANSFER;
        $this->dagMap = [];
        $this->dagExclude = [];

        # Transfer activation
        $this->manualTransferEnabled = true;
        $this->exportOnFormSave = false;
        $this->emailFormSaveErrors = true;

        foreach (self::DAY_LABELS as $day => $label) {
            $this->schedule[$day] = [];
        }

        $this->emailSchedulingErrors = true;
        $this->emailSchedulingCompletions = false;
    }

    /**
     * Indicated if the transfer project information in the configuration has been completed. This information
     * has to be completed first for most of the configure page tabs to be useable.
     *
     * @return boolean
     */
    public function isProjectComplete()
    {
        $isProjectConmplete = false;

        if ($this->direction === self::DIRECTION_IMPORT || $this->direction === self::DIRECTION_EXPORT) {
            if ($this->location === self::LOCATION_LOCAL) {
                if (!empty($this->projectId)) {
                    $isProjectComplete = true;
                }
            } elseif ($this->location === self::LOCATION_REMOTE) {
                if (!empty($this->apiUrl) && !empty($this->apiToken)) {
                    $isProjectComplete = true;
                }
            }
        }

        return $isProjectComplete;
    }

    /**
     * Gets the source project, i.e., the project from which data are being exported.
     */
    public function getSourceProject($module)
    {
        $project = null;

        if ($this->direction === self::DIRECTION_IMPORT) {
            # importing data to the config project, so the transfer project is the source project
            $project = $this->getTransferProject($module);
        } elseif ($this->direction === self::DIRECTION_EXPORT) {
            # exporting data from the config project, so it is the source project
            $project = $this->getConfigProject($module);
        } else {
            $message = "The source project cannot be retrieved,"
               . " because the direction of the data transfer has not been set.";
            throw new \Exception($message);
        }

        return $project;
    }

    /**
     * Gets the destination project, i.e., the project to which data are being imported.
     */
    public function getDestinationProject($module)
    {
        $project = null;

        if ($this->direction === self::DIRECTION_IMPORT) {
            # importing data to the config project, so it is the destination project
            $project = $this->getConfigProject($module);
        } elseif ($this->direction === self::DIRECTION_EXPORT) {
            # exporting data from the config project, so the transfer project the destination project
            $project = $this->getTransferProject($module);
        } else {
            $message = "The destination project cannot be retrieved,"
               . " because the direction of the data transfer has not been set.";
            throw new \Exception($message);
        }

        return $project;
    }

    /**
     * Sets some of the configuration properties from a properties array.
     */
    public function setTransferProject($properties, $username, $projectUsers, $isSuperUser)
    {
        if (!$this->mayBeModifiedByUser($username, $projectUsers, $isSuperUser)) {
            $message = "User \"{$username}\" does not have perrmission to modify"
                . " data transfer configuration \"{$this->name}\".";
            throw new \Exception($message);
        }

        $this->isEnabled = false;

        foreach ($properties as $key => $value) {
            if ($key === self::IS_ENABLED) {
                $this->isEnabled = $this->convertCheckboxValue($value);
            } elseif ($key === self::CONFIG_PROJECT_ID) {
                $this->configProjectId = Filter::sanitizeInt($value);
            } elseif ($key === self::DIRECTION) {
                if ($value === self::DIRECTION_IMPORT || $value === self::DIRECTION_EXPORT) {
                    $this->direction = $value;
                }
            } elseif ($key === self::LOCATION) {
                if ($value === self::LOCATION_LOCAL || $value === self::LOCATION_REMOTE) {
                    $this->location = $value;
                }
            } elseif ($key === self::TRANSFER_PROJECT_ID) {
                $this->projectId = (int) $value;
            } elseif ($key === self::API_URL) {
                $this->apiUrl = Filter::sanitizeString($value);
            } elseif ($key === self::API_TOKEN) {
                if ($value !== Configuration::API_TOKEN_MASK) {
                    $this->apiToken = Filter::sanitizeApiToken($value);
                }
            } else {
                ; // ignore
            }
        }
    }

    public function setFieldMapFromProperties($properties, $username, $projectUsers, $isSuperUser)
    {
        if (!$this->mayBeModifiedByUser($username, $projectUsers, $isSuperUser)) {
            $message = "User \"{$username}\" does not have perrmission to modify"
                . " data transfer configuration \"{$this->name}\".";
            throw new \Exception($message);
        }

        foreach ($properties as $key => $value) {
            if ($key === self::FIELD_MAP) {
                $this->fieldMap = Filter::sanitizeJson($value);
            } else {
                ; // ignore
            }
        }
    }


    public function setTransferOptions($properties, $username, $projectUsers, $isSuperUser)
    {
        if (!$this->mayBeModifiedByUser($username, $projectUsers, $isSuperUser)) {
            $message = "User \"{$username}\" does not have perrmission to modify"
                . " data transfer configuration \"{$this->name}\".";
            throw new \Exception($message);
        }

        $this->transferFiles = false;
        $this->overwriteWithBlanks = false;

        $this->manualTransferEnabled = false;

        $this->exportOnFormSave    = false;
        $this->emailFormSaveErrors = false;

        foreach ($properties as $key => $value) {
            if ($key === self::RECORD_MATCH) {
                if (in_array($value, [self::MATCH_RECORD_ID, self::MATCH_SECONDARY_ID, self::MATCH_LOGIC])) {
                    $this->recordMatch = $value;
                }
            } elseif ($key === self::TRANSFER_FILES) {
                $this->transferFiles = $this->convertCheckboxValue($value);
            } elseif ($key === self::OVERWRITE_WITH_BLANKS) {
                $this->overwriteWithBlanks = $this->convertCheckboxValue($value);
            } elseif ($key === self::SOURCE_FILTER_LOGIC) {
                $this->sourceFilterLogic = strip_tags($value);
            } elseif ($key === self::REPEATING_TO_NON_REPEATING) {
                $this->repeatingToNonRepeating = Filter::sanitizeLabel($value);
            } elseif ($key === self::NON_REPEATING_TO_REPEATING) {
                $this->nonRepeatingToRepeating = Filter::sanitizeLabel($value);
            } elseif ($key === self::UPDATE_RECORDS) {
                $this->updateRecords = $value === 'true' ? true : false;
            } elseif ($key === self::RECORD_CREATION) {
                $possibleValues = [self::ADD_RECORDS_AND_INSTANCES, self::ADD_INSTANCES, self::ADD_NONE];
                if (in_array($value, $possibleValues)) {
                    $this->recordCreation = $value;
                }
            } elseif ($key === self::BATCH_SIZE) {
                $this->batchSize = intval($value);
            } elseif ($key === self::MANUAL_TRANSFER_ENABLED) {
                $this->manualTransferEnabled = true;
            } elseif ($key === self::EXPORT_ON_FORM_SAVE) {
                $this->exportOnFormSave = true;
            } elseif ($key === self::EMAIL_FORM_SAVE_ERRORS) {
                $this->emailFormSaveErrors = true;
            } else {
                ; // ignore
            }
        }
    }


    public function setDagMapFromProperties($properties, $username, $projectUsers, $isSuperUser)
    {
        if (!$this->mayBeModifiedByUser($username, $projectUsers, $isSuperUser)) {
            $message = "User \"{$username}\" does not have perrmission to modify"
                . " data transfer configuration \"{$this->name}\".";
            throw new \Exception($message);
        }

        $this->dagMap = [];   // remove any old values
        $this->dagExclude = [];   // remove any old values

        foreach ($properties as $key => $value) {
            $key   = Filter::sanitizeString($key);
            $value = Filter::sanitizeString($value);

            if ($key === self::DAG_OPTION) {
                $this->dagOption = Filter::sanitizeLabel($value);
            } elseif (preg_match("/^dag-map-(.*)/", $key, $matches) === 1) {
                $sourceDag = $matches[1];
                $this->dagMap[$sourceDag] = Filter::sanitizeLabel($value);
            } elseif (preg_match("/^dag-exclude-(.*)/", $key, $matches) === 1) {
                $sourceDag = $matches[1];
                $this->dagExclude[$sourceDag] = true;
            }
        }
    }

    public function setScheduleFromProperties($properties, $username, $projectUsers, $isSuperUser)
    {
        if (!$this->mayBeModifiedByUser($username, $projectUsers, $isSuperUser)) {
            $message = "User \"{$username}\" does not have perrmission to modify"
                . " the schedule for data transfer configuration \"{$this->name}\".";
            throw new \Exception($message);
        }

        $this->emailSchedulingErrors = false;
        $this->emailSchedulingCompletions = false;

        #----------------------------------
        # Set the schedule
        #----------------------------------
        # $this->schedule[0] = array_map(['IU\DataTransfer\Filter', 'sanitizeInt'], $_POST['Sunday'] ?? []);
        # $this->schedule[1] = array_map(['IU\DataTransfer\Filter', 'sanitizeInt'], $_POST['Monday'] ?? []);
        # $this->schedule[2] = array_map(['IU\DataTransfer\Filter', 'sanitizeInt'], $_POST['Tuesday'] ?? []);
        # $this->schedule[3] = array_map(['IU\DataTransfer\Filter', 'sanitizeInt'], $_POST['Wednesday'] ?? []);
        # $this->schedule[4] = array_map(['IU\DataTransfer\Filter', 'sanitizeInt'], $_POST['Thursday'] ?? []);
        # $this->schedule[5] = array_map(['IU\DataTransfer\Filter', 'sanitizeInt'], $_POST['Friday'] ?? []);
        # $this->schedule[6] = array_map(['IU\DataTransfer\Filter', 'sanitizeInt'], $_POST['Saturday'] ?? []);
        $this->schedule[0] = array_map('intval', $_POST['Sunday'] ?? []);
        $this->schedule[1] = array_map('intval', $_POST['Monday'] ?? []);
        $this->schedule[2] = array_map('intval', $_POST['Tuesday'] ?? []);
        $this->schedule[3] = array_map('intval', $_POST['Wednesday'] ?? []);
        $this->schedule[4] = array_map('intval', $_POST['Thursday'] ?? []);
        $this->schedule[5] = array_map('intval', $_POST['Friday'] ?? []);
        $this->schedule[6] = array_map('intval', $_POST['Saturday'] ?? []);

        foreach ($properties as $key => $value) {
            if ($key === self::EMAIL_SCHEDULING_ERRORS) {
                $this->emailSchedulingErrors = true;
            } elseif ($key === self::EMAIL_SCHEDULING_COMPLETIONS) {
                $this->emailSchedulingCompletions = true;
            }
        }
    }


    public function convertCheckboxValue($value)
    {
        if ($value === 'on' || $value === '1' || $value === 'true') {
            $value = true;
        } elseif ($value === true || $value === 1) {
            $value = true;
        } else {
            $value = false;
        }

        return $value;
    }


    /**
     * Validates a configuration name.
     *
     * @param string $name the configuration name to check.
     *
     * @return boolean returns true if the configuration name is valid, or throws an
     *     exception if not.
     */
    public static function validateName($name)
    {
        $matches = array();
        if (empty($name)) {
            throw new \Exception('No configuration name specified.');
        } elseif (!is_string($name)) {
            throw new \Exception('Configuration name is not a string; has type: ' . gettype($name) . '.');
        } elseif (preg_match('/([^a-zA-Z0-9_\- .])/', $name, $matches) === 1) {
            $errorMessage = 'Invalid character in configuration name: ' . $matches[0];
            throw new \Exception($errorMessage);
        }
        return true;
    }

    /**
     * Gets the project that contains the configuration.
     */
    public function getConfigProject($module)
    {
        $pid = $this->getConfigProjectId();
        $project = new Project($module);
        $project->initialize($pid);

        return $project;
    }

    /**
     * Gets the project that the configuration project is
     * transferring data to or from.
     */
    public function getTransferProject($module)
    {
        if ($this->location === self::LOCATION_LOCAL) {
            $pid = $this->projectId;
            $project = new Project($module);
            $project->initialize($pid);
        } else {
            $project = new Project($module);
            $project->initializeUsingApi($this->apiUrl, $this->apiToken);
        }

        return $project;
    }

    /* OBSOLETE?
    public function getFieldMapArray()
    {
        $fieldMapArray = json_decode($this->fieldMap, true, 128, JSON_THROW_ON_ERROR);
        return $fieldMapArray;
    }
    */

    /**
     * @return FieldMap a FieldMap object.
     */
    public function getFieldMapObject()
    {
        $fieldMapObject = new FieldMap();
        $fieldMapObject->setFromJson($this->fieldMap);
        return $fieldMapObject;
    }

    #------------------------------------------------------------------
    # Permissions/Access
    #------------------------------------------------------------------

    #public function updatePermissionCheck($username)
    #{
    #    # Only allow the owner of a configuration to update it
    #    if (empty($username)) {
    #        throw new \Exception("No username specified for confguration \"{$this->name}\" update.");
    #    } elseif ($username !== $this->getOwner()) {
    #        $message = "User \"{$username}\" cannot update configuration \"{$this->name}\".";
    #        throw new \Exception($message);
    #    }
    #}

    public function userMayViewApiToken($user)
    {
        $mayViewApiToken = false;

        if ($user === $this->getOwner()) {
            $mayViewApiToken = true;
        }

        return $mayViewApiToken;
    }

    public function mayBeModifiedByUser($user, $projectUsers, $isSuperUser)
    {
        $mayBeModified = false;

        if ($user === $this->getOwner() && in_array($user, $projectUsers)) {
            $mayBeModified = true;
        } elseif ($isSuperUser) {
            $mayBeModified = true;
        }

        return $mayBeModified;
    }

    public function mayBeDeletedByUser($user, $projectUsers, $isSuperUser)
    {
        $mayBeDeleted = false;

        # Allow the user to delete the configuration if they are the
        # owner, OR if the owner no longer has access to the project
        if ($user === $this->getOwner() && in_array($user, $projectUsers)) {
            $mayBeDeleted = true;
        } elseif ($isSuperUser) {
            $mayBeDeleted = true;
        } elseif (!in_array($this->getOwner(), $projectUsers) && in_array($user, $projectUsers)) {
            $mayBeDeleted = true;
        }

        return $mayBeDeleted;
    }

    public function mayBeRenamedByUser($user, $projectUsers, $isSuperUser)
    {
        $mayBeRenamed = false;

        # Allow the user to rename the configuration if they are the
        # owner, OR if the owner no longer has access to the project
        if ($user === $this->getOwner() && in_array($user, $projectUsers)) {
            $mayBeRenamed = true;
        } elseif ($isSuperUser) {
            $mayBeRenamed = true;
        } elseif (!in_array($this->getOwner(), $projectUsers) && in_array($user, $projectUsers)) {
            $mayBeRenamed = true;
        }

        return $mayBeRenamed;
    }

    public function hasSchedule()
    {
        $hasSchedule = false;

        if (!empty($this->schedule) && is_array($this->schedule)) {
            foreach ($this->schedule as $day) {
                if (!empty($day) && is_array($day)) {
                    $hasSchedule = true;
                    break;
                }
            }
        }

        return $hasSchedule;
    }

    public function toJson($indent)
    {
        $json = '';

        $vars = get_object_vars($this);

        foreach ($vars as $name => $value) {
            if ($name === 'dagExclude' || $name === 'dagMap') {
                $json .= $indent . '"' . $name . '": ';
                $json .= json_encode($value, JSON_FORCE_OBJECT);
            } elseif ($name === 'fieldMap') {
                $jsonValue = '""';
                if (!empty($value)) {
                    $jsonValue = json_encode(json_encode(json_decode($value)));
                }
                $json .= $indent . '"' . $name . '": ' . $jsonValue;
            } elseif ($name === 'schedule') {
                $json .= $indent . '"' . $name . '": ';
                $json .= json_encode($value);
            } elseif (is_string($value)) {
                $json .= $indent . '"' . $name . '": "' . $value . '"';
            } elseif (is_int($value)) {
                $json .= $indent . '"' . $name . '": ' . $value;
            } elseif (is_float($value)) {
                $json .= $indent . '"' . $name . '": ' . $value;
            } elseif (is_bool($value)) {
                $json .= $indent . '"' . $name . '": ' . ($value ? 'true' : 'false');
            } elseif (is_null($value)) {
                $json .= $indent . '"' . $name . '": null';
            } else {
                $json .= $indent . '"' . $name . '": "' . $value . '"';
            }

            if ($name === array_key_last($vars)) {
                $json .= "\n";
            } else {
                $json .= ",\n";
            }
        }

        return $json;
    }

    /**
     * Sets the Configuration from an object created from decoding
     * a JSON string representation of a Configuration.
     */
    public function setFromJsonObj($jsonObj)
    {
        foreach ($jsonObj as $var => $value) {
            if (property_exists($this, $var)) {
                $this->$var = $value;
            }
        }
    }

    #------------------------------------------------------------------
    # Getters and Setters
    #------------------------------------------------------------------
    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getConfigProjectId()
    {
        return $this->configProjectId;
    }

    public function setConfigProjectId($configProjectId)
    {
        $this->configProjectId = $configProjectId;
    }

    public function isEnabled()
    {
        return $this->isEnabled;
    }

    public function setIsEnabled($isEnabled)
    {
        $this->isEnabled = $isEnabled;
    }

    public function getDirection()
    {
        return $this->direction;
    }

    public function setDirection($direction)
    {
        $this->direction = $direction;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function setLocation($location)
    {
        $this->location = $location;
    }

    public function getProjectId()
    {
        return $this->projectId;
    }

    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }

    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    public function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    public function getApiToken()
    {
        return $this->apiToken;
    }

    public function setApiToken($apiToken)
    {
        $this->apiToken = $apiToken;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    public function getRecordMatch()
    {
        return $this->recordMatch;
    }

    public function setRecordMatch($recordMatch)
    {
        $this->recordMatch = $recordMatch;
    }

    public function getUpdateRecords()
    {
        return $this->updateRecords;
    }

    public function setUpdateRecords($updateRecords)
    {
        $this->updateRecords = $updateRecords;
    }

    public function createNewRecords()
    {
        return ($this->recordCreation === self::ADD_RECORDS_AND_INSTANCES);
    }

    public function createNewInstances()
    {
        $createNewInstances = $this->recordCreation === self::ADD_RECORDS_AND_INSTANCES
            || $this->recordCreation === self::ADD_INSTANCES;
        return $createNewInstances;
    }

    public function getRecordCreation()
    {
        return $this->recordCreation;
    }

    public function setRecordCreation($recordCreation)
    {
        $this->recordCreation = $recordCreation;
    }

    public function getTransferFiles()
    {
        return $this->transferFiles;
    }

    public function getOverwriteWithBlanks()
    {
        return $this->overwriteWithBlanks;
    }

    public function getSourceFilterLogic()
    {
        return $this->sourceFilterLogic;
    }

    public function getRepeatingToNonRepeating()
    {
        return $this->repeatingToNonRepeating;
    }

    public function getNonRepeatingToRepeating()
    {
        return $this->nonRepeatingToRepeating;
    }

    public function getBatchSize()
    {
        return $this->batchSize;
    }

    public function getManualTransferEnabled()
    {
        return $this->manualTransferEnabled;
    }

    public function getExportOnFormSave()
    {
        return $this->exportOnFormSave;
    }

    public function getEmailFormSaveErrors()
    {
        return $this->emailFormSaveErrors;
    }

    public function getFieldMap()
    {
        return $this->fieldMap;
    }

    public function getDagOption()
    {
        return $this->dagOption;
    }

    public function getDagMap()
    {
        return $this->dagMap;
    }

    public function getDagExclude()
    {
        return $this->dagExclude ?? [];
    }

    public function getSchedule()
    {
        return $this->schedule;
    }

    public function setSchedule($schedule)
    {
        $this->schedule = $schedule;
    }

    public function getEmailSchedulingErrors()
    {
        return $this->emailSchedulingErrors;
    }

    public function getEmailSchedulingCompletions()
    {
        return $this->emailSchedulingCompletions;
    }
}
