#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Repeating Events Data Import
  In order be able to import data from one project into another project
  As a user
  I need to be able to specify and run data imports between longitudinal projects

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer data between non-longitudinal projects

    # Delete data, if any, from the destination project
    When I erase all data from project "Data Transfer - Repeating Events Destination Project"
    And I go to record status dashboard for project "Data Transfer - Repeating Events Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete test configuration, if it exists
    When I go to Data Transfer for project "Data Transfer - Repeating Events Destination Project"
    And I delete configuration "behat-import-repeating-api-test" if it exists
    Then I should not see "behat-import-repeating-api-test"
    And I should not see "REDCap crashed"

    # Create data transfer configuration
    When I add configuration "behat-import-repeating-api-test"
    And I follow configuration "behat-import-repeating-api-test"
    And I enable import from API project with URL property "redcap" "api_url" and token property "project_repeating_events_source" "api_token"
    And I check transfer files
    And I add field mapping "ALL" "ALL" "ALL" to "MATCHING" "MATCHING" "EQUIVALENT"
    Then I should see "behat-import-repeating-api-test"
    But I should not see "REDCap crashed"

    # Transfer the data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the transferred data
    When I go to report all for project "Data Transfer - Repeating Events Destination Project"
    Then I should see "1001"
    And I should see "1002"
    And the project data should match test data file "repeating-events.csv"
    But I should not see "REDCap crashed"
    When I go to project home for project "Data Transfer - Repeating Events Destination Project"
    Then I should see table row ("Records in project, 50")


