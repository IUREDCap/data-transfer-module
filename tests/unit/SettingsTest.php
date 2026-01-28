<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testCreate()
    {
        $result = Settings::test();
        $this->assertTrue($result);
    }
}
