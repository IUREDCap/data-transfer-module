<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use IU\PHPCap\RedCapProject;

/**
 * Class for transferring data from one REDCap project to another.
 */
class DataTransferer
{
    # Transfer types
    public const MANUAL_TRANSFER = "manualTransfer";
    public const EXPORT_ON_FORM_SAVE_TRANSFER = "exporOnFormSaveTransfer";
    public const SCHEDULED_TRANSFER = "scheduledTransfer";

    private $module;
    private $configuration;

    private $sourceProject;
    private $destinationProject;

    private $createdRecordIds;
    private $updatedRecordIds;

    /** @var FieldMap the expanded field mapping for the data transfer configuration. */
    private $expandedFieldMap;

    private $overwriteBehavior;

    /** @var array list of new instances create for new option for copying non-repeating to repeating data
     *     to avoid multiple creations per record.
     */
    private $newInstances;


    /**
     * @param DataTransfer $module the external module object.
     * @param Configuration $configuration the data transfer configuration.
     */
    public function __construct(DataTransfer $module, Configuration $configuration)
    {
        if (!$configuration->isEnabled()) {
            throw new \Exception("The data transfer configuration has not been enabled.");
        }

        $this->module = $module;
        $this->configuration = $configuration;

        $this->sourceProject      = $configuration->getSourceProject($this->module);
        $this->destinationProject = $configuration->getDestinationProject($this->module);

        #------------------------------------------------------------------------
        # Set the expanded field map (with wildcards and checkboxes expanded).
        #------------------------------------------------------------------------
        $fieldMap = $this->configuration->getFieldMapObject();

        $expandCheckboxes = true;
        $this->expandedFieldMap = $fieldMap->simplify($this->module, $this->configuration, $expandCheckboxes);


        $this->createdRecordIds = [];
        $this->updatedRecordIds = [];

        $this->overwriteBehavior = 'normal';
        if ($configuration->getOverwriteWithBlanks()) {
            $this->overwriteBehavior = 'overwrite';
        }

        $this->newInstances = [];
    }

    /**
     * Transfer data from the source project to the destination project according
     * to properties specified in the data transfer configuration.
     *
     * @param string $username the username of the user transferring the data.
     * @param string $transferType the type of the transfer (MANUAL_TRANSFER or EXPORT_ON_FORM_SAVE_TRANSFER)
     * @param string $recordId the record ID of the record to transfer (if empty, then all records are transferred).
     * @param string $sourceForm optional source form name used to restrict the data transferred. Only
     *     data from the source project with the specified form name will be transferred.
     * @pram string $sourceEvent optional source event name used to restrict the data transferred.
     *
     */
    public function transferData($username, $transferType, $recordId = null, $sourceForm = null, $sourceEvent = null)
    {
        try {
            if (empty($username)) {
                throw new \Exception("No username specified for data transfer.");
            }

            if (!empty($sourceForm)) {
                $this->expandedFieldMap->filterMappings($sourceForm, $sourceEvent);
            }

            #----------------------------------------------------------------
            # Check if the specified transfer type is allowed
            #----------------------------------------------------------------
            if ($transferType === DataTransferer::MANUAL_TRANSFER) {
                if (!$this->configuration->getManualTransferEnabled()) {
                    $message = "Manual transfer is not enabled for"
                        . " configuration \"{$this->configuration->getName()}\".";
                    throw new \Exception($message);
                }
            } elseif ($transferType === DataTransferer::EXPORT_ON_FORM_SAVE_TRANSFER) {
                if (!$this->configuration->getExportOnFormSave()) {
                    $message = "Export on form save transfer is not enabled for"
                       . " configuration \"{$this->configuration->getName()}\".";
                    throw new \Exception($message);
                }
            } elseif ($transferType === DataTransferer::SCHEDULED_TRANSFER) {
                # if (!$this->configuration->getExportOnFormSave()) {
                #     $message = "Export on form save transfer is not enabled for"
                #        . " configuration \"{$this->configuration->getName()}\".";
                #     throw new \Exception($message);
                # }
            } else {
                throw new \Exception("Unrecognized data transfer type \"{$transferType}\".");
            }

            # Clear the new instances array before the start of the transfer
            $this->newInstances = [];

            #----------------------------------------------
            # Check user's project permissions
            #----------------------------------------------
            $this->sourceProject->allowedToTransferDataFrom($username);
            $this->destinationProject->allowedToTransferDataTo($username, $this->configuration->createNewRecords());

            #-------------------------------------------------------------------------
            # Get the record ID batches (if batch size if not set, or is less than 1,
            # then a single batch with all records will be returned).
            #-------------------------------------------------------------------------
            $batchSize = $this->configuration->getBatchSize();
            $sourceFilterLogic = $this->configuration->getSourceFilterLogic();

            if (!\LogicTester::isValid($sourceFilterLogic)) {
                throw new \Exception("The following source filter logic is invalid: \"{$sourceFilterLogic}\".");
            }

            $idFieldBatches = new IdFieldBatches($this->sourceProject, $batchSize, $sourceFilterLogic, $recordId);

            $sourceParameters = [];

            foreach ($idFieldBatches->getBatches() as $idFieldBatch) {
                $this->transferRecordBatch($idFieldBatch, $idFieldBatches->getIdToSecondaryMap());
            }

            #-----------------------------------------------
            # Log data transfer to the external module log
            #-----------------------------------------------
            $projectId = $this->configuration->getConfigProjectId();
            $logParameters = ['project_id' => $projectId];

            $message = "Data transfer"
                . ' from project ' . $this->sourceProject->getProjectIdentifier()
                . ' to '
                . $this->destinationProject->getProjectIdentifier()
                . ' using configuration "' . $this->configuration->getName() . '"'
                . '.'
                ;
            $this->module->log($message, $logParameters);

            #-----------------------------------------------
            # Log data transfer to the REDCap project log
            #-----------------------------------------------
            if ($this->configuration->getDirection() === Configuration::DIRECTION_IMPORT) {
                # Import
                $actionDescription = "Data import (external module)";
                $changesMade = "Data Transfer module import from project "
                    . $this->sourceProject->getProjectIdentifier()
                    . ' using configuration "' . $this->configuration->getName() . '"'
                    . '.'
                    ;
                \REDCap::logEvent($actionDescription, $changesMade);
            } else {
                # Export
                $actionDescription = "Data export (external module)";
                $changesMade = "Data Transfer module export to project "
                    . $this->destinationProject->getProjectIdentifier()
                    . ' using configuration "' . $this->configuration->getName() . '"'
                    . '.'
                    ;
                \REDCap::logEvent($actionDescription, $changesMade);
            }
        } catch (\Throwable $throwable) {
            #------------------------------------------------------
            # Log data transfer error to external module log
            #------------------------------------------------------
            $projectId = $this->configuration->getConfigProjectId();
            $logParameters = ['project_id' => $projectId];

            $message = "Data transfer failure"
                . " from project " . $this->sourceProject->getProjectIdentifier()
                . " to "
                . $this->destinationProject->getProjectIdentifier()
                . ' using configuration "' . $this->configuration->getName() . '"'
                . ": " . $throwable->getMessage();
            $this->module->log($message, $logParameters);

            # Rethrow throwable
            throw $throwable;
        }
    }

    /**
     * Transfers a batch of records from one project to another.
     */
    public function transferRecordBatch($idFieldBatch, $idToSecondaryMap)
    {
        $modifiedRecordIds = []; // Keep track of which record IDs actually get modified

        $configuration = $this->configuration;

        $sourceProject      = $this->sourceProject;
        $destinationProject = $this->destinationProject;

        # Get the source data
        $sourceParameters['recordIds'] = $idFieldBatch[0];
        if (!empty($configuration->getSourceFilterLogic())) {
            $sourceParameters['filterLogic'] = $this->configuration->getSourceFilterLogic();
        }
        $sourceData = $sourceProject->exportDataMapAp($sourceParameters);

        # Get the matching destination records, if any
        if ($this->configuration->getRecordMatch() === Configuration::MATCH_RECORD_ID) {
            $destinationParameters['recordIds'] = $idFieldBatch[0];
        } elseif ($this->configuration->getRecordMatch() === Configuration::MATCH_SECONDARY_ID) {
            $destinationParameters['recordIds'] = $idFieldBatch[1];
        } else {
            $message = "Unknown record match value: \"{$this->configuration->getRecordMatch()}\".";
            throw new \Exception($messsage);
        }
        $destinationData = $destinationProject->exportDataMapAp($destinationParameters);

        #-------------------------------------------------------------------------------
        # For each source record, modify or add the record into the destination data
        # for any (non-file) fields and events that match.
        #-------------------------------------------------------------------------------
        foreach ($sourceData as $recordId => $sourceRows) {
            $secondaryUniqueField = $idToSecondaryMap[$recordId];

            $recordUpdated = $this->transferRecord(
                $recordId,
                $secondaryUniqueField,
                $sourceRows,
                $destinationData
            );

            if ($recordUpdated) {
                if ($this->configuration->getRecordMatch() === Configuration::MATCH_RECORD_ID) {
                    $modifiedRecordIds[$recordId] = 1;
                } elseif ($this->configuration->getRecordMatch() === Configuration::MATCH_SECONDARY_ID) {
                    $modifiedRecordIds[$secondaryUniqueField] = 1;
                } else {
                    $message = "Unknown record match value: \"{$this->configuration->getRecordMatch()}\".";
                    throw new \Exception($messsage);
                }
            }
        }

        # Import the record batch data into the destination project
        # TODO replace modified record IDs with filtering before call
        # using array_intersect_key ???
        $this->importDestinationData($destinationData, $modifiedRecordIds, $this->overwriteBehavior);

        #-----------------------------------------------
        # Transfer files
        #-----------------------------------------------
        if ($configuration->getTransferFiles()) {
            $this->transferFiles(
                $sourceData,
                $destinationData,
                $idToSecondaryMap
            );
        }
    }

    public function importDestinationData($destinationData, $modifiedRecordIds, $overwriteBehavior = 'normal')
    {
        #----------------------------------------------------------
        # Format the data to import into the destination project
        #----------------------------------------------------------
        # Remove record ID mapping
        $data = [];
        foreach ($destinationData as $recordId => $rows) {
            # Only add the rows for a record ID to the data
            # to be imported if that record was actually modified
            # in some way.
            if (array_key_exists($recordId, $modifiedRecordIds)) {
                $data = array_merge($data, $rows);
            }
        }

        if (!empty($data)) {
            #----------------------------------------------------------------------
            # Work-around for REDCap bug. Split out data that has a DAG specified
            #----------------------------------------------------------------------
            $dagData = [];
            for ($i = count($data) - 1; $i >= 0; $i--) {
                if (!empty($data[$i])) {
                    $dag = $data[$i][Project::REDCAP_DATA_ACCESS_GROUP] ?? '';
                    if (!empty($dag)) {
                        $dagData[] = $data[$i];
                        unset($data[$i]);
                    }
                }
                $jsonDagData = json_encode($dagData, JSON_PRETTY_PRINT);
                $this->destinationProject->importData($jsonDagData, 'json', $overwriteBehavior);
            }
            # $data = array_values($data);
            #--------------------------
            # End of work-around
            #--------------------------

            # Convert to JSON
            $jsonData = json_encode($data, JSON_PRETTY_PRINT);

            # Import possibly modified and/or new data into the destination project
            $this->destinationProject->importData($jsonData, 'json', $overwriteBehavior);
        }
    }


    /**
     * Transfers the specified record from the source project data to the destination project data.
     * This method transfers data from and to internal data structures, and does not transfer
     * andy data from or to REDCap.
     *
     * @param Project $sourceProject the source project from which data is being transferred.
     * @param string $recordId the record ID in the destination project of the record being transferred.
     * @param array $sourceRows the rows of the source project's record that are being transferred to the
     *     destination project. Because of multiple events, repeating events, and repeating instruments,
     *     a single record may have multiple data rows.
     *
     * @param Configuration $configuration the data transfer configuration.
     *
     * @return boolean true is returned if the record was updated in some way (added, instance added, or updated),
     *     and false is returned if there were no updates to the destination record.
     */
    public function transferRecord(
        $recordId,
        $secondaryUniqueField,
        $sourceRows,
        &$destinationData
    ) {
        $recordUpdated = false;
        $recordAdded   = false;

        # Record instances created for the record
        # to avoid creating a new instance multiple times
        # for the "new instance" option for transferring non-repeating to repeating

        if ($this->configuration->getRecordMatch() === Configuration::MATCH_RECORD_ID) {
            $destinationRecordId = $recordId;
        } elseif ($this->configuration->getRecordMatch() === Configuration::MATCH_SECONDARY_ID) {
            $destinationRecordId = $secondaryUniqueField;
        } else {
            $message = "Unknown record match value: \"{$this->configuration->getRecordMatch()}\".";
            throw new \Exception($messsage);
        }


        if (!array_key_exists($destinationRecordId, $destinationData) && !$this->configuration->createNewRecords()) {
            # The record ID does not exist in the destination data and the configuration indicates that
            # new records should not be created in the destination project, so stop processing
            ;
        } else {
            # The destination record already exists or it can be created
            foreach ($sourceRows as $sourceRow) {
                $sourceEvent          = $sourceRow[Project::REDCAP_EVENT_NAME] ?? null;
                $sourceRepeatForm     = $sourceRow[Project::REDCAP_REPEAT_INSTRUMENT] ?? null;
                $sourceRepeatInstance = $sourceRow[Project::REDCAP_REPEAT_INSTANCE] ?? null;

                $sourceFields = $this->sourceProject->getTransferFieldsInRow($sourceRow);

                #-----------------------------------------------
                # Put instance calculation here???????????
                #-----------------------------------------------

                foreach ($sourceFields as $sourceField) {
                    if (!$this->sourceProject->isFileField($sourceField)) {
                        $sourceForm = $this->sourceProject->getTransferFieldForm($sourceField);

                        #-----------------------------------------------------------------------
                        # Get the mappings for this source event + form + field (if any)
                        #-----------------------------------------------------------------------
                        $mappings = $this->expandedFieldMap->getMappingsForSource(
                            $sourceEvent,
                            $sourceForm,
                            $sourceField
                        )
                            ??
                            []
                        ;

                        foreach ($mappings as $mapping) {
                            $fieldUpdated = $this->transferField(
                                $sourceRows,
                                $sourceRow,
                                $destinationRecordId,
                                $destinationData,
                                $mapping
                            );


                            if ($fieldUpdated) {
                                $recordUpdated = true;
                            }
                        }
                    }
                }
            }
        }

        #---------------------------------------------------
        # Transfer DAG
        #---------------------------------------------------
        $dagUpdated = $this->transferDag($sourceRows, $destinationRecordId, $destinationData);
        if ($dagUpdated) {
            $recordUpdated = true;
        }

        return $recordUpdated;
    }

    /**
     * Processes DAG (Data Access Group) transfer from the source
     * to destination project.
     *
     * TODO: need to have data access group column in all rows that are
     * sent to destination project if any one row has it.
     */
    public function transferDag($sourceRows, $recordId, &$destinationData)
    {
        $dagUpdated = false;

        #---------------------------------------------------
        # Transfer DAG
        #---------------------------------------------------
        if (!empty($destinationData) && array_key_exists($recordId, $destinationData)) {
            if ($this->configuration->getDagOption() === Configuration::DAG_TRANSFER) {
                $sourceDag = $sourceRows[0][Project::REDCAP_DATA_ACCESS_GROUP] ?? null;
                if (!empty($sourceDag) && in_array($sourceDag, $this->destinationProject->getDagUniqueGroupNames())) {
                    $destinationRows = &$destinationData[$recordId] ?? [];
                    $destinationDag = $destinationRows[0][Project::REDCAP_DATA_ACCESS_GROUP] ?? null;

                    if ($destinationDag !== $sourceDag) {
                        for ($i = 0; $i < count($destinationRows); $i++) {
                            $destinationRows[$i][Project::REDCAP_DATA_ACCESS_GROUP] = $sourceDag;
                        }
                        $dagUpdated = true; // New DAG set in record
                    }
                }
            } elseif ($this->configuration->getDagOption() === Configuration::DAG_MAPPING) {
                $sourceDag = $sourceRows[0][Project::REDCAP_DATA_ACCESS_GROUP] ?? null;
                $dagMap = $this->configuration->getDagMap();
                $dagExclude = $this->configuration->getDagExclude();

                # If the source DAG is in the DAG map, and has not been excluded
                if (array_key_exists($sourceDag, $dagMap) && !array_key_exists($sourceDag, $dagExclude)) {
                    $destinationRows = &$destinationData[$recordId] ?? [];
                    $destinationDag = $destinationRows[0][Project::REDCAP_DATA_ACCESS_GROUP] ?? null;

                    $transferDag = $dagMap[$sourceDag];
                    if ($destinationDag !== $transferDag) {
                        for ($i = 0; $i < count($destinationRows); $i++) {
                            $destinationRows[$i][Project::REDCAP_DATA_ACCESS_GROUP] = $transferDag;
                        }
                        $dagUpdated = true; // New DAG set in record
                    }
                }
            }
        }

        return $dagUpdated;
    }

    /**
     * Transfers the field in the specified mapping for the specified source row.
     * The transfer is done from the in-memory source project data to the in-memory
     * destination project data, so this method does not modify any data in
     * REDCap.
     *
     * @param integer $recordId the record ID of the REDCap record being transferred.
     *
     * @param Configuration $configuration the data transfer configuration that
     *     describes how to transfer the data from the source project to the
     *     destination project.
     *
     * @param FieldMapping $mapping the source field to destination field mapping.
     */
    public function transferField($sourceRows, $sourceRow, $recordId, &$destinationData, $mapping)
    {
        $fieldUpdated = false;  // if field was updated

        $updateField = false;   // if field should be updated

        $sourceRepeatInstance = $sourceRow[Project::REDCAP_REPEAT_INSTANCE] ?? null;

        $sourceEvent    = $mapping->getSourceEvent();
        $sourceForm     = $mapping->getSourceForm();
        $sourceField    = $mapping->getSourceField();
        $sourceInstance = null;

        $destinationEvent    = $mapping->getDestinationEvent();
        $destinationForm     = $mapping->getDestinationForm();
        $destinationField    = $mapping->getDestinationField();
        $destinationInstance = null;

        # Set source repeating form (if any)
        $sourceRepeatForm = null;
        if ($this->sourceProject->isRepeatingForm($sourceEvent, $sourceForm)) {
             $sourceRepeatForm = $sourceForm;
        }

        # Set destination repeating form (if any)
        $destinationRepeatForm = null;
        if ($this->destinationProject->isRepeatingForm($destinationEvent, $destinationForm)) {
             $destinationRepeatForm = $destinationForm;
        }

        $sourceValue = $sourceRow[$sourceField];

        if (!$this->configuration->getOverwriteWithBlanks() && ($sourceValue === null || $sourceValue === '')) {
            # Don't update the destination field if the source field is blank
            # and the configuration indicates not to overwrite existing values
            # with blanks
            $updateField = false;
        } elseif (empty($sourceRepeatInstance)) {
            if ($this->destinationProject->isRepeating($destinationEvent, $destinationForm)) {
                #---------------------------------------
                # From Non-Repeating to Repeating
                #---------------------------------------
                $destinationInstance = $this->getNonRepeatingToRepeatingInstance(
                    $this->destinationProject,
                    $destinationData,
                    $recordId,
                    $destinationEvent,
                    $destinationRepeatForm,
                    $this->configuration
                );

                $updateField = true;
            } else {
                #---------------------------------------
                # From Non-Repeating to Non-Repeating
                #---------------------------------------
                $updateField = true;
            }
        } else {
            # Source field is repeating
            if ($this->destinationProject->isRepeating($destinationEvent, $destinationForm)) {
                #---------------------------------------
                # From Repeating to Repeating
                #---------------------------------------
                $sourceInstance      = $sourceRepeatInstance;
                $destinationInstance = $sourceRepeatInstance;

                $updateField = true;
            } else {
                #---------------------------------------
                # From Repeating to Non-Repeating
                #---------------------------------------
                $destinationInstance =  null;

                $sourceInstance = $this->getRepeatingToNonRepeatingInstance(
                    $this->sourceProject,
                    $sourceRows,
                    $sourceEvent,
                    $sourceRepeatForm,
                    $this->configuration
                );

                if ($sourceRepeatInstance === $sourceInstance) {
                    # the instance for this source row matches the source instance
                    # that should be used for the transfer, so transfer the field
                    $updateField = true;
                } else {
                    $updateField = false;
                }
            }
        }

        if ($updateField) {
            list($rowIndex, $recordAdded, $rowAdded) = $this->addBlankRecordRowIfNotExists(
                $this->configuration,
                $this->destinationProject,
                $destinationData,
                $recordId,
                $destinationEvent,
                $destinationRepeatForm,
                $destinationInstance
            );


            if ($rowIndex === Project::ROW_NOT_FOUND) {
                # The instance for the new data cannot be created
                $fieldUpdated = false;
            } else {
                $destinationRow = &$destinationData[$recordId][$rowIndex];

                #--------------------------------------------------------------------------------
                # Only update the destination field if the source field has a different value
                #--------------------------------------------------------------------------------
                if ($destinationRow[$destinationField] === $sourceValue) {
                    # If the destination field has the same value as the source field, don't update
                    $fieldUpdated = false;
                } else {
                    #-----------------------------------------
                    # Update destination field (in memory)
                    #-----------------------------------------
                    $destinationRow[$destinationField] = $sourceValue;
                    $fieldUpdated = true;
                }

                #-----------------------------------------------------------------------------------
                # If a new record or instance was added to the destination project, then count
                # the field as updated (even if the destination field value was not updated)
                #-----------------------------------------------------------------------------------
                if ($recordAdded || $rowAdded) {
                    $fieldUpdated = true;
                }
            }
        }

        return $fieldUpdated;
    }

    /**
     * Adds a blank record row with the specified record ID and optionally specified event, repeating form and instance
     * to the specified in-memeory data for the project (the actual REDCap project's data is not affected).
     *
     * @param Project $project the Project object for the REDCap project thats (in-memory) data is having a blank
     *     record row added
     * @param array $projectData the in-memory representation of the project's (or part of the project's) data.
     */
    public function addBlankRecordRow(
        $project,
        &$projectData,
        $recordId,
        $event = null,
        $repeatingForm = null,
        $instance = null
    ) {
        $rowIndex = null;

        $recordRow = $project->getBlankRecordRow($recordId, $event, $repeatingForm, $instance);

        $projectData[$recordId][] = $recordRow;
        $rowIndex = array_key_last($projectData[$recordId]);

        return $rowIndex;
    }

    /**
     * @return array An array with the following 3 values:
     *     row index of for the row meeting the specified criteria,
     *     boolean that indicates if a new record (and row) were added,
     *     boolean that indicates if a new row (only) was added
     */
    public function addBlankRecordRowIfNotExists(
        $configuration,
        $project,
        &$projectData,
        $recordId,
        $event = null,
        $repeatingForm = null,
        $instance = null
    ) {
        $rowIndex = null;

        $recordAdded   = false;
        $rowAdded = false;

        if (!array_key_exists($recordId, $projectData)) {
            # Record doesn't exists, so add it
            # (code assumes that the check that rows can be added has already been made)
            $rowIndex = $this->addBlankRecordRow($project, $projectData, $recordId, $event, $repeatingForm, $instance);
            $recordAdded = true;
            $this->createdRecordIds[] = $recordId;
        } else {
            # Record exists in the project's data, but row for specified event/form/instance might not
            $record = &$projectData[$recordId];
            $rowIndex = $project->getRowIndex($record, $event, $repeatingForm, $instance);

            if ($rowIndex === Project::ROW_NOT_FOUND) {
                if ($instance === null || $configuration->createNewInstances()) {
                    $rowIndex = $this->addBlankRecordRow(
                        $project,
                        $projectData,
                        $recordId,
                        $event,
                        $repeatingForm,
                        $instance
                    );
                    $rowAdded = true;
                }
            }
        }

        return [$rowIndex, $recordAdded, $rowAdded];
    }

    /**
     * Gets the destination instance to use, based on the data transfer configuration,
     * when transferring data from a non-repeating field to a repeating field.
     */
    public function getNonRepeatingToRepeatingInstance(
        $destinationProject,
        &$destinationData,
        $recordId,
        $destinationEvent,
        $destinationRepeatForm,
        $configuration
    ) {
        $instance = null;

        $toInstance = $configuration->getNonRepeatingToRepeating();

        if ($toInstance === Configuration::TO_1) {
            $instance = 1;
        } elseif ($toInstance === Configuration::TO_FIRST) {
            if (array_key_exists($recordId, $destinationData)) {
                $destinationRecord = &$destinationData[$recordId];
                $instance = $destinationProject->getFirstInstance(
                    $destinationRecord,
                    $destinationEvent,
                    $destinationRepeatForm
                );
            } else {
                $instance = 1;
            }
        } elseif ($toInstance === Configuration::TO_LAST) {
            if (array_key_exists($recordId, $destinationData)) {
                $destinationRecord = &$destinationData[$recordId];
                $instance = $destinationProject->getLastInstance(
                    $destinationRecord,
                    $destinationEvent,
                    $destinationRepeatForm
                );
            } else {
                $instance = 1;
            }
        } elseif ($toInstance === Configuration::TO_NEW) {
            # If the data transfer configuration indicates that new instances
            # should be created, then create the new instance
            if ($configuration->createNewInstances()) {
                # Determine the new instance number
                if (array_key_exists($recordId, $destinationData)) {
                    $destinationRecord = &$destinationData[$recordId];
                    $instance = $destinationProject->getLastInstance(
                        $destinationRecord,
                        $destinationEvent,
                        $destinationRepeatForm
                    );

                    if ($instance === null) {
                        # Record exists, but has no instances of this row type
                        $instance = 1;
                    } else {
                        $instanceKey = "{$recordId}:{$destinationEvent}:{$destinationRepeatForm}:{$instance}";

                        if (!array_key_exists($instanceKey, $this->newInstances)) {
                            # There is at least one instance of this row type, and this
                            # instance was not previously create, so increment the last
                            # instance by 1 to get the new instance, and add this new
                            # instance to the instances created.
                            $instance++;

                            $instanceKey = "{$recordId}:{$destinationEvent}:{$destinationRepeatForm}:{$instance}";
                            $this->newInstances[$instanceKey] = true;
                        }
                    }
                } else {
                    # The record does not exist in the destination project
                    $instance = 1;
                }
            }
        }

        return $instance;
    }

    /**
     * Gets the source instance to use, based on the data transfer configuration,
     * when transferring data from a repeating field to a non-repeating field.
     */
    public function getRepeatingToNonRepeatingInstance(
        $sourceProject,
        $sourceRows,
        $sourceEvent,
        $sourceRepeatForm,
        $configuration
    ) {
        $instance = null;

        $fromInstance = $configuration->getRepeatingToNonRepeating();

        if ($fromInstance === Configuration::FROM_FIRST) {
            $instance = $sourceProject->getFirstInstance(
                $sourceRows,
                $sourceEvent,
                $sourceRepeatForm
            );
        } elseif ($fromInstance === Configuration::FROM_LAST) {
            $instance = $sourceProject->getLastInstance(
                $sourceRows,
                $sourceEvent,
                $sourceRepeatForm
            );
        }

        return $instance;
    }


    /**
     * Transfer the file fields from the source project data (which may be a subset of the data)
     * to the destination project.
     */
    public function transferFiles(
        $sourceData,
        $destinationData,
        $idToSecondaryMap
    ) {
        #---------------------------------------------------------
        # Create temporary directory to store files in
        #---------------------------------------------------------
        $tempDir = APP_PATH_TEMP . 'data-transfer-' . uniqid();

        $result = mkdir($tempDir, 0700);

        if ($result === false) {
            $message = 'Unable to create directory for file transfers.';
            throw new \Exception($message);
        }

        foreach ($sourceData as $recordId => $sourceRows) {
            $secondaryUniqueField = $idToSecondaryMap[$recordId] ?? null;

            $this->transferFilesInRecord($sourceRows, $recordId, $secondaryUniqueField, $destinationData, $tempDir);
        }

        rmdir($tempDir);
    }

    public function transferFilesInRecord($sourceRows, $recordId, $secondaryUniqueField, &$destinationData, $tempDir)
    {
        $recordUpdated = false;

        if ($this->configuration->getRecordMatch() === Configuration::MATCH_RECORD_ID) {
            $destinationRecordId = $recordId;
        } elseif ($this->configuration->getRecordMatch() === Configuration::MATCH_SECONDARY_ID) {
            $destinationRecordId = $secondaryUniqueField;
        } else {
            $message = "Unknown record match value: \"{$this->configuration->getRecordMatch()}\".";
            throw new \Exception($messsage);
        }

        if (!array_key_exists($destinationRecordId, $destinationData) && !$this->configuration->createNewRecords()) {
            # The record ID does not exist in the destination data and the configuration indicates that
            # new records should not be created in the destination project, so stop processing
            ;
        } else {
            foreach ($sourceRows as $sourceRow) {
                $sourceEvent          = $sourceRow['redcap_event_name'] ?? null;
                $sourceRepeatForm     = $sourceRow['redcap_repeat_instrument'] ?? null;
                $sourceRepeatInstance = $sourceRow['redcap_repeat_instance'] ?? null;

                $sourceFields = $this->sourceProject->getTransferFieldsInRow($sourceRow);

                # Copy the source file fields for this row to the destination row
                foreach ($sourceFields as $sourceField) {
                    if ($this->sourceProject->isFileField($sourceField)) {
                        $sourceForm = $this->sourceProject->getTransferFieldForm($sourceField);

                        $mappings =
                            $this->expandedFieldMap->getMappingsForSource(
                                $sourceEvent,
                                $sourceForm,
                                $sourceField
                            ) ?? [];

                        foreach ($mappings as $mapping) {
                            $fileUpdated = $this->transferFile(
                                $sourceRow,
                                $recordId,
                                $secondaryUniqueField,
                                $destinationData,
                                $mapping,
                                $tempDir
                            );

                            if ($fileUpdated) {
                                $recordUpdated = true;
                            }
                        }
                    }
                }
            }

            #---------------------------------------------------
            # Transfer DAG
            #---------------------------------------------------
            $dagUpdated = $this->transferDag($sourceRows, $destinationRecordId, $destinationData);
            if ($dagUpdated) {
                $recordUpdated = true;
            }
        } // else

        return $recordUpdated;
    }

    public function transferFile(
        $sourceRow,
        $recordId,
        $secondaryUniqueField,
        &$destinationData,
        $mapping,
        $tempDir
    ) {
        $fileUpdated = false;
        $updateFile  = false;

        if ($this->configuration->getRecordMatch() === Configuration::MATCH_RECORD_ID) {
            $destinationRecordId = $recordId;
        } elseif ($this->configuration->getRecordMatch() === Configuration::MATCH_SECONDARY_ID) {
            $destinationRecordId = $secondaryUniqueField;
        } else {
            $message = "Unknown record match value: \"{$this->configuration->getRecordMatch()}\".";
            throw new \Exception($messsage);
        }

        $sourceRepeatInstance = $sourceRow[Project::REDCAP_REPEAT_INSTANCE] ?? null;

        $sourceEvent    = $mapping->getSourceEvent();
        $sourceForm     = $mapping->getSourceForm();
        $sourceField    = $mapping->getSourceField();
        $sourceInstance = null;

        $destinationEvent    = $mapping->getDestinationEvent();
        $destinationForm     = $mapping->getDestinationForm();
        $destinationField    = $mapping->getDestinationField();
        $destinationInstance = null;

        # Set source repeating form (if any)
        $sourceRepeatForm = null;
        if ($this->sourceProject->isRepeatingForm($sourceEvent, $sourceForm)) {
             $sourceRepeatForm = $sourceForm;
        }

        # Set destination repeating form (if any)
        $destinationRepeatForm = null;
        if ($this->destinationProject->isRepeatingForm($destinationEvent, $destinationForm)) {
             $destinationRepeatForm = $destinationForm;
        }

        #-----------------------------------------
        # Get the source file
        #-----------------------------------------
        $sourceValue = null;
        $sourceFileInfo = [];
        try {
            $sourceFileInfo = $this->sourceProject->exportFile(
                $recordId,
                $sourceField,
                $sourceEvent,
                $sourceRepeatInstance
            );

            list($sourceMimeType, $sourceFileName, $sourceFileContents) = $sourceFileInfo;
            $sourceValue = $sourceFileContents;
        } catch (\Exception $exception) {
            $sourceFileInfo = [];
        }

        # error_log("\nSource value for record {$recordId}: {$sourceValue}\n", 3, __DIR__ . '/../transfer.log');

        if (!$this->configuration->getOverwriteWithBlanks() && empty($sourceValue)) {
            # Don't update the destination file if the source file is blank
            # and the configuration indicates not to overwrite existing values
            # with blanks
            $updateFile = false;
        } elseif (empty($sourceRepeatInstance)) {
            if ($this->destinationProject->isRepeating($destinationEvent, $destinationForm)) {
                #---------------------------------------
                # From Non-Repeating to Repeating
                #---------------------------------------
                $destinationInstance = $this->getNonRepeatingToRepeatingInstance(
                    $this->destinationProject,
                    $destinationData,
                    $destinationRecordId,
                    $destinationEvent,
                    $destinationRepeatForm,
                    $this->configuration
                );

                $updateFile = true;
            } else {
                #---------------------------------------
                # From Non-Repeating to Non-Repeating
                #---------------------------------------
                $updateFile = true;
            }
        } else {
            # Source field is repeating
            if ($this->destinationProject->isRepeating($destinationEvent, $destinationForm)) {
                #---------------------------------------
                # From Repeating to Repeating
                #---------------------------------------
                $sourceInstance      = $sourceRepeatInstance;
                $destinationInstance = $sourceRepeatInstance;

                $updateFile = true;
            } else {
                #---------------------------------------
                # From Repeating to Non-Repeating
                #---------------------------------------
                $destinationInstance =  null;

                $sourceInstance = $this->getRepeatingToNonRepeatingInstance(
                    $this->sourceProject,
                    $sourceRows,
                    $sourceEvent,
                    $sourceRepeatForm,
                    $this->configuration
                );

                if ($sourceRepeatInstance === $sourceInstance) {
                    # the instance for this source row matches the source instance
                    # that should be used for the transfer, so transfer the field
                    $updateFile = true;
                } else {
                    $updateFile = false;
                }
            }
        }

        if ($updateFile) {
            list($rowIndex, $recordAdded, $rowAdded) = $this->addBlankRecordRowIfNotExists(
                $this->configuration,
                $this->destinationProject,
                $destinationData,
                $destinationRecordId,
                $destinationEvent,
                $destinationRepeatForm,
                $destinationInstance
            );

            if ($rowIndex === Project::ROW_NOT_FOUND) {
                # The instance for the new data cannot be created
                $fileUpdated = false;
            } else {
                $destinationFileInfo = [];
                try {
                    $destinationFileInfo = $this->destinationProject->exportFile(
                        $destinationRecordId,
                        $destinationField,
                        $destinationEvent,
                        $destinationRepeatInstance
                    );
                } catch (\Exception $exception) {
                    $destinationFileInfo = [];
                }

                if (empty($destinationFileInfo)) {
                    $destinationMimeType     = '';
                    $destinationFileName     = '';
                    $destinationFileContents = '';
                } else {
                    list($destinationMimeType, $destinationFileName, $destinationFileContents)
                        = $destinationFileInfo;
                }

                if (
                    $destinationMimeType        === $sourceMimeType
                    && $destinationFileName     === $sourceFileName
                    && $destinationFileContents === $sourceFileContents
                ) {
                    # The destination file already matches the source file,
                    # so there is no need to update it
                    $fileUpdated = false;
                } elseif (empty($sourceFileInfo) || count($sourceFileInfo) < 3) {
                    # There is no source file, so if "overwrite with blanks" has been specified
                    # in the configuration and there is a destination file, then delete the
                    # destination file, otherwise don't update the destination file.
                    if ($this->configuration->getOverwriteWithBlanks() && !empty($destinationFileInfo)) {
                        $this->destinationProject->deleteFile(
                            $destinationRecordId,
                            $destinationField,
                            $destinationEvent,
                            $destinationInstance
                        );
                        $fileUpdated = true;
                    } else {
                        $fileUpdated = false;
                    }
                } else {
                    $sourceFilePath = $tempDir . DIRECTORY_SEPARATOR . $sourceFileName;

                    $result = file_put_contents($sourceFilePath, $sourceFileContents);
                    if ($result === false) {
                        $message = "Unable to create file \"{$sourceFilePath}\" for file transfer. "
                            . print_r($sourceFileInfo, true);
                        throw new \Exception($message);
                    }

                    # If the record or row was added, need to import that into destination project
                    # before file import, so the record/row will exist
                    if ($recordAdded || $rowAdded) {
                        $modifiedRecordIds[$destinationRecordId] = 1;
                        $this->importDestinationData($destinationData, $modifiedRecordIds, $this->overwriteBehavior);
                    }

                    $this->destinationProject->importFile(
                        $sourceFilePath,
                        $destinationRecordId,
                        $destinationField,
                        $destinationEvent,
                        $destinationInstance
                    );

                    unlink($sourceFilePath);
                    $fileUpdated = true;
                }

                #-----------------------------------------------------------------------------------
                # If a new record or instance was added to the destination project, then count
                # the field as updated (even if the destination field value was not updated)
                #-----------------------------------------------------------------------------------
                if ($recordAdded || $rowAdded) {
                    $fileUpdated = true;
                }
            }
        }

        return $fileUpdated;
    }

    public static function test()
    {
        return true;
    }

    public function getCreatedRecordIds()
    {
        return $this->createdRecordIds;
    }

    public function getSourceProject()
    {
        return $this->sourceProject;
    }

    public function getDestinationProject()
    {
        return $this->destinationProject;
    }
}
