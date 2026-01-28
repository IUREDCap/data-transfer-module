<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use IU\PHPCap\RedCapProject;

/**
 * Class for representing a field mapping from the source project to the destination project.
 */
class FieldMapping
{
    /** @var string the source project's unique event name for the field mapping */
    private $sourceEvent;
    private $sourceForm;
    private $sourceField;
    # private $sourceInstance;

    private $destinationEvent;
    private $destinationForm;
    private $destinationField;
    # private $destinationInstance;

    /** @var boolean indicates if the specified destination field(s) should be
     * excluded from data transfer.
     */
    private $excludeDestination;

    public const ALL        = 'ALL';
    public const COMPATIBLE = 'COMPATIBLE';
    public const EQUIVALENT = 'EQUIVALENT';
    public const FIRST      = 'FIRST';
    public const LAST       = 'LAST';
    public const MATCHING   = 'MATCHING';


    public function __construct()
    {
        $this->sourceEvent    = '';
        $this->sourceForm     = '';
        $this->sourceField    = '';

        $this->destinationEvent    = '';
        $this->destinationForm     = '';
        $this->destinationField    = '';

        $this->excludeDestination = false;
    }

    public function isIncomplete($sourceProject, $destinationProject)
    {
        $fieldMappingStatus = $this->check($sourceProject, $destinationProject);

        $incomplete = $fieldMappingStatus->isIncomplete();

        return $incomplete;
    }

    public function isError($sourceProject, $destinationProject)
    {
        $fieldMappingStatus = $this->check($sourceProject, $destinationProject);

        $isError = $fieldMappingStatus->isError();

        return $isError;
    }

    /*
     * Checks the field mapping for missing values and errors.
     *
     * MAYBE: just check for valid names and compatible fields (not for valid form for event, etc.).
     * Then also indicate in interface the count of transformations, so users can see mappings
     * that generate zero transfers.
     *
     * @param Project sourceProject the source project for the data transfer.
     * @param Project destinationProject the destination project for the data transfer.
     *
     * @return FieldMappingStatus the status of the field mapping.
     */
    public function check($sourceProject, $destinationProject)
    {
        $mappingStatus = new FieldMappingStatus();
        $mappingStatus->setStatus(FieldMappingStatus::OK);

        #==============================================
        # Process source values
        #==============================================
        if (!$this->excludeDestination) {
            # If this is not an "exclude destination" mapping (which only have destination values).

            #---------------------
            # Process source event
            #---------------------
            if ($sourceProject->isLongitudinal()) {
                if ($this->sourceEvent === self::ALL) {
                    $mappingStatus->mergeStatus(FieldMappingStatus::OK);
                } elseif ($sourceProject->hasUniqueEventName($this->sourceEvent)) {
                    $mappingStatus->mergeStatus(FieldMappingStatus::OK);
                } elseif (empty($this->sourceEvent)) {
                    $mappingStatus->mergeStatus(FieldMappingStatus::INCOMPLETE);
                } else {
                    $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                    $mappingStatus->addError("Mapping source event \"{$this->sourceEvent}\" is undefined.");
                }
            }

            #--------------------
            # Process source form
            #--------------------
            if ($this->sourceForm === self::ALL) {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            } elseif ($sourceProject->hasForm($this->sourceForm)) {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            } elseif (empty($this->sourceForm)) {
                $mappingStatus->mergeStatus(FieldMappingStatus::INCOMPLETE);
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $mappingStatus->addError("Mapping source form \"{$this->sourceForm}\" is undefined.");
            }

            #---------------------
            # Process source field
            #---------------------
            // Check for recordId field and indicate as an error for destination (but NOT source)
            if ($this->sourceField === self::ALL) {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            } elseif (empty($this->sourceField)) {
                $mappingStatus->mergeStatus(FieldMappingStatus::INCOMPLETE);
            } else {
                if ($sourceProject->hasField($this->sourceField)) {
                    $mappingStatus->mergeStatus(FieldMappingStatus::OK);
                } else {
                    $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                    $errorMessage = "Mapping source field \"{$this->sourceField}\" is not valid.";
                    $mappingStatus->addError($errorMessage);
                }
            }
        }

        #==============================================
        # Process destination values
        #==============================================

        #--------------------------
        # Process destination event
        #--------------------------
        if ($destinationProject->isLongitudinal()) {
            if ($this->destinationEvent === self::ALL) {
                if ($this->excludeDestination) {
                    $mappingStatus->mergeStatus(FieldMappingStatus::OK);
                } else {
                    $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                    $mappingStatus->addError("Illegal ALL destination event for non-excluded destination.");
                }
            } elseif ($this->destinationEvent === self::MATCHING) {
                if ($this->excludeDestination) {
                    $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                    $mappingStatus->addError("Illegal MATCHING destination event for excluded destination.");
                } else {
                    $mappingStatus->mergeStatus(FieldMappingStatus::OK);
                }
            } elseif ($destinationProject->hasUniqueEventName($this->destinationEvent)) {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            } elseif (empty($this->destinationEvent)) {
                $mappingStatus->mergeStatus(FieldMappingStatus::INCOMPLETE);
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $mappingStatus->addError("Mapping destination event \"{$this->destinationEvent}\" is undefined.");
            }
        }

        #-------------------------
        # Process destination form
        #-------------------------
        if ($this->destinationForm === self::ALL) {
            if ($this->excludeDestination) {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $mappingStatus->addError("Illegal ALL destination form for non-excluded destination.");
            }
        } elseif ($this->destinationForm === self::MATCHING) {
            if ($this->excludeDestination) {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $mappingStatus->addError("Illegal MATCHING destination form for excluded destination.");
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            }
        } elseif ($destinationProject->hasForm($this->destinationForm)) {
            if ($this->sourceForm === self::ALL && !$this->excludeDestination) {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $errorMessage = "Mapping destination form \"{$this->destinationForm}\" is not compatible"
                    . " with source form \"{$this->sourceForm}\".";
                $mappingStatus->addError($errorMessage);
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            }
        } elseif (empty($this->destinationForm)) {
            $mappingStatus->mergeStatus(FieldMappingStatus::INCOMPLETE);
        } else {
            $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
            $mappingStatus->addError("Mapping destination form \"{$this->destinationForm}\" is undefined.");
        }

        #--------------------------
        # Process destination field
        #--------------------------
        if ($this->destinationField === self::ALL) {
            if ($this->excludeDestination) {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $mappingStatus->addError("Illegal ALL destination field for non-excluded destination.");
            }
        } elseif ($this->destinationField === self::EQUIVALENT) {
            if ($this->excludeDestination) {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $mappingStatus->addError("Illegal EQUIVALENT destination field for excluded destination.");
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            }
        } elseif ($this->destinationField === self::COMPATIBLE) {
            if ($this->excludeDestination) {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $mappingStatus->addError("Illegal COMPATIBLE destination field for excluded destination.");
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::OK);
            }
        } elseif (empty($this->destinationField)) {
            $mappingStatus->mergeStatus(FieldMappingStatus::INCOMPLETE);
        } else {
            # Single destination field specified
            if (!$this->excludeDestination) {
                if ($this->sourceField === self::ALL) {
                    $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                    $errorMessage = "Illegal non-MATCHING destination field \"{$this->destinationField}\""
                       . " for ALL source field.";
                    $mappingStatus->addError($errorMessage);
                }
            }

            if ($sourceProject->hasField($this->destinationField)) {
                if ($this->excludeDestination) {
                    $mappingStatus->mergeStatus(FieldMappingStatus::OK);
                } else {
                    $sourceFieldMetadata      = $sourceProject->getFieldMetadata($this->sourceField) ?? null;
                    $destinationFieldMetadata = $destinationProject->getFieldMetadata($this->destinationField) ?? null;

                    if (!empty($sourceFieldMetadata) && !empty($destinationFieldMetadata)) {
                        $sourceVariable      = new Variable(
                            $sourceFieldMetadata,
                            $sourceProject->getMissingDataCodes(),
                            $sourceProject->getFieldActionTags($this->sourceField)
                        );
                        $destinationVariable = new Variable(
                            $destinationFieldMetadata,
                            $destinationProject->getMissingDataCodes(),
                            $destinationProject->getFieldActionTags($this->destinationField)
                        );

                        if ($sourceVariable->isCompatibleWith($destinationVariable)) {
                            $mappingStatus->mergeStatus(FieldMappingStatus::OK);
                        } else {
                            $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                            $errorMessage = "Destination field \"{$this->destinationField}\" is not compatible"
                                . " with source field \"{$this->sourceField}\".";
                            $mappingStatus->addError($errorMessage);
                        }
                    } else {
                        $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                        $errorMessage = "Destination field \"{$this->destinationField}\" and"
                            . " source field \"{$this->sourceField}\" compatibility could not be determined.";
                        $mappingStatus->addError($errorMessage);
                    }
                }
            } else {
                $mappingStatus->mergeStatus(FieldMappingStatus::ERROR);
                $errorMessage = "Mapping desintion field \"{$this->destinationField}\" is not valid.";
                $mappingStatus->addError($errorMessage);
            }
        }

        return $mappingStatus;
    }


    /**
     * Expands this field mapping by replacing
     * wild-card and matching specifications, e.g., ALL, MATCHING.
     *
     * Note: ALL instances cannot be expanded, because the
     * specific instances will depend on the record.
     *
     * @parameter boolean $expandCheckboxes indicates if the expanded field mappings should
     *     also be expanded for checkboxes. A single checkbox field in REDCap is represented
     *     by multiple fields in the data transfer, with one field for each checkbox
     *     choice.
     *
     * @return array an array of zero or more field mappings.
     */
    public function expand($module, $configuration, $expandCheckboxes = false)
    {
        $mappings = [];

        $sourceProject = $configuration->getSourceProject($module);
        $destinationProject = $configuration->getDestinationProject($module);

        $fieldMappingStatus = $this->check($sourceProject, $destinationProject);

        if ($fieldMappingStatus->isOk()) {
            # only expand if the mapping is OK (i.e., it iscomplete and does not have errors).

            $includeRecordId = false;
            $equivalentFields = $sourceProject->getEquivalentFields($destinationProject, $includeRecordId);
            $compatibleFields = $sourceProject->getCompatibleFields($destinationProject, $includeRecordId);

            #----------------------------------------
            # Set possible source events
            #----------------------------------------
            $sourceEvents = [];
            if ($sourceProject->isLongitudinal()) {
                if ($this->sourceEvent === FieldMapping::ALL) {
                    $sourceEvents = $sourceProject->getUniqueEventNames();
                } elseif (!empty($this->sourceEvent)) {
                    $sourceEvents[] = $this->sourceEvent;
                }
            } else {
                $sourceEvents = [null];
            }

            $allDestinationEvents = $destinationProject->getUniqueEventNames();
            $allDestinationForms  = $destinationProject->getForms();

            #------------------------------------
            # Source event loop
            #------------------------------------
            foreach ($sourceEvents as $sourceEvent) {
                # Set source forms for source event
                $sourceForms = [];
                if ($this->sourceForm === FieldMapping::ALL) {
                    if ($sourceProject->isLongitudinal()) {
                        $sourceForms = $sourceProject->getEventForms($sourceEvent);
                    } else {
                        $sourceForms = $sourceProject->getForms();
                    }
                } elseif (!empty($this->sourceForm)) {
                    $sourceForms[] = $this->sourceForm;
                }

                #--------------------------------
                # Source form loop
                #--------------------------------
                foreach ($sourceForms as $sourceForm) {
                    $sourceFields = [];
                    if ($this->sourceField === FieldMapping::ALL) {
                        $includeCompleteField = true;
                        $includeRecordId      = false;
                        $sourceFields = $sourceProject->getFormFields(
                            $sourceForm,
                            $includeCompleteField,
                            $includeRecordId
                        );
                    } elseif (!empty($this->sourceField)) {
                        $sourceFields[] = $this->sourceField;
                    }

                    #---------------------------------------------
                    # Source field loop
                    #---------------------------------------------
                    foreach ($sourceFields as $sourceField) {
                        if ($destinationProject->isLongitudinal() && empty($this->destinationEvent)) {
                            ; // Destination project is longitudinal, but has no destination event;
                            // don't add the mapping
                        } elseif (
                            $destinationProject->isLongitudinal()
                            && $this->destinationEvent === FieldMapping::MATCHING
                            && !in_array($sourceEvent, $allDestinationEvents)
                        ) {
                            ; // There is no matching destination event; don't add the mapping
                        } elseif (empty($this->destinationForm)) {
                            ; // There is no destination form; don't add the mapping
                        } elseif (
                            $this->destinationForm === FieldMapping::MATCHING
                            && !in_array($sourceForm, $allDestinationForms)
                        ) {
                            ; // There is no matching destination form; don't add the mapping
                        } elseif (empty($this->destinationField)) {
                            ; // There is no destination field; don't add the mapping
                        } else {
                            if (
                                $this->destinationField === 'EQUIVALENT'
                                && !in_array($sourceField, $equivalentFields)
                            ) {
                                ; // No equivalent destination field, so don't create a mapping
                            } elseif (
                                $this->destinationField === 'COMPATIBLE'
                                && !in_array($sourceField, $compatibleFields)
                            ) {
                                ; // No compatible destination field, so don't create a mapping
                            } else {
                                $mapping = new FieldMapping();

                                $mapping->sourceEvent    = $sourceEvent;
                                $mapping->sourceForm     = $sourceForm;
                                $mapping->sourceField    = $sourceField;
                                $mapping->sourceInstance = $this->sourceInstance;

                                if ($this->destinationEvent === FieldMapping::MATCHING) {
                                    $mapping->destinationEvent = $sourceEvent;
                                } else {
                                    $mapping->destinationEvent = $this->destinationEvent;
                                }

                                if ($this->destinationForm === FieldMapping::MATCHING) {
                                    $mapping->destinationForm = $sourceForm;
                                } else {
                                    $mapping->destinationForm = $this->destinationForm;
                                }

                                if ($this->destinationField === self::EQUIVALENT) {
                                    $mapping->destinationField = $sourceField;
                                } elseif ($this->destinationField === self::COMPATIBLE) {
                                    $mapping->destinationField = $sourceField;
                                } else {
                                    $mapping->destinationField = $this->destinationField;
                                }

                                $mapping->destinationInstance = $this->destinationInstance;
                                $mapping->excludeDestination  = $this->excludeDestination;

                                $expandedCheckboxes = false;
                                if ($expandCheckboxes) {
                                    $expandedCheckboxes = $sourceProject->expandCheckboxField($sourceField);
                                }

                                if ($expandedCheckboxes !== false) {
                                    foreach ($expandedCheckboxes as $fieldName) {
                                        $checkboxMapping = clone $mapping;
                                        $checkboxMapping->sourceField = $fieldName;

                                        $index = strpos($fieldName, Project::CHECKBOX_SEPARATOR);
                                        $destinationFieldName = $mapping->destinationField . substr($fieldName, $index);
                                        $checkboxMapping->destinationField = $destinationFieldName;
                                        $mappings[] = $checkboxMapping;
                                    }
                                } else {
                                    $mappings[] = $mapping;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $mappings;
    }

    public function expandExcludeDestination($module, $configuration, $expandCheckboxes = false)
    {
        $mappings = [];

        $destinationProject = $configuration->getDestinationProject($module);

        $includeRecordId = false;

        #----------------------------------------
        # Set desintation events
        #----------------------------------------
        $destinationEvents = [];
        if ($destinationProject->isLongitudinal()) {
            if ($this->destinationEvent === FieldMapping::ALL) {
                $destinationEvents = $destinationProject->getUniqueEventNames();
            } elseif (!empty($this->destinationEvent)) {
                $destinationEvents[] = $this->destinationEvent;
            }
        } else {
            $destinationEvents = [null];
        }

        #------------------------------------
        # Desintation event loop
        #------------------------------------
        foreach ($destinationEvents as $destinationEvent) {
            # Set destination forms for destination event
            $destinationForms = [];
            if ($this->destinationForm === FieldMapping::ALL) {
                if ($destinationProject->isLongitudinal()) {
                    $destinationForms = $destinationProject->getEventForms($destinationEvent);
                } else {
                    $destinationForms = $destinationProject->getForms();
                }
            } elseif (!empty($this->destinationForm)) {
                $destinationForms[] = $this->destinationForm;
            }

            #--------------------------------
            # Desintation form loop
            #--------------------------------
            foreach ($destinationForms as $destinationForm) {
                $destinationFields = [];
                if ($this->destinationField === FieldMapping::ALL) {
                    $includeCompleteField = true;
                    $includeRecordId      = false;
                    $destinationFields = $destinationProject->getFormFields(
                        $destinationForm,
                        $includeCompleteField,
                        $includeRecordId
                    );
                } elseif (!empty($this->destinationField)) {
                    $destinationFields[] = $this->destinationField;
                }

                #---------------------------------------------
                # Desination field loop
                #---------------------------------------------
                foreach ($destinationFields as $destinationField) {
                    $mapping = new FieldMapping();

                    $mapping->destinationEvent = $destinationEvent;
                    $mapping->destinationForm  = $destinationForm;
                    $mapping->destinationField = $destinationField;
                    $mapping->excludeDestination = true;

                    $mappings[] = $mapping;
                }
            }
        }

        return $mappings;
    }


    #---------------------------------------------
    # Getters and Setters
    #---------------------------------------------

    public function getSourceEvent()
    {
        return $this->sourceEvent;
    }

    public function setSourceEvent($event)
    {
        $this->sourceEvent = $event;
    }

    public function getSourceForm()
    {
        return $this->sourceForm;
    }

    public function setSourceForm($form)
    {
        $this->sourceForm = $form;
    }

    public function getSourceField()
    {
        return $this->sourceField;
    }

    public function setSourceField($field)
    {
        $this->sourceField = $field;
    }

    public function getDestinationEvent()
    {
        return $this->destinationEvent;
    }

    public function setDestinationEvent($event)
    {
        $this->destinationEvent = $event;
    }

    public function getDestinationForm()
    {
        return $this->destinationForm;
    }

    public function setDestinationForm($form)
    {
        $this->destinationForm = $form;
    }

    public function getDestinationField()
    {
        return $this->destinationField;
    }

    public function setDestinationField($field)
    {
        $this->destinationField = $field;
    }

    public function getExcludeDestination()
    {
        return $this->excludeDestination;
    }

    public function setExcludeDestination($excludeDestination)
    {
        $this->excludeDestination = $excludeDestination;
    }
}
