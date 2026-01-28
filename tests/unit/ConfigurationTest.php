<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testCreate()
    {
        $configuration = new Configuration();
        $this->assertNotNull($configuration, 'Object creation test');
    }

    public function testGettersAndSetters()
    {
        $configuration = new Configuration();
        $this->assertNotNull($configuration, 'Object creation test');

        #----------------------------------------------------
        # API token
        #----------------------------------------------------
        $apiToken = '12345678901234567890123456789012';
        $configuration->setApiToken($apiToken);

        $getApiToken = $configuration->getApiToken();
        $this->assertEquals($apiToken, $getApiToken, 'API token test');
    }

    public function testNameVaidation()
    {
        #----------------------------------
        # Empty name
        #----------------------------------
        $exceptionCaught = false;
        try {
            Configuration::validateName(null);
        } catch (\Exception $exception) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught, 'Empty name validation test');

        #----------------------------------
        # Non-string name
        #----------------------------------
        $exceptionCaught = false;
        try {
            Configuration::validateName(123);
        } catch (\Exception $exception) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught, 'Non-string name validation test');

        #----------------------------------
        # Invalid name string
        #----------------------------------
        $exceptionCaught = false;
        try {
            Configuration::validateName("test|123");
        } catch (\Exception $exception) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught, 'Invalid name string validation test');
    }

    public function testConvertCheckbox()
    {
        $configuration = new Configuration();
        $this->assertNotNull($configuration, 'Object creation test');

        $value = $configuration->convertCheckboxValue('on');
        $this->assertTrue($value, 'Value "on" check');

        $value = $configuration->convertCheckboxValue('1');
        $this->assertTrue($value, 'Value "1" check');

        $value = $configuration->convertCheckboxValue('true');
        $this->assertTrue($value, 'Value "true" check');

        $value = $configuration->convertCheckboxValue(true);
        $this->assertTrue($value, 'Value true check');

        $value = $configuration->convertCheckboxValue(1);
        $this->assertTrue($value, 'Value 1 check');

        $value = $configuration->convertCheckboxValue('off');
        $this->assertFalse($value, 'Value "off" check');

        $value = $configuration->convertCheckboxValue('false');
        $this->assertFalse($value, 'Value "false" check');

        $value = $configuration->convertCheckboxValue(0);
        $this->assertFalse($value, 'Value 0 check');

        $value = $configuration->convertCheckboxValue(false);
        $this->assertFalse($value, 'Value false check');
    }

    public function testSetDagMapWithoutPermission()
    {
        $configuration = new Configuration();
        $this->assertNotNull($configuration, 'Object creation test');

        $exceptionCaught = false;
        try {
            $properties = [];
            $username = 'a_test_user';
            $projectUsers = ['test_user1', 'test_user2'];
            $isSuperUser = false;

            $configuration->setDagMapFromProperties($properties, $username, $projectUsers, $isSuperUser);
        } catch (\Exception $exception) {
            $exceptionCaught = true;
            $this->assertStringContainsString(
                'does not have perrmission to modify',
                $exception->getMessage(),
                'Exception message test'
            );
        }

        $this->assertTrue($exceptionCaught, 'Exception caught test');
    }

    public function testPermissions()
    {
        $configuration = new Configuration();
        $this->assertNotNull($configuration, 'Object creation test');

        #---------------------------------------------
        # May modify
        #---------------------------------------------
        $configuration->setOwner('test_user1');
        $user = 'test_user3';
        $projectUsers = ['test_user1', 'test_user2'];
        $isSuperUser = false;
        $mayModify = $configuration->mayBeModifiedByUser($user, $projectUsers, $isSuperUser);
        $this->assertFalse($mayModify, 'May modify non-project user test');

        $user = 'test_user2';
        $mayModify = $configuration->mayBeModifiedByUser($user, $projectUsers, $isSuperUser);
        $this->assertFalse($mayModify, 'May modify project user, non-owner test');

        $user = 'abitc';
        $isSuperUser = true;
        $mayModify = $configuration->mayBeModifiedByUser($user, $projectUsers, $isSuperUser);
        $this->assertTrue($mayModify, 'May modify superuser test');

        $user = 'test_user1';
        $isSuperUser = false;
        $mayModify = $configuration->mayBeModifiedByUser($user, $projectUsers, $isSuperUser);
        $this->assertTrue($mayModify, 'May modify owner test');

        #---------------------------------------------
        # May delete
        #---------------------------------------------
        $configuration->setOwner('test_user1');
        $user = 'test_user3';
        $projectUsers = ['test_user1', 'test_user2'];
        $isSuperUser = false;
        $mayDelete = $configuration->mayBeDeletedByUser($user, $projectUsers, $isSuperUser);
        $this->assertFalse($mayDelete, 'May delete non-project user test');

        $user = 'test_user2';
        $mayDelete = $configuration->mayBeDeletedByUser($user, $projectUsers, $isSuperUser);
        $this->assertFalse($mayDelete, 'May delete project user, non-owner test');

        $user = 'abitc';
        $isSuperUser = true;
        $mayDelete = $configuration->mayBeDeletedByUser($user, $projectUsers, $isSuperUser);
        $this->assertTrue($mayDelete, 'May delete superuser test');

        $user = 'test_user1';
        $isSuperUser = false;
        $mayDelete = $configuration->mayBeDeletedByUser($user, $projectUsers, $isSuperUser);
        $this->assertTrue($mayDelete, 'May delete owner test');

        $user = 'test_user2';
        $projectUsers = ['test_user2'];
        $isSuperUser = false;
        $mayDelete = $configuration->mayBeDeletedByUser($user, $projectUsers, $isSuperUser);
        $this->assertTrue($mayDelete, 'May delete non-owneri, but owner no longer a project user test');

        #---------------------------------------------
        # May rename
        #---------------------------------------------
        $configuration->setOwner('test_user1');
        $user = 'test_user3';
        $projectUsers = ['test_user1', 'test_user2'];
        $isSuperUser = false;
        $mayRename = $configuration->mayBeRenamedByUser($user, $projectUsers, $isSuperUser);
        $this->assertFalse($mayRename, 'May rename non-project user test');

        $user = 'test_user2';
        $mayRename = $configuration->mayBeRenamedByUser($user, $projectUsers, $isSuperUser);
        $this->assertFalse($mayRename, 'May rename project user, non-owner test');

        $user = 'abitc';
        $isSuperUser = true;
        $mayRename = $configuration->mayBeRenamedByUser($user, $projectUsers, $isSuperUser);
        $this->assertTrue($mayRename, 'May rename superuser test');

        $user = 'test_user1';
        $isSuperUser = false;
        $mayRename = $configuration->mayBeRenamedByUser($user, $projectUsers, $isSuperUser);
        $this->assertTrue($mayRename, 'May rename owner test');

        $user = 'test_user2';
        $projectUsers = ['test_user2'];
        $isSuperUser = false;
        $mayRename = $configuration->mayBeRenamedByUser($user, $projectUsers, $isSuperUser);
        $this->assertTrue($mayRename, 'May rename non-owneri, but owner no longer a project user test');
    }
}
