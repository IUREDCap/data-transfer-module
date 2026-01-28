<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use IU\PHPCap\RedCapProject;

/**
 * Class for representing a map of fields from the source project to the destination project.
 */
class FieldMap
{
    private $wildcardsExpanded;
    private $checkboxesExpanded;

    private $sourceIndex;

    private $fieldMappings;

    public function __construct()
    {
        $this->wildcardsExpanded  = false;
        $this->checkboxesExpanded = false;

        $this->fieldMappings = [];

        $this->sourceIndex = [];
    }

    public function getMappings()
    {
        return $this->fieldMappings;
    }

    public function getNumberOfMappings()
    {
        return count($this->fieldMappings);
    }

    public function setMappings($mappings)
    {
        $this->fieldMappings = $mappings;

        $this->wildcardsExpanded  = false;
        $this->checkboxesExpanded = false;
    }

    /**
     * Gets the incomplete mappings for the field map (if any).
     *
     * @return array an array of the incomplete field mappings.
     */
    public function getIncompleteMappings($sourceProject, $destinationProject)
    {
        $incomplete = [];
        foreach ($this->fieldMappings as $mapping) {
            if ($mapping->isIncomplete($sourceProject, $destinationProject)) {
                $incomplete[] = $mapping;
            }
        }

        return $incomplete;
    }


    /**
     * Get error mappings
     */
    public function getErrorMappings($sourceProject, $destinationProject)
    {
        $errorMappings = [];
        foreach ($this->fieldMappings as $mapping) {
            if ($mapping->isError($sourceProject, $destinationProject)) {
                $errorMappings[] = $mapping;
            }
        }

        return $errorMappings;
    }

    public function setFromJson($json)
    {
        $mappings = json_decode($json);

        if ($mappings !== null && is_array($mappings)) {
            foreach ($mappings as $mapping) {
                $fieldMapping = new FieldMapping();

                $fieldMapping->setSourceEvent($mapping->sourceEvent);
                $fieldMapping->setSourceForm($mapping->sourceForm);
                $fieldMapping->setSourceField($mapping->sourceField);

                $fieldMapping->setDestinationEvent($mapping->destinationEvent);
                $fieldMapping->setDestinationForm($mapping->destinationForm);
                $fieldMapping->setDestinationField($mapping->destinationField);

                $fieldMapping->setExcludeDestination($mapping->excludeDestination);

                $this->fieldMappings[] = $fieldMapping;
            }
        }

        $this->wildcardsExpanded  = false;
        $this->checkboxesExpanded = false;
    }

    /**
     * Simplify the rules so that all wildcard and duplicate rules are removed.
     * For duplicate rules, the last rule is kept.
     */
    public function simplify($module, $configuration, $expandCheckboxes = false)
    {
        $simplifiedFieldMap = $this->expand($module, $configuration, $expandCheckboxes);
        $simplifiedFieldMap->removeDuplicateDestinationFieldMappings();
        $simplifiedFieldMap->removeExcludedFieldMappings();
        $simplifiedFieldMap->createSourceIndex();

        return $simplifiedFieldMap;
    }

    /**
     * Expands wildcards (e.g., "ALL"), and optionally checkbox fields.
     *
     * @return FieldMap the expanded version of this FieldMap.
     */
    public function expand($module, $configuration, $expandCheckboxes = false)
    {
        $expandedFieldMap = new FieldMap();
        $expandedMappings = [];

        $sourceProject      = $configuration->getSourceProject($module);
        $destinationProject = $configuration->getDestinationProject($module);

        foreach ($this->fieldMappings as $mapping) {
            if ($mapping->getExcludeDestination()) {
                $mappings = $mapping->expandExcludeDestination($module, $configuration, $expandCheckboxes);
            } else {
                $mappings = $mapping->expand($module, $configuration, $expandCheckboxes);
            }

            $expandedMappings = array_merge($expandedMappings, $mappings);
        }

        $expandedFieldMap->setMappings($expandedMappings);
        $expandedFieldMap->createSourceIndex();

        $expandedFieldMap->wildcardsExpanded  = true;
        $expandedFieldMap->checkboxesExpanded = $expandCheckboxes;

        return $expandedFieldMap;
    }

    public function createSourceIndex()
    {
        $mappings = $this->fieldMappings;

        $this->sourceIndex = array();

        for ($i = 0; $i < count($mappings); $i++) {
            $mapping = $mappings[$i];
            $sourceEvent = $mapping->getSourceEvent();
            $sourceForm  = $mapping->getSourceForm();
            $sourceField = $mapping->getSourceField();

            $key = $this->createSourceKey($sourceEvent, $sourceForm, $sourceField);

            $this->sourceIndex[$key][] = $this->fieldMappings[$i];
        }
    }

    /**
     * Removes duplicate destination field mappings (last field mapping is kept).
     */
    public function removeDuplicateDestinationFieldMappings()
    {
        $destMap = [];
        for ($i = count($this->fieldMappings) - 1; $i >= 0; $i--) {
            $mapping = $this->fieldMappings[$i];

            $destinationEvent = $mapping->getDestinationEvent() ?? '';
            $destinationForm  = $mapping->getDestinationForm() ?? '';
            $destinationField = $mapping->getDestinationField() ?? '';

            $key = $destinationEvent . ':' . $destinationForm . ':' . $destinationField;
            if (array_key_exists($key, $destMap)) {
                # A later rule for this destination already exists, so delete this rule
                unset($this->fieldMappings[$i]);
            } else {
                $destMap[$key] = true;
            }
        }

        // re-index field mappings
        $this->fieldMappings = array_values($this->fieldMappings);
    }

    /**
     * Filters field mappings based on source form and optionally source event.
     * Inteded for use where the field mappings have been expanded, and
     * the results might not be as expected, if that is not the case.
     */
    public function filterMappings($sourceForm, $sourceEvent = null)
    {
        if (empty($sourceForm)) {
            throw new \Exception("No form specified for field map filter.");
        }

        if (empty($sourceEvent)) {
            # No event specified; filter by form only
            for ($i = count($this->fieldMappings) - 1; $i >= 0; $i--) {
                $mapping = $this->fieldMappings[$i];
                $mappingSourceForm = $mapping->getSourceForm();
                if ($mappingSourceForm !== $sourceForm) {
                    unset($this->fieldMappings[$i]);
                }
            }
        } else {
            # Event specified; filter by form and event
            for ($i = count($this->fieldMappings) - 1; $i >= 0; $i--) {
                $mapping = $this->fieldMappings[$i];
                $mappingSourceForm  = $mapping->getSourceForm();
                $mappingSourceEvent = $mapping->getSourceEvent();
                if ($mappingSourceForm !== $sourceForm || $mappingSourceEvent !== $sourceEvent) {
                    unset($this->fieldMappings[$i]);
                }
            }
        }

        // re-index field mappings
        $this->fieldMappings = array_values($this->fieldMappings);
        $this->createSourceIndex();
    }

    public function removeExcludedFieldMappings()
    {
        for ($i = count($this->fieldMappings) - 1; $i >= 0; $i--) {
            $mapping = $this->fieldMappings[$i];
            if ($mapping->getExcludeDestination()) {
                unset($this->fieldMappings[$i]);
            }
        }

        // re-index field mappings
        $this->fieldMappings = array_values($this->fieldMappings);
    }

    public function getMappingsForSource($sourceEvent, $sourceForm, $sourceField)
    {
        $key = $this->createSourceKey($sourceEvent, $sourceForm, $sourceField);
        $mappings = $this->sourceIndex[$key] ?? [];
        return $mappings;
    }

    public function createSourceKey($event, $form, $field)
    {
        $key = ($event ?? '') . ':' . ($form ?? '') . ($field ?? '');
        return $key;
    }
}
