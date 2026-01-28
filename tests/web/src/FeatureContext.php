<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer\WebTests;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends MinkContext implements SnippetAcceptingContext
{
    const CONFIG_FILE = __DIR__.'/../config.ini';

    private $testConfig;
    private $timestamp;
    private $baseUrl;

    private static $featureFileName;

    private $previousWindowName;

    private $session;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
        $this->timestamp = date('Y-m-d-H-i-s');
        $this->testConfig = new TestConfig(self::CONFIG_FILE);
        $this->baseUrl = $this->testConfig->getRedCap()['base_url'];
    }

    /** @BeforeFeature */
    public static function setupFeature($scope)
    {
        $feature = $scope->getFeature();
        $filePath = $feature->getFile();
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
        self::$featureFileName = $fileName;
    }

    /** @AfterFeature */
    public static function teardownFeature($scope)
    {
    }


    /**
     * @BeforeScenario
     */
    public function setUpBeforeScenario()
    {
        echo "Feature file name :'".(self::$featureFileName)."'\n";

        $cookieName  = 'data-transfer-code-coverage-id';
        $cookieValue = 'web-test';

        $session = $this->getSession();
        #print_r($session);

        $this->setMinkParameter('base_url', $this->baseUrl);
        echo "Base URL set to: {$this->baseUrl}\n";

        $this->getSession()->visit($this->baseUrl);
        $this->getSession()->setCookie($cookieName, $cookieValue);
        echo "Cookie '{$cookieName}' set to '{$cookieValue}'\n";

        $this->testConfig = new TestConfig(self::CONFIG_FILE);
    }

    /**
     * @AfterScenario
     */
    public function afterScenario($event)
    {
        $session = $this->getSession();
        $session->restart();

        // $session->reset();  # Tests run much slower using reset (contrary to the documentation)

        // $scenario = $event->getScenario();
        // $tags = $scenario->getTags();
    }

    /**
     * @When /^I wait for (\d+) seconds$/
     */
    public function iWaitForSeconds($seconds)
    {
        sleep($seconds);
    }

    /**
     * @When /^I log in as user$/
     */
    public function iLogInAsUser()
    {
        $username = $this->testConfig->getUserUsername();
        $password = $this->testConfig->getUserPassword();

        $session = $this->getSession();
        Util::logInAsUser($session, $username, $password);
    }

    /**
     * @When /^I log in as "([^"]*)"$/
     */
    public function iLogInAs($user)
    {
        $username = $this->testConfig->getProperty($user, 'username');
        $password = $this->testConfig->getProperty($user, 'password');

        $session = $this->getSession();
        Util::logInAsUser($session, $username, $password);
    }

    /**
     * @When /^I select project "([^"]*)"$/
     */
    public function iSelectProject($name)
    {
        $session = $this->getSession();

        $page = $session->getPage();

        $page->clickLink("My Projects");

        $projectTitle = $this->testConfig->getProjectTitle($name);
        $page->clickLink($projectTitle);
    }

    /**
     * @When /^I go to project "([^"]*)"$/
     */
    public function iGoToProject($projectName)
    {
        $session = $this->getSession();

        Util::goToProject($session, $projectName);
    }

    /**
     * @When /^I go to Data Transfer for project "([^"]*)"$/
     */
    public function iGoToDataTransferdForProject($projectName)
    {
        $session = $this->getSession();

        Util::goToDataTransfer($session, $projectName);
    }

    /**
     * @When /^I go to project home for project "([^"]*)"$/
     */
    public function iGoToProjectHomeForProject($projectName)
    {
        $session = $this->getSession();

        Util::goToProjectHome($session, $projectName);
    }

    /**
     * @When /^I go to record status dashboard for project "([^"]*)"$/
     */
    public function iGoToRecordStatusDashboardForProject($projectName)
    {
        $session = $this->getSession();

        Util::goToRecordStatusDashboard($session, $projectName);
    }

    /**
     * @When /^I go to report all for project "([^"]*)"$/
     */
    public function iGoToReportAllForProject($projectName)
    {
        $session = $this->getSession();

        Util::goToReportAll($session, $projectName);
    }

    /**
     * @When /^I erase all data from project "([^"]*)"$/
     */
    public function iEraseAllDataFromProject($projectName)
    {
        $session = $this->getSession();

        Util::eraseAllData($session, $projectName);
    }

    #--------------------------------------------
    # Tables
    #--------------------------------------------

    /**
     * @Then I should see table:
     *
     * Example:
     *
     * Then I should see table:
     *   | username | email        |
     *   | user1    | user1@iu.edu |
     *   | user2    | user2@iu.edu |
     *
     * Use * for a wildcard value in the expected table specification
     * (this will match any value in the actual table)
     */
    public function iShouldSeeTable(TableNode $tableNode)
    {
        $tableFound = false;
        sleep(2);
        $session = $this->getSession();
        $page = $session->getPage();

        $tables = $page->findAll('css', 'table');

        $expectedRows = $tableNode->getRows();

        foreach ($tables as $table) {
            $trs = $table->findAll('css', 'tr') ?? [];

            if (count($trs) !== count($expectedRows)) {
                continue 1;
            }

            for ($row = 0; $row < count($trs); $row++) {
                $expectedRow = $expectedRows[$row];

                $tr = $trs[$row];
                $tds = $tr->findAll('css', 'td, th') ?? [];

                if (count($tds) !== count($expectedRow)) {
                    continue 2;
                }

                for ($column = 0; $column < count($tds); $column++) {
                    $td = $tds[$column];
                    $value = $td->getText();
                    $expectedValue = $expectedRow[$column];
                    if ($value !== $expectedValue && $expectedValue !== '*') {
                        continue 3;
                    }
                }
            }

            # If to this point, all rows and columns should have matched
            $tableFound = true;
            break;
        }

        if (!$tableFound) {
            throw new \Exception("The specified table was not found.\n");
        }
    }

    /**
     * @Then I should see table row ("([^"]*)"(,(\s)*"([^"]*)")*)
     */
    public function iShouldSeeTableRow($row)
    {
        $session = $this->getSession();
        $row = explode(',', $row);
        $found = Util::hasTableRow($session, $row);
        if (!$found) {
            throw new \Exception("Row not found: \n" . print_r($row, true));
        }
    }

    /**
     * @Then I should see table row (:row)
     */
    public function iShouldSeeTableRow2($row)
    {
        $session = $this->getSession();
        $row = array_map('trim', explode(',', $row));

        $found = Util::hasTableRow($session, $row);
        if (!$found) {
            throw new \Exception("Row not found: \n" . print_r($row, true));
        }
    }

    /**
     * @Then I should see table :tableId with only rows:
     *
     * Example:
     *
     * Then I should see table "userTable" with only rows:
     *   | username | email        |
     *   | user1    | user1@iu.edu |
     *   | user2    | user2@iu.edu |
     */
    public function iShouldSeeTableWithOnlyRows($tableId, TableNode $tableNode)
    {
        $session = $this->getSession();
        $table = Util::waitForElement($session, $tableId);

        if (empty($table)) {
            throw new \Exception("Could not find table \"{$tableId}\".");
        }

        $expectedRows = $tableNode->getRows();
        # print "\nExpected rows:\n";
        # foreach ($expectedRows as $expectedRow) {
        #     print_r($expectedRow);
        # }

        $trs = $table->findAll('css', 'tr');
        if (empty($trs)) {
            throw new \Exception("Could not find any rows for table \"{$tableId}\".");
        }

        # Remove the header row
        # array_shift($trs);

        if (count($trs) !== count($expectedRows)) {
            $message = "Expecting " . count($expectedRows) . " rows in table {$tableId}, but"
                . " found " . count($trs) . " rows.\n";
            throw new \Exception($message);
        }

        for ($row = 0; $row < count($trs); $row++) {
            $expectedRow = $expectedRows[$row];

            $tr = $trs[$row];


            $tds = $tr->findAll('css', 'td, th');

            if (count($tds) !== count($expectedRow)) {
                $message = "Expecting " . count($expectedRow) . " columns in row {$row} in table {$tableId}, but"
                    . " found " . count($tds) . " columns.\n";
                throw new \Exception($message);
            }

            for ($column = 0; $column < count($tds); $column++) {
                $td = $tds[$column];
                $value = $td->getText();
                $expectedValue = $expectedRow[$column];
                if ($value !== $expectedValue) {
                    $message = "Expecting value \"{$expectedValue}\" in column {$column} of row {$row}"
                        ." of table {$tableId}, but"
                        . " found value \"{$value}\"\n";
                    throw new \Exception($message);
                }
            }
        }
    }


    /**
     * @Then /^I should see table headers ("([^"]*)"(,(\s)*"([^"]*)")*)$/
     */
    public function iShouldSeeTableHeaders($headers)
    {
        $headers = explode(',', $headers);
        for ($i = 0; $i < count($headers); $i++) {
            # trim standard character plus quotes
            $headers[$i] = trim($headers[$i], " \t\n\r\0\x0B\"");
        }

        $session = $this->getSession();
        
        Util::checkTableHeaders($session, $headers);
    }


    /**
     * @When I click table element :row :column :element to new window
     */
    public function iClickTableElementToNewWindow($row, $column, $element)
    {
        $session = $this->getSession();
        $tableId = 'dtm-field-map-table';

        $element = Util::getTableElement($session, $tableId, $row, $column, $element);

        Util::clickElementToNewWindow($session, $element);
    }

    #-------------------------------------------------------
    # REDCap form management
    #-------------------------------------------------------
    /**
     * @When /^I go to form "([^"]*)" "([^"]*)" "([^"]*)"$/
     */
    public function iGoToForm($recordId, $event, $form)
    {
        $session = $this->getSession();
        FormPage::goToForm($session, $recordId, $event, $form, null);
    }


    #-------------------------------------------------------------------
    # Configurations management step definitions
    #
    # Note: Assumes you are on the data transfers configurations page
    #-------------------------------------------------------------------

    /**
     * @When /^I follow configuration "([^"]*)"$/
     */
    public function iFollowConfiguration($configName)
    {
        $session = $this->getSession();
        ConfigurationsPage::followConfiguration($session, $configName);
    }

    /**
     * @When /^I copy configuration "([^"]*)" to "([^"]*)"$/
     */
    public function iCopyConfiguration($configName, $copyToConfigName)
    {
        $session = $this->getSession();
        ConfigurationsPage::copyConfiguration($session, $configName, $copyToConfigName);
    }

    /**
     * @When /^I rename configuration "([^"]*)" to "([^"]*)"$/
     */
    public function iRenameConfiguration($configName, $newConfigName)
    {
        $session = $this->getSession();
        ConfigurationsPage::renameConfiguration($session, $configName, $newConfigName);
    }

    /**
     * @When /^I delete configuration "([^"]*)" if it exists$/
     *
     * Note: This step definition assumes that you are on the
     * data transfer configurations page.
     */
    public function iDeleteConfigurationIfItExists($configName)
    {
        $session = $this->getSession();
        ConfigurationsPage::deleteConfigurationIfExists($session, $configName);
    }

    /**
     * @When /^I add configuration "([^"]*)"$/
     *
     * Note: This step definition assumes that you are on the
     * data transfer configurations page.
     */
    public function iAddConfiguration($configName)
    {
        $session = $this->getSession();
        ConfigurationsPage::addConfiguration($session, $configName);
    }

    #-------------------------------------------------------------------
    # Configuration management step definitions
    #
    # Note: Assumes you are on a data transfer configuration page
    #-------------------------------------------------------------------

    /**
     * @When /^I enable import from local project "([^"]*)"$/
     */
    public function iEnableImportFromLocalProject($projectName)
    {
        $session = $this->getSession();
        ConfigurationPage::enableWithLocalProject($session, $projectName, 'import');
    }

    /**
     * @When /^I enable export to local project "([^"]*)"$/
     */
    public function iEnableExportToLocalProject($projectName)
    {
        $session = $this->getSession();
        ConfigurationPage::enableWithLocalProject($session, $projectName, 'export');
    }

    /**
     * @When /^I enable import from API project with URL property "([^"]*)" "([^"]*)" and token property "([^"]*)" "([^"]*)"$/
     */
    public function iEnableImportFromApiProject(
        $apiUrlPropertySection,
        $apiUrlPropertyName,
        $apiTokenPropertySection,
        $apiTokenPropertyName
    ) {
        $session = $this->getSession();

        $testConfig = new TestConfig(FeatureContext::CONFIG_FILE);

        $apiUrl   = $testConfig->getProperty($apiUrlPropertySection, $apiUrlPropertyName);
        $apiToken = $testConfig->getProperty($apiTokenPropertySection, $apiTokenPropertyName);

        ConfigurationPage::enableWithApiProject($session, $apiUrl, $apiToken, 'import');
    }


    /**
     * @When /^I enable export to API project with URL property "([^"]*)" "([^"]*)" and token property "([^"]*)" "([^"]*)"$/
     */
    public function iEnableExportToApiProject(
        $apiUrlPropertySection,
        $apiUrlPropertyName,
        $apiTokenPropertySection,
        $apiTokenPropertyName
    ) {
        $session = $this->getSession();

        $testConfig = new TestConfig(FeatureContext::CONFIG_FILE);

        $apiUrl   = $testConfig->getProperty($apiUrlPropertySection, $apiUrlPropertyName);
        $apiToken = $testConfig->getProperty($apiTokenPropertySection, $apiTokenPropertyName);

        ConfigurationPage::enableWithApiProject($session, $apiUrl, $apiToken, 'export');
    }

    /**
     * @When /^I enable configuration$/
     *
     * Note: This step definition assumes that you are on a
     * data transfer configuration page.
     */
    public function iEnableConfiguration()
    {
        $session = $this->getSession();
        ConfigurationPage::enable($session);
    }

    /**
     * @When /^I set export to for configuration$/
     */
    public function iSetExportToForConfiguration()
    {
        $session = $this->getSession();
        ConfigurationPage::setExportTo($session);
    }

    /**
     * @When /^I set import from for configuration$/
     */
    public function iSetImportFromForConfiguration()
    {
        $session = $this->getSession();
        ConfigurationPage::setImportFrom($session);
    }

    /**
     * @When /^I set local project to "([^"]*)"$/
     */
    public function iSetLocalProjectTo($projectName)
    {
        $session = $this->getSession();
        ConfigurationPage::setLocalProject($session, $projectName);
    }

    /**
     * @When /^I set API project with URL property "([^"]*)" "([^"]*)" and token property "([^"]*)" "([^"]*)"$/
     */
    public function iSetApiProject(
        $apiUrlPropertySection,
        $apiUrlPropertyName,
        $apiTokenPropertySection,
        $apiTokenPropertyName
    ) {
        $session = $this->getSession();
        ConfigurationPage::setApiProject(
            $session,
            $apiUrlPropertySection,
            $apiUrlPropertyName,
            $apiTokenPropertySection,
            $apiTokenPropertyName
        );
    }

    /**
     * @When /^I set source project filter logic to "([^"]*)"$/
     */
    public function iSetSourceProjectFilterLogicTo($filterLogic)
    {
        $session = $this->getSession();
        ConfigurationPage::setSourceProjectFilterLogic($session, $filterLogic);
    }

    /**
     * @When /^I set repeating to non-repeating to first instance$/
     */
    public function iSetRepeatingToNonRepeatingToFirstInstance()
    {
        $session = $this->getSession();
        ConfigurationPage::setRepeatingToNonRepeating($session, 'fromFirst');
    }

    /**
     * @When /^I set non-repeating to repeating to instance 1$/
     */
    public function iSetNonRepeatingToRepeatingToInstance1()
    {
        $session = $this->getSession();
        ConfigurationPage::setNonRepeatingToRepeating($session, 'to1');
    }

    /**
     * @When /^I set non-repeating to repeating to first instance$/
     */
    public function iSetNonRepeatingToRepeatingToFirstInstance()
    {
        $session = $this->getSession();
        ConfigurationPage::setNonRepeatingToRepeating($session, 'toFirst');
    }

    /**
     * @When /^I set non-repeating to repeating to last instance$/
     */
    public function iSetNonRepeatingToRepeatingToLastInstance()
    {
        $session = $this->getSession();
        ConfigurationPage::setNonRepeatingToRepeating($session, 'toLast');
    }

    /**
     * @When /^I set non-repeating to repeating to new instance$/
     */
    public function iSetNonRepeatingToRepeatingToNewInstance()
    {
        $session = $this->getSession();
        ConfigurationPage::setNonRepeatingToRepeating($session, 'toNew');
    }

    /**
     * @When /^I set record creation to none$/
     */
    public function iSetRecordCreationToNone()
    {
        $session = $this->getSession();
        ConfigurationPage::setRecordCreation($session, 'addNone');
    }

    /**
     * @When /^I check transfer files$/
     */
    public function iCheckTransferFiles()
    {
        $session = $this->getSession();
        ConfigurationPage::setTransferFiles($session);
    }

    /**
     * @When /^I set update existing records$/
     */
    public function iSetUpdateExistingRecords()
    {
        $session = $this->getSession();
        ConfigurationPage::setUpdateExistingRecords($session);
    }

    /**
     * @When /^I set overwrite existing values with blank values$/
     */
    public function iSetOverwriteWithBlanks()
    {
        $session = $this->getSession();
        ConfigurationPage::setOverwriteWithBlanks($session);
    }

    /**
     * @When /^I set record match to secondary unique field$/
     */
    public function iSetRecordMatchToSecondaryUniqueField()
    {
        $session = $this->getSession();
        ConfigurationPage::setMatchSecondaryUniqueField($session);
    }

    /**
     * @When /^I set on form save$/
     */
    public function iSetOnFormSave()
    {
        $session = $this->getSession();
        ConfigurationPage::setOnFormSave($session);
    }

    /**
     * @When /^I add field mapping "([^"]*)" "([^"]*)" "([^"]*)" to "([^"]*)" "([^"]*)" "([^"]*)"$/
     */
    public function iAddFieldMapping($sourceEvent, $sourceForm, $sourceField, $destinationEvent, $destinationForm, $destinationField)
    {
        $session = $this->getSession();
        $mapping = [
            'sourceEvent' => $sourceEvent,
            'sourceForm'  => $sourceForm,
            'sourceField' => $sourceField,
            'destinationEvent' => $destinationEvent,
            'destinationForm'  => $destinationForm,
            'destinationField' => $destinationField,
            'exclude' => false
        ];
        FieldMapPage::addFieldMapping($session, $mapping);
    }

    /**
     * @Then I add field mapping exclude :destinationEvent :destinationForm :destinationField
     */
    public function iAddFieldMappingExclude($destinationEvent, $destinationForm, $destinationField)
    {
        $session = $this->getSession();
        $mapping = [
            'sourceEvent' => '',
            'sourceForm'  => '',
            'sourceField' => '',
            'destinationEvent' => $destinationEvent,
            'destinationForm'  => $destinationForm,
            'destinationField' => $destinationField,
            'exclude' => true
        ];
        FieldMapPage::addFieldMapping($session, $mapping);
    }

    /**
     * @When /^I set no DAG transfer$/
     */
    public function iSetNoDagTransfer()
    {
        $session = $this->getSession();
        DagMapPage::setNoDagTransfer($session);
    }


    /**
     * @When /^I set DAG mapping option$/
     */
    public function iSetDagMappingOption()
    {
        $session = $this->getSession();
        DagMapPage::setDagMappingOption($session);
    }

    /**
     * @When /^I set DAG mapping from "([^"]*)" to "([^"]*)"$/
     */
    public function iSetDagMapping($sourceDag, $destinationDag)
    {
        $session = $this->getSession();
        DagMapPage::setDagMapping($session, $sourceDag, $destinationDag);
    }

    /**
     * @When /^I exclude DAG mapping from "([^"]*)"$/
     */
    public function iExcludeDagMapping($sourceDag)
    {
        $session = $this->getSession();
        DagMapPage::setDagMappingExclude($session, $sourceDag);
    }



    // ===================================================================================================================

    #-------------------------------------------------------------------
    # Scheduling
    #-------------------------------------------------------------------
    #
    /**
     * @When /^I schedule for next hour$/
     */
    public function iScheduleForNextHour()
    {
        $session = $this->getSession();
        SchedulePage::scheduleForNextHour($session);
    }




    /**
     * @Then /^the project data should match test data file "([^"]*)"$/
     *
     * Assumes logged in and in the project for which the data is being checked.
     */
    public function theProjectDataShouldMatchTestDataFile($testFile)
    {
        $session = $this->getSession();
        Util::compareProjectDataToTestFile($session, $testFile);
    }

    /**
     * @Then /^I should eventually see "([^"]*)"$/
     */
    public function iShouldEventuallySee($value)
    {
        $session = $this->getSession();
        $found = Util::waitForAndSee($session, $value, 20);
    }

    /**
     * @When /^I wait for and press "([^"]*)"$/
     */
    public function iWaitForAndPress($id)
    {
        $session = $this->getSession();
        $found = Util::waitForAndPressButton($session, $id, 20);
    }

    /**
     * @When /^I wait for and check "([^"]*)"$/
     */
    public function iWaitForAndCheck($id)
    {
        $session = $this->getSession();
        $found = Util::waitForAndCheckField($session, $id, 20);
    }

    /**
     * @When /^I wait for and uncheck "([^"]*)"$/
     */
    public function iWaitForAndUncheck($id)
    {
        $session = $this->getSession();
        $found = Util::waitForAndUncheckField($session, $id, 20);
    }



    /**
     * @Then /^I go to previous window$/
     */
    public function iGoToPreviousWindow()
    {
        if (!empty($this->previousWindowName)) {
            print "*** SWITCH TO PREVIOUS WINDOW {$this->previousWindowName}\n";
            $this->getSession()->switchToWindow($this->previousWindowName);
            $this->previousWindowName = '';
        }
    }

    /**
     * @Then /^Print element "([^"]*)" text$/
     */
    public function printElementText($css)
    {
        $session = $this->getSession();
        $page = $session->getPage();
        $element = $page->find('css', $css);
        $text = $element->getText();
        print "{$text}\n";
    }

    /**
     * @Then /^Print element "([^"]*)" value$/
     */
    public function printValueText($css)
    {
        $session = $this->getSession();
        $page = $session->getPage();
        $element = $page->find('css', $css);
        $value = $element->getValue();
        print "{$value}\n";
    }

    /**
     * @Then /^Field "([^"]*)" should contain value "([^"]*)"$/
     */
    public function fieldShouldContainValue($fieldLocator, $value)
    {
        $session = $this->getSession();
        $page = $session->getPage();
        $element = $page->findField($fieldLocator);
        if (!isset($element)) {
            throw new \Exception("Field \"{$css}\" not found.");
        }

        $fieldValue = $element->getValue();

        if (strpos($fieldValue, $value) === false) {
            throw new \Exception("Field \"{$css}\" does not contain value \"{$value}\".");
        }
    }

    /**
    /**
     * @Then /^Print select "([^"]*)" text$/
     */
    public function printSelectText($selectCss)
    {
        $session = $this->getSession();
        $page = $session->getPage();
        $select = $page->find('css', $selectCss);
        if (!empty($select)) {
            #$html = $select->getHtml();
            #print "\n{$html}\n\n";
            $option = $page->find('css', $selectCss." option:selected");
            #$option = $select->find('css', "option:selected");
            #$option = $select->find('xpath', "//option[@selected]");
            if (!empty($option)) {
                $text = $option->getText();
                print "{$text}\n";
            } else {
                print "Selected option not found\n";
            }
        } else {
            print 'Select "'.$selectCss.'" not found'."\n";
        }
    }

    /**
     * @Then /^I should see tabs? ("([^"]*)"(,(\s)*"([^"]*)")*)$/
     */
    public function iShouldSeeTabs($tabs)
    {
        $tabs = explode(',', $tabs);
        for ($i = 0; $i < count($tabs); $i++) {
            # trim standard character plus quotes
            $tabs[$i] = trim($tabs[$i], " \t\n\r\0\x0B\"");
        }

        $session = $this->getSession();
        Util::checkTabs($session, $tabs);
    }
    
    
    /**
     * @Then /^tab ("([^"]*)") should be selected$/
     */
    public function tabShouldBeSelected($tab)
    {
        $tab = trim($tab, " \t\n\r\0\x0B\"");

        $session = $this->getSession();
        Util::isSelectedTab($session, $tab);
    }

    /**
     * @Then /^I should not see tabs? ("([^"]*)"(,(\s)*"([^"]*)")*)$/
     */
    public function iShouldNotSeeTabs($tabs)
    {
        $tabs = explode(',', $tabs);
        for ($i = 0; $i < count($tabs); $i++) {
            # trim standard character plus quotes
            $tabs[$i] = trim($tabs[$i], " \t\n\r\0\x0B\"");
        }

        $session = $this->getSession();
        $shouldFind = false;
        Util::checkTabs($session, $tabs, $shouldFind);
    }



    /**
     * @When /^I print window names$/
     */
    public function iPrintWindowNames()
    {
        $windowName = $this->getSession()->getWindowName();
        $windowNames = $this->getSession()->getWindowNames();
        print "Current window: {$windowName} [".array_search($windowName, $windowNames)."]\n";
        print_r($windowNames);
    }

    /**
     * @When /^print link "([^"]*)"$/
     */
    public function printLink($linkId)
    {
        $session = $this->getSession();

        $page = $session->getPage();
        $link = $page->findLink($linkId);
        print "\n{$linkId}\n";
        print_r($link);
    }

    /**
     * @When /^I press first number of projects button$/
     */
    public function iPressFirstNumberOfProjectsButton()
    {
        $session = $this->getSession();

        UsersPage::pressFirstNumberOfProjectsButton($session);
    }

    /**
     * @When /^I click "([^"]*)"$/
     */
    public function iClick($id)
    {
        $session = $this->getSession();
        $page = $session->getPage();

        $element = $page->find('named', ['id_or_name', $id]);
        if ($element == null) {
            throw new \Exception('Could not find element "' . $id . '".');
        }
        $element->click();
    }

    /**
     * @When /^I click on element containing "([^"]*)"$/
     */
    public function iClickOnElementContaining($text)
    {
        $session = $this->getSession();

        $page = $session->getPage();
        $element = $page->find('xpath', "//*[contains(text(), '{$text}')]");
        $element->click();
    }



    /**
     * @When /^I log in as admin$/
     */
    public function iLogInAsAdmin()
    {
        $session = $this->getSession();
        Util::loginAsAdmin($session);
    }


    /**
     * @When /^I log out$/
     */
    public function iLogOut()
    {
        $session = $this->getSession();
        Util::logOut($session);
    }

    /**
     * @When /^I access the admin interface$/
     */
    public function iAccessTheAdminInterface()
    {
        $session = $this->getSession();
        Util::logInAsAdminAndAccessAutoNotify($session);
    }

    /**
     * @When /^I follow "([^"]*)" to new window$/
     */
    public function iFollowLinkToNewWindow($link)
    {
        $session = $this->getSession();
        $this->previousWindowName = $session->getWindowName();
        Util::goToNewWindow($session, $link);
    }

    /**
     * @When /^I press button "([^"]*)" to new window$/
     */
    public function iPressButtonToNewWindow($button)
    {
        $session = $this->getSession();
        $this->previousWindowName = $session->getWindowName();
        Util::pressButtonToNewWindow($session, $button);
    }

    /**
     * @When /^I select user from "([^"]*)"$/
     */
    public function iSelectUserFromSelect($select)
    {
        $session = $this->getSession();
        Util::selectUserFromSelect($session, $select);
    }


    #---------------------------------
    # REDCAP USER
    #---------------------------------

    /**
     * @When /^I create user "([^"]*)" "([^"]*)" "([^"]*)" "([^"]*)" "([^"]*)"$/
     */
    public function iCreateUser($username, $password, $firstName, $lastName, $email)
    {
        $session = $this->getSession();
        Util::createUser($session, $username, $password, $firstName, $lastName, $email);
    }

    /**
     * @When /^I delete user "([^"]*)"$/
     */
    public function iDeleteUser($username)
    {
        $session = $this->getSession();
        Util::deleteUserIfExists($session, $username);
    }
}
