#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

Feature: Data Transfer
  In order to transfer data from one project to another
  As a user
  I need to see errors and warnings when there are issues

  Background:
    Given I am on "/"
    When I log in as user

  Scenario: Transfer data configuration that has issues that prevent it from manual data transfer

    # Delete the test data transfer configuration
    When I go to Data Transfer for project "Data Transfer - Basic Source Project"
    And I delete configuration "behat-export-basic-test" if it exists
    Then I should not see "behat-export-basic-test"
    And I should not see "REDCap crashed"

    # No configuration selected
    When I follow "Manual Transfer"
    Then I should see "No configuration selected."

    # Create the test data transfer configuration
    When I follow "Data Transfer Configurations"
    When I add configuration "behat-export-basic-test"
    And I follow configuration "behat-export-basic-test"
    And I wait for and press "Save"

    # Source project cannot be retrieved (because configuration is incomplete)
    When I follow "Manual Transfer"
    Then I should see "The data transfer project information for configuration"
    Then I should see "needs to be completed"

    # Set transfer project
    When I follow "Data Transfer Configurations"
    And I follow configuration "behat-export-basic-test"
    And I set local project to "Data Transfer - Basic Destination Project"
    And I set export to for configuration
    And I follow "Transfer Options"
    And I wait for and uncheck "manualTransferEnabled"
    And I wait for and press "Save"

    # Configuration has not been enabled
    When I follow "Data Transfer Configurations"
    And I follow configuration "behat-export-basic-test"
    And I follow "Manual Transfer"
    And I should see "contains no field mappings; no data will be transferred."
    And I should see "This configuration has not been enabled."

    # Enable configuration
    When I follow "Data Transfer Configurations"
    And I follow configuration "behat-export-basic-test"
    When I follow "Transfer Project"
    And I enable configuration

    # Manual configuration has not been enabled
    When I follow "Manual Transfer"
    And I should see "contains no field mappings; no data will be transferred."
    And I should see "Manual data transfer has not been enabled"

    # Add incomplete and error field mappings
    When I follow "Data Transfer Configurations"
    And I follow configuration "behat-export-basic-test"
    And I follow "Transfer Options"
    And I wait for and check "manualTransferEnabled"
    And I press "Save"
    When I follow "Field Map"
    And I add field mapping "" "demographics" "first_name" to "" "" ""
    And I add field mapping "" "demographics" "last_name" to "" "demographics" ""
    And I add field mapping "" "demographics" "emai" to "" "demographics" "dob"

    # Field mappings that are incomplete or have errors
    When I follow "Manual Transfer"
    Then I should see "WARNING:"
    And I should see "2 incomplete field mappings"
    And I should see "ERROR:"
    And I should see "1 field mapping with errors"

