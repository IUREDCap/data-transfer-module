#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data Transfer
  In order to transfer data from one project to another
  As a user
  I need to be able to transfer repeating data to non-repeating data

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer repeating data to non-repeating data using default of last instance of repeating data
    # Erase all data from the destination project
    When I erase all data from project "Data Transfer - Repeating Events Destination Project"
    And I go to record status dashboard for project "Data Transfer - Repeating Events Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete the test data transfer configuration if it exists
    When I go to Data Transfer for project "Data Transfer - Repeating Events Source Project"
    And I delete configuration "behat-export-rep-to-non-rep-test" if it exists
    Then I should not see "behat-export-rep-to-non-rep-test"
    And I should not see "REDCap crashed"

    # Create the test data transfer configuration
    # Use the default of copying the last instance of repeating data to non-repeating data 
    When I add configuration "behat-export-rep-to-non-rep-test"
    And I follow configuration "behat-export-rep-to-non-rep-test"
    And I enable export to local project "Data Transfer - Repeating Events Destination Project"
    And I add field mapping "visit_arm_1" "ALL" "ALL" to "baseline_arm_1" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-rep-to-non-rep-test"
    But I should not see "REDCap crashed"

    # Transfer the data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the transfered data
    When I go to report all for project "Data Transfer - Repeating Events Destination Project"
    Then I should see "1001"
    And I should see "1005"
    And the project data should match test data file "repeating-events-rep-to-non-rep-last.csv"
    But I should not see "1006"
    And I should not see "REDCap crashed"

    When I go to project home for project "Data Transfer - Repeating Events Destination Project"
    Then I should see table row ("Records in project, 14")


  Scenario: Transfer repeating data to non-repeating data using first instance of repeating data
    # Erase all data from the destination project
    When I erase all data from project "Data Transfer - Repeating Events Destination Project"
    And I go to record status dashboard for project "Data Transfer - Repeating Events Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete the test data transfer configuration if it exists
    When I go to Data Transfer for project "Data Transfer - Repeating Events Source Project"
    And I delete configuration "behat-export-rep-to-non-rep-test" if it exists
    Then I should not see "behat-export-rep-to-non-rep-test"
    And I should not see "REDCap crashed"

    # Create the test data transfer configuration
    # Set to copy the first instance of repeating data to non-repeating data 
    When I add configuration "behat-export-rep-to-non-rep-test"
    And I follow configuration "behat-export-rep-to-non-rep-test"
    And I enable export to local project "Data Transfer - Repeating Events Destination Project"
    And I set repeating to non-repeating to first instance
    And I wait for 20 seconds
    And I add field mapping "visit_arm_1" "ALL" "ALL" to "baseline_arm_1" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-rep-to-non-rep-test"
    But I should not see "REDCap crashed"

    # Transfer the data
    When I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the transfered data
    When I go to report all for project "Data Transfer - Repeating Events Destination Project"
    Then I should see "1001"
    And I should see "1005"
    And the project data should match test data file "repeating-events-rep-to-non-rep-first.csv"
    But I should not see "1006"
    And I should not see "REDCap crashed"

    When I go to project home for project "Data Transfer - Repeating Events Destination Project"
    Then I should see table row ("Records in project, 14")

