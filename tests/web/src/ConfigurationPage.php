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
 * Class for interacting with the user "Data Transfer Configuration" page.
 */
class ConfigurationPage
{
    /**
     * @param $direction direction of file transfer ("import" or "export")
     */
    public static function enableWithLocalProject($session, $projectName, $direction)
    {
        $page = $session->getPage();

        # Go to tab that has "Enabled" setting
        $page->clickLink("Transfer Project");

        Util::waitForAndCheckField($session, "isEnabled");

        $page->fillField('direction', $direction);

        $page->fillField('location', 'local');

        $element = $page->find("xpath", "//option[starts-with(text(), '".$projectName." [')]");
        $optionValue = $element->getValue();
        $page->selectFieldOption('projectId', $optionValue);

        $page->pressButton("Save");
    }

    /**
     * @param $direction direction of file transfer ("import" or "export")
     *
     * TODO - need to handle API URL and token from config file
     */
    public static function enableWithApiProject($session, $apiUrl, $apiToken, $direction)
    {
        $page = $session->getPage();

        # Go to tab that has "Enabled" setting
        $page->clickLink("Transfer Project");

        Util::waitForAndCheckField($session, "isEnabled");

        $page->fillField('direction', $direction);

        $page->fillField('location', 'remote');

        $page->fillField('apiUrl', $apiUrl);
        $page->fillField('apiToken', $apiToken);

        $page->pressButton("Save");
    }

    public static function enable($session)
    {
        $page = $session->getPage();

        # Go to tab that has "Enabled" setting
        $page->clickLink("Transfer Project");

        Util::waitForAndCheckField($session, "isEnabled");

        $page->pressButton("Save");
    }

    public static function setImportFrom($session)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Project");

        Util::waitForAndFillField($session, 'direction', 'import');

        $page->pressButton("Save");
    }

    public static function setExportTo($session)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Project");

        Util::waitForAndFillField($session, 'direction', 'export');

        $page->pressButton("Save");
    }

    public static function setLocalProject($session, $projectName)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Project");

        Util::waitForAndFillField($session, 'location', 'local');

        $element = $page->find("xpath", "//option[starts-with(text(), '".$projectName." [')]");
        $optionValue = $element->getValue();
        $page->selectFieldOption('projectId', $optionValue);

        sleep(1);

        $page->pressButton("Save");
    }

    public static function setApiProject(
        $session,
        $apiUrlPropertySection,
        $apiUrlPropertyName,
        $apiTokenPropertySection,
        $apiTokenPropertyName
    ) {
        $testConfig = new TestConfig(FeatureContext::CONFIG_FILE);

        $apiUrl   = $testConfig->getProperty($apiUrlPropertySection, $apiUrlPropertyName);
        $apiToken = $testConfig->getProperty($apiTokenPropertySection, $apiTokenPropertyName);

        $page = $session->getPage();

        $page->clickLink("Transfer Project");

        Util::waitForAndFillField($session, 'location', 'remote');

        Util::waitForAndFillField($session, 'apiUrl', $apiUrl);
        Util::waitForAndFillField($session, 'apiToken', $apiToken);

        $page->pressButton("Save");
    }

    public static function setSourceProjectFilterLogic($session, $filterLogic)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Options");

        $page->fillField('sourceFilterLogic', $filterLogic);

        $page->pressButton("Save");

        sleep(3);
    }

    public static function setRecordCreation($session, $value)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Options");

        Util::waitForAndFillField($session, 'recordCreation', $value);

        $page->pressButton("Save");
    }

    public static function setTransferFiles($session)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Options");

        Util::waitForAndCheckField($session, 'transferFiles');

        $page->pressButton("Save");

        sleep(3);
    }

    public static function setRepeatingToNonRepeating($session, $value)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Options");

        Util::waitForAndFillField($session, 'repeatingToNonRepeating', $value);

        $page->pressButton("Save");
    }

    public static function setNonRepeatingToRepeating($session, $value)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Options");

        Util::waitForAndFillField($session, 'nonRepeatingToRepeating', $value);

        $page->pressButton("Save");
    }

    public static function setUpdateExistingRecords($session)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Options");

        Util::waitForAndFillField($session, 'updateRecords', 'true');

        $page->pressButton("Save");
    }

    public static function setOverwriteWithBlanks($session)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Options");

        Util::waitForAndCheckField($session, 'overwriteWithBlanks');

        $page->pressButton("Save");

        sleep(3);
    }

    public static function setMatchSecondaryUniqueField($session)
    {
        $page = $session->getPage();

        $page->clickLink("Transfer Options");

        Util::waitForAndFillField($session, 'recordMatch', 'matchSecondaryId');

        $page->pressButton("Save");
    }

    public static function setOnFormSave($session)
    {
        $page = $session->getPage();

        Util::waitForAndCheckField($session, "exportOnFormSave");

        $page->pressButton("Save");
    }
}
