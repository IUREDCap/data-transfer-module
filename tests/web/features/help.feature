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
    And I click "data-transfer-configurations-help-link"
    Then I should see "This page lets you manage the Data Transfer configurations"
    And I should see "View text on separate page"
    But I should not see "REDCap crashed"

    When I follow "View text on separate page" to new window
    Then I should see "This page lets you manage the Data Transfer configurations"
    But I should not see "View text on separate page"
    But I should not see "REDCap crashed"


