<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class FieldMappingTest extends TestCase
{
    public function testCreate()
    {
        $fieldMapping = new FieldMapping();
        $this->assertNotNull($fieldMapping, 'Object creation test');
    }
}
