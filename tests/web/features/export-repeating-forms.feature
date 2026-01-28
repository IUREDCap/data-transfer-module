#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data transfer for non-longitudinal project with repeating forms
  In order to transfer data between non-longitudinal projects with repeating forms
  As a user
  I need to be able to specify and run data transfers for non-longitudinal projects with repeating forms

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer data

    # Erase any existing data in the destination project
    When I erase all data from project "Data Transfer - Repeating Forms Destination Project"
    And I go to record status dashboard for project "Data Transfer - Repeating Forms Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete test configuration, if it exists
    When I go to Data Transfer for project "Data Transfer - Repeating Forms Source Project"
    And I delete configuration "behat-export-repeating-forms-test" if it exists
    Then I should not see "behat-export-repeating-forms-test"
    And I should not see "REDCap crashed"

    # Add test configuration
    When I add configuration "behat-export-repeating-forms-test"
    And I follow configuration "behat-export-repeating-forms-test"
    And I enable export to local project "Data Transfer - Repeating Forms Destination Project"
    And I add field mapping "ALL" "ALL" "ALL" to "MATCHING" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-repeating-forms-test"
    But I should not see "REDCap crashed"

    # Transfer the data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the data transfer results
    When I go to report all for project "Data Transfer - Repeating Forms Destination Project"
    Then I should see "1001"
    And I should see "1002"
    And the project data should match test data file "repeating-forms.csv"
    But I should not see "REDCap crashed"

    When I go to project home for project "Data Transfer - Repeating Forms Destination Project"
    Then I should see table row ("Records in project, 50")



