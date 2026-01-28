<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class DataTransfererTest extends TestCase
{
    public function testStatic()
    {
        $result = DataTransferer::test();
        $this->assertTrue($result);
    }

    public function testCreate()
    {
        $module = new DataTransfer();
        $configuration = new Configuration();

        $exceptionCaught = false;
        try {
            $dataTransferer = new DataTransferer($module, $configuration);
        } catch (\Exception $exception) {
            $exceptionCaught = true;
            $message = $exception->getMessage();
            $this->assertStringContainsString(
                'configuration has not been enabled',
                $message,
                'Configuration not enabled message test'
            );
        }
        $this->assertTrue($exceptionCaught, 'Configuration not enabled exception caught test');
    }
}
