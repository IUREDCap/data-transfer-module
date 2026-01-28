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

    # Erase the destination project data
    When I erase all data from project "Data Transfer - Basic Destination Project"
    And I go to record status dashboard for project "Data Transfer - Basic Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete the test data transfer configuration that does not overwrite with blanks if it exists
    When I go to Data Transfer for project "Data Transfer - Basic Source Project"
    And I delete configuration "behat-export-basic-test" if it exists
    Then I should not see "behat-export-basic-test"
    And I should not see "REDCap crashed"

    # Create the test data transfer configuration that does not overwrite with blanks
    When I add configuration "behat-export-basic-test"
    And I follow configuration "behat-export-basic-test"
    And I enable export to local project "Data Transfer - Basic Destination Project"
    And I check transfer files
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-basic-test"
    But I should not see "REDCap crashed"

    # Transfer the basic data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the basic data
    When I go to report all for project "Data Transfer - Basic Destination Project"
    Then I should see "1001"
    And I should see "1002"
    And I should see "notes_file_1001.txt"
    And the project data should match test data file "basic-dag.csv"
    But I should not see "REDCap crashed"
    When I go to project home for project "Data Transfer - Basic Destination Project"
    Then I should see table row ("Records in project, 50")

    # Delete the test data transfer configuration that overwrites with blanks if it exists
    When I go to Data Transfer for project "Data Transfer - Basic Source Project with Blanks"
    And I delete configuration "behat-export-basic-with-blanks-test" if it exists
    Then I should not see "behat-export-basic-with-blanks-test"
    And I should not see "REDCap crashed"

    # Create the test data transfer configuration that does overwrites with blanks
    When I add configuration "behat-export-basic-with-blanks-test"
    And I follow configuration "behat-export-basic-with-blanks-test"
    And I enable export to local project "Data Transfer - Basic Destination Project"
    And I check transfer files
    And I set update existing records
    And I set overwrite existing values with blank values
    And I wait for 10 seconds
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-basic-with-blanks-test"
    But I should not see "REDCap crashed"

    # Transfer the basic data with overwriting of blanks
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the basic data with blanks overwritten
    When I go to report all for project "Data Transfer - Basic Destination Project"
    Then I should see "1001"
    And I should see "1002"
    And I should not see "notes_file_1001.txt"
    And I should not see "REDCap crashed"
    And the project data should match test data file "basic-dag-with-blanks.csv"
    When I go to project home for project "Data Transfer - Basic Destination Project"
    Then I should see table row ("Records in project, 50")

