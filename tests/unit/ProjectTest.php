<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    public function testCreate()
    {
        $module = null;
        $project = new Project($module);
        $this->assertNotNull($project, 'Object creation test');
    }
}
