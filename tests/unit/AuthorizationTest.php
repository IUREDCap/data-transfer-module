<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use PHPUnit\Framework\TestCase;

class AuthorizationTest extends TestCase
{
    public function testRights()
    {
        $userRights = [
            'project_id' => 486,
            'username' => 'test_user',
            'expiration' => '',
            'role_id' => '',
            'group_id' => '',
            'lock_record' => 0,
            'lock_record_multiform' => 0,
            'lock_record_customize' => 0,
            'data_export_tool' => '',
            'data_import_tool' => 0,
            'data_comparison_tool' => 0,
            'data_logging' => 0,
            'email_logging' => 0,
            'file_repository' => 1,
            'double_data' => 0,
            'user_rights' => 0,
            'data_access_groups' => 1,
            'graphical' => 1,
            'reports' => 1,
            'design' => 1,
            'alerts' => 0,
            'calendar' => 1,
            'api_token' => 'AC12395034F1439iCD12493AB1249844',
            'api_export' => 1,
            'api_import' => 1,
            'api_modules' => 0,
            'mobile_app' => 0,
            'mobile_app_download_data' => 0,
            'record_create' => 1,
            'record_rename' => 0,
            'record_delete' => 0,
            'dts' => 0,
            'participants' => 1,
            'data_quality_design' => 0,
            'data_quality_execute' => 0,
            'data_quality_resolution' => 0,
            'random_setup' => 0,
            'random_dashboard' => 0,
            'random_perform' => 0,
            'realtime_webservice_mapping' => 0,
            'realtime_webservice_adjudicate' => 0,
            'external_module_config' => '',
            'mycap_participants' => 1,
            'forms' => ['demographics' => 1 ],
            'forms_export' => ['demographics' => 1]
        ];

        $instrumentExportRights = Authorization::getInstrumentExportRights($userRights);
        $this->assertNotNull($instrumentExportRights, 'Non-null return value test');

        $this->assertEquals(['demographics' => 1], $instrumentExportRights, 'Instrument export rights test');
    }
}
