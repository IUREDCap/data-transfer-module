#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data Transfer
  In order to be able to see the differences in projects
  As a user
  I need to be able to access and view the project comparison page

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Project comparison for projects that don't match

    When I go to Data Transfer for project "Data Transfer - Basic Source Project"
    And I delete configuration "behat-export-basic-diff-test" if it exists
    Then I should not see "behat-export-basic-diff-test"
    And I should not see "REDCap crashed"

    # Create the data transfer configuration
    When I add configuration "behat-export-basic-diff-test"
    And I follow configuration "behat-export-basic-diff-test"
    And I enable export to local project "Data Transfer - Repeating Forms Destination Project"
    Then I should see "behat-export-basic-diff-test"
    But I should not see "REDCap crashed"

    # Check the diff page
    When I follow "Project Comparison"
    Then I should see "Source Project"
    And I should see "Destination Project"
    And I should see table:
        |                                 | Source Project                       | Desitnation Project                                 |
        | Project Name	                  | Data Transfer - Basic Source Project | Data Transfer - Repeating Forms Destination Project |
        | Project ID                      | *                                    | *                                                   |
        | Longitudinal                    | No	                                 | No                                                  |
        | Surveys Enabled                 | No                                   | No                                                  |
        | Repeating Instruments or Events | No	                                 | Yes                                                 |
        | Record Autonumbering Enabled	  | No                                   | No                                                  |
        | Missing Data Codes          	  |                                      |                                                     |
    And I should see table row ("dob, text, date_ymd,,,, No, dob, text, date_ymd,,,, No")
    And I should see table row ("height, text, number, 130, 215,, No,,,,,,,")
    And I should see table row (",,,,,,,height_m, text, number_2dp, 0.00, 3.00,,No")


