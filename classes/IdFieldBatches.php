<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use IU\PHPCap\RedCapProject;

/**
 * Class for representing id field batches for a project.
 */
class IdFieldBatches
{
    private $batches;
    private $idToSecondaryMap;
    private $hasSecondaryUniqueField;

    public function __construct($project, $batchSize = 0, $filterLogic = '', $recordId = null)
    {
        $this->batches = [];
        $this->idToSecondaryMap = [];
        $this->hasSecondaryUniqueField = false;

        if (empty($batchSize) || !is_int($batchSize) || $batchSize < 1) {
            $batchSize = PHP_INT_MAX;
        }

        $recordIdFieldName = $project->getRecordIdField();
        $secondaryUniqueFieldName = $project->getSecondaryUniqueField();

        $fields = [$recordIdFieldName];
        if (!empty($secondaryUniqueFieldName)) {
            $fields[] = $secondaryUniqueFieldName;
            $this->hasSecondaryUniqueField = true;
        }

        # Get the specified "record ID" and "secondary unique field" fields for the project
        if (empty($recordId)) {
            $exportFields = $project->exportDataAp(['fields' => $fields, 'filterLogic' => $filterLogic]);
        } else {
            $exportFields = $project->exportDataAp(
                ['fields' => $fields, 'filterLogic' => $filterLogic, 'recordIds' => [$recordId]]
            );
        }

        # error_log("Export fields: " . print_r($exportFields, true) . "\n", 3, __DIR__ . '/../transfer.log');

        #-------------------------------------------------
        # Create record ID to secondary unique field map
        #-------------------------------------------------
        $this->idToSecondaryMap = array();
        foreach ($exportFields as $exportFieldsRow) {
            $recordId = $exportFieldsRow[$recordIdFieldName];

            if ($this->hasSecondaryUniqueField) {
                $secondaryUniqueField = $exportFieldsRow[$secondaryUniqueFieldName];
                if (!empty($secondaryUniqueField)) {
                    if (!array_key_exists($recordId, $this->idToSecondaryMap)) {
                        $this->idToSecondaryMap[$recordId] = $secondaryUniqueField;
                    }
                }
            } else {
                if (!array_key_exists($recordId, $this->idToSecondaryMap)) {
                        $this->idToSecondaryMap[$recordId] = null;
                }
            }
        }

        #error_log(
        #    "ID to Secondary Map: " . print_r($this->idToSecondaryMap, true) . "\n",
        #    3,
        #    __DIR__ . '/../transfer.log'
        #);

        for ($position = 0; $position < count($this->idToSecondaryMap); $position += $batchSize) {
            $recordIdBatch = array();
            $secondaryUniqueFieldBatch = array();

            $idFieldBatch = array();

            $origBatch = array_slice($this->idToSecondaryMap, $position, $batchSize, true);

            foreach ($origBatch as $recordId => $secondaryUniqueField) {
                $recordIdBatch[] = $recordId;
                $secondaryUniqueFieldBatch[] = $secondaryUniqueField;
            }

            $this->batches[] = [$recordIdBatch, $secondaryUniqueFieldBatch];
        }
    }

    public function getNumberOfBatches()
    {
        return count($this->batches);
    }

    public function getSecondaryUniqueFieldBatch($batchNumber)
    {
        $batch = $this->batches[$batchNumber][1] ?? [];
        return $batch;
    }

    public function getBatches()
    {
        return $this->batches;
    }

    public function hasSecondaryUniqueField()
    {
        return $this->hasSecondaryUniqueField;
    }

    public function getIdToSecondaryMap()
    {
        return $this->idToSecondaryMap;
    }

    public static function test()
    {
        return true;
    }
}
