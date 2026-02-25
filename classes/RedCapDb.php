<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

/**
 * Class for methods that access the REDCap database directly.
 */
class RedCapDb
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }


    /**
     * Gets the projects (ID and project name) for the specified user where
     * the project has not been deleted and the project is not completed
     * and the user's rights for the project have not expired.
     *
     * @param string $username the username for use whose projects should be gotten.
     */
    public function getUserProjects($username)
    {
        $projects = array();

        $sql = "select p.project_id, p.app_title"
            . " from redcap_user_rights ur, redcap_projects p"
            . " where "
            . "     ur.username = ?"
            . "     and ur.project_id = p.project_id"
            . "     and p.date_deleted IS NULL"
            . "     and p.completed_time IS NULL"
            . "     and ("
            . "         ur.expiration IS NULL"
            . "         or"
            . "         ur.expiration > DATE_SUB(now(), INTERVAL 0 SECOND)"
            . "     )"
            . " order by p.app_title, p.project_id"
            ;
        $params = [$username];

        $result = $this->module->query($sql, $params);

        while ($row = db_fetch_assoc($result)) {
            $projectId    = $row['project_id'];
            $projectTitle = $row['app_title'];
            $projects[$projectId] = $projectTitle;
        }

        return $projects;
    }

    public function getProjectInfo($projectId)
    {
        $projectInfo = [];
        $sql = "select project_id, app_title as project_title,"
            . " creation_time, production_time,"
            . " project_language,"
            . " purpose, purpose_other,"
            . " secondary_pk as secondary_unique_field,"
            . " repeatforms as is_longitudinal,"
            . " surveys_enabled,"
            . " auto_inc_set as record_autonumbering_enabled,"
            . " missing_data_codes"
            . " from redcap_projects"
            . " where project_id = ?";
        $params = [$projectId];
        $result = $this->module->query($sql, $params);
        $projectInfo = db_fetch_assoc($result);
        return $projectInfo;
    }

    public function getArmEventFormInfo($projectId)
    {
        $mappings = [];
        $sql = "select distinct arms.arm_id, arms.arm_name, arms.arm_num,"
            . " concat(lower(replace(e_meta.descrip, ' ', '_')), '_arm_', arms.arm_num) as unique_event_name,"
            . " e_meta.descrip as event_label,"
            . " e_forms.form_name, meta.form_menu_description as form_label"
            . " from redcap_events_arms arms, redcap_events_metadata e_meta,"
            . " redcap_events_forms e_forms, redcap_metadata meta"
            . " where arms.project_id = ?"
            . " and arms.arm_id = e_meta.arm_id"
            . " and arms.project_id = meta.project_id"
            . " and e_forms.form_name = meta.form_name"
            . " and meta.form_menu_description is NOT NULL"
            ;
        $params = [$projectId];
        $result = $this->module->query($sql, $params);

        while ($row = db_fetch_assoc($result)) {
            $mappings[] = $row;
        }

        return $mappings;
    }

    /**
     * Gets arms for project in a format that matches the
     * API exportArms method.
     */
    public function getArms($projectId)
    {
        $arms = [];
        $sql = "select arm_num, arm_name as name"
            . " from redcap_events_arms"
            . " where project_id = ?"
            ;
        $params = [$projectId];
        $result = $this->module->query($sql, $params);

        while ($row = db_fetch_assoc($result)) {
            $arms[] = $row;
        }

        return $arms;
    }

    public function getEvents($projectId)
    {
        $mappings = [];
        $sql = "select distinct e_meta.descrip as event_name, arms.arm_num,"
            . " concat(lower(replace(e_meta.descrip, ' ', '_')), '_arm_', arms.arm_num) as unique_event_name,"
            . " e_meta.custom_event_label, e_meta.event_id"
            . " from redcap_events_arms arms, redcap_events_metadata e_meta,"
            . " redcap_events_forms e_forms, redcap_metadata meta"
            . " where arms.project_id = ?"
            . " and arms.arm_id = e_meta.arm_id"
            . " and arms.project_id = meta.project_id"
            . " and e_forms.form_name = meta.form_name"
            . " and meta.form_menu_description is NOT NULL"
            ;
        $params = [$projectId];
        $result = $this->module->query($sql, $params);

        while ($row = db_fetch_assoc($result)) {
            $mappings[] = $row;
        }

        return $mappings;
    }


    public function getProjectUserRights($projectId, $username = null)
    {
        $userRights = [];

        $sql = "select rights.username, users.user_email as email,"
            . " users.user_firstname as firstname, users.user_lastname as lastname,"
            . " rights.expiration, dags.group_name as data_access_group, rights.group_id as data_access_group_id,"
            . " rights.design, rights.alerts, rights.user_rights, rights.data_access_groups,"
            . " rights.reports, rights.graphical as stats_and_charts, "
            . " rights.participants as manage_survey_participants, rights.calendar,"
            . " rights.data_import_tool, rights.data_comparison_tool,"
            . " rights.data_logging as logging, rights.email_logging,"
            . " rights.file_repository,"
            . " rights.data_quality_design as data_quality_create, rights.data_quality_execute,"
            . " rights.api_export, rights.api_import, rights.mobile_app,"
            . " rights.mobile_app_download_data,"
            . " rights.record_create, rights.record_rename, rights.record_delete,"
            . " rights.lock_record_multiform as lock_records_all_forms,"
            . " rights.lock_record as lock_records,"
            . " rights.lock_record_customize as lock_records_customization,"
            . " rights.data_entry, rights.data_export_instruments"
            . " "
            . " from redcap_projects projects, redcap_user_information users, "
            . "     redcap_user_rights rights"
            . " left join redcap_data_access_groups dags"
            . "     on (rights.group_id = dags.group_id AND rights.project_id = dags.project_id)"
            . " where projects.project_id = ?"
            . " and projects.project_id = rights.project_id"
            . " and users.username = rights.username"
            ;

        $params = [$projectId];

        if (!empty($username)) {
            $sql .= " and users.username = ?";
            $params[] = $username;
        }

        $result = $this->module->query($sql, $params);
        while ($row = db_fetch_assoc($result)) {
            $aUserRights = [];
            $aUserRights['username']    = $row['username'];
            $aUserRights['email']       = $row['email'];
            $aUserRights['firstname']   = $row['firstname'];
            $aUserRights['lastname']    = $row['lastname'];
            $aUserRights['expiration']  = $row['expiration'];

            $aUserRights['data_access_group']    = $row['data_access_group'];
            $aUserRights['data_access_group_id'] = $row['data_access_group_id'];

            $aUserRights['design']             = $row['design'];
            $aUserRights['alerts']             = $row['alerts'];
            $aUserRights['user_rights']        = $row['user_rights'];
            $aUserRights['data_access_groups'] = $row['data_access_groups'];

            $aUserRights['reports']                    = $row['reports'];
            $aUserRights['stats_and_charts']           = $row['stats_and_charts'];
            $aUserRights['manage_survey_participants'] = $row['manage_survey_participants'];

            $aUserRights['calendar']             = $row['calendar'];
            $aUserRights['data_import_tool']     = $row['data_import_tool'];
            $aUserRights['data_comparison_tool'] = $row['data_comparison_tool'];
            $aUserRights['logging']              = $row['logging'];
            $aUserRights['email_logging']        = $row['email_logging'];

            $aUserRights['file_repository']      = $row['file_repository'];
            $aUserRights['data_quality_create']  = $row['data_quality_create'];
            $aUserRights['data_quality_execute'] = $row['data_quality_execute'];

            $aUserRights['api_export']                = $row['api_export'];
            $aUserRights['api_import']                = $row['api_import'];
            $aUserRights['mobile_app']                = $row['mobile_app'];
            $aUserRights['mobile_app_download_data']  = $row['mobile_app_download_data'];

            $aUserRights['record_create']  = $row['record_create'];
            $aUserRights['record_rename']  = $row['record_rename'];
            $aUserRights['record_delete']  = $row['record_delete'];

            $aUserRights['lock_records_all_forms']     = $row['rights.lock_records_all_forms'];
            $aUserRights['lock_records']               = $row['lock_records'];
            $aUserRights['lock_records_customization'] = $row['lock_records_customization'];


            $dataEntry = $row['data_entry'];
            $matches = [];
            preg_match_all('/\[([^,]*),([^\]]*)\]/', $dataEntry, $matches);

            $forms = [];
            if (count($matches) === 3) {
                $formNames  = $matches[1];
                $formValues = $matches[2];
                for ($i = 0; $i < count($formNames); $i++) {
                    $forms[$formNames[$i]] = $formValues[$i];
                }
            }
            $aUserRights['forms'] = $forms;

            $dataExportInstruments = $row['data_export_instruments'];
            $matches = [];
            preg_match_all('/\[([^,]*),([^\]]*)\]/', $dataExportInstruments, $matches);

            $formsExport = [];
            if (count($matches) === 3) {
                $formNames  = $matches[1];
                $formValues = $matches[2];
                for ($i = 0; $i < count($formNames); $i++) {
                    $formsExport[$formNames[$i]] = $formValues[$i];
                }
            }
            $aUserRights['forms_export'] = $formsExport;

            $userRights[] = $aUserRights;
        }

        return $userRights;
    }

    /**
     * Deletes the file in the specified REDCap project field. This method is needed, because
     * the REDCap developer methods do not include a method for deleteing a file.
     *
     * See the REDCap API/file/delete.php code.
     *
     * @param integer $projectId the ID of the project from which the file is to be deleted.
     * @param string $recordId the ID of the record from which the file is to be deleted.
     * @param string $event the unique event name.
     * @param boolean $ifExists if set to true, then the file will be deleted if it exists. if set
     *     to false, an error will be generated if the file does not exist.
     */
    public function deleteFile($projectId, $recordId, $field, $event = null, $instance = null, $ifExists = true)
    {
        #----------------------------------------------------------
        # Get the data table for this project
        #----------------------------------------------------------
        $dataTable = $this->module->getDataTable($projectId);

        #----------------------------------------------------------
        # Get the event ID
        #----------------------------------------------------------
        $project = new \Project($projectId);
        if (empty($event)) {
            $eventId = $project->firstEventId;
        } else {
            $eventId = $project->getEventIdUsingUniqueEventName($event);
        }

        #----------------------------------------------------------
        # Get the doc ID for the file (which is contained in the
        # "value" field for the file field in the data table)
        #----------------------------------------------------------
        $sql = "select value from {$dataTable}"
            . " where project_id = ? and record = ? and event_id = ? and field_name = ?";
            ;
        $params = [$projectId, $recordId, $eventId, $field];

        if (!empty($instance) && $instance > 0) {
            $sql .= ' and instance = ?';
            $params[] = $instance;
        } else {
            $sql .= ' and instance is null';
        }

        $result = $this->module->query($sql, $params);

        $noDocIdMessage = "No docuent ID found for field \"{$field}\" "
            . " in record \"{$recordId}\" in project with ID {$projectId}.";

        $docId = null;

        if (!empty($result)) {
            $row = db_fetch_assoc($result);
            if (!empty($row) && array_key_exists('value', $row)) {
                $docId = (int) $row['value'];
                if (empty($docId) || $docId < 1) {
                    $docId = null;
                }
            }
        }

        if ($docId === null && $ifExists === false) {
            throw new \Exception($noDocIdMessage);
        }


        #-------------------------------------------------------------------
        # Set the file as deleted in the redcap_edocs_metadata table
        #-------------------------------------------------------------------
        if ($docId !== null) {
            if (defined('NOW')) {
                $now = NOW;
            } else {
                $now = 'NOW()';
            }

            $sql = "update redcap_edocs_metadata SET delete_date = ? WHERE doc_id = ?";
            $params = [$now, $docId];
            $result = $this->module->query($sql, $params);
        }

        #-------------------------------------------------------------
        # Delete the file value from the data table
        #-------------------------------------------------------------
        $sql = "delete from " . $dataTable
            . " where project_id = ? and record = ? and event_id = ? and field_name = ?";
        $params = [$projectId, $recordId, $eventId, $field];

        if (!empty($instance) && $instance > 0) {
            $sql .= ' and instance = ?';
            $params[] = $instance;
        } else {
            $sql .= ' and instance is null';
        }

        $result = $this->module->query($sql, $params);

        #----------------------------------------
        # Log the delete
        #----------------------------------------
        if (class_exists("\REDCap")) {
            $action = "Deleted Document";
            $change = $field;
            \REDCap::logEvent($action, $change, $sql, $recordId, $event, $projectId);
        }

        return $result;
    }



    /**
     * Gets all the Data Transfer configuration settings (for all projects).
     *
     * @return array array of maps that contain keys 'project_id' and 'value'. The
     *     'value' key value contains the configuration data in PHP serialization format.
     */
    public function getDataTransferConfigurationsSettings($module)
    {
        $dirName = $module->getModuleDirectoryName();

        $dataTransferConfigs = array();
        $sql = "select rems.project_id, rems.value "
            . " from redcap_external_modules rem, redcap_external_module_settings rems "
            . " where rem.external_module_id = rems.external_module_id "
            . " and ? like concat(rem.directory_prefix, '%') "
            . " and rems.`key` = 'configurations'" // @codeCoverageIgnore
            ;
        $params = [$dirName];

        $queryResult = db_query($sql, $params);
        while ($row = db_fetch_assoc($queryResult)) {
            $dataTransferConfigs[] = $row;
        }
        return $dataTransferConfigs;
    }


    /**
     * Starts a database transaction.
     */
    public function startTransaction()
    {
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");
    }

    /**
     * Ends a database transaction.
     *
     * @param boolean $commit indicates if the transaction should be committed.
     */
    public function endTransaction($commit)
    {
        try {
            if ($commit) {
                db_query("COMMIT");
            } else {
                db_query("ROLLBACK");
            }
        } catch (\Exception $exception) {
            ;
        }
        db_query("SET AUTOCOMMIT=1");
    }
}
