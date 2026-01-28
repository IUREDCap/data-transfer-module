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

  Scenario: Transfer data

    # Erase the data (if any) from the destination test project
    When I erase all data from project "Data Transfer - Repeating Events Destination Project"
    And I go to record status dashboard for project "Data Transfer - Repeating Events Destination Project"
    # And Print element "body" text
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete the data transfer test configuration, if it exists
    When I go to Data Transfer for project "Data Transfer - Repeating Events Source Project"
    And I delete configuration "behat-export-repeating-test" if it exists
    Then I should not see "behat-export-repeating-test"
    And I should not see "REDCap crashed"

    # Create the data transfer test configuration
    When I add configuration "behat-export-repeating-test"
    And I follow configuration "behat-export-repeating-test"
    And I enable export to local project "Data Transfer - Repeating Events Destination Project"
    And I add field mapping "ALL" "ALL" "ALL" to "MATCHING" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-repeating-test"
    But I should not see "REDCap crashed"

    # Transfer the data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the data transfer results
    When I go to report all for project "Data Transfer - Repeating Events Destination Project"
    Then I should see "1001"
    And I should see "1002"
    And the project data should match test data file "repeating-events.csv"
    But I should not see "REDCap crashed"

    When I go to project home for project "Data Transfer - Repeating Events Destination Project"
    Then I should see table row ("Records in project, 50")



