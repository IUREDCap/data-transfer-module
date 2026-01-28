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

    # Delete the test data transfer configuration, if it exists
    When I go to Data Transfer for project "Data Transfer - Repeating Events Source Project"
    And I delete configuration "behat-export-repeating-events-diff-test" if it exists
    Then I should not see "behat-export-repeating-events-diff-test"
    And I should not see "REDCap crashed"

    # Create the data transfer configuration
    When I add configuration "behat-export-repeating-events-diff-test"
    And I follow configuration "behat-export-repeating-events-diff-test"
    And I enable export to local project "Data Transfer - Repeating Forms Destination Project"
    Then I should see "behat-export-repeating-events-diff-test"
    But I should not see "REDCap crashed"

    # Check the diff page
    When I follow "Project Comparison"
    Then I should see "Source Project"
    And I should see "Destination Project"
    And I should see "Arms"
    And I should see "Events"
    And I should see "Fields"

    And I should see table:
        |                                 | Source Project                                  | Desitnation Project                                 |
        | Project Name                    | Data Transfer - Repeating Events Source Project | Data Transfer - Repeating Forms Destination Project |
        | Project ID                      | *                                               | *                                                   |
        | Longitudinal                    | Yes                                             | No                                                  |
        | Surveys Enabled                 | No                                              | No                                                  |
        | Repeating Instruments or Events | Yes                                             | Yes                                                 |
        | Record Autonumbering Enabled    | Yes                                             | No                                                  |
        | Missing Data Codes              |                                                 |                                                     |

    And I should see table:
        | Source Project                                        | Destination Project                                       |
        #        | Data Transfer - Repeating Events Source Project [518] | Data Transfer - Repeating Forms Destination Project [620] |
        | * | * |
        | control                                               |                                                           |
        | lchf                                                  |                                                           |
        | wfpb                                                  |                                                           |

        # And I should see table:
        # | Data Transfer - Repeating Events Source Project [518] | Data Transfer - Repeating Forms Destination Project [620] | | | | |
        # | Event Unique Name | Repeating? | Repeating Forms?     | Event Unique Name | Repeating? | Repeating Forms?         |
        # | baseline_arm_1    | No         | No                   |                   |            |                          |
        # | baseline_arm_2    | No         | No                   |                   |            |                          |
        # | baseline_arm_3    | No         | No                   |                   |            |                          |
        # | enrollment_arm_1  | No         | No                   |                   |            |                          |
        # | enrollment_arm_2  | No         | No                   |                   |            |                          |
        # | enrollment_arm_3  | No         | No                   |                   |            |                          |
        # | home_visit_arm_1  | No         | Yes                  |                   |            |                          |
        # | home_visit_arm_2  | No         | Yes                  |                   |            |                          |
        # | home_visit_arm_3  | No         | Yes                  |                   |            |                          |
        # | visit_arm_1       | Yes        | No                   |                   |            |                          |
        # | visit_arm_2       | Yes        | No                   |                   |            |                          |
        # | visit_arm_3       | Yes        | No                   |                   |            |                          |

    And I should see table row ("visit_arm_3, Yes, No, , ,  ")

    And I should see table row ("email, text, email, , , ,No,email, text, email, , , ,No")

