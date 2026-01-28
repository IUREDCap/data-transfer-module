#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Basic Data Import
  In order be able to import data from one projct into another project that might be remote
  As a user
  I need to be able to specify and run data imports using REDCap's API

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer data
    When I erase all data from project "Data Transfer - Basic Destination Project"
    And I go to record status dashboard for project "Data Transfer - Basic Destination Project"
    # And Print element "body" text
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Basic Destination Project"
    And I delete configuration "behat-import-basic-api-test" if it exists
    Then I should not see "behat-import-basic-api-test"
    And I should not see "REDCap crashed"

    When I add configuration "behat-import-basic-api-test"
    And I follow configuration "behat-import-basic-api-test"
    And I enable import from API project with URL property "redcap" "api_url" and token property "project_basic_source" "api_token"
    # And I enable configuration
    # And I set import from for configuration
    # And I set API project with URL property "redcap" "api_url" and token property "project_basic_source" "api_token"
    And I check transfer files
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "EQUIVALENT"
    Then I should see "behat-import-basic-api-test"
    But I should not see "REDCap crashed"

    # Add code to transfer data ...
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    When I go to report all for project "Data Transfer - Basic Destination Project"
    Then I should see "1001"
    And I should see "1002"
    And I should see "notes_file_1001.txt"
    And the project data should match test data file "basic-dag.csv"
    But I should not see "REDCap crashed"
    When I go to project home for project "Data Transfer - Basic Destination Project"
    Then I should see table row ("Records in project, 50")


