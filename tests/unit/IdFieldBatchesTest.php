<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class IdFieldBatchesTest extends TestCase
{
    public function testCreate()
    {
        $result = IdFieldBatches::test();
        $this->assertTrue($result);
    }
}
