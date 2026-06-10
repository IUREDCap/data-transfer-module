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

  Scenario: Explicit compatible field mapping with different field names

    # Erase the destination project data
    When I erase all data from project "Data Transfer - Different Form & Field Names Destination Project"
    And I go to record status dashboard for project "Data Transfer - Different Form & Field Names Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    # Delete the test data transfer configuration
    When I go to Data Transfer for project "Data Transfer - Different Form & Field Names Source Project"
    And I delete configuration "behat-export-different-names-test" if it exists
    Then I should not see "behat-export-different-names-test"
    And I should not see "REDCap crashed"

    # Create the test data transfer configuration
    When I add configuration "behat-export-different-names-test"
    And I follow configuration "behat-export-different-names-test"
    And I enable export to local project "Data Transfer - Different Form & Field Names Destination Project"
    And I check transfer files
    And I add field mapping "" "demographics" "name" to "" "information" "name" 
    And I add field mapping "" "demographics" "phone" to "" "information" "telephone" 
    Then I should see "behat-export-different-names-test"
    But I should not see "REDCap crashed"

    # Verify field mapping
    When I press button "Field Mapping Detail" to new window
    Then I should see "Field Mapping Detail for Configuration"
    Then I should see "Field Mapping Detail for Configuration"
    And I should not see "Incomplete Field Mappings for Configuration"
    And I should not see "Error Field Mappings for Configuration"
    Then I should see table:
      | Event | Form         | Field |  | Event | Form           | Field     |
      |       | demographics | name  |  |       | information_d  | name      |
      |       | demographics | phone |  |       | information_d  | telephone |

    # Transfer the data
    When I go to Data Transfer for project "Data Transfer - Different Form & Field Names Source Project"
    And I follow "Manual Transfer"
    And I wait for and press "Transfer"
    Then I should eventually see "Data transferred"
    But I should not see "REDCap crashed"

    # Check the data
    When I go to report all for project "Data Transfer - Different Form & Field Names Destination Project"
    Then I should see "(Name Terry Smith)"
    And I should see "(Name Pat Jones)"
    And I should not see "REDCap crashed"
    And the project data should match test data file "different-form-field-names.csv"
