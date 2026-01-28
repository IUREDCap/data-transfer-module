#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Configurations
  In order to use Data Transfer
  As an user
  I need to be able create, copy, rename and delete Data Transfer configurations

  Background:
    Given I am on "/"
    When I log in as user
    And I go to Data Transfer for project "Data Transfer - Basic Source Project"
    # And I select project "basic_source"
    # And I follow "Data Transfer"

  Scenario: Add a configuration
    When I delete configuration "behat-test" if it exists
    And I fill in "configurationName" with "behat-test"
    And I press "Add"
    Then I should see "behat-test"
    But I should not see "REDCap crashed"
    And I should not see "ERROR:"

  Scenario: Add an existing configuration
    And I fill in "configurationName" with "behat-test"
    And I press "Add"
    Then I should see "ERROR:"
    And I should see "already exists"
    But I should not see "REDCap crashed"

  Scenario: Copy a configuration
    When I delete configuration "behat-copy" if it exists
    And I copy configuration "behat-test" to "behat-copy"
    Then I should see "behat-test"
    Then I should see "behat-copy"
    But I should not see "REDCap crashed"
    And I should not see "ERROR:"

  Scenario: Copy a configuration to a name that already exists
    And I copy configuration "behat-test" to "behat-copy"
    Then I should see "behat-test"
    And I should see "ERROR:"
    And I should see "already exists"
    But I should not see "REDCap crashed"

  Scenario: Copy a configuration of another user (which should succeed)
    When I log out
    And I log in as "user2"
    And I go to Data Transfer for project "Data Transfer - Basic Source Project"
    # And I select project "basic_source"
    # And I follow "Data Transfer"
    And I delete configuration "behat-copy-user2" if it exists
    And I copy configuration "behat-test" to "behat-copy-user2"
    Then I should see "behat-copy-user2"
    But I should not see "REDCap crashed"

  Scenario: Rename a configuration
    When I delete configuration "behat-rename" if it exists
    And I rename configuration "behat-test" to "behat-rename"
    Then I should see "behat-rename"
    But I should not see "behat-test"
    And I should not see "REDCap crashed"
    And I should not see "ERROR:"

    # Rename a configuration to a name that already exists
    When I wait for 4 seconds
    And I rename configuration "behat-rename" to "behat-copy"
    Then I should see "ERROR:"
    And I should see "already exists"
    But I should not see "REDCap crashed"

  Scenario: Delete a configuration
    When I delete configuration "behat-delete-config" if it exists
    And  I add configuration "behat-delete-config"
    Then I should see "behat-delete-config"
    But I should not see "REDCap crashed"

    When I delete configuration "behat-delete-config" if it exists
    Then I should not see "behat-delete-config"
    And I should not see "REDCap crashed"

  Scenario: Non-owner and non-admin delete configuration
    When I delete configuration "behat-delete-config" if it exists
    And  I add configuration "behat-delete-config"
    Then I should see "behat-delete-config"
    But I should not see "REDCap crashed"

    # Try to delete previoulsy created configuration as a 
    # non-admin, non-owner user, which should fail
    When I log out
    And I log in as "user2"
    And I go to Data Transfer for project "Data Transfer - Basic Source Project"
    # And I select project "basic_source"
    # And I follow "Data Transfer"
    And I delete configuration "behat-delete-config" if it exists
    Then I should see "behat-delete-config"
    But I should not see "REDCap crashed"

  Scenario: Non-owner and non-admin rename configuration
    When I delete configuration "behat-rename-config" if it exists
    And  I add configuration "behat-rename-config"
    Then I should see "behat-rename-config"
    But I should not see "REDCap crashed"

    # Try to rename previoulsy created configuration as a 
    # non-admin, non-owner user, which should fail
    When I log out
    And I log in as "user2"
    And I go to Data Transfer for project "Data Transfer - Basic Source Project"
    # And I select project "basic_source"
    # And I follow "Data Transfer"
    And I rename configuration "behat-rename-config" to "behat-non-owner-rename-config"
    Then I should see "behat-rename-config"
    But I should not see "behat-non-owner-rename-config"
    And I should not see "REDCap crashed"

