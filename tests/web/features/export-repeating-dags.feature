#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data Transfer
  In order to transfer data from one project to another
  As a user
  I need to be able to specify and run data transfers

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer data with default DAG mapping - DAGS transferred if it exists in destination
    When I erase all data from project "Data Transfer - Repeating Events Destination Project - DAGs"
    And I go to record status dashboard for project "Data Transfer - Repeating Events Destination Project - DAGs"
    # And Print element "body" text
    Then I should eventually see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Repeating Events Source Project - DAGs"
    And I delete configuration "behat-export-repeating-dags-test" if it exists
    Then I should not see "behat-export-repeating-dags-test"
    And I should not see "REDCap crashed"

    When I add configuration "behat-export-repeating-dags-test"
    And I follow configuration "behat-export-repeating-dags-test"
    And I enable export to local project "Data Transfer - Repeating Events Destination Project - DAGs"
    And I add field mapping "ALL" "ALL" "ALL" to "MATCHING" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-repeating-dags-test"
    But I should not see "REDCap crashed"

    # Transfer data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check transferred data
    When I go to report all for project "Data Transfer - Repeating Events Destination Project - DAGs"
    Then I should see "1001"
    And I should see "1002"
    And the project data should match test data file "repeating-events-dags.csv"
    But I should not see "REDCap crashed"

    When I go to project home for project "Data Transfer - Repeating Events Destination Project - DAGs"
    Then I should see table row ("Records in project, 50")


  Scenario: Transfer data with no DAG mapping - no DAGS are transferred
    When I erase all data from project "Data Transfer - Repeating Events Destination Project - DAGs"
    And I go to record status dashboard for project "Data Transfer - Repeating Events Destination Project - DAGs"
    # And Print element "body" text
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Repeating Events Source Project - DAGs"
    And I delete configuration "behat-export-repeating-dags-test" if it exists
    Then I should not see "behat-export-repeating-dags-test"
    And I should not see "REDCap crashed"

    When I add configuration "behat-export-repeating-dags-test"
    And I follow configuration "behat-export-repeating-dags-test"
    And I enable export to local project "Data Transfer - Repeating Events Destination Project - DAGs"
    And I add field mapping "ALL" "ALL" "ALL" to "MATCHING" "MATCHING" "EQUIVALENT"
    And I set no DAG transfer
    Then I should see "behat-export-repeating-dags-test"
    But I should not see "REDCap crashed"

    # Transfer data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check transferred data
    When I go to report all for project "Data Transfer - Repeating Events Destination Project - DAGs"
    Then I should see "1001"
    And I should see "1002"
    And the project data should match test data file "repeating-events-no-dags.csv"
    But I should not see "REDCap crashed"

    When I go to project home for project "Data Transfer - Repeating Events Destination Project - DAGs"
    Then I should see table row ("Records in project, 50")


  Scenario: Transfer data with user-specified DAG mapping
    When I erase all data from project "Data Transfer - Repeating Events Destination Project - DAGs"
    And I go to record status dashboard for project "Data Transfer - Repeating Events Destination Project - DAGs"
    # And Print element "body" text
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Repeating Events Source Project - DAGs"
    And I delete configuration "behat-export-repeating-dags-test" if it exists
    Then I should not see "behat-export-repeating-dags-test"
    And I should not see "REDCap crashed"

    When I add configuration "behat-export-repeating-dags-test"
    And I follow configuration "behat-export-repeating-dags-test"
    And I enable export to local project "Data Transfer - Repeating Events Destination Project - DAGs"
    And I add field mapping "ALL" "ALL" "ALL" to "MATCHING" "MATCHING" "EQUIVALENT"
    And I set DAG mapping option
    And I set DAG mapping from "nh" to "ut"
    And I set DAG mapping from "ut" to "nh"
    Then I should see "behat-export-repeating-dags-test"
    But I should not see "REDCap crashed"

    # Transfer data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check transferred data
    When I go to report all for project "Data Transfer - Repeating Events Destination Project - DAGs"
    Then I should see "1001"
    And I should see "1002"
    And the project data should match test data file "repeating-events-dag-map.csv"
    But I should not see "REDCap crashed"

    When I go to project home for project "Data Transfer - Repeating Events Destination Project - DAGs"
    Then I should see table row ("Records in project, 50")


  Scenario: Transfer data with DAG mapping with mappings excluded - no DAGS are transferred
    When I erase all data from project "Data Transfer - Repeating Events Destination Project - DAGs"
    And I go to record status dashboard for project "Data Transfer - Repeating Events Destination Project - DAGs"
    # And Print element "body" text
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Repeating Events Source Project - DAGs"
    And I delete configuration "behat-export-repeating-dags-test" if it exists
    Then I should not see "behat-export-repeating-dags-test"
    And I should not see "REDCap crashed"

    When I add configuration "behat-export-repeating-dags-test"
    And I follow configuration "behat-export-repeating-dags-test"
    And I enable export to local project "Data Transfer - Repeating Events Destination Project - DAGs"
    And I add field mapping "ALL" "ALL" "ALL" to "MATCHING" "MATCHING" "EQUIVALENT"
    And I set DAG mapping option
    And I exclude DAG mapping from "nh"
    And I exclude DAG mapping from "ut"
    Then I should see "behat-export-repeating-dags-test"
    But I should not see "REDCap crashed"

    # Transfer data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check transferred data
    When I go to report all for project "Data Transfer - Repeating Events Destination Project - DAGs"
    Then I should see "1001"
    And I should see "1002"
    And the project data should match test data file "repeating-events-no-dags.csv"
    But I should not see "REDCap crashed"

    When I go to project home for project "Data Transfer - Repeating Events Destination Project - DAGs"
    Then I should see table row ("Records in project, 50")






