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
    When I erase all data from project "Data Transfer - Basic Destination Project"
    And I go to record status dashboard for project "Data Transfer - Basic Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Basic Source Project"
    And I delete configuration "behat-export-basic-no-new-records-test" if it exists
    Then I should not see "behat-export-basic-no-new-records-test"
    And I should not see "REDCap crashed"

    # Create the data transfer configuration
    When I add configuration "behat-export-basic-no-new-records-test"
    And I follow configuration "behat-export-basic-no-new-records-test"
    And I enable export to local project "Data Transfer - Basic Destination Project"
    # And I enable configuration
    # And I set export to for configuration
    # And I set local project to "Data Transfer - Basic Destination Project"
    And I set record creation to none
    And I check transfer files
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-basic-no-new-records-test"
    But I should not see "REDCap crashed"

    # Transfer the data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    When I go to report all for project "Data Transfer - Basic Destination Project"
    Then I should not see "1001"
    And I should not see "1002"
    And I should not see "notes_file_1001.txt"
    And I should not see "REDCap crashed"
    When I go to project home for project "Data Transfer - Basic Destination Project"
    Then I should see table row ("Records in project, 0")


