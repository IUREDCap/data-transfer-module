#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Admin Interface
  In order to monitor and configure Data Transfer
  As an admin
  I need to be able to access the Data Transfer admin pages

  Background:
    Given I am on "/"
    When I log in as admin
    And I follow "Control Center"
    And I wait for 2 seconds
    And I follow "Data Transfer"
    And I wait for 2 seconds

  Scenario: Access the default admin page
    Then I should see "Data Transfer Admin"
    And I should see "Overview"
    And I should see "Admin Pages"
    But I should not see "REDCap crashed"

  Scenario: Access the admin config page
    When I follow "Config"
    Then I should see "Allowed Data Transfer cron job times"
    But I should not see "REDCap crashed"

  Scenario: Access the admin schedule detail page
    When I follow "Schedule Detail"
    Then I should see "Data Transfers Scheduled for:"
    But I should not see "REDCap crashed"

