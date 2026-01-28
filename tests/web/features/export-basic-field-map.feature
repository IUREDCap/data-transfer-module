#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data Transfer
  In order to know what data will be transfered
  As a user
  I need to be able to view the field mapping detail page

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Field map with no errors or incomplete mappings
    When I erase all data from project "Data Transfer - Basic Destination Project"
    And I go to record status dashboard for project "Data Transfer - Basic Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Basic Source Project"
    And I delete configuration "behat-export-basic-test" if it exists
    Then I should not see "behat-export-basic-test"
    And I should not see "REDCap crashed"

    # Create the data transfer configuration
    When I add configuration "behat-export-basic-test"
    And I follow configuration "behat-export-basic-test"
    And I enable export to local project "Data Transfer - Basic Destination Project"
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "EQUIVALENT"
    Then I should see "behat-export-basic-test"
    But I should not see "REDCap crashed"

    When I press button "Field Mapping Detail" to new window
    Then I should see "Field Mapping Detail for Configuration"
    Then I should see table "fieldMappingDetailTable" with only rows:
        | Event | Form         | Field                 | | Event | Form         | Field                 |
        |       | demographics | first_name            | |       | demographics | first_name            |
        |       | demographics | last_name             | |       | demographics | last_name             |
        |       | demographics | address               | |       | demographics | address               |
        |       | demographics | telephone             | |       | demographics | telephone             |
        |       | demographics | email                 | |       | demographics | email                 |
        |       | demographics | dob                   | |       | demographics | dob                   |
        |       | demographics | ethnicity             | |       | demographics | ethnicity             |
        |       | demographics | race                  | |       | demographics | race                  |
        |       | demographics | sex                   | |       | demographics | sex                   |
        |       | demographics | height                | |       | demographics | height                |
        |       | demographics | weight                | |       | demographics | weight                |
        |       | demographics | bmi                   | |       | demographics | bmi                   |
        |       | demographics | notes_file            | |       | demographics | notes_file            |
        |       | demographics | comments              | |       | demographics | comments              |
        |       | demographics | demographics_complete | |       | demographics | demographics_complete |


  Scenario: Field map with errors and incomplete mappings
    When I erase all data from project "Data Transfer - Basic Destination Project"
    And I go to record status dashboard for project "Data Transfer - Basic Destination Project"
    Then I should see "No records exist yet"
    But I should not see "REDCap crashed"

    When I go to Data Transfer for project "Data Transfer - Basic Source Project"
    And I delete configuration "behat-export-basic-test" if it exists
    Then I should not see "behat-export-basic-test"
    And I should not see "REDCap crashed"

    # Create the data transfer configuration
    When I add configuration "behat-export-basic-test"
    And I follow configuration "behat-export-basic-test"
    And I enable export to local project "Data Transfer - Basic Destination Project"
    And I add field mapping exclude "" "demographics" "first_name"
    And I add field mapping "" "demographics" "first_name" to "" "demographics" "first_name"
    And I add field mapping "" "demographics" "last_name" to "" "MATCHING" "EQUIVALENT"
    And I add field mapping "" "demographics" "email" to "" "MATCHING" "EQUIVALENT"
    And I add field mapping exclude "" "demographics" "email"
    # ERROR:
    And I add field mapping "" "demographics" "address" to "" "MATCHING" "telephone"
    # INCOMPLETE:
    And I add field mapping "" "demographics" "" to "" "MATCHING" ""
    Then I should see "behat-export-basic-test"
    But I should not see "REDCap crashed"

    When I press button "Field Mapping Detail" to new window
    Then I should see "Field Mapping Detail for Configuration"
    And I should see "Incomplete Field Mappings for Configuration"
    And I should see "Error Field Mappings for Configuration"
    And I should see table:
        | Event | Form         | Field      | | Event | Form         | Field      |
        |       | demographics | first_name | |       | demographics | first_name |
        |       | demographics | last_name  | |       | demographics | last_name  |
    And I should see table:
        | Event | Form         | Field    | | Event | Form         | Field | Exclude |
        |       | demographics |          | |       | MATCHING     |       |         |
    And I should see table:
        | Event | Form         | Field   | | Event    | Form      | Field     | Exclude | Errors                                                                       |
        |       | demographics | address | |          | MATCHING  | telephone |         | Destination field "telephone" is not compatible with source field "address". |



