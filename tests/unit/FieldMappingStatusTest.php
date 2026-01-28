<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class FieldMappingStatusTest extends TestCase
{
    public function test()
    {
        #----------------------------------
        # Creation and default values test
        #----------------------------------
        $fieldMappingStatus = new FieldMappingStatus();

        $this->assertNotNull($fieldMappingStatus, 'Object creation test');

        $this->assertTrue($fieldMappingStatus->isOk(), 'Default status test');

        $errors = $fieldMappingStatus->getErrors();
        $this->assertEquals([], $errors, 'Default errors test');

        #-------------------------
        # Errors test
        #-------------------------
        $fieldMappingStatus->addError('A');
        $fieldMappingStatus->addError('test');

        $errors = $fieldMappingStatus->getErrors();
        $this->assertEquals(['A', 'test'], $errors, 'Errors test');

        #-------------------------------------
        # Merge status
        #-------------------------------------
        $fieldMappingStatus->mergeStatus(FieldMappingStatus::ERROR);
        $this->assertTrue($fieldMappingStatus->isError(), 'Is error check');

        $fieldMappingStatus->mergeStatus(FieldMappingStatus::INCOMPLETE);
        $this->assertTrue($fieldMappingStatus->isIncomplete(), 'Is incomplete check');
    }
}
