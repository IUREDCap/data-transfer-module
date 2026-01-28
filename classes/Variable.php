<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

/**
 * Class for representing a variable/field in a REDCap Project.
 * This class implements the method that determines if to variables/fields
 * are equal (equivalent) or compatiable.
 */
class Variable
{
    public const TYPES_EQUAL      = 'equal';
    public const TYPES_COMPATIBLE = 'compatible';
    public const TYPES_NOT_EQUAL  = 'not-equal';

    private $name;
    private $formName; // the name of the form containing the variable
    private $fieldType;
    private $selectOptions;
    private $calculations;
    private $validationType;
    private $min;
    private $max;
    private $required;
    private $fieldAnnotation;
    private $actionTags;

    private $missingDataCodes;

    /**
     * @param array $fieldMetadata the metadata array for a single REDCap field/variable.
     *
     * MISSING DATA CODES ?????????????????????? FIX !!!!!!!!!!!!!!!!!!!
     */
    public function __construct($fieldMetadata, $missingDataCodes, $actionTags)
    {
        $this->actionTags = $actionTags;

        if (in_array('@NOMISSING', $actionTags)) {
            $this->missingDataCodes = [];
        } else {
            $this->missingDataCodes = $missingDataCodes;
        }

        $this->name = $fieldMetadata['field_name'];

        $this->formName = $fieldMetadata['form_name'];
        $this->fieldType = $fieldMetadata['field_type'];

        $this->calculations  = '';
        $this->selectOptions = array();

        if ($this->fieldType === 'calc') {
            $this->calculations = $fieldMetadata['select_choices_or_calculations'];
        } elseif ($this->fieldType === 'checkbox' || $this->fieldType === 'dropdown' || $this->fieldType === 'radio') {
            $optionsString = $fieldMetadata['select_choices_or_calculations'];
            $options = explode('|', $optionsString);
            foreach ($options as $option) {
                list($value, $label) = explode(', ', $option);
                $this->selectOptions[trim($value)] = trim($label);
            }
        }

        $this->validationType = '';
        if ($this->fieldType === 'text') {
            $this->validationType = $fieldMetadata['text_validation_type_or_show_slider_number'];
        }

        # Set min and max
        $this->min = '';
        $this->max = '';
        if ($this->fieldType === 'text') {
            $this->min = $fieldMetadata['text_validation_min'];
            $this->max = $fieldMetadata['text_validation_max'];
        } elseif ($this->fieldType === 'slider') {
            $this->min = $fieldMetadata['text_validation_min'];
            $this->max = $fieldMetadata['text_validation_max'];

            if ($this->min === null || $this->min === '') {
                # If no value, set to default
                $this->min = 0;
            }

            if ($this->max === null || $this->max === '') {
                # If no value, set to default
                $this->max = 100;
            }
        }

        $this->required = $fieldMetadata['required_field'];

        $this->fieldAnnotation = $fieldMetadata['field_annotation'];
    }

    /**
     * @param array $metadata metadata array for all variable in the project;
     *
     * @return array a map from variable name to Variable object.
     */
    public static function createVariableMap($metadata, $missingDataCodes, $actionTagsMap)
    {
        $map = [];
        foreach ($metadata as $variableMetadata) {
            $fieldName = $variableMetadata['field_name'];

            $variable = new Variable(
                $variableMetadata,
                $missingDataCodes,
                $actionTagsMap[$fieldName] ?? []
            );

            $map[$variable->getName()] = $variable;
        }

        return $map;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFormName()
    {
        return $this->formName;
    }

    public function getFieldType()
    {
        return $this->fieldType;
    }

    public function hasActionTag($actionTag)
    {
        return in_array($actionTag, $this->actionTags);
    }

    /**
     * Checks compatiblity for copying this variable to the specified variable.
     * Note that the direction of the copy matters. Copying an interger
     * to a number would be OK, because the types would be compatible,
     * However, when copying a number to an integer the variables
     * are considered "not equal", because the number may be too large to
     * be represented as an integer.
     *
     * @param $copyToVariable the variable to which this
     *     variable would be copied for the check.
     */
    public function compareVariable($copyToVariable)
    {
        $compare = self::TYPES_EQUAL;

        $fieldTypeCompare        = $this->compareFieldType($copyToVariable);
        $selectOptionsCompare    = $this->compareSelectOptions($copyToVariable);
        $calculationsCompare     = $this->compareCalculations($copyToVariable);
        $validationTypeCompare   = $this->compareValidationType($copyToVariable);
        $minCompare              = $this->compareMin($copyToVariable);
        $maxCompare              = $this->compareMax($copyToVariable);
        $requiredCompare         = $this->compareRequired($copyToVariable);
        $missingDataCodesCompare = $this->compareMissingDataCodes($copyToVariable);

        if (
            # If any of the type elements are not equal, then the types are not equal
            $fieldTypeCompare === self::TYPES_NOT_EQUAL
            || $selectOptionsCompare === self::TYPES_NOT_EQUAL
            || $calculationsCompare === self::TYPES_NOT_EQUAL
            || $validationTypeCompare === self::TYPES_NOT_EQUAL
            || $minCompare === self::TYPES_NOT_EQUAL
            || $maxCompare === self::TYPES_NOT_EQUAL
            || $requiredCompare === self::TYPES_NOT_EQUAL
            || $missingDataCodesCompare === self::TYPES_NOT_EQUAL
        ) {
            $compare = self::TYPES_NOT_EQUAL;
        } elseif (
            $fieldTypeCompare === self::TYPES_COMPATIBLE
            || $selectOptionsCompare === self::TYPES_COMPATIBLE
            || $calculationsCompare === self::TYPES_COMPATIBLE
            || $validationTypeCompare === self::TYPES_COMPATIBLE
            || $minCompare === self::TYPES_COMPATIBLE
            || $maxCompare === self::TYPES_COMPATIBLE
            || $requiredCompare === self::TYPES_COMPATIBLE
            || $missingDataCodesCompare === self::TYPES_COMPATIBLE
        ) {
            $compare = self::TYPES_COMPATIBLE;
        }

        # error_log("FIELD TYPE COMPARE: {$fieldTypeCompare}\n", 3, __DIR__ . '/../var.log');
        # error_log("SELECT OPTIONS COMPARE: {$selectOptionsCompare}\n", 3, __DIR__ . '/../var.log');
        # error_log("CALCULATIONS COMPARE: {$calculationsCompare}\n", 3, __DIR__ . '/../var.log');
        # error_log("VALIDATION TYPE COMPARE: {$validationTypeCompare}\n", 3, __DIR__ . '/../var.log');
        # error_log("MIN COMPARE: {$minCompare}\n", 3, __DIR__ . '/../var.log');
        # error_log("MAX COMPARE: {$maxCompare}\n", 3, __DIR__ . '/../var.log');
        # error_log("REQUIRED COMPARE: {$requiredCompare}\n", 3, __DIR__ . '/../var.log');
        # error_log("MISSING DATA CODE COMPARE: {$missingDataCodesCompare}\n", 3, __DIR__ . '/../var.log');
        # error_log("\nVARIABLE COMPARE: {$compare}\n", 3, __DIR__ . '/../var.log');

        return $compare;
    }

    /**
     * Compares the missing data codes for each variable.
     */
    public function compareMissingDataCodes($copyToVariable)
    {
        $compare = self::TYPES_NOT_EQUAL;

        if ($this->missingDataCodes === $copyToVariable->missingDataCodes) {
            $compare = self::TYPES_EQUAL;
        } else {
            $intersection = array_intersect_assoc($this->missingDataCodes, $copyToVariable->missingDataCodes);
            if ($this->missingDataCodes === $intersection) {
                # If the source missing data codes are a proper subset of the destination missing data codes
                $compare = self::TYPES_COMPATIBLE;
            }
        }

        return $compare;
    }

    public static function compareMissingDataCodeLists($fromList, $toList)
    {
        $compare = self::TYPES_NOT_EQUAL;

        if ($fromList === $toList) {
            $compare = self::TYPES_EQUAL;
        } else {
            $intersection = array_intersect_assoc($fromList, $toList);
            if ($fromList === $intersection) {
                # If the from missing data codes are a proper subset of the to missing data codes
                $compare = self::TYPES_COMPATIBLE;
            }
        }
        return $compare;
    }

    /**
     * @param Variable $copyToVariable the variable that this variable's values would
     *     be copied to.
     */
    public function compareFieldType($copyToVariable)
    {
        $compare = self::TYPES_NOT_EQUAL;

        $fromType = $this->getFieldType();
        $toType   = $copyToVariable->getFieldType();

        if ($fromType === $toType) {
            $compare = self::TYPES_EQUAL;
        } elseif ($fromType === 'radio' && $toType === 'dropdown') {
            $compare = self::TYPES_COMPATIBLE;
        } elseif ($fromType === 'dropdown' && $toType === 'radio') {
            $compare = self::TYPES_COMPATIBLE;
        }

        return $compare;
    }

    /**
     * @return array a map from select option value to option label.
     *     This should be set for variables with the following
     *     field types: checkbox dropdown, radio
     */
    public function getSelectOptions()
    {
        return $this->selectOptions;
    }

    /**
     * @param Variable $copyToVariable the variable that this variable's values would
     *     be copied to.
     */
    public function compareSelectOptions($copyToVariable)
    {
        $compare = self::TYPES_EQUAL;

        $fromOptions = $this->getSelectOptions();
        $toOptions   = $copyToVariable->getSelectOptions();

        foreach ($fromOptions as $value => $label) {
            if (!array_key_exists($value, $toOptions)) {
                $compare = self::TYPES_NOT_EQUAL;
                break;
            } elseif ($fromOptions[$value] !== $toOptions[$value]) {
                $compare = self::TYPES_NOT_EQUAL;
                break;
            }
        }

        if ($compare === self::TYPES_EQUAL && count($toOptions) > count($fromOptions)) {
            # If the to variable options are a superset of the options that are in the from variable
            $compare = self::TYPES_COMPATIBLE;
        }

        return $compare;
    }

    /**
     * @return string calculation formula for calc fields, and blank for all other field types
     */
    public function getCalculations()
    {
        return $this->calculations;
    }

    public function compareCalculations($copyToVariable)
    {
        $compare = self::TYPES_NOT_EQUAL;

        $fromCalc = $this->getCalculations();
        $toCalc   = $copyToVariable->getCalculations();

        if (($fromCalc === null || $fromCalc === '') && ($toCalc === null || $toCalc === '')) {
            $compare = self::TYPES_EQUAL;
        } elseif ($fromCalc === $toCalc) {
            $compare = self::TYPES_EQUAL;
        }

        return $compare;
    }


    /**
     * @return string the validation type for text fields, and blank for all other field types
     */
    public function getValidationType()
    {
        return $this->validationType;
    }

    public function compareValidationType($copyToVariable)
    {
        $compare = self::TYPES_NOT_EQUAL;

        $fromType = $this->getValidationType();
        $toType   = $copyToVariable->getValidationType();

        if (empty($fromType) && empty($toType)) {
            $compare = self::TYPES_EQUAL;
        } elseif ($fromType === $toType) {
            $compare = self::TYPES_EQUAL;
        } elseif ($fromType === 'integer' && $toType === 'number') {
            $compare = self::TYPES_COMPATIBLE;
        } elseif ($fromType === 'date_dmy') {
            if ($toType === 'date_mdy' || $toType === 'date_ymd') {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($fromType === 'date_mdy') {
            if ($toType === 'date_dmy' || $toType === 'date_ymd') {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($fromType === 'date_ymd') {
            if ($toType === 'date_dmy' || $toType === 'date_mdy') {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($fromType === 'datetime_dmy') {
            if ($toType === 'datetime_mdy' || $toType === 'datetime_ymd') {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($fromType === 'datetime_mdy') {
            if ($toType === 'datetime_dmy' || $toType = 'datetime_ymd') {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($fromType === 'datetime_ymd') {
            if ($toType === 'datetime_dmy' || $toType === 'datetime_mdy') {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($fromType === 'datetime_seconds_dmy') {
            if ($toType === 'datetime_seconds_mdy' || $toType === 'datetime_seconds_ymd') {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($fromType === 'datetime_seconds_mdy') {
            if ($toType === 'datetime_seconds_dmy' || $toType = 'datetime_seconds_ymd') {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($fromType === 'datetime_seconds_ymd') {
            if ($toType === 'datetime_seconds_dmy' || $toType === 'datetime_seconds_mdy') {
                $compare = self::TYPES_COMPATIBLE;
            }
        }

        return $compare;
    }

    /**
     * @return number the minimum value for text fields with a numeric type and slider fields,
     *     blank for all other field types.
     */

    public function getMin()
    {
        return $this->min;
    }

    public function compareMin($copyToVariable)
    {
        $compare = self::TYPES_NOT_EQUAL;

        $fromMin = $this->getMin();
        $toMin   = $copyToVariable->getMin();

        if ($toMin === null || $toMin === '') {
            # No minimum for "copy to" variable
            if ($fromMin === null || $fromMin === '') {
                $compare = self::TYPES_EQUAL;
            } else {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($toMin === $fromMin) {
            $compare = self::TYPES_EQUAL;
        } elseif (is_numeric($toMin) && is_numeric($fromMin) && $fromMin >= $toMin) {
            $compare = self::TYPES_COMPATIBLE;
        }

        return $compare;
    }

    public function getMax()
    {
        return $this->max;
    }

    public function compareMax($copyToVariable)
    {
        $compare = self::TYPES_NOT_EQUAL;

        $fromMax = $this->getMax();
        $toMax   = $copyToVariable->getMax();

        if ($toMax === null || $toMax === '') {
            # No maximum for "copy to" variable
            if ($fromMax === null || $fromMax === '') {
                $compare = self::TYPES_EQUAL;
            } else {
                $compare = self::TYPES_COMPATIBLE;
            }
        } elseif ($toMax === $fromMax) {
            $compare = self::TYPES_EQUAL;
        } elseif (is_numeric($toMax) && is_numeric($fromMax) && $fromMax <= $toMax) {
            $compare = self::TYPES_COMPATIBLE;
        }

        return $compare;
    }


    public function isRequired()
    {
        return $this->required;
    }

    public function compareRequired($copyToVariable)
    {
        $compare = self::TYPES_EQUAL;

        if (!($this->required) && $copyToVariable->required) {
            $compare = self::TYPES_NOT_EQUAL;
        } elseif ($this->required && !($copyToVariable->required)) {
            $compare = self::TYPES_COMPATIBLE;
        }

        return $compare;
    }


    public function isCompatibleWith($copyToVariable)
    {
        $compatible = false;
        $compare = $this->compareVariable($copyToVariable);

        if ($compare === self::TYPES_EQUAL || $compare === self::TYPES_COMPATIBLE) {
            $compatible = true;
        }

        return $compatible;
    }
}
