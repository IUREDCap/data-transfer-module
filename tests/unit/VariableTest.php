<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class VariableTest extends TestCase
{
    private $recordIdMetadata = [
        'field_name'  => 'record_id',
        'form_name'   => 'enrollment',
        'field_type'  => 'text',
        'field_label' => 'Study ID',
        'select_choices_or_calculations',
        'text_validation_type_or_show_slider_number' => '',
        'text_validation_min'                        => '',
        'text_validation_max'                        => '',
        'required_field' => 1,
        'field_annotation' => ''
    ];

    private $addressMetadata = [
        'field_name' => 'address',
        'form_name' => 'demographics',
        'section_header' => '',
        'field_type' => 'notes',
        'field_label' => 'Street, City, State, ZIP',
        'select_choices_or_calculations' => '',
        'field_note' => '',
        'text_validation_type_or_show_slider_number' => '',
        'text_validation_min' => '',
        'text_validation_max' => '',
        'identifier' => 'y',
        'branching_logic' => '',
        'required_field' => 0,
        'field_annotation' => ''
    ];

    private $birthdateYmdMetadata = [
        'field_name' => 'dob',
        'form_name' => 'demographics',
        'field_type' => 'text',
        'field_label' => 'Date of birth',
        'select_choices_or_calculations' => '',
        'field_note' => '',
        'text_validation_type_or_show_slider_number' => 'date_ymd',
        'text_validation_min' => '',
        'text_validation_max' => '',
        'identifier' => 'y',
        'branching_logic' => '',
        'required_field' => 0,
        'field_annotation' => ''
    ];

    private $ethnicityMetadata = [
        'field_name' => 'ethnicity',
        'form_name' => 'demographics',
        'field_type' => 'radio',
        'field_label' => 'Ethnicity',
        'select_choices_or_calculations'
             => '0, Hispanic or Latino|1, NOT Hispanic or Latino|2, Unknown / Not Reported',
        'field_note' => '',
        'text_validation_type_or_show_slider_number' => '',
        'text_validation_min' => '',
        'text_validation_max' => '',
        'identifier' => '',
        'branching_logic' => '',
        'required_field' => 0,
        'field_annotation' => ''
    ];

    private $weightMetadata = [
        'field_name' => 'weight',
        'form_name' => 'demographics',
        'field_type' => 'text',
        'field_label' => 'Weight (kilograms)',
        'select_choices_or_calculations' => '',
        'field_note' => '',
        'text_validation_type_or_show_slider_number' => 'integer',
        'text_validation_min' => 35,
        'text_validation_max' => 200,
        'identifier' => '',
        'branching_logic' => '',
        'required_field' => 0,
        'field_annotation' => ''
    ];

    private $bmiMetadata = [
        'field_name' => 'bmi',
        'form_name' => 'demographics',
        'field_type' => 'calc',
        'field_label' => 'BMI',
        'select_choices_or_calculations' => 'round(([weight]*10000)/(([height])^(2)),1)',
        'field_note' => '',
        'text_validation_type_or_show_slider_number' => '',
        'text_validation_min' => null,
        'text_validation_max' => null,
        'identifier' => '',
        'branching_logic' => '',
        'required_field' => null,
        'field_annotation' => ''
    ];

    private $ratingMetadata = [
        'field_name' => 'rating',
        'form_name' => 'form_1',
        'section_header' => '',
        'field_type' => 'slider',
        'field_label' => 'Rating',
        'select_choices_or_calculations' => '',
        'field_note' => '','
        text_validation_type_or_show_slider_number' => '',
        'text_validation_min' => '',
        'text_validation_max' => '',
        'identifier' => '',
        'branching_logic' => '',
        'required_field' => '',
        'field_annotation' => ''
    ];

    private $rating2Metadata = [
        'field_name' => 'rating2',
        'form_name' => 'form_1',
        'section_header' => '',
        'field_type' => 'slider',
        'field_label' => 'Rating 2',
        'select_choices_or_calculations' => '',
        'field_note' => '','text_validation_type_or_show_slider_number' => '',
        'text_validation_min' => '1',
        'text_validation_max' => '10',
        'identifier' => '',
        'branching_logic' => '',
        'required_field' => '',
        'custom_alignment' => 'RH',
        'question_number' => '',
        'matrix_group_name' => '',
        'matrix_ranking' => '',
        'field_annotation' => ''
    ];

    public function testCreate()
    {
        $variable = new Variable($this->recordIdMetadata, [], []);
        $this->assertNotNull($variable, 'Object creation test');

        $this->assertEquals('record_id', $variable->getName(), 'Name test');

        $this->assertEquals('enrollment', $variable->getFormName(), 'Form name test');

        $rating = new Variable($this->ratingMetadata, [], []);
        $this->assertNotNull($rating, 'Rating variable creation test');
        $this->assertEquals(0, $rating->getMin(), 'Rating min test');
        $this->assertEquals(100, $rating->getMax(), 'Rating max test');

        $rating2 = new Variable($this->rating2Metadata, [], []);
        $this->assertNotNull($rating2, 'Rating 2 variable creation test');
        $this->assertEquals(1, $rating2->getMin(), 'Rating 2 min test');
        $this->assertEquals(10, $rating2->getMax(), 'Rating 2 max test');
    }

    public function testCompare()
    {
        $recordIdVariable = new Variable($this->recordIdMetadata, [], []);
        $this->assertNotNull($recordIdVariable, 'Record ID variable creation test');

        $addressVariable = new Variable($this->addressMetadata, [], []);
        $this->assertNotNull($addressVariable, 'Address variable creation test');

        $birthdateYmdVariable = new Variable($this->birthdateYmdMetadata, [], []);
        $this->assertNotNull($birthdateYmdVariable, 'Birthdate YMD variable creation test');

        $weightVariable = new Variable($this->weightMetadata, [], []);
        $this->assertNotNull($weightVariable, 'Weight variable creation test');

        $bmiVariable = new Variable($this->bmiMetadata, [], []);
        $this->assertNotNull($bmiVariable, 'BMI variable creation test');

        #-----------------------------------
        # Check equal variables
        #-----------------------------------
        $cmp = $recordIdVariable->compareVariable($recordIdVariable);
        $this->assertEquals(Variable::TYPES_EQUAL, $cmp, 'Record ID self comparison');
        $isCompatible = $recordIdVariable->isCompatibleWith($recordIdVariable);
        $this->assertTrue($isCompatible, 'Record ID self comptible test');

        $cmp = $addressVariable->compareVariable($addressVariable);
        $this->assertEquals(Variable::TYPES_EQUAL, $cmp, 'Address self comparison');

        $cmp = $birthdateYmdVariable->compareVariable($birthdateYmdVariable);
        $this->assertEquals(Variable::TYPES_EQUAL, $cmp, 'Birthdate YMD self comparison');

        $cmp = $weightVariable->compareVariable($weightVariable);
        $this->assertEquals(Variable::TYPES_EQUAL, $cmp, 'Weight self comparison');

        $cmp = $bmiVariable->compareVariable($bmiVariable);
        $this->assertEquals(Variable::TYPES_EQUAL, $cmp, 'BMI self comparison');

        #------------------------------------
        # Check not equals variables
        #------------------------------------
        $cmp = $recordIdVariable->compareVariable($addressVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Record ID and address comparison');

        $cmp = $recordIdVariable->compareVariable($birthdateYmdVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Record ID and birthdate YMD comparison');

        $cmp = $addressVariable->compareVariable($birthdateYmdVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Address and birthdate YMD comparison');

        $cmp = $addressVariable->compareVariable($bmiVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Address and BMI comparison');
    }

    public function testRequired()
    {
        #---------------------------------------------
        # Check required vs. non-required field
        #---------------------------------------------
        $addressVariable = new Variable($this->addressMetadata, [], []);
        $this->assertEquals(0, $addressVariable->isRequired(), 'Non-required variable not required test');

        $addressRequiredMetadata = $this->addressMetadata;
        $addressRequiredMetadata['required_field'] = 1;
        $addressRequiredVariable = new Variable($addressRequiredMetadata, [], []);

        $this->assertEquals(1, $addressRequiredVariable->isRequired(), 'Required variable required test');

        $cmp = $addressRequiredVariable->compareVariable($addressVariable);
        # Should be compatible, because checking copying of required to non-required
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'address required and address non-required comparison');

        $isCompatible = $addressRequiredVariable->isCompatibleWith($addressVariable);
        $this->assertTrue($isCompatible, 'address required to addree compatible test');

        $cmp = $addressVariable->compareVariable($addressRequiredVariable);
        # Should be not equal, because checking copying of non-required to equired
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'address non-required and address required comparison');

        $isCompatible = $addressVariable->isCompatibleWith($addressRequiredVariable);
        $this->assertFalse($isCompatible, 'address to addree required compatible test');
    }

    public function testCompareRadioAndDropdown()
    {
        #--------------------------------------------
        # Check radio vs. dropdown
        #--------------------------------------------
        $ethnicityRadioVariable = new Variable($this->ethnicityMetadata, [], []);
        $ethnicityDropdownMetadata = $this->ethnicityMetadata;
        $ethnicityDropdownMetadata['field_type'] = 'dropdown';
        $ethnicityDropdownVariable = new Variable($ethnicityDropdownMetadata, [], []);

        $cmp = $ethnicityRadioVariable->compareVariable($ethnicityDropdownVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'radio and dropdown comparison');

        $cmp = $ethnicityDropdownVariable->compareVariable($ethnicityRadioVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'dropdown and radio comparison');
    }

    public function testCompareSelection()
    {
        #--------------------------------------------
        # Check radio vs. dropdown
        #--------------------------------------------
        $ethnicityVariable = new Variable($this->ethnicityMetadata, [], []);

        $ethnicityLessMetadata = $this->ethnicityMetadata;
        $ethnicityLessMetadata['select_choices_or_calculations'] = '0, Hispanic or Latino|1, NOT Hispanic or Latino';
        $ethnicityLessVariable = new Variable($ethnicityLessMetadata, [], []);

        # Compare selections that match except one has one less value
        $cmp = $ethnicityVariable->compareVariable($ethnicityLessVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Select copied to select with one less option comparison');

        $cmp = $ethnicityLessVariable->compareVariable($ethnicityVariable);
        $this->assertEquals(
            Variable::TYPES_COMPATIBLE,
            $cmp,
            'Select with one less option copied to select comparison'
        );

        $ethnicityDiffMetadata = $this->ethnicityMetadata;
        $ethnicityDiffMetadata['select_choices_or_calculations']
             = '0, Hispanic or Latino|1, NOT Hispanic or Latino|2, NA';

        $ethnicityDiffVariable = new Variable($ethnicityDiffMetadata, [], []);

        $cmp = $ethnicityDiffVariable->compareVariable($ethnicityVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Different selects 1');

        $cmp = $ethnicityVariable->compareVariable($ethnicityDiffVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Different selects 2');
    }

    public function testNumberAndInteger()
    {
        $weightIntegerVariable = new Variable($this->weightMetadata, [], []);

        $weightNumberMetadata = $this->weightMetadata;
        $weightNumberMetadata['text_validation_type_or_show_slider_number'] = 'number';
        $weightNumberVariable = new Variable($weightNumberMetadata, [], []);

        $cmp = $weightIntegerVariable->compareVariable($weightNumberVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'Integer to number test');

        $cmp = $weightNumberVariable->compareVariable($weightIntegerVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Number to integer test');
    }

    public function testDateTypes()
    {
        $ymdVariable = new Variable($this->birthdateYmdMetadata, [], []);

        $mdyMetadata = $this->birthdateYmdMetadata;
        $mdyMetadata['text_validation_type_or_show_slider_number'] = 'date_mdy';
        $mdyVariable = new Variable($mdyMetadata, [], []);

        $dmyMetadata = $this->birthdateYmdMetadata;
        $dmyMetadata['text_validation_type_or_show_slider_number'] = 'date_dmy';
        $dmyVariable = new Variable($dmyMetadata, [], []);

        #----------------------------------------
        # Compare YMD to other date formats
        #----------------------------------------
        $cmp = $ymdVariable->compareVariable($mdyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'YMD to MDY comparison');

        $cmp = $ymdVariable->compareVariable($dmyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'YMD to DMY comparison');

        #----------------------------------------
        # Compare MDY to other date formats
        #----------------------------------------
        $cmp = $mdyVariable->compareVariable($ymdVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'MDY to YMD comparison');

        $cmp = $mdyVariable->compareVariable($dmyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'MDY to DMY comparison');

        #----------------------------------------
        # Compare DMY to other date formats
        #----------------------------------------
        $cmp = $dmyVariable->compareVariable($ymdVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'DMY to YMD comparison');

        $cmp = $dmyVariable->compareVariable($mdyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'DMY to MDY comparison');
    }

    public function testDatetimeTypes()
    {
        $ymdMetadata = $this->birthdateYmdMetadata;
        $ymdMetadata['text_validation_type_or_show_slider_number'] = 'datetime_ymd';
        $ymdVariable = new Variable($ymdMetadata, [], []);

        $mdyMetadata = $this->birthdateYmdMetadata;
        $mdyMetadata['text_validation_type_or_show_slider_number'] = 'datetime_mdy';
        $mdyVariable = new Variable($mdyMetadata, [], []);

        $dmyMetadata = $this->birthdateYmdMetadata;
        $dmyMetadata['text_validation_type_or_show_slider_number'] = 'datetime_dmy';
        $dmyVariable = new Variable($dmyMetadata, [], []);

        #----------------------------------------
        # Compare YMD to other date formats
        #----------------------------------------
        $cmp = $ymdVariable->compareVariable($mdyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'YMD to MDY comparison');

        $cmp = $ymdVariable->compareVariable($dmyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'YMD to DMY comparison');

        #----------------------------------------
        # Compare MDY to other date formats
        #----------------------------------------
        $cmp = $mdyVariable->compareVariable($ymdVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'MDY to YMD comparison');

        $cmp = $mdyVariable->compareVariable($dmyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'MDY to DMY comparison');

        #----------------------------------------
        # Compare DMY to other date formats
        #----------------------------------------
        $cmp = $dmyVariable->compareVariable($ymdVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'DMY to YMD comparison');

        $cmp = $dmyVariable->compareVariable($mdyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'DMY to MDY comparison');
    }

    public function testDatetimeSecondsTypes()
    {
        $ymdMetadata = $this->birthdateYmdMetadata;
        $ymdMetadata['text_validation_type_or_show_slider_number'] = 'datetime_seconds_ymd';
        $ymdVariable = new Variable($ymdMetadata, [], []);

        $mdyMetadata = $this->birthdateYmdMetadata;
        $mdyMetadata['text_validation_type_or_show_slider_number'] = 'datetime_seconds_mdy';
        $mdyVariable = new Variable($mdyMetadata, [], []);

        $dmyMetadata = $this->birthdateYmdMetadata;
        $dmyMetadata['text_validation_type_or_show_slider_number'] = 'datetime_seconds_dmy';
        $dmyVariable = new Variable($dmyMetadata, [], []);

        #----------------------------------------
        # Compare YMD to other date formats
        #----------------------------------------
        $cmp = $ymdVariable->compareVariable($mdyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'YMD to MDY comparison');

        $cmp = $ymdVariable->compareVariable($dmyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'YMD to DMY comparison');

        #----------------------------------------
        # Compare MDY to other date formats
        #----------------------------------------
        $cmp = $mdyVariable->compareVariable($ymdVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'MDY to YMD comparison');

        $cmp = $mdyVariable->compareVariable($dmyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'MDY to DMY comparison');

        #----------------------------------------
        # Compare DMY to other date formats
        #----------------------------------------
        $cmp = $dmyVariable->compareVariable($ymdVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'DMY to YMD comparison');

        $cmp = $dmyVariable->compareVariable($mdyVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'DMY to MDY comparison');
    }

    public function testMinMax()
    {
        $weightMinMaxVariable = new Variable($this->weightMetadata, [], []);
        $this->assertNotNull($weightMinMaxVariable, 'Weight min/max variable creation test');

        $weightNoMinNoMaxMetadata = $this->weightMetadata;
        $weightNoMinNoMaxMetadata['text_validation_min'] = null;
        $weightNoMinNoMaxMetadata['text_validation_max'] = null;
        $weightNoMinNoMaxVariable = new Variable($weightNoMinNoMaxMetadata, [], []);

        # Can transfer data from a field with a min and a max to a field with no min and no max
        $cmp = $weightMinMaxVariable->compareVariable($weightNoMinNoMaxVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'Min and max to no min and no max test');

        # Cannot trasfer data from a field with no min and no max to a field with a min and a max
        $cmp = $weightNoMinNoMaxVariable->compareVariable($weightMinMaxVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'No min and no max to min and max test');

        $weightHigherMinLowerMaxMetadata = $this->weightMetadata;
        $weightHigherMinLowerMaxMetadata['text_validation_min'] = 50;
        $weightHigherMinLowerMaxMetadata['text_validation_max'] = 150;
        $weightHigherMinLowerMaxVariable = new Variable($weightHigherMinLowerMaxMetadata, [], []);

        # Can transfer data from a field with a higher min and lower max to a field with min and max
        $cmp = $weightHigherMinLowerMaxVariable->compareVariable($weightMinMaxVariable);
        $this->assertEquals(Variable::TYPES_COMPATIBLE, $cmp, 'Higher min and lower max to min and max');

        # Cannot transfer data from a field with min and max to a field with a higher min or a lower max
        $cmp = $weightMinMaxVariable->compareVariable($weightHigherMinLowerMaxVariable);
        $this->assertEquals(Variable::TYPES_NOT_EQUAL, $cmp, 'Min and max to higher min and lower max');
    }

    public function testCreateVariableMap()
    {
        $metadata = [$this->recordIdMetadata, $this->addressMetadata];
        $variableMap = Variable::createVariableMap($metadata, [], []);

        $this->assertNotNull($variableMap, 'Not null check');
        $this->assertIsArray($variableMap, 'Is array check');
        $this->assertEquals(2, count($variableMap), 'Array size check');

        $variable = $variableMap['record_id'];
        $this->assertNotNull($variable, 'Variable not null check');
    }
}
