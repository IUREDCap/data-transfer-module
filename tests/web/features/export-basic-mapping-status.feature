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

    When I go to Data Transfer for project "Data Transfer - Basic Source Project"
    And I delete configuration "behat-export-basic-mapping-status-test" if it exists
    Then I should not see "behat-export-basic-mapping-status-test"
    And I should not see "REDCap crashed"

    # Create the data transfer configuration
    When I add configuration "behat-export-basic-mapping-status-test"
    And I follow configuration "behat-export-basic-mapping-status-test"
    And I enable export to local project "Data Transfer - Repeating Forms Destination Project"
    And I enable export to local project "Data Transfer - Basic Destination Project"
    And I add field mapping "" "ALL" "ALL" to "" "MATCHING" "EQUIVALENT"

    Then I should see "behat-export-basic-mapping-status-test"
    But I should not see "REDCap crashed"

    # TODO : change to single field map status
    When I follow "Field Map"
    And I click table element "2" "9" "i" to new window
    Then I should eventually see "Field Mapping Detail for Configuration"
    And I should see "Specified Mapping:"
    And I should see "Expanded Mapping:"
    And I should see table:
        | Event | Form | Field | | Event | Form     | Field      | 
        |       | ALL  | ALL   | |       | MATCHING | EQUIVALENT |
    And I should see table:
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



