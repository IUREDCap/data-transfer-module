<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class FieldMapTest extends TestCase
{
    public function testCreate()
    {
        $fieldMap = new FieldMap();
        $this->assertNotNull($fieldMap, 'Object creation test');
    }
}
