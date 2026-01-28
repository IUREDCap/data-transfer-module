#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data Transfer
  In order to transfer data from one project to another
  As a user
  I need to be able to schedule data transfers

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer data

    # Erase the destination project data
    When I erase all data from project "Data Transfer - Basic Destination Project - Schedule"
    And I go to record status dashboard for project "Data Transfer - Basic Destination Project - Schedule"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete the test data transfer configuration that does not overwrite with blanks if it exists
    When I go to Data Transfer for project "Data Transfer - Basic Source Project"
    And I delete configuration "behat-export-basic-schedule-test" if it exists
    Then I should not see "behat-export-basic-schedule-test"
    And I should not see "REDCap crashed"

    # Create the test data transfer configuration that does not overwrite with blanks
    When I add configuration "behat-export-basic-schedule-test"
    And I follow configuration "behat-export-basic-schedule-test"
    And I enable export to local project "Data Transfer - Basic Destination Project - Schedule"
    And I check transfer files
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-basic-schedule-test"
    But I should not see "REDCap crashed"

    # Schedule the transfer of data
    When I follow "Schedule"
    And I schedule for next hour
    And I check "emailSchedulingCompletions"
    And I press "Save"
    Then I should see "Send e-mail when a scheduled data transfer succeeds"
    But I should not see "REDCap crashed"
    # Need to check the project results manually


