<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use IU\PHPCap\RedCapProject;

/**
 * Class for representing a REDCap Project that is either on the current or a remote REDCap system.
 * Access for the project to REDCap uses either a "project ID" or an "API URL" and "API token".
 */
class Project
{
    public const REDCAP_XML_NAMESPACE = 'https://projectredcap.org';

    public const CHECKBOX_SEPARATOR = '___';
    public const FORM_COMPLETE_SUFFIX = '_complete';

    # REDCap record identifier fields
    public const REDCAP_EVENT_NAME        = 'redcap_event_name';
    public const REDCAP_REPEAT_INSTRUMENT = 'redcap_repeat_instrument';
    public const REDCAP_REPEAT_INSTANCE   = 'redcap_repeat_instance';
    public const REDCAP_DATA_ACCESS_GROUP = 'redcap_data_access_group';

    public const ROW_NOT_FOUND = -1;

    private $module;

    private $pid;   // project ID

    // For API tolen accessed (typically remote) projects only
    private $apiUrl;
    private $apiToken;
    private $redCapProject;

    private $location;

    private $metadataXml;
    private $metadataXmlDom;

    private $projectInfo;

    /** @var array Map from missing data code value to missing data code label. */
    private $missingDataCodes;

    private $metadata;

    /** @var array map from field name to the field's metadata */
    private $metadataMap;

    private $dags;
    private $arms;
    private $events;
    private $eventInfo;
    private $eventMap;

    /** @var array map from field name to a map with keys: 'field_name', 'form_name', 'events'; key 'events' is an
     *     array of maps with keys: 'unique_event_name', 'event_name', 'repeating_event', 'repeating_form'
     */
    private $fieldFormEventMap;

    private $formFieldsMap;   // map from form name to array of field names

    private $formTransferFieldsMap; // map from form name to array of field names used
                                    // for data transfer, i.e., with checkbox fields expanded

    private $nonRepeatFormFields;   // For non-longitudinal projects

    private $repeatingFormsMap;  // For non-lonitudinal projects
    private $repeatingEventsMap;  // For lonitudinal projects
    private $repeatingFormsInEventsMap;  // For lonitudinal projects

    /**
     * @param $projectId The project ID for the project; if set this implies that it is a local project.
     */
    public function __construct($module)
    {
        $this->module = $module;

        $this->pid    = null;

        $this->apiUrl   = null;
        $this->apiToken = null;

        $this->events = [];
        $this->eventInfo = [];
        $this->eventMap = [];

        $this->projectInfo = [];
        $this->missingDataCodes = [];

        $this->metadata    = [];
        $this->metadataMap = [];

        $this->fieldFormEventMap = [];

        $this->formFieldsMap = [];
        $this->formTransferFieldsMap = [];

        $this->nonRepeatFormFields = [];

        $this->repeatingFormsMap = [];
        $this->repeatingEventsMap = [];
        $this->repeatingFormsInEventsMap = [];
    }

    public function initialize($pid)
    {
        $this->pid = $pid;
        $this->location = 'local';

        // The order of this is important, because it sets "is longitudinal"
        $this->projectInfo = $this->module->getProjectInfo($this->pid);

        $this->missingDataCodes = $this->createMissingDataCodes();

        $returnMetadataOnly = true;
        $this->metadataXml = \REDCap::getProjectXml($this->pid, $returnMetadataOnly);

        $this->metadataXmlDom = new \DomDocument();
        $this->metadataXmlDom->loadXML($this->metadataXml);


        $this->metadata    = \REDCap::getDataDictionary($this->pid, 'array');
        $this->addCompleteFieldsToMetadata();
        $this->metadataMap = $this->createMetadataMap();

        #---------------------------------------------------------------
        # Get DAG (Data Access Group) information.
        #
        # Unfortunately, the developer method can't be used, because
        # it doesn't have a project ID parameter. The database cannot
        # be queried, because the unique group name is calculated
        # (and it is not a straight forward calculation), so the
        # internal REDCap Project class is used to get the information.
        #---------------------------------------------------------------
        $proj = new \Project($this->pid);    // Internal REDCap Project
        $idToNameMap = $proj->getGroups();
        $idToUniqueNameMap = $proj->getUniqueGroupNames();
        $this->dags = [];
        foreach ($idToNameMap as $id => $name) {
            $dag['data_access_group_name'] = $name;
            $dag['unique_group_name']      = $idToUniqueNameMap[$id];
            $dag['data_access_group_id']   = $id;
            $this->dags[] = $dag;
        }

        $this->arms = $this->module->getArms($this->pid);

        $this->events = $this->module->getEvents($this->pid);

        $this->finishInitialization();
    }

    public function initializeUsingApi($apiUrl, $apiToken)
    {
        $this->redCapProject = new RedCapProject($apiUrl, $apiToken);

        $this->apiUrl   = $apiUrl;
        $this->apiToken = $apiToken;

        // The order of this is important, because it sets "is longitudinal"
        $this->projectInfo = $this->redCapProject->exportProjectInfo();

        $this->missingDataCodes = $this->createMissingDataCodes();

        $this->location = preg_replace('/api\/?\s*$/', '', $apiUrl);


        $returnMetadataOnly = true;
        $this->metadataXml = $this->redCapProject->exportProjectXml($returnMetadataOnly);

        $this->metadataXmlDom = new \DomDocument();
        $this->metadataXmlDom->loadXML($this->metadataXml);

        $this->pid   = $this->projectInfo['project_id'];

        $this->metadata    = $this->redCapProject->exportMetadata();
        $this->addCompleteFieldsToMetadata();
        $this->metadataMap = $this->createMetadataMap();

        $this->dags = $this->redCapProject->exportDags();

        if ($this->isLongitudinal()) {
            $this->arms = $this->redCapProject->exportArms();
            $this->events = $this->redCapProject->exportEvents();
        } else {
            $this->arms = [['arm_num' => 1, 'name' => 'Arm 1']];
            $this->events = [
                [
                    'event_name'         => 'Event 1',
                    'arm_num'            => 1,
                    'unique_event_name'  => 'event_1_arm_1',
                    'custom_event_label' => '',
                    'event_id' => ''   // FIX !!!!!!!!!!!!!!!!!!!
                ]
            ];
        }

        $this->finishInitialization();
    }

    public function addCompleteFieldsToMetadata()
    {
        $forms = $this->getInstrumentNames();
        foreach ($forms as $form) {
            $fieldInfo = array();

            $fieldName = $form . self::FORM_COMPLETE_SUFFIX;

            $fieldInfo['field_name'] = $fieldName;
            $fieldInfo['form_name']  = $form;

            $fieldInfo['field_type'] = 'text';
            $fieldInfo['text_validation_type_or_show_slider_number'] = 'integer';
            $fieldInfo['text_validation_min'] = '';
            $fieldInfo['text_validation_max'] = '';
            $fieldInfo['required_field'] = '';

            $events = $this->getFormEvents($form);
            $fieldInfo['events'] = $events;

            $this->metadata[$fieldName] = $fieldInfo;
        }
    }

    public function finishInitialization()
    {
        $this->eventInfo = $this->createEventInfo();

        $this->eventMap = $this->createEventMap();

        $this->fieldFormEventMap = $this->createFieldFormEventMap();

        $this->formFieldsMap = $this->createFormFieldsMap();
        $this->formTransferFieldsMap = $this->createFormTransferFieldsMap();

        $this->nonRepeatFormFields = $this->createNonRepeatFormFields();

        $this->repeatingFormsMap = $this->createRepeatingFormsMap();
        $this->repeatingEventsMap = $this->createRepeatingEventsMap();
        $this->repeatingFormsInEventsMap = $this->createRepeatingFormsInEventsMap();
    }

    /**
     * Note: this needs to be called after projectInfo is set.
     *
     * @return array map from missing data code value to
     *     missing data code label.
     */
    public function createMissingDataCodes()
    {
        $missingDataCodes = [];
        $projectInfo = $this->projectInfo;

        if (array_key_exists('missing_data_codes', $projectInfo)) {
            $codesString = trim($projectInfo['missing_data_codes']);
            if (!empty($codesString)) {
                $codes = explode("\n", $codesString);
                if (count($codes) > 0) {
                    foreach ($codes as $code) {
                        $codeMap = explode(",", $code);
                        if (count($codeMap) === 2) {
                            $value = trim($codeMap[0]);
                            $label = trim($codeMap[1]);
                            $missingDataCodes[$value] = $label;
                        }
                    }
                }
            }
        }

        return $missingDataCodes;
    }

    /**
     * Indicates if the specified user has the necessary permissions to transfer data from this project.
     */
    public function allowedToTransferDataFrom($username)
    {
        if ($this->isApiProject()) {
            ; // Can't check permissions for API project (without trying to execute API calls)
        } else {
            $rightsMap = $this->getUserRightsMap();

            if (!array_key_exists($username, $rightsMap) || empty($rightsMap[$username])) {
                throw new \Exception("User \"{$username}\" does not have access to project \"{$this->getTitle()}\".");
            }

            $userRights = $rightsMap[$username];

            #-------------------------------------------------
            # Check for the user's rights being expired
            #-------------------------------------------------
            if (!empty($userRights['expiration'])) {
                $expiration = $userRights['expiration'];
                $expirationTime = strtotime($expiration);
                $nowTime = strtotime("now");
                if ($nowTime > $expirationTime) {
                    $message = "User \"{$username}\" rights for project \"{$this->getTitle()}\" have expired.";
                    throw new \Exception($message);
                }
            }

            #---------------------------------------------------------------
            # Check for the user belonging to a DAG (Data Access Group)
            #---------------------------------------------------------------
            if (!empty($userRights['data_access_group'])) {
                $message = "User \"{$username}\" cannot transfer data from project \"{$this->getTitle()}\","
                    . " because the user belongs to a data access group, which restricts the user's access"
                    . " to the project's data.";
                throw new \Exception($message);
            }

            #--------------------------------------------------
            # Check for user's right to export all forms
            #--------------------------------------------------
            if (!array_key_exists('forms_export', $userRights) || empty($userRights['forms_export'])) {
                $message = "User \"{$username}\" cannot transfer data from project \"{$this->getTitle()}\","
                    . " because the user has no forms export rights.";
                throw new \Exception($message);
            }

            $formsExportRights = $userRights['forms_export'];
            foreach ($formsExportRights as $form => $canExport) {
                if (!$canExport) {
                    $message = "User \"{$username}\" cannot transfer data from project \"{$this->getTitle()}\","
                        . " because the user does not have permission to export form \"{$form}\".";
                    throw new \Exception($message);
                }
            }
        }
    }

    /**
     * Indicates if the specified user has the necessary permissions to transfer data to this project.
     */
    public function allowedToTransferDataTo($username, $createRecords = true)
    {
        if ($this->isApiProject()) {
            ; // Can't check permissions for API project (without trying to execute API calls)
        } else {
            $rightsMap = $this->getUserRightsMap();

            if (!array_key_exists($username, $rightsMap) || empty($rightsMap[$username])) {
                throw new \Exception("User \"{$username}\" does not have access to project \"{$this->getTitle()}\".");
            }

            $userRights = $rightsMap[$username];

            #-------------------------------------------------
            # Check for the user's rights being expired
            #-------------------------------------------------
            if (!empty($userRights['expiration'])) {
                $expiration = $userRights['expiration'];
                $expirationTime = strtotime($expiration);
                $nowTime = strtotime("now");
                if ($nowTime > $expirationTime) {
                    $message = "User \"{$username}\" rights for project \"{$this->getTitle()}\" have expired.";
                    throw new \Exception($message);
                }
            }

            #--------------------------------------------------------------------
            # Check for user's permission to create records, if it is necessary
            #--------------------------------------------------------------------
            if ($createRecords) {
                if (!array_key_exists('record_create', $userRights) || !$userRights['record_create']) {
                    $message = "User \"{$username}\" cannot transfer data to project \"{$this->getTitle()}\","
                        . " because the user has does not have record creation rights.";
                    throw new \Exception($message);
                }
            }

            #--------------------------------------------------
            # Check for user's right to edit all forms
            #--------------------------------------------------
            if (!array_key_exists('forms', $userRights) || empty($userRights['forms'])) {
                $message = "User \"{$username}\" cannot transfer data to project \"{$this->getTitle()}\","
                    . " because the user has no forms edit rights.";
                throw new \Exception($message);
            }

            $formsExportRights = $userRights['forms'];
            foreach ($formsExportRights as $form => $canEdit) {
                if (!$canEdit) {
                    $message = "User \"{$username}\" cannot transfer data to project \"{$this->getTitle()}\","
                        . " because the user does not have permission to edit form \"{$form}\".";
                    throw new \Exception($message);
                }
            }
        }
    }

    /**
     * Gets an indentifier string for the project with the following format:
     *
     * "<project-name>" (<project-id>) [at <api-url>]
     *
     * The API URL is only included for projects accessed by the API
     * that have an API URL specified.
     */
    public function getProjectIdentifier()
    {
        $identifier = '"' . $this->getTitle() . '" (' . $this->getPid() . ')';

        if ($this->isApiProject() && !empty($this->getApiUrl())) {
            $identifier .= ' at ' . $this->getApiUrl();
        }

        return $identifier;
    }

    public function getMissingDataCodes()
    {
        return $this->missingDataCodes;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    public function getApiToken()
    {
        return $this->apiToken;
    }

    public function getTitle()
    {
        $title = $this->projectInfo['project_title'];
        return $title;
    }

    public function isLongitudinal()
    {
        $isLongitudinal = $this->projectInfo['is_longitudinal'];
        return $isLongitudinal;
    }

    public function hasRepeatingInstrumentsOrEvents()
    {
        $hasRepeatingInstrumentsOrEvents = $this->projectInfo['has_repeating_instruments_or_events'];
        return $hasRepeatingInstrumentsOrEvents;
    }

    public function surveysEnabled()
    {
        $surveysEnabled = $this->projectInfo['surveys_enabled'];
        return $surveysEnabled;
    }

    public function recordAutonumberingEnabled()
    {
        $recordAutonumberingEnabled = $this->projectInfo['record_autonumbering_enabled'];
        return $recordAutonumberingEnabled;
    }

    public function getProjectInfo()
    {
        return $this->projectInfo;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getSortedMetadata()
    {
        $sortedMetadata = $this->metadata;

        usort($sortedMetadata, function ($a, $b) {
            return strcmp($a['field_name'], $b['field_name']);
        });

        return $sortedMetadata;
    }

    public function createMetadataMap()
    {
        $map = [];

        foreach ($this->metadata as $entry) {
            $map[$entry['field_name']] = $entry;
        }

        return $map;
    }

    public function getMetadataMap()
    {
        return $this->metadataMap;
    }

    /**
     * Gets the metadata for the specified field.
     */
    public function getFieldMetadata($field)
    {
        # error_log("\n" . print_r(($this->metadataMap[$field] ?? []), true) . "\n", 3, __DIR__ . '/../field.log');
        return $this->metadataMap[$field] ?? [];
    }

    public function getFieldActionTags($field)
    {
        $actionTags = [];

        $fieldAnnotation = $this->metadataMap[$field]['field_annotation'] ?? '';
        $actionTags = \ActionTags::getActionTags($fieldAnnotation);

        return $actionTags;
    }

    /**
     * Returns a map from field name to an array action tags.
     */
    public function getActionTagsMap()
    {
        $actionTagMap = [];

        foreach ($this->metadataMap as $field => $fieldMetadata) {
            $fieldAnnotation = $fieldMetadata['field_annotation'] ?? '';
            $actionTags = \ActionTags::getActionTags($fieldAnnotation);
            $actionTagMap[$field] = $actionTags;
        }

        return $actionTagMap;
    }

    public function isFileField($field)
    {
        $isFileField = false;

        $fieldMetadata = $this->metadataMap[$field] ?? [];
        if (!empty($fieldMetadata)) {
            if (($fieldMetadata['field_type'] ?? '') === 'file') {
                $isFileField = true;
            }
        }

        return $isFileField;
    }

    public function getSortedMetadataMap()
    {
        $map = [];

        $sortedMetadata = $this->getSortedMetadata();
        foreach ($sortedMetadata as $entry) {
            $map[$entry['field_name']] = $entry;
        }

        return $map;
    }

    public function createFormFieldsMap()
    {
        $map = [];
        # TODO: metadata won't have form with no fields
        foreach ($this->metadata as $fieldMetadata) {
            $fieldName = $fieldMetadata['field_name'];
            $formName  = $fieldMetadata['form_name'];

            if (!array_key_exists($formName, $map)) {
                $map[$formName] = [];
            }

            $map[$formName][] = $fieldName;
        }

        return $map;
    }

    /**
     * Expands a checkbox field to be the fields that will
     * actually be returned from REDCap (with one field per choice)
     * and used for data transfers.
     *
     * @return false if the field is not a checkbox, and an
     *     array of checkbox field names (which will be exported from REDCap)
     *     if it is a checkbox.
     */
    public function expandCheckboxField($fieldName)
    {
        $expandedCheckboxFields = [];

        $fieldMetadata = $this->getFieldMetadata($fieldName);
        $fieldType = $fieldMetadata['field_type'];
        if ($fieldType === 'checkbox') {
            #---------------------------------
            # Process normal choices
            #---------------------------------
            $choicesString = $fieldMetadata['select_choices_or_calculations'];
            $choices = array_map('trim', explode("|", $choicesString));
            foreach ($choices as $choice) {
                list ($value, $label) = array_map('trim', explode(",", $choice, 2));
                $checkboxFieldName = $fieldName . self::CHECKBOX_SEPARATOR . self::convertCheckboxValue($value);
                $expandedCheckboxFields[] = $checkboxFieldName;
            }

            #---------------------------------
            # Process missing data codes
            #---------------------------------
            $actionTags = $this->getFieldActionTags($fieldName);
            if (empty($this->missingDataCodes)) {
                ; // No Missing data codes
            } elseif (in_array('@NOMISSING', $actionTags)) {
                ; // Missing data codes ignored for this field
            } else {
                foreach ($this->missingDataCodes as $value => $label) {
                    $checkboxFieldName = $fieldName . self::CHECKBOX_SEPARATOR . self::convertCheckboxValue($value);
                    $expandedCheckboxFields[] = $checkboxFieldName;
                }
            }
        } else {
            $expandedCheckboxFields = false;
        }

        return $expandedCheckboxFields;
    }

    /**
     * Converts the user-specified checkbox value to the value
     * used for the field name in REDCap.
     */
    public static function convertCheckboxValue($value)
    {
        # Convert to lower-case and replace '.' and '-' with '_'.
        $convertedValue = strtolower($value);
        $convertedValue = str_replace('.', '_', $convertedValue);
        $convertedValue = str_replace('-', '_', $convertedValue);
        return $convertedValue;
    }


    /**
     * Creates a map from form name to transfer fields,  which will have checkbox
     * fields expanded to the actual fields used in data transfer (import and export)
     * and the addition of the form complete field.
     */
    public function createFormTransferFieldsMap()
    {
        $map = [];
        foreach ($this->metadata as $fieldMetadata) {
            $fieldName = $fieldMetadata['field_name'];
            $fieldType = $fieldMetadata['field_type'];
            $formName  = $fieldMetadata['form_name'];

            if (!array_key_exists($formName, $map)) {
                $map[$formName] = [];
            }

            if ($fieldType === 'checkbox') {
                # For checkboxs, expand the field based on the choices
                # TODO add missing data codes
                $choicesString = $fieldMetadata['select_choices_or_calculations'];
                $choices = array_map('trim', explode("|", $choicesString));
                foreach ($choices as $choice) {
                    list ($value, $label) = array_map('trim', explode(",", $choice, 2));
                    $checkboxFieldName = $fieldName . self::CHECKBOX_SEPARATOR . strtolower($value);
                    $map[$formName][] = $checkboxFieldName;
                }
            } else {
                $map[$formName][] = $fieldName;
            }
        }

        # Add form complete fields
        foreach ($map as $formName => $fieldNames) {
            $map[$formName][] = $formName . self::FORM_COMPLETE_SUFFIX;
        }

        return $map;
    }

    public function getFormTransferFieldsMap()
    {
        return $this->formTransferFieldsMap;
    }

    /**
     * Indicates if the project has the specified field in the possibly specified
     * event and/or form. If no event or form is specified, then the check
     * is for all fields in the project.
     *
     * @param string $field the field name to check.
     * @param string $event event name to check for having the specified field; if empty
     *     then all applicable events are checked.
     * @param string $form form name to check for having the specified field; if empty
     *     then all applicable forms are checked.
     * @return boolean true if the field exists.
     */
    public function hasField($field, $event = null, $form = null)
    {
        $has = false;

        $includeCompleteField = true;
        $includeRecordId = true;

        $fields = [];

        if (!empty($form)) {
            # form specified (only check this form)
            $fields = $this->getFormFields($formName, $includeCompleteField, $includeRecordId);
        } elseif (!empty($event)) {
            # event specified with no form (only check forms belongin to the event)
            $eventForms = $this->getEventForms($event);
            if (!empty($eventForms)) {
                foreach ($eventForms as $eventForm) {
                    $formFields = $this->getFormFields($eventForm, $includeCompleteField, $includeRecordId);
                    $fields = array_merge($fields, $formFields);
                }
            }
        } else {
            # no event or form specified (check all fields)
            $allForms = $this->getForms();
            if (!empty($allForms)) {
                foreach ($allForms as $allForm) {
                    $formFields = $this->getFormFields($allForm, $includeCompleteField, $includeRecordId);
                    $fields = array_merge($fields, $formFields);
                }
            }
        }

        $has = in_array($field, $fields);

        return $has;
    }

    /**
     * Creates an array with the field that are not in repeating forms for
     * a non-longitudinal project.
     */
    public function createNonRepeatFormFields()
    {
        $fields = [];

        if (!$this->isLongitudinal()) {
            $repeatForms = $this->getRepeatingInstruments();
            foreach ($this->metadata as $fieldMetadata) {
                $fieldName = $fieldMetadata['field_name'];
                $formName  = $fieldMetadata['form_name'];

                if (!in_array($formName, $repeatForms)) {
                    $fields[] = $fieldName;
                }
            }
        }

        return $fields;
    }

    public function getNonRepeatFormFields()
    {
        return $this->nonRepeatFormFields;
    }

    /**
     * Note: this method is intended for use with non-longitudinal projects.
     */
    public function getNonRepeatFormTransferFields()
    {
        $transferFields = [];

        $forms = $this->getNonLongitudinalNonRepeatForms();

        foreach ($forms as $form) {
            $fields = $this->getFormTransferFields($form);
            $transferFields = array_merge($transferFields, $fields);
        }

        return $transferFields;
    }

    /**
     * Gets the instrument names, and has no depedencies on other
     * member variables except for redCapProject for the API case.
     */
    public function getInstrumentNames()
    {
        $instrumentNames = [];
        if ($this->isApiProject()) {
            $instruments = $this->redCapProject->exportInstruments();
            $instrumentNames = array_keys($instruments);
        } else {
            # Note: need to include the project ID in this call for
            # the cron case
            $instruments = \REDCap::getInstrumentNames(null, $this->pid);
            $instrumentNames = array_keys($instruments);
        }

        return $instrumentNames;
    }

    public function getForms()
    {
        $forms = array_keys($this->formFieldsMap);
        return $forms;
    }


    public function getNonLongitudinalNonRepeatForms()
    {
        $forms = [];

        if (!$this->isLongitudinal()) {
            $repeatForms = $this->getRepeatingInstruments();
            foreach ($this->getForms() as $form) {
                if (!in_array($form, $repeatForms)) {
                    $forms[] = $form;
                }
            }
        }

        return $forms;
    }

    public function getFormFields($formName, $includeCompleteField = false, $includeRecordId = true)
    {
        $fields = [];
        if (array_key_exists($formName, $this->formFieldsMap)) {
            $fields = $this->formFieldsMap[$formName];
        }

        if (!$includeCompleteField) {
            $completeFieldName = $formName . self::FORM_COMPLETE_SUFFIX;
            unset($fields[$completeFieldName]);
        }

        if (!$includeRecordId) {
            $recordIdField = $this->getRecordIdField();
            $index = array_search($recordIdField, $fields);
            if ($index !== false) {
                unset($fields[$index]);
                $fields = array_values($fields);  // re-index array
            }
        }

        return $fields;
    }

    public function getFormTransferFields($formName)
    {
        $fields = [];
        if (array_key_exists($formName, $this->formTransferFieldsMap)) {
            $fields = $this->formTransferFieldsMap[$formName];
        }
        return $fields;
    }

    /**
     * Gets project information in JSON format.
     */
    public function getInfoJson()
    {
        $project = array();
        $fields = array();

        foreach ($this->metadataMap as $key => $field) {
            $fieldInfo = array();

            $fieldName = $field['field_name'];

            $fieldInfo = $field;

            $events = $this->getFieldEvents($fieldName);
            $fieldInfo['events'] = $events;

            $fields[$fieldName] = $fieldInfo;
        }

        # Add complete fields to the fields data
        foreach ($this->getForms() as $form) {
            $fieldInfo = array();

            $fieldName = $form . self::FORM_COMPLETE_SUFFIX;

            $fieldInfo['field_name'] = $fieldName;
            $fieldInfo['form_name']  = $form;

            $fieldInfo['field_type'] = 'text';
            $fieldInfo['text_validation_type_or_show_slider_number'] = 'integer';
            $fieldInfo['text_validation_min'] = '';
            $fieldInfo['text_validation_max'] = '';
            $fieldInfo['required_field'] = '';

            $events = $this->getFormEvents($form);
            $fieldInfo['events'] = $events;

            $fields[$fieldName] = $fieldInfo;
        }

        $project['project_info'] = $this->getProjectInfo();
        $project['record_id'] = $this->getRecordIdField();
        $project['forms'] = $this->getForms();

        #----------------
        # Get form events
        #----------------
        $formEventsMap = [];
        foreach ($this->getForms() as $form) {
            $formEventsMap[$form] = $this->getFormEvents($form);
        }
        $project['form_events'] = $formEventsMap;

        #----------------
        # Get form fields
        #----------------
        $formFieldsMap = [];
        foreach ($this->getForms() as $form) {
            $includeCompleteField = true;
            $formFieldsMap[$form] = $this->getFormFields($form, $includeCompleteField);
        }
        $project['form_fields'] = $formFieldsMap;

        $project['events'] = $this->getEvents();
        // $project['event_info'] = $this->getEventInfo();
        $project['event_map'] = $this->getEventMap();

        # Get event forms
        $eventFormsMap = [];
        foreach ($this->getUniqueEventNames() as $event) {
            $eventFormsMap[$event] = $this->getEventForms($event);
        }
        $project['event_forms'] = $eventFormsMap;

        $project['repeating_forms'] = $this->getRepeatingInstruments();
        $project['repeating_events'] = $this->getRepeatingEvents();
        $project['fields'] = $fields;

        $projectJson = json_encode($project, JSON_PRETTY_PRINT);
        return $projectJson;
    }


    public function getBlankRecordRow($recordId = null, $event = null, $repeatForm = null, $repeatInstance = null)
    {
        $recordRow = [];
        $forms = $this->getForms();

        $isFirstField = true;
        foreach ($forms as $form) {
            $fields = $this->getFormTransferFields($form);

            foreach ($fields as $field) {
                $recordRow[$field] = '';
                if ($isFirstField) {
                    #------------------------------
                    # Add record id fields
                    #------------------------------
                    if ($this->isLongitudinal()) {
                        $recordRow[self::REDCAP_EVENT_NAME] = '';

                        if ($this->hasEventWithRepeatingForm()) {
                            $recordRow[self::REDCAP_REPEAT_INSTRUMENT] = '';
                        }

                        if ($this->hasRepeatingEvent() || $this->hasEventWithRepeatingForm()) {
                            $recordRow[self::REDCAP_REPEAT_INSTANCE] = '';
                        }
                    } else {
                        if ($this->hasRepeatingInstrumentsOrEvents()) {
                            $recordRow[self::REDCAP_REPEAT_INSTRUMENT] = '';
                            $recordRow[self::REDCAP_REPEAT_INSTANCE] = '';
                        }
                    }

                    #------------------------------
                    # Add DAG field (if any)
                    #------------------------------
                    if ($this->hasDags()) {
                        $recordRow[self::REDCAP_DATA_ACCESS_GROUP] = '';
                        // $recordRow[self::REDCAP_DATA_ACCESS_GROUP] = null;
                    }

                    $isFirstField = false;
                }
            }
        }

        if (!empty($recordId)) {
            $recordRow[array_key_first($recordRow)] = $recordId;
        }

        if (!empty($event)) {
            $recordRow[self::REDCAP_EVENT_NAME] = $event;
        }

        if (!empty($repeatForm)) {
            $recordRow[self::REDCAP_REPEAT_INSTRUMENT] = $repeatForm;
        }

        if (!empty($repeatInstance)) {
            $recordRow[self::REDCAP_REPEAT_INSTANCE] = $repeatInstance;
        }

        return $recordRow;
    }

    public function getFieldNames($includeCompleteFields = false)
    {
        $fieldNames = [];
        $fieldNames = array_column($this->metadata, 'field_name');

        if ($includeCompleteFields) {
            $previousFormName = $this->metadataMap[$fieldNames[0]];
            for ($i = 0; $i < count($fieldNames); $i++) {
                $fieldName = $fieldNames[$i];
                $formName = $this->metadataMap[$fieldName]['form_name'];

                if ($formName !== $previousFormName) {
                    array_splice($fieldNames, $i, 0, $formName . self::FORM_COMPLETE_SUFFIX);
                }

                $previousFormName = $formName;
            }
            $fieldNames[] = $fieldName . self::FORM_COMPLETE_SUFFIX; // Add last form complete field
        }

        return $fieldNames;
    }

    /**
     * Gets the field name for the record ID field of the project.
     */
    public function getRecordIdField()
    {
        $first = array_key_first($this->metadata);
        $recordIdField = $this->metadata[$first]['field_name'];

        return $recordIdField;
    }

    public function getSecondaryUniqueField()
    {
        $secondaryUniqueField = $this->projectInfo['secondary_unique_field'];
        return $secondaryUniqueField;
    }


    public function isApiProject()
    {
        $isApiProject = false;

        if (!empty($this->apiToken)) {
            $isApiProject = true;
        }

        return $isApiProject;
    }

    /**
     * Gets an array of rights for users of the project. Each element of the top-level
     * array represents the rights for one user of the project.
     */
    public function getUserRights()
    {
        $userRights = [];

        if ($this->isApiProject()) {
            $userRights = $this->redCapProject->exportUsers('php');
        } else {
            $userRights = $this->module->getProjectUserRights($this->pid);
        }

        return $userRights;
    }

    public function getUserRightsMap()
    {
        $userRightsMap = [];

        $userRights = $this->getUserRights();

        foreach ($userRights as $rights) {
            $userRightsMap[$rights['username']] = $rights;
        }

        return $userRightsMap;
    }

    /**
     * Gets the records from the project as a map from record ID
     * to the rows for that record ID's rows.
     *
     * @return array a map from record ID to the data rows for that record ID.
     */
    public function exportDataMapAp($parameters = [])
    {
        $dataMap = [];

        $data = $this->exportDataAp($parameters);

        foreach ($data as $row) {
            $recordId = $row[array_key_first($row)];
            if (!array_key_exists($recordId, $dataMap)) {
                $dataMap[$recordId] = [];
            }
            $dataMap[$recordId][] = $row;
        }

        return $dataMap;
    }

    /**
     * Exports data from the project using a single array parameter (AP) to specify all of
     * the export options. Options available:
     *
     * format
     * recordIds
     * fields
     * filterLogic
     */
    public function exportDataAp($parameters = [])
    {
        $data = [];

        #---------------------------------------
        # Process parameters (if any)
        #---------------------------------------
        $format      = 'php';
        $recordIds   = null;
        $fields      = null;
        $filterLogic = null;

        if (!empty($parameters)) {
            if (array_key_exists('format', $parameters)) {
                $format = $parameters['format'];
            }

            if (array_key_exists('recordIds', $parameters)) {
                $recordIds = $parameters['recordIds'];
            }

            if (array_key_exists('fields', $parameters)) {
                $fields = $parameters['fields'];
            }

            if (array_key_exists('filterLogic', $parameters)) {
                $filterLogic = $parameters['filterLogic'];
            }
        }


        if ($this->isApiProject()) {
            $data = $this->redCapProject->exportRecordsAp(
                [
                    'format'                 => $format,
                    'recordIds'              => $recordIds,
                    'fields'                 => $fields,
                    'filterLogic'            => $filterLogic,
                    'exportDataAccessGroups' => true
                ]
            );
        } else {
            # Local project accessed using REDCap developer methods.

            if ($format === 'php') {
                # Unfortunately, the array data format returned by the developer method does
                # not match the format returned by the API method, so get the JSON format
                # and then decode the JSON to generate the same format returned by the API
                # method.
                $json = \REDCap::getData(
                    [
                        'project_id'    => $this->pid,
                        'return_format' => 'json',
                        'records'       => $recordIds,
                        'fields'        => $fields,
                        'filterLogic'   => $filterLogic,
                        'exportDataAccessGroups' => true
                    ]
                );

                $data = json_decode($json, true);
            } else {
                # format other than php
                $data = \REDCap::getData(
                    [
                        'project_id'    => $this->pid,
                        'return_format' => $format,
                        'records'       => $recordIds,
                        'fields'        => $fields,
                        'filterLogic'   => $filterLogic,
                        'exportDataAccessGroups' => true
                    ]
                );
            }
        }

        return $data;
    }

    /**
     * Exports the specified data from the project.
     *
     * @param $groups mixed an array or single DAG (Data Access Group).
     */
    public function exportData($records = null, $fields = null, $events = null, $groups = null, $filterLogic = null)
    {
        $data = [];

        if ($this->isApiProject()) {
            # If this is an API project, use PHPCap to get the data

            $format = 'php';
            $type   = 'flat';
            $forms  = null;   // Not supported by REDCap::getData method ???

            $data = $this->redCapProject->exportRecordsAp(
                [
                    'format'    => 'php',
                    'recordIds' => $records,
                    'fields'    => $fields,
                    'events'    => $events,

                    'filterLogic' => $filterLogic,

                    'exportDataAccessGroups' => true
                ]
            );

            # Only include rows of data from the specified DAGs (data access groups).
            #
            # If there are any DAGs, then the field "redcap_data_access_group" should
            # be set to the unique data access group for the record (if any)
            if (is_array($groups)) {
                foreach ($groups as $group) {
                }
                # array of unique group names (strings) or group ids (ints)
            } elseif (!empty($groups) && (is_int($groups) || is_string($groups))) {
                # single value unique group name (string) or group id (int)
            }
        } else {
            # Local project accessed using REDCap developer methods.
            #
            # Unfortunately, the array data format returned by the developer method does
            # not match the format returned by the API method, so get the JSON format
            # and then decode the JSON to generate the same format returned by the API
            # method.
            $returnFormat = 'json';
            $combineCheckboxValue = false;
            $exportDataAccessGroups = true;
            $json = \REDCap::getData(
                [
                    'project_id'    => $this->pid,
                    'return_format' => $returnFormat,
                    'records'       => $records,
                    'fields'        => $fields,
                    'events'        => $events,
                    'groups'        => $groups,
                    'filterLogic'   => $filterLogic,
                    'combine_checkbox_values' => $combineCheckboxValues,
                    'exportDataAccessGroups'  => $exportDataAccessGroups
                ]
            );

            $data = json_decode($json, true);
        }

        return $data;
    }

    /**
     * Imports the specified data into the project.
     *
     * @param $overwriteBehavior "normal" or "overwrite"
     */
    public function importData($data, $format, $overwriteBehavior = 'normal')
    {
        $status = null;

        $type = 'flat';

        if ($this->isApiProject()) {
            $status = $this->redCapProject->importRecords($data, $format, $type, $overwriteBehavior);
        } else {
            $status = \REDCap::saveData(
                [
                    'project_id' => $this->pid,
                    'dataFormat' => $format,
                    'data'       => $data,
                    'overwriteBehavior' => $overwriteBehavior,
                    'performAutoCalc'   => true,
                    'type'       => 'flat'
                ]
            );

            if (!empty($status) && is_array($status) && array_key_exists('errors', $status)) {
                $errors = $status['errors'];
                if (!is_array($errors)) {
                    throw new \Exception("This following data import error(s) occurred. " . $errors);
                } elseif (count($errors) > 0) {
                    $error = implode(' ', $errors);
                    throw new \Exception("This following data import error(s) occurred. " . $error);
                }
            }
        }

        return $status;
    }


    /**
     * Exports a file from the project.
     *
     * @param string recordId the ID of the record containing the file.
     * @param string field the field in the record containing the file.
     *
     * @return array numerically indexed array with 3 elements where the first (index 0) is the mime-type,
     *     the second is the original file name, and the third is the file contents.
     *     And optional fourth element, which is the doc ID, is returned for the
     *     case where the project is NOT an API project; access is through
     *     the developer methods.
     *     If there is no file, then a blank array is returned or an exception is thrown.
     */
    public function exportFile($recordId, $field, $event = null, $repeatInstance = null)
    {
        $fileData = [];

        if ($this->isApiProject()) {
            # Acces to project is using the API; the project could be in another REDCap instance.
            $fileInfo = array();
            $fileContents = $this->redCapProject->exportFile($recordId, $field, $event, $repeatInstance, $fileInfo);
            $fileData = [$fileInfo['mime_type'], $fileInfo['name'], $fileContents];
        } else {
            # Project is in local REDCap and access for it is with REDCap developer methods

            # Note: the value in redcap_data for the file will be the doc_id; use getFile method to retrieve the file
            # based on the doc_id
            # Note: The information for the file, based on doc_id, is in table redcap_edocs_metadata.

            $parameters = [
                'project_id'    => $this->pid,
                'return_format' => 'array',
                'records'       => $recordId,
                'fields'        => $field,
            ];

            $eventId = null;
            if (!empty($event)) {
                $eventId = $this->getEventIdFromUniqueEventName($event);
                $parameters['events'] = $eventId;
            }

            $data = \REDCap::getData(
                $parameters
                # [
                #     'project_id'    => $this->pid,
                #     'return_format' => 'csv',
                #     'records'       => $recordId,
                #     'fields'        => $field,
                #     'events'        => $event,
                # ]
            );

            # error_log("\n[{$recordId}] {$event}:{$repeatInstance} DATA: \"{$data}\"\n", 3, __DIR__ . '/../doc.log');
            # error_log("\n\nPARAMETERS:\n" . print_r($parameters, true) . "\n", 3, __DIR__ . '/../data.log');

            #--------------------------------------------------------------------------------------------------------
            # Split into an array where element zero will be the name of the field, and element one is the doc ID,
            # or, for multi-instance records, element i is the doc ID for instance i.
            #--------------------------------------------------------------------------------------------------------
            # $dataValues = explode("\n", $data);

            if (empty($repeatInstance)) {
                # $docId = (int) trim($dataValues[1]);
                # Still need event ID for non-longitudinal case????
                if (!empty($data)) {
                    $record = $data[$recordId] ?? null;
                    if (!empty($record)) {
                        $eventId = array_key_first($record);
                        $event = $record[$eventId] ?? null;
                        if (!empty($event)) {
                            $docId = $event[$field];
                            $fileData = \REDCap::getFile($docId);
                            $fileData[] = $docId;
                        }
                    }
                }
            } else {
                if (!empty($data)) {
                    $record = $data[$recordId] ?? null;
                    if (!empty($record)) {
                        $repeatInstances = $record['repeat_instances'] ?? null;
                        if (!empty($repeatInstances)) {
                            if (empty($eventId)) {
                                $eventId = array_key_first($repeatInstances);
                            }

                            $event = $repeatInstances[$eventId] ?? null;
                            if (!empty($event)) {
                                $form = $this->getFieldForm($field);
                                $instances = $event[$form] ?? null;
                                if (!empty($instances)) {
                                    $fields = $instances[$repeatInstance] ?? null;
                                    if (!empty($fields)) {
                                        $value = $fields[$field] ?? null;
                                        if (!empty($value)) {
                                            $docId = $value;
                                            $fileData = \REDCap::getFile($docId);
                                            $fileData[] = $docId;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $fileData;
    }

    /**
     * Gets the doc ID of the specified data record file, but this method only works for
     * projects accessed using developer methods (and not API methods).
     */
    public function getFileDocId($recordId, $field, $event = null, $repeatInstance = null)
    {
        $docId = null;

        if ($this->isApiProject()) {
            throw new \Exception("Can't get the doc ID for a file in an API-accessed project.");
        } else {
            # Project is in local REDCap and access for it is with REDCap developer methods

            # Note: the value in redcap_data for the file will be the doc_id; use getFile method to retrieve the file
            $data = \REDCap::getData(
                [
                    'project_id'    => $this->pid,
                    'return_format' => 'csv',
                    'records'       => $recordId,
                    'fields'        => $field,
                    'events'        => $event,
                ]
            );
            $docId = (int) substr($data, strlen($field)); // TODO check this substr (what if column name
                                                          // is shorter than doc ID???)
        }

        return $docId;
    }


    /**
     * Imports the specified file into the specified project at the specified location.
     *
     * @param string filename the path to the file.
     * @param string recordId the ID of the record where the file is to be inserted.
     * @param string field the name of the field where the file is to be inserted.
     */
    public function importFile($filename, $recordId, $field, $event = null, $repeatInstance = null)
    {
        if ($this->isApiProject()) {
            $status = $this->redCapProject->importFile($filename, $recordId, $field, $event, $repeatInstance);
        } else {
            $docId = \REDCap::storeFile($filename, $this->pid);
            if ($docId === 0) {
                throw new \Exception("Unable to store file \"{$filename}\" in REDCap [pid={$this->pid}].");
            }

            # Need to get the event ID, because that is what the REDCap method uses
            $eventId = $this->getEventIdFromUniqueEventName($event);

            # error_log("[{$recordId}] {$event}:{$repeatInstance} {$field}\n", 3, __DIR__ . '/../transfer.log');

            $status = \REDCap::addFileToField($docId, $this->pid, $recordId, $field, $eventId, $repeatInstance);

            if ($status === false) {
                $message = "Unable to add file \"{$filename}\" to field \"{$field}\"";

                if (!empty($event)) {
                    $message .= " for event \"{$event}\"";
                }

                if (!empty($repeatInstance)) {
                    $message .= " for instance \"{$repeatInstance}\"";
                }

                $message .= " in record \"{$recordId}\" for project with pid = {$this->pid}.";
                throw new \Exception($message);
            }
        }

        return $status;
    }

    /**
     * Deletes the specified file from the project.
     */
    public function deleteFile($recordId, $field, $event = null, $repeatInstance = null)
    {
        if ($this->isApiProject()) {
            $this->redCapProject->deleteFile($recordId, $field, $event, $repeatInstance);
        } else {
            $this->module->deleteFile($this->pid, $recordId, $field, $event, $repeatInstance);
        }
    }

    public function exportFormEventsMap()
    {
        $formEventsMap = [];

        if ($this->isApiProject()) {
            $instrumentEventMapping = $this->redCapProject->exportInstrumentEventMappings();

            foreach ($instrumentEventMapping as $map) {
                $form = $map['form'];
                $uniqueEventName = $map['unique_event_name'];
                if (!array_key_exists($form, $formEventsMap)) {
                    $formEventsMap[$form] = array();
                }
                $formEventsMap[$form][] = $uniqueEventName;
            }
        } else {
            $mapping = $this->module->getArmEventFormInfo($this->pid);

            foreach ($mapping as $map) {
                $form = $map['form_name'];
                $uniqueEventName = $map['unique_event_name'];
                if (!array_key_exists($form, $formEventsMap)) {
                    $formEventsMap[$form] = array();
                }
                $formEventsMap[$form][] = $uniqueEventName;
            }
        }

        return $formEventsMap;
    }

    public function getMetadataXml()
    {
        return $this->metadataXml;
    }

    /**
     * Gets a map from event unqiue name to event information.
     */
    public function createEventMap()
    {
        $eventMap = [];

        $eventInfos = $this->getEventInfo();
        foreach ($eventInfos as $eventInfo) {
            $event = [];

            $uniqueEventName = $eventInfo['unique_event_name'];

            $event['unique_event_name'] = $uniqueEventName;
            $event['event_name']        = $eventInfo['event_name'];

            if ($eventInfo['repeating'] === 'Yes') {
                $event['repeating'] = true;
            } else {
                $event['repeating'] = false;
            }

            $event['repeating_forms'] = false;
            if (array_key_exists('forms', $eventInfo) && !empty($eventInfo['forms'])) {
                $firstKey = array_key_first($eventInfo['forms']);
                if ($eventInfo['forms'][$firstKey]['repeating'] === 'Yes') {
                    $event['repeating_forms'] = true;
                }
            }

            $eventMap[$uniqueEventName] = $event;
        }

        return $eventMap;
    }

    public function getEventMap()
    {
        return $this->eventMap;
    }

    public function hasRepeatingEvent()
    {
        $hasRepeatingEvent = false;

        foreach ($this->eventMap as $eventUniqueName => $event) {
            if ($event['repeating']) {
                $hasRepeatingEvent = true;
                break;
            }
        }
        return $hasRepeatingEvent;
    }

    public function hasEventWithRepeatingForm()
    {
        $hasEventWithRepeatingForm = false;

        foreach ($this->eventMap as $eventUniqueName => $event) {
            if ($event['repeating_forms']) {
                $hasEventWithRepeatingForm = true;
                break;
            }
        }
        return $hasEventWithRepeatingForm;
    }

    public function hasUniqueEventName($uniqueEventName)
    {
        $hasName = false;
        if (array_key_exists($uniqueEventName, $this->eventMap)) {
            $hasName = true;
        }

        return $hasName;
    }

    public function hasForm($form)
    {
        $hasForm = false;

        if (array_key_exists($form, $this->formFieldsMap)) {
            $hasForm = true;
        }

        return $hasForm;
    }

    public function getEventName($uniqueEventName)
    {
        $eventName = null;

        if (array_key_exists($uniqueEventName, $this->eventMap)) {
            $event = $this->eventMap[$uniqueEventName];
            $eventName = $event['event_name'];
        }

        return $eventName;
    }

    public function isEventNonRepeating($uniqueEventName)
    {
        $isNonRepeating = false;

        if (array_key_exists($uniqueEventName, $this->eventMap)) {
            $event = $this->eventMap[$uniqueEventName];
            $isNonRepeating = (!$event['repeating']) && (!$event['repeating_form']);
        }

        return $isNonRepeating;
    }

    public function isEventRepeating($uniqueEventName)
    {
        $isRepeating = false;

        if (array_key_exists($uniqueEventName, $this->eventMap)) {
            $event = $this->eventMap[$uniqueEventName];
            $isRepeating = $event['repeating'];
        }

        return $isRepeating;
    }

    public function areEventFormsRepeating($uniqueEventName)
    {
        $areFormsRepeating = false;

        if (array_key_exists($uniqueEventName, $this->eventMap)) {
            $event = $this->eventMap[$uniqueEventName];
            $areFormsRepeating = $event['repeating_forms'];
        }

        return $areFormsRepeating;
    }

    /**
     * Indicates if the event and form are repeating. If the event
     * is empty, the check is for a non-longitudinal repeating form.
     */
    public function isRepeating($event, $form)
    {
        $isRepeating = false;

        if (empty($event)) {
            $isRepeating = $this->repeatingFormsMap[$form] ?? false;
        } else {
            $isRepeating = ($this->repeatingEventsMap[$event] ?? false)
                || ($this->repeatingFormsInEventsMap[$event][$form] ?? false);
        }

        return $isRepeating;
    }

    public function isRepeatingForm($event, $form)
    {
        $isRepeating = false;

        if (empty($event)) {
            $isRepeating = $this->repeatingFormsMap[$form] ?? false;
        } else {
            $isRepeating = $this->repeatingFormsInEventsMap[$event][$form] ?? false;
        }

        return $isRepeating;
    }

    public function getEventForms($event)
    {
        $forms = [];

        if (array_key_exists($event, $this->eventInfo)) {
            $info = $this->eventInfo[$event];
            if (!empty($info) && array_key_exists('forms', $info)) {
                $infoForms = $info['forms'];
                if (!empty($infoForms)) {
                    $forms = array_keys($infoForms);
                }
            }
        }

        return $forms;
    }

    public function getEventFields($event)
    {
        $fields = [];

        $forms = $this->getEventForms($event);

        foreach ($forms as $form) {
            $formFields = $this->getFormFields($form);
            $fields = array_merge($fields, $formFields);
        }

        return $fields;
    }

    public function getEventTransferFields($event)
    {
        $fields = [];

        $forms = $this->getEventForms($event);

        foreach ($forms as $form) {
            $formFields = $this->getFormTransferFields($form);
            $fields = array_merge($fields, $formFields);
        }

        return $fields;
    }

    public function createEventInfo()
    {
        $eventInfo = [];

        #-------------------------------------------------------------
        # Get the event information from the project's metadata XML
        #-------------------------------------------------------------
        $eventNodes = $this->metadataXmlDom->getElementsByTagName('StudyEventDef');

        foreach ($eventNodes as $eventNode) {
            $event = [];

            $eventName = $eventNode->getAttribute('redcap:EventName');
            $event['event_name'] = $eventName;

            $uniqueEventName = $eventNode->getAttribute('redcap:UniqueEventName');
            $event['unique_event_name'] = $uniqueEventName;

            $repeating = $eventNode->getAttribute('Repeating');
            $event['repeating'] = $repeating;

            # Process forms
            $formNodes = $eventNode->getElementsByTagName('FormRef');
            $forms = [];
            foreach ($formNodes as $formNode) {
                $form = [];
                $formName = $formNode->getAttribute('redcap:FormName');
                $form['form_name'] = $formName;
                $form['repeating'] = 'No';   // Set default value, which may get updated
                $forms[$formName] = $form;
            }
            $event['forms'] = $forms;

            $eventInfo[$uniqueEventName] = $event;
        }

        #--------------------------------------------
        # Update repeating form status
        #--------------------------------------------
        $repeatingForms = $this->getRepeatingInstruments();
        foreach ($repeatingForms as $repeatingForm) {
            if (is_array($repeatingForm) && array_key_exists('unique_event_name', $repeatingForm)) {
                $uniqueEventName = $repeatingForm['unique_event_name'];
                $form            = $repeatingForm['form'];
                if (array_key_exists($uniqueEventName, $eventInfo)) {
                    if (array_key_exists($form, $eventInfo[$uniqueEventName]['forms'])) {
                        $eventInfo[$uniqueEventName]['forms'][$form]['repeating'] = 'Yes';
                    }
                }
            }
        }

        return $eventInfo;
    }

    public function createRepeatingFormsMap()
    {
        $repeatingForms = $this->getRepeatingInstruments();
        $map = [];

        if (!$this->isLongitudinal()) {
            foreach ($repeatingForms as $repeatingForm) {
                $map[$repeatingForm] = true;
            }
        }

        return $map;
    }

    public function getRepeatingFormsMap()
    {
        return $this->repeatingFormsMap;
    }

    public function createRepeatingEventsMap()
    {
        $repeatingEvents = $this->getRepeatingEvents();
        $map = [];

        foreach ($repeatingEvents as $repeatingEvent) {
            $map[$repeatingEvent] = true;
        }

        return $map;
    }

    public function createRepeatingFormsInEventsMap()
    {
        $map = [];

        $eventInfo = $this->getEventInfo() ?? [];

        foreach ($eventInfo as $eventName => $info) {
            $forms = $info['forms'] ?? [];
            foreach ($forms as $formName => $formInfo) {
                if ($formInfo['repeating'] === 'Yes') {
                    $map[$eventName][$formName] = true;
                }
            }
        }

        return $map;
    }

    public function getRepeatingFormsInEventsMap()
    {
        return $this->repeatingFormsInEventsMap;
    }

    public function getRepeatingEventsMap()
    {
        return $this->repeatingEventsMap;
    }

    public function getEventInfo()
    {
        return $this->eventInfo;
    }

    public function getRepeatingInstruments()
    {
        $repeatingInstruments = array();

        $repeatingInstrumentNodes = $this->metadataXmlDom->getElementsByTagNameNS(
            self::REDCAP_XML_NAMESPACE,
            'RepeatingInstrument'
        );

        foreach ($repeatingInstrumentNodes as $instrumentNode) {
            $instrumentName = $instrumentNode->getAttribute('redcap:RepeatInstrument');

            if ($this->isLongitudinal()) {
                $eventName      = $instrumentNode->getAttribute('redcap:UniqueEventName');
                $entry = array();
                $entry['form'] = $instrumentName;
                $entry['unique_event_name'] = $eventName;
                array_push($repeatingInstruments, $entry);
            } else {
                array_push($repeatingInstruments, $instrumentName);
            }
        }
        return $repeatingInstruments;
    }

    /**
     * Gets the unique event names for the events containing the speified form.
     *
     * @param string $form the name of the form for which events are to be retrieved.
     */
    public function getFormEvents($form)
    {
        $events = [];

        if ($this->isLongitudinal()) {
            foreach ($this->eventInfo as $uniqueEventName => $eventInfo) {
                $forms = $eventInfo['forms'];
                if (!empty($forms)) {
                    $formNames = array_column($forms, 'form_name');
                    if (in_array($form, $formNames)) {
                        $events[] = $uniqueEventName;
                    }
                }
            }
        }

        return $events;
    }


    /**
     * Gets the repeating events.
     *
     * @return arrray list or unique event names for the repeating events.
     */
    public function getRepeatingEvents()
    {
        $repeatingEvents = array();
        $repeatingEventNodes = $this->metadataXmlDom->getElementsByTagNameNS(
            self::REDCAP_XML_NAMESPACE,
            'RepeatingEvent'
        );

        foreach ($repeatingEventNodes as $eventNode) {
            $eventName = $eventNode->getAttribute('redcap:UniqueEventName');
            array_push($repeatingEvents, $eventName);
        }
        return $repeatingEvents;
    }

    public function hasDags()
    {
        return !empty($this->dags);
    }

    public function getDags()
    {
        return $this->dags;
    }

    public function getDagNames()
    {
        $dagNames = [];
        if (!empty($this->dags)) {
            $dagNames = array_column($this->dags, 'data_access_group_name');
        }

        return $dagNames;
    }

    public function getDagUniqueGroupNames()
    {
        $dagNames = [];
        if (!empty($this->dags)) {
            $dagNames = array_column($this->dags, 'unique_group_name');
        }

        return $dagNames;
    }

    public function getArms()
    {
        return $this->arms;
    }

    public function getDefinedArmNames()
    {
        $armNames = [];

        if ($this->isLongitudinal()) {
            $armNames = array_column($this->arms, 'name');
            sort($armNames);
        }

        return $armNames;
    }

    /**
     * @return array array of event information maps with keys:
     *     'event_name', 'arm_num', 'unique_event_name', 'custom_event_label', 'event_id'
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Returns a map from event ID to unique event name
     */
    public function getEventIdMap()
    {
        $map = [];
        foreach ($this->events as $event) {
            $map[$event['event_id']] = $event['unique_event_name'];
        }

        return $map;
    }

    public function getUniqueEventNameFromEventId($eventId)
    {
        $map = $this->getEventIdMap();
        $uniqueEventName = $map[$eventId] ?? null;

        return $uniqueEventName;
    }

    public function getEventIdFromUniqueEventName($uniqueEventName)
    {
        $eventId = null;
        # Note: POSSIBLE TODO create a map for this (?)
        foreach ($this->events as $event) {
            if ($uniqueEventName === $event['unique_event_name']) {
                $eventId = $event['event_id'];
                break;
            }
        }

        return $eventId;
    }

    public function getUniqueEventNames()
    {
        $uniqueEventNames = array_column($this->events, 'unique_event_name');
        return $uniqueEventNames;
    }

    public function getDefinedUniqueEventNames()
    {
        $uniqueEventNames = [];

        if ($this->isLongitudinal()) {
            $uniqueEventNames = array_column($this->events, 'unique_event_name');
        }

        return $uniqueEventNames;
    }

    /**
     * Gets a map from field name to form and event information for the field.
     *
     * For non-longitudinal projects, an array file the following keys is returned
     * for each field name:
     *
     * ['field_name']
     * ['form_name']
     * ['form_repeating']
     */
    public function createFieldFormEventMap()
    {
        $map = [];

        $metadataMap = $this->getMetadataMap();
        $fieldNames = $this->getFieldNames();

        if ($this->isLongitudinal()) {
            foreach ($fieldNames as $fieldName) {
                $fieldMetadata = $metadataMap[$fieldName];

                $formName = $fieldMetadata['form_name'];

                $info = [];
                $info['field_name']     = $fieldName;
                $info['form_name']      = $formName;

                $info['events'] = [];    // 'events' key only defined for longitudinal projects
                $events = $this->getFormEvents($formName);

                foreach ($events as $event) {
                    $eventInfo = $this->eventInfo[$event];

                    $eventData = [];
                    $eventData['unique_event_name'] = $event;
                    $eventData['event_name']        = $eventInfo['event_name'];
                    $eventData['repeating_event']   = ($eventInfo['repeating'] === 'Yes') ? true : false;

                    $formInfo = $eventInfo['forms'][$formName];
                    $eventData['repeating_form'] = ($formInfo['repeating'] === 'Yes') ? true : false;

                    $info['events'][] = $eventData;
                }

                $map[$fieldName] = $info;
            }
        } else {
            # non-longitudinal (classic) project
            $repeatingForms = $this->getRepeatingInstruments();

            foreach ($fieldNames as $fieldName) {
                $fieldMetadata = $metadataMap[$fieldName];

                $formName = $fieldMetadata['form_name'];

                $info = [];
                $info['field_name']     = $fieldName;
                $info['form_name']      = $formName;
                $info['form_repeating'] = in_array($formName, $repeatingForms);

                $map[$fieldName] = $info;
            }
        }

        return $map;
    }

    public function getFieldFormEventMap()
    {
        return $this->fieldFormEventMap;
    }

    /**
     * Gets the unique event names that contain the form of the specified field
     */
    public function getFieldEvents($field)
    {
        $events = [];

        $map = $this->fieldFormEventMap[$field];

        if (!empty($map)) {
            $fieldEvents = $map['events'];
            if (!empty($fieldEvents)) {
                $events = array_column($fieldEvents, 'unique_event_name');
            }
        }

        return $events;
    }

    /**
     * Gets the form name for the specified field name.
     */
    public function getFieldForm($field)
    {
        $form = $this->metadataMap[$field]['form_name'];
        return $form;
    }

    /**
     * Call this for fields if the checkbox is expanded
     */
    public function getTransferFieldForm($field)
    {
        // TODO have metadata based on transfer field name to get form as above
        $form = null;
        foreach ($this->formTransferFieldsMap as $formName => $fields) {
            if (in_array($field, $fields)) {
                $form = $formName;
                break;
            }
        }
        return $form;
    }

    /**
     * Gets the equivalent transfer fields. Transfer fields are
     * the fields used when importing and exporting data from
     * REDCap, and differ from metadata fields in that checkbox
     * fields are expanded to multiple fields, one for each
     * choice.
     *
     * Form complete fields only equivalent if form is in
     * both projects and they contain the same equivalent
     * fields. (???)
     */
    public function getEquivalentTransferFields($destinationProject)
    {
        $transferFields = [];
        $fields = $this->getEquivalentFields($destinationProject);

        foreach ($fields as $field) {
            $fieldType = $this->metadataMap[$field]['field_type'];

            if ($fieldType === 'checkbox') {
                # Expand checkbox fields
                # TODO add missing data codes
                $choicesString = $this->metadataMap[$field]['select_choices_or_calculations'];
                $choices = array_map('trim', explode("|", $choicesString));
                foreach ($choices as $choice) {
                    list ($value, $label) = array_map('trim', explode(",", $choice, 2));
                    $checkboxField = $field . self::CHECKBOX_SEPARATOR . strtolower($value);
                    $transferFields[] = $checkboxField;
                }
            } else {
                $transferFields[] = $field;
            }
        }

        #----------------------------------------------------------------
        # Add form complete fields for cases where the form is in
        # both projects
        #----------------------------------------------------------------
        $sourceForms = $this->getForms();
        $destinationForms = $destinationProject->getForms();
        $commonForms = array_intersect($sourceForms, $destinationForms);

        foreach ($commonForms as $commonForm) {
            $transferFields[] = $commonForm . self::FORM_COMPLETE_SUFFIX;
        }

        return $transferFields;
    }

    /**
     * Gets the equivalent variables/fields based on name and type that
     * this project could transfer to the specified destination project.
     */
    public function getEquivalentFields($destinationProject, $includeRecordId = true)
    {
        $equivalentFields = [];

        $includeCompleteFields = true;
        $sourceFieldNames = $this->getFieldNames($includeCompleteFields);

        $recordIdField = $this->getRecordIdField();

        foreach ($sourceFieldNames as $fieldName) {
            if ($this->hasEquivalentField($destinationProject, $fieldName)) {
                if ($includeRecordId || $fieldName !== $recordIdField) {
                    $equivalentFields[] = $fieldName;
                }
            }
        }

        return $equivalentFields;
    }

    public function hasEquivalentField($destinationProject, $fieldName)
    {
        $hasEquivalent = false;

        $sourceFieldMetadata      = $this->getFieldMetadata($fieldName);
        $destinationFieldMetadata = $destinationProject->getFieldMetadata($fieldName);

        if (!empty($sourceFieldMetadata) && !empty($destinationFieldMetadata)) {
            $sourceVariable      = new Variable(
                $sourceFieldMetadata,
                $this->missingDataCodes,
                $this->getFieldActionTags($fieldName)
            );
            $destinationVariable = new Variable(
                $destinationFieldMetadata,
                $destinationProject->getMissingDataCodes(),
                $destinationProject->getFieldActionTags($fieldName)
            );
            $cmp = $sourceVariable->compareVariable($destinationVariable);
            if ($cmp === Variable::TYPES_EQUAL) {
                $hasEquivalent = true;
            }
        } else {
            # Check for complete fields
            if (str_ends_with($fieldName, self::FORM_COMPLETE_SUFFIX)) {
                $formName = substr($fieldName, 0, strlen($fieldName) - strlen(self::FORM_COMPLETE_SUFFIX));
                if (array_key_exists($formName, $this->formFieldsMap)) {
                    if (array_key_exists($formName, $destinationProject->formFieldsMap)) {
                        $hasEquivalent = true;
                    }
                }
            }
        }

        return $hasEquivalent;
    }

    /**
     * Gets the compatible variables/fields based on name and type that
     * this project could transfer to the specified destination project.
     */
    public function getCompatibleFields($destinationProject, $includeRecordId = true)
    {
        $compatibleFields = [];

        $includeCompleteFields = true;
        $sourceFieldNames = $this->getFieldNames($includeCompleteFields);

        $recordIdField = $this->getRecordIdField();

        foreach ($sourceFieldNames as $fieldName) {
            if ($this->hasCompatibleField($destinationProject, $fieldName)) {
                if ($includeRecordId || $fieldName !== $recordIdField) {
                    $compatibleFields[] = $fieldName;
                }
            }
        }

        return $compatibleFields;
    }

    /**
     * Indicates if the destination project for this (source) project
     * has a field with the specified field name that is compatible.
     */
    public function hasCompatibleField($destinationProject, $fieldName)
    {
        $hasCompatible = false;

        $sourceFieldMetadata      = $this->getFieldMetadata($fieldName);
        $destinationFieldMetadata = $destinationProject->getFieldMetadata($fieldName);

        if (!empty($sourceFieldMetadata) && !empty($destinationFieldMetadata)) {
            $sourceVariable      = new Variable(
                $sourceFieldMetadata,
                $this->missingDataCodes,
                $this->getFieldActionTags($fieldName)
            );
            $destinationVariable = new Variable(
                $destinationFieldMetadata,
                $destinationProject->getMissingDataCodes(),
                $destinationProject->getFieldActionTags($fieldName)
            );
            $cmp = $sourceVariable->compareVariable($destinationVariable);
            if ($cmp === Variable::TYPES_EQUAL || $cmp === Variable::TYPES_COMPATIBLE) {
                $hasCompatible = true;
            }
        } else {
            # Check for complete fields
            if (str_ends_with($fieldName, self::FORM_COMPLETE_SUFFIX)) {
                $formName = substr($fieldName, 0, strlen($fieldName) - strlen(self::FORM_COMPLETE_SUFFIX));
                if (array_key_exists($formName, $this->formFieldsMap)) {
                    if (array_key_exists($formName, $destinationProject->formFieldsMap)) {
                        $hasCompatible = true;
                    }
                }
            }
        }

        return $hasCompatible;
    }

    /**
     * Gets a row from the specified record with the specified identifier fields.
     *
     * For longitudinal study:
     *     returns row matching event (or none if no event specified)
     *
     * For non-longitudinal:
     *     returns non-repeating forms row, if no repeat form specified
     */
    public function getRowIndex($record, $event, $repeatForm, $repeatInstance)
    {
        $rowIndex = self::ROW_NOT_FOUND;

        if ($this->isLongitudinal()) {
            if (!empty($event)) {
                if (!empty($repeatForm) && !empty($repeatInstance)) {
                    # Both repeating form and instance specified; looking for repeating form in event

                    for ($i = 0; $i < count($record); $i++) {
                        $row = $record[$i];

                        $rowEvent          = $row['redcap_event_name'] ?? null;
                        $rowRepeatForm     = $row['redcap_repeat_instrument'] ?? null;
                        $rowRepeatInstance = $row['redcap_repeat_instance'] ?? null;

                        if (
                            $rowEvent === $event
                            && $rowRepeatForm === $repeatForm
                            && $rowRepeatInstance === $repeatInstance
                        ) {
                            $rowIndex = $i;
                            break;
                        }
                    }
                } elseif (!empty($repeatInstance)) {
                    # Repeat instance (but not repeat form) specified; looking for repeating event
                    for ($i = 0; $i < count($record); $i++) {
                        $row = $record[$i];

                        $rowEvent          = $row['redcap_event_name'] ?? null;
                        $rowRepeatForm     = $row['redcap_repeat_instrument'] ?? null;
                        $rowRepeatInstance = $row['redcap_repeat_instance'] ?? null;

                        if ($rowEvent === $event && empty($rowRepeatForm) && $rowRepeatInstance === $repeatInstance) {
                            $rowIndex = $i;
                            break;
                        }
                    }
                } else {
                    # Neither repeat form or repeat instance specified; looking for non-repeating event
                    for ($i = 0; $i < count($record); $i++) {
                        $row = $record[$i];

                        $rowEvent          = $row['redcap_event_name'] ?? null;
                        $rowRepeatForm     = $row['redcap_repeat_instrument'] ?? null;
                        $rowRepeatInstance = $row['redcap_repeat_instance'] ?? null;

                        if ($rowEvent === $event && empty($rowRepeatForm) && empty($rowRepeatInstance)) {
                            $rowIndex = $i;
                            break;
                        }
                    }
                }
            }
        } else {
            # Non-longitudinal (classic); ignore event specification
            if (empty($repeatForm)) {
                # not requesting a repeat form, so return the row (there should only be one)
                # that has no, or a blank, repeating form
                for ($i = 0; $i < count($record); $i++) {
                    $row = $record[$i];
                    $rowRepeatForm = $row['redcap_repeat_instrument'] ?? null;
                    if (empty($rowRepeatForm)) {
                        $rowIndex = $i;
                        break;
                    }
                }
            } else {
                for ($i = 0; $i < count($record); $i++) {
                    $row = $record[$i];
                    $rowRepeatForm     = $row['redcap_repeat_instrument'] ?? null;
                    $rowRepeatInstance = $row['redcap_repeat_instance'] ?? null;
                    if ($rowRepeatForm === $repeatForm && $rowRepeatInstance === $repeatInstance) {
                        $rowIndex = $i;
                        break;
                    }
                }
            }
        }

        return $rowIndex;
    }

    /**
     * Gets ia map from instance numbers in the specified record for the specified
     * event and form to the row number in the record.
     */
    public function getInstanceMap($record, $event, $form)
    {
        $instanceMap = [];

        if (!empty($record)) {
            for ($rowNumber = 0; $rowNumber < count($record); $rowNumber++) {
                $row = $record[$rowNumber];

                $rowEvent          = $row[Project::REDCAP_EVENT_NAME] ?? null;
                $rowRepeatForm     = $row[Project::REDCAP_REPEAT_INSTRUMENT] ?? null;
                $rowRepeatInstance = $row[Project::REDCAP_REPEAT_INSTANCE] ?? null;

                if (empty($event)) {
                    # No event specified, so this should be non-longitudinal
                    if (empty($form)) {
                        # No form or event, so not repeating instances
                    } else {
                        # Repeting Form (non-longitudinal)
                        if (empty($rowEvent) && $form === $rowRepeatForm) {
                            $instanceMap[$rowRepeatInstance] = $rowNumber;
                        }
                    }
                } else {
                    # Event specified, so this should be longitudinal
                    if (empty($form)) {
                        # Repeating Event (no form specified)
                        if (empty($rowRepeatForm) && $event === $rowEvent) {
                            $instanceMap[$rowRepeatInstance] = $rowNumber;
                        }
                    } else {
                        # Repeating Form in Event
                        if ($event === $rowEvent && $form === $rowRepeatForm) {
                            $instanceMap[$rowRepeatInstance] = $rowNumber;
                        }
                    }
                }
            }
        }

        return $instanceMap;
    }

    public function getFirstInstance($record, $event, $form)
    {
        $firstInstance = null;

        $map = $this->getInstanceMap($record, $event, $form);

        if (!empty($map)) {
            $instances = array_keys($map);
            sort($instances);
            $firstInstance = $instances[0];
        }

        return $firstInstance;
    }

    public function getLastInstance($record, $event, $form)
    {
        $lastInstance = null;

        $map = $this->getInstanceMap($record, $event, $form);

        if (!empty($map)) {
            $instances = array_keys($map);
            sort($instances);
            $lastInstance = end($instances);
        }

        return $lastInstance;
    }

    /**
     * Get the field names for the fields in a data row returned for a record from REDCap.
     * Rows will contain keys for all fields in the project, but only some may actually
     * be used in the row.
     */
    public function getTransferFieldsInRow($row, $includeRecordId = false)
    {
        $fields = [];

        $event      = null;
        $repeatForm = null;

        $event      = $row[self::REDCAP_EVENT_NAME] ?? null;
        $repeatForm = $row[self::REDCAP_REPEAT_INSTRUMENT] ?? null;

        $fields = $this->getTransferFields($event, $repeatForm, $includeRecordId);

        return $fields;
    }

    /**
     * Gets the REDCap data fields for the specified identifier fields. This can be used
     * to identify the fields that belong to a record data row returned from REDCap.
     *
     * Need to eliminate the "record ID" field. It is handled separately.
     */
    public function getTransferFields($event = null, $repeatForm = null, $includeRecordId = false)
    {
        $fields = [];

        if ($this->isLongitudinal()) {
            if (empty($event)) {
                throw new \Exception("No event specified for longitudinal project \"{$this->getTitle()}\".");
            } elseif (!$this->hasUniqueEventName($event)) {
                throw new \Exception("Event \"{$event}\" is not in project \"{$this->getTitle()}\".");
            } else {
                # Check event type (standard, repeating, repeating forms)
                # repeating forms will only have field for that repeating form; others will have all event forms
                if ($this->areEventFormsRepeating($event)) {
                    if (empty($repeatForm)) {
                        $message = "No repeat form specified for Event \"{$event}\""
                           . " in project \"{$this->getTitle()}\".";
                        throw new \Exception($message);
                    } else {
                        $fields = $this->getFormTransferFields($repeatForm);
                    }
                } else {
                    # Get fields for event
                    $fields = $this->getEventTransferFields($event);
                }
            }
        } else {
            # Non-longitudinal project
            if (empty($repeatForm)) {
                # include all fields not in a repeating form
                $fields = $this->getNonRepeatFormTransferFields();
            } else {
                # include fields in the specified repeating form
                $fields = $this->getFormTransferFields($repeatForm);
            }
        }

        if (!$includeRecordId) {
            $fields = array_diff($fields, [$this->getRecordIdField()]);
        }

        return $fields;
    }
}
