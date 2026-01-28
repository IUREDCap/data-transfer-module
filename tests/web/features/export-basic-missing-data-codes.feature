#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data Transfer
  In order to transfer data from one project to another
  As a user
  I need to be able to specify and run data transfers that use missing data codes`

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer data

    # Erase the destination project data
    When I erase all data from project "Data Transfer - Basic Destination Project - Missing Data Codes"
    And I go to record status dashboard for project "Data Transfer - Basic Destination Project - Missing Data Codes"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete the test data transfer configuration that does not overwrite with blanks if it exists
    When I go to Data Transfer for project "Data Transfer - Basic Source Project - Missing Data Codes"
    And I delete configuration "behat-export-basic-missing-data-codes-test" if it exists
    Then I should not see "behat-export-basic-missing-data-codes-test"
    And I should not see "REDCap crashed"

    # Create the test data transfer configuration that does not overwrite with blanks
    When I add configuration "behat-export-basic-missing-data-codes-test"
    And I follow configuration "behat-export-basic-missing-data-codes-test"
    And I enable export to local project "Data Transfer - Basic Destination Project - Missing Data Codes"
    And I check transfer files
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "COMPATIBLE"
    Then I should see "behat-export-basic-missing-data-codes-test"
    But I should not see "REDCap crashed"

    # Transfer the basic data with missing data codes
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the basic data with missing data codes
    When I go to report all for project "Data Transfer - Basic Destination Project - Missing Data Codes"
    Then I should see "1001"
    And I should see "1002"
    And I should see "notes_file_1001.txt"
    And the project data should match test data file "basic-missing-data-codes.csv"
    But I should not see "REDCap crashed"
    When I go to project home for project "Data Transfer - Basic Destination Project - Missing Data Codes"
    Then I should see table row ("Records in project, 25")

