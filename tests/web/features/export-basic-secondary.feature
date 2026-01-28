#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data Transfer
  In order to transfer data from one project to another that have different record IDs
  As a user
  I need to be able to specify and run data transfers using a secondary unique field

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer data
    When I erase all data from project "Data Transfer - Basic Destination Project"
    And I go to record status dashboard for project "Data Transfer - Basic Destination Project"
    # And Print element "body" text
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Basic Source Project - Secondary ID"
    And I delete configuration "behat-export-basic-secondary-test" if it exists
    Then I should not see "behat-export-basic-secondary-test"
    And I should not see "REDCap crashed"

    # Create the data transfer configuration
    When I add configuration "behat-export-basic-secondary-test"
    And I follow configuration "behat-export-basic-secondary-test"
    And I enable export to local project "Data Transfer - Basic Destination Project"
    And I check transfer files
    And I set record match to secondary unique field
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-basic-secondary-test"
    But I should not see "REDCap crashed"

    # Transfer the data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    When I go to report all for project "Data Transfer - Basic Destination Project"
    Then I should see "1001"
    And I should see "1002"
    And I should see "notes_file_1001.txt"
    And the project data should match test data file "basic.csv"
    But I should not see "REDCap crashed"
    When I go to project home for project "Data Transfer - Basic Destination Project"
    Then I should see table row ("Records in project, 50")



