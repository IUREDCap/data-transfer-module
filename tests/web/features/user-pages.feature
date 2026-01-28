#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: User-Interface
  In order to execute user actions
  As a user
  I need to be able to access the Data Transfer user pages

  Background:
    Given I am on "/"
    When I log in as user
    And I go to Data Transfer for project "Data Transfer - Basic Source Project"
    # When I select project "basic_source"
    # When I follow "Data Transfer"

  Scenario: Access the default data transfer page
    Then I should see "Data Transfer configuration name:"
    But I should not see "REDCap crashed"

  Scenario: Access the default data transfer configure page
    When I follow "Configure"
    Then I should see "No configuration selected"
    But I should not see "REDCap crashed"

  Scenario: Access the data transfer user manual page
    When I follow "User Manual"
    Then I should see "Overview"
    But I should not see "REDCap crashed"

