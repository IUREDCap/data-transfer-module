<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

/**
 * Authorization class for determining who has permission
 * to for various operations.
 */
class Authorization
{
    /**
     * Indicates if the current user has the REDCap user rights
     * to access the Data Transfer external module
     * for the current project (admins always have access).
     *
     * @return boolean true if the user has permission (or is an admin), and
     *     false otherwise.
     */
    public static function hasRedCapUserRightsForDataTransfer($module)
    {
        $hasPermission = false;

        if ($module->isSuperUser()) {
            $hasPermission = true;
        } else {
            $rights = $module->getUserRights();

            # Users need to have project design permission and "full data set" data export permission
            # and not belong to a data access group (DAG)
            $canExportAllData = false;

            # If the user has design rights, can export all instruments,
            # and does not belong to a DAG (Data Access Croup)
            if ($rights['design'] && self::canExportAllInstruments($rights) && empty($rights['group_id'])) {
                $hasPermission = true;
            }
        }
        return $hasPermission;
    }

    /**
     * Gets individual instrument export rights, if defined.
     *
     * @param array $rights a user's REDCap project rights.
     */
    public static function getInstrumentExportRights($rights)
    {
        $instrumentExportRights = null;

        if (array_key_exists('forms_export', $rights)) {
            $formsExport = $rights['forms_export'];

            foreach ($formsExport as $form => $value) {
                $instrumentExportRights[$form] = $value;
            }
        }

        return $instrumentExportRights;
    }

    public static function canExportAllInstruments($rights)
    {
        $canExportAllInstruments = false;

        if (array_key_exists('forms_export', $rights) || array_key_exists('data_export_instruments', $rights)) {
            $canExportAllInstruments = true;
            $instrumentExportRights = self::getInstrumentExportRights($rights);
            foreach ($instrumentExportRights as $instrument => $accessLevel) {
                if ($accessLevel !== '1') {
                    $canExportAllInstruments = false;
                    break;
                }
            }
        }

        return $canExportAllInstruments;
    }
}
