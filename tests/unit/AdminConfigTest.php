<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class AdminConfigTest extends TestCase
{
    public function testCreate()
    {
        $adminConfig = new AdminConfig();
        $this->assertNotNull($adminConfig, 'Object creation test');

        $expectedCaCertFile = '/tmp/cacert.pem';

        $properties = array();
        $properties[AdminConfig::ALLOWED_CRON_TIMES] = array([0 => 'on'], [], [], [], [], [], []);
        $adminConfig->set($properties);

        $allowed = $adminConfig->isAllowedCronTime(1, 12);
        $this->assertFalse($allowed, 'Allowed cron time false test');

        $allowed = $adminConfig->isAllowedCronTime(0, 0);
        $this->assertTrue($allowed, 'Allowed cron time true test');
    }
}
