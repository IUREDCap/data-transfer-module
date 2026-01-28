<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function test()
    {
        $releaseNumber = Version::RELEASE_NUMBER;

        $this->assertNotNull($releaseNumber, 'Not null test');

        $this->assertMatchesRegularExpression('/^[\d]+\.[\d]+\.[\d]+$/', $releaseNumber, 'Pattern test');
    }
}
