<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class ConfigurationsTest extends TestCase
{
    public function testCreate()
    {
        $configurations = new Configurations();
        $this->assertNotNull($configurations, 'Object creation test');
    }
}
