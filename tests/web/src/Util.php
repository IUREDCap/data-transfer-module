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

use WebDriver\Exception\NoAlertOpenError;

/**
 * Utility class that has helpful methods.
 */
class Util
{
    public const TEST_DATA_DIR = __DIR__ . '/../../data/';

    /**
     * Gets a web browser sessions. This can be useful for interacting with
     * a web browser outside of the context of a scenario.
     */
    public static function getSession()
    {
        $testConfig = new TestConfig(FeatureContext::CONFIG_FILE);
        $baseUrl = $testConfig->getRedCap()['base_url'];

        $driver = new \DMore\ChromeDriver\ChromeDriver('http://localhost:9222', null, $baseUrl);
        $session = new \Behat\Mink\Session($driver);
        $session->start();

        return $session;
    }

    /**
     * Logs in to REDCap as the admin.
     */
    public static function logInAsAdmin($session)
    {
        $testConfig = new TestConfig(FeatureContext::CONFIG_FILE);
        $baseUrl  = $testConfig->getRedCap()['base_url'];
        $username = $testConfig->getAdmin()['username'];
        $password = $testConfig->getAdmin()['password'];

        $session->visit($baseUrl);

        $page = $session->getPage();

        $page->fillField('username', $username);
        $page->fillField('password', $password);
        $page->pressButton('login_btn');
    }

    /**
     * Logs out of REDCap.
     */
    public static function logOut($session)
    {
        $page = $session->getPage();
        $page->clickLink('Log out');
    }

    public static function waitForElement($session, $id, $timeout = 10)
    {
        $waitTime = 0;

        $page = $session->getPage();

        $element = $page->findById($id);
        while (empty($element) && $waitTime < $timeout) {
            sleep(1);
            $element = $page->findById($id);
            $waitTime++;
        }

        return $element;
    }

    public static function waitForButton($session, $locator, $timeout = 10)
    {
        $waitTime = 0;

        sleep(2);

        $page = $session->getPage();

        $button = $page->findButton($locator);
        while (empty($button) && $waitTime < $timeout) {
            sleep(1);
            $button = $page->findButton($locator);
            $waitTime++;
        }

        return $button;
    }

    public static function waitForAndPressButton($session, $buttonLocator, $timeout = 10)
    {
        $button = Util::waitForButton($session, $buttonLocator, $timeout);

        if (empty($button)) {
            throw new \Exception("Button \"{$buttonLocator}\" not found.\n");
        }

        $button->press();
    }

    public static function waitForAndFillField($session, $fieldId, $fieldValue, $timeout = 10)
    {
        Util::waitForElement($session, $fieldId, $timeout);
        $page = $session->getPage();
        $page->fillField($fieldId, $fieldValue);
    }

    public static function waitForAndCheckField($session, $fieldId, $timeout = 10)
    {
        Util::waitForElement($session, $fieldId, $timeout);
        $page = $session->getPage();
        $page->checkField($fieldId);
    }

    public static function waitForAndUncheckField($session, $fieldId, $timeout = 10)
    {
        Util::waitForElement($session, $fieldId, $timeout);
        $page = $session->getPage();
        $page->uncheckField($fieldId);
    }

    public static function waitForAndSelectOption($session, $fieldId, $fieldValue, $timeout = 10)
    {
        Util::waitForElement($session, $fieldId, $timeout);
        $page = $session->getPage();
        $page->selectFieldOption($fieldId, $fieldValue);
    }


    public static function waitForAndSee($session, $value, $timeout = 10)
    {
        $waitTime = 0;
        $found = false;

        $page = $session->getPage();

        $pageText = $page->getText();
        while ($waitTime < $timeout) {
            if (str_contains($pageText, $value)) {
                $found = true;
                break;
            }
            sleep(1);
            $page = $session->getPage();
            $pageText = $page->getText();
            $waitTime++;
        }

        if (!$found) {
            throw new \Exception("Value \"{$value}\" was not found on the page.");
        }
    }

    /**
     * Checks for module page tabs.
     *
     * @param array $tabs array of strings that are tab names
     * @param boolean $shouldFind if true, checks that tabs exist, if false
     *     checks that tabs do not exist.
     */
    public static function checkTabs($session, $tabs, $shouldFind = true)
    {
        $page = $session->getPage();
        $element = $page->find('css', '#sub-nav');

        foreach ($tabs as $tab) {
            $link = $element->findLink($tab);
            if (empty($link)) {
                if ($shouldFind) {
                    throw new \Exception("Tab {$tab} not found.");
                }
            } else {
                if (!$shouldFind) {
                    throw new \Exception("Tab {$tab} found.");
                }                
            }
        }
    }
    
    /**
     * Goes to the specified project for the current user (assumes user is already logged in).
     */
    public static function goToProject($session, $projectName)
    {
        $page = $session->getPage();

        # Go to "My Projects" page
        $link = $page->findLink('My Projects');
        if (empty($link)) {
            throw new \Exception('Could not find the "My Projects" link.');
        }
        $link->click();

        # Select the specified project
        $link = $page->findLink($projectName);
        if (empty($link)) {
            throw new \Exception('Could not find link for project"' . $projectName . '".');
        }
        $link->click();
    }

    /**
     * Goes to the Data Transfer external module interface for the specified project.
     */
    public static function goToDataTransfer($session, $projectName)
    {
        Util::goToProject($session, $projectName); 

        $page = $session->getPage();

        # Go to "Data Transfer" page
        $link = $page->findLink("Data Transfer");
        if (empty($link)) {
            throw new \Exception('Could not find the "Data Transfer" link.');
        }
        $link->click();
    }

    /**
     * Goes to the Record Status Dashboard for the specified project.
     */
    public static function goToRecordStatusDashboard($session, $projectName)
    {
        Util::goToProject($session, $projectName); 

        $page = $session->getPage();

        # Go to "Record Status Dashboard" page
        $link = $page->findLink("Record Status Dashboard");
        if (empty($link)) {
            throw new \Exception('Could not find the "Record Status Dashboard" link.');
        }
        $link->click();
    }

    public static function goToProjectHome($session, $projectName)
    {
        Util::goToProject($session, $projectName); 

        $page = $session->getPage();

        $link = $page->findLink("Project Home");
        if (empty($link)) {
            throw new \Exception('Could not find the "Project Home" link.');
        }
        $link->click();
        sleep(4); // it takes a while for this page to load the values
    }


    public static function goToReportAll($session, $projectName)
    {
        Util::goToProject($session, $projectName); 

        $page = $session->getPage();

        # Go to "Record Status Dashboard" page
        $link = $page->findLink("Data Exports, Reports, and Stats");
        if (empty($link)) {
            throw new \Exception('Could not find the "Data Exports, Reports, and Stats" link.');
        }
        $link->click();

        $page = $session->getPage();

        $page->pressButton('View Report');
        sleep(3);
    }

    /**
     * Assumes that the user is logged in and in a project.
     */
    public static function goToCurrentReportAll($session)
    {
        $page = $session->getPage();

        # Go to "Record Status Dashboard" page
        $link = $page->findLink("Data Exports, Reports, and Stats");
        if (empty($link)) {
            throw new \Exception('Could not find the "Data Exports, Reports, and Stats" link.');
        }
        $link->click();

        $page = $session->getPage();

        $page->pressButton('View Report');
        sleep(3);
    }

    /**
     * Erases the data for the specified project.
     *
     * TODO: test...
     */
    public static function eraseAllData($session, $projectName)
    {
        Util::goToProject($session, $projectName); 

        $page = $session->getPage();

        # Go to "Other Functionality" page
        $link = $page->findLink("Other Functionality");
        if (empty($link)) {
            throw new \Exception('Could not find the "Other Functionality" link.');
        }
        $link->click();

        sleep(3);

        // $page->pressButton("Erase all data");
        Util::waitForAndPressButton($session, "Erase all data");

        # Dialog "Erase all data" confirmation
        $elements = $page->findAll('css', '.ui-button');
        foreach ($elements as $element) {
            if ($element->getText() === "Erase all data") {
                $button = $element;
                break;
            }
        }
        $button->press();

        $page->pressButton("Close"); // Success dialog
    }

    // TODO
    public static function testData($session, $projectName)
    {
        Util::goToProject($session, $projectName); 

        $page = $session->getPage();

    }


    public static function isSelectedTab($session, $tab)
    {
        $page = $session->getPage();
        $element = $page->find('css', '#sub-nav');

        $link = $element->findLink($tab);
        if (empty($link)) {
            throw new \Exception("Tab {$tab} not found.");
        }
        
        if (!$link->getParent()->hasClass('active')) {
            throw new \Exception("Tab {$tab} is not selected.");
        }
    }
    
    
    /**
     * Checks that the specified table headers exist on the current page.
     *
     * @param array $headers array of strings that are table headers.
     */
    public static function checkTableHeaders($session, $headers)
    {
        $page = $session->getPage();
        $elements = $page->findAll('css', 'th');
        
        $headersMap = array();
        if (!empty($elements)) {
            foreach ($elements as $element) {
                $headersMap[$element->getText()] = 1;
            }
        }

        foreach ($headers as $header) {
            if (!array_key_exists($header, $headersMap)) {
                throw new \Exception("Table header \"{$header}\" not found.");
            }
        }
    }

    /**
     * @param string $element the element (e.g., "a", "button")
     * @param int $elementNumber the element number, zero-indexed. For example, to get the
     *     2nd "a" element, specify this as 1.
     */
    public static function getTableElement($session, $tableId, $row, $column, $element, $elementNumber = 0)
    {
        $tableElement = null;

        $page = $session->getPage();
        $table = $page->findById($tableId);

        if (empty($table)) {
            throw new \Exception("Table \"{$tableId}\" not found.\n");
        }

        $trs = $table->findAll('css', 'tr');

        if (empty($trs) || $row >= count($trs)) {
            throw new \Exception("Row {$row} does not exist in table \"{$tableId}\"\n");
        }

        $tr = $trs[$row];
        $tds = $tr->findAll('css', 'td, th');

        if (empty($tds) || $column >= count($tds)) {
            throw new \Exception("Column {$column} does not exist in row {$row} of table \"{$tableId}\"\n");
        }

        $cell = $tds[$column];

        $elements = $cell->findAll('css', $element);

        if (empty($elements) || $elementNumber >= count($elements)) {
            $message = "Element \"{$element}\" number {$elementNumber} not found in column {$column}"
                . " of row {$row} of table \"{$tableId}\"\n";
            throw new \Exception($message);
        }

        $tableElement = $elements[$elementNumber];

        return $tableElement;
    }
            
    public static function findTextFollowedByText($session, $textA, $textB)
    {
        $content = $session->getPage()->getContent();

        // Get rid of stuff between script tags
        $content = self::removeContentBetweenTags('script', $content);

        // ...and stuff between style tags
        $content = self::removeContentBetweenTags('style', $content);

        $content = preg_replace('/<[^>]+>/', ' ',$content);

        // Replace line breaks and tabs with a single space character
        $content = preg_replace('/[\n\r\t]+/', ' ',$content);

        $content = preg_replace('/ {2,}/', ' ',$content);

        if (strpos($content,$textA) === false) {
            throw new \Exception(sprintf('"%s" was not found in the page', $textA));
        }

        if ($textB) {
            $seeking = $textA . ' ' . $textB;
            if (strpos($content,$textA . ' ' . $textB) === false) {
                throw new \Exception(sprintf('"%s" was not found in the page', $seeking));
            }
        }
    }

    public static function findThisText($session, $see, $textA)
    {
        $content = $session->getPage()->getContent();

        // Get rid of stuff between script tags
        $content = self::removeContentBetweenTags('script', $content);

        // ...and stuff between style tags
        $content = self::removeContentBetweenTags('style', $content);

        $content = preg_replace('/<[^>]+>/', ' ',$content);

        // Replace line breaks and tabs with a single space character
        $content = preg_replace('/[\n\r\t]+/', ' ',$content);

        $content = preg_replace('/ {2,}/', ' ',$content);

        $seeError = "was not";
        if ($see === "should not") {
            $seeError = "was";
        }

        if ($see === 'should') {
            if (strpos($content,$textA) === false) {
               throw new \Exception(sprintf('"%s" was not found in the page', $textA));
            }
        } elseif ($see === 'should not') {
            if (strpos($content,$textA) === true) {
               throw new \Exception(sprintf('"%s" was found in the page', $textA));
            }
        } else {
            throw new \Exception(sprintf('"%s" option is unrecognized', $see));
        }
    }

    /**
     * Note: matches full values
     */
    public static function tableColumnContains($session, $columnName, $value)
    {
        $values = self::getTableColumnValues($session, $columnName);
        return in_array($value, $values);
    }

    /**
     * Note: matches full values
     */
    public static function tableColumnDoesNotContain($session, $columnName, $value)
    {
        $values = self::getTableColumnValues($session, $columnName);
        return !in_array($value, $values);
    }

    /**
     * Gets the values (td element text) for the specified table column name.
     */
    public static function getTableColumnValues($session, $columnName)
    {
        $page = $session->getPage();
        $elements = $page->findall('xpath', "//table//td[count(//table//th[text()='{$columnName}']/preceding-sibling::*) +1]");

        $values = [];
        if ($elements != null && is_array($elements)) {
            # $i = 0;
            foreach ($elements as $element) {
                $values[] = $element->getText();
                # print ("{$i}: " . $element->getText() . "\n");
                # $i++;
            }
        }

        return $values;
    }

    /**
     * @param array $row array of row values.
     */
    public static function hasTableRow($session, $row)
    {
        $found = false;

        $page = $session->getPage();
        $rowElements = $page->findAll('css', 'tr');

        # print "\nHas table row - row elements: " . count($rowElements) . "\n";

        if (!empty($rowElements)) {
            foreach ($rowElements as $rowElement) {
                $columnElements = $rowElement->findAll('css', 'td');
                if (!empty($columnElements)) {
                    if (count($columnElements) === count($row)) {
                        $pageRow = [];
                        foreach ($columnElements as $columnElement) {
                            $pageRow[] = $columnElement->getText();
                            # print "        " . $columnElement->getText() . "\n";
                        }
                        if ($row === $pageRow) {
                            $found = true;
                        }
                    }
                }
            }
        }

        return $found;
    }

    public static function compareProjectDataToTestFile($session, $testFile)
    {
        $testConfig = new TestConfig(FeatureContext::CONFIG_FILE);

        self::goToCurrentReportAll($session);

        $page = $session->getPage();

        $page->pressButton('Export Data');
        $page->fillField('export_format', 'csvraw');    // Export format radio button

        $page->pressButton('Export Data');
        $div = $page->find('css', 'div.ui-dialog-buttonset');
        $button = $div->find('xpath', '//button[text()="Export Data"]');
        $button->click();

        sleep(2);

        $downloadDir = $testConfig->getDownloadDir();
        # print "\nDownload Dir: {$downloadDir}\n";

        #----------------------------------------------------------
        # Get CSV files in download directory before download
        #----------------------------------------------------------
        $files = scandir($downloadDir);
        $csvFiles = [];
        foreach ($files as $file) {
            $filePath = $downloadDir . '/' . $file;
            if (is_file($filePath) && str_ends_with($filePath, '.csv')) {
                $csvFiles[] = $filePath;
            }
        }

        $img = $page->find('xpath', '//img[contains(@src, "download_csvexcel_raw")]');
        $img->click();

        sleep(4);   // Give time for file to be downloaded

        #----------------------------------------------------------
        # Get CSV files in download directory after download
        #----------------------------------------------------------
        $files = scandir($downloadDir);
        $afterDownloadCsvFiles = [];
        foreach ($files as $file) {
            $filePath = $downloadDir . '/' . $file;
            if (is_file($filePath) && str_ends_with($filePath, '.csv')) {
                $afterDownloadCsvFiles[] = $filePath;
            }
        }

        $newCsvFiles = array_diff($afterDownloadCsvFiles, $csvFiles);

        if (empty($newCsvFiles)) {
            throw new \Exception("Could not find CSV download file.");
        } elseif (count($newCsvFiles) > 1) {
            throw new \Exception("Found more than 1 CSV download file.");
        } else {
            $downloadCsvFile = $newCsvFiles[array_key_first($newCsvFiles)];
        }

        $downloadCsvArray = CsvUtil::csvFileToArray($downloadCsvFile);

        $testFilePath = self::TEST_DATA_DIR . $testFile;
        $testFileArray = CsvUtil::csvFileToArray($testFilePath);

        #---------------------------------------------------------
        # Check the download results
        #---------------------------------------------------------
        if (count($downloadCsvArray) !== count($testFileArray)) {
            $message = "The download CSV file has " . count($downloadCsvArray) . " rows, but"
                . " it was expected to have " . count($testFileArray) . " rows.";
            throw new \Exception($message);
        }

        for ($i = 0; $i < count($downloadCsvArray); $i++) {
            if ($downloadCsvArray[$i] !== $testFileArray[$i]) {
                $message = "Row number {$i} of the download CSV file does not match the"
                    . " expected results. It has the following values:\n" . print_r($downloadCsvArray[$i], true)
                    . "\nbut these values were expected:\n" . print_r($testFileArray[$i], true) . "\n";

                if (count($downloadCsvArray[$i]) !== count($testFileArray[$i])) {
                    $message .= "\nThe download row has " . count($downloadCsvArray[$i]) . " elements,"
                        . " but " . count($testFileArray[$i]) . " elements were expected.";
                } else {
                    for ($j = 0; $j < count($downloadCsvArray[$i]); $j++) {
                        if ($downloadCsvArray[$i][$j] !== $testFileArray[$i][$j]) {
                            $message .= "\nValue {$j} does not match. Expected \"{$testFileArray[$i][$j]}\","
                                . " but found \"{$downloadCsvArray[$i][$j]}\".\n";
                        }
                    }
                }

                throw new \Exception($message);
            }
        }
    }


    /**
     * Follow a link that goes to a new window.
     *
     * @param string $link the link that goes to a new window.
     *
     * @return string the name of the new window
     */
    public static function goToNewWindow($session, $link)
    {
        # Save the current window names
        $windowNames = $session->getWindowNames();

        # Follow the link (which should create a new window name)
        $page = $session->getPage();
        $page->clickLink($link);
        sleep(2); // Give some time for new window to open

        # See what window name was added (this should be the new window)
        $newWindowNames = $session->getWindowNames();
        $windowNamesDiff = array_diff($newWindowNames, $windowNames);
        $newWindowName = array_shift($windowNamesDiff); // There should be only 1 element in the diff

        $session->switchToWindow($newWindowName);

        return $newWindowName;
    }

    public static function pressButtonToNewWindow($session, $button)
    {
        # Save the current window names
        $windowNames = $session->getWindowNames();

        # Press the button (which should create a new window name)
        $page = $session->getPage();
        $page->pressButton($button);
        sleep(4); // Give some time for new window to open

        # See what window name was added (this should be the new window)
        $newWindowNames = $session->getWindowNames();
        $windowNamesDiff = array_diff($newWindowNames, $windowNames);
        $newWindowName = array_shift($windowNamesDiff); // There should be only 1 element in the diff

        $session->switchToWindow($newWindowName);

        return $newWindowName;
    }

    public static function clickElementToNewWindow($session, $element)
    {
        # Save the current window names
        $windowNames = $session->getWindowNames();

        # Press the button (which should create a new window name)
        sleep(2);
        $page = $session->getPage();
        $element->click();
        sleep(5); // Give some time for new window to open

        # See what window name was added (this should be the new window)
        $newWindowNames = $session->getWindowNames();
        $windowNamesDiff = array_diff($newWindowNames, $windowNames);
        $newWindowName = array_shift($windowNamesDiff); // There should be only 1 element in the diff

        $session->switchToWindow($newWindowName);

        return $newWindowName;
    }

    /**
     * Created the specified user in REDCap, including setting the user's password.
     * This method assumes that the admin account has already been logged into.
     */
    public static function createUser($session, $username, $password, $firstName, $lastName, $email)
    {
        $page = $session->getPage();
        $page->clickLink('Control Center');
        sleep(1);

        $page->clickLink('Add Users (Table-based Only)');
        sleep(1);
        $page->fillField('username', $username);
        $page->fillField('user_firstname', $firstName);
        $page->fillField('user_lastname', $lastName);
        $page->fillField('user_email', $email);
        sleep(1);
        $page->pressButton('Save');
        sleep(1);

        self::logOut($session);

        $mailHogApi = new MailHogApi();
        $messages = $mailHogApi->getMessages($email, 'REDCap access granted');

        if (count($messages) <= 0) {
            throw new \Exception('No password reset e-mail found for creation of user "' . $username . '".');
        }

        # Get the HTML for the first message
        $message = $messages[0];
        $htmlMessage = $message->getMessageHtml();

        # Get the password reset url
        $matches = array();
        preg_match_all('/<a(.*)href="([^"]+)"(.*)>(.*)<\/a>/', $htmlMessage, $matches);
        if ($matches === null || count($matches) < 3 || $matches[2] === null) {
            throw new \Exception("Password reset URL not found in password reset e-mail for user \"{$username}\".");
        }
        $url = $matches[2][0];

        sleep(2);

        $session->visit($url);
        $page = $session->getPage();

        $page->fillField('password', $password);
        $page->fillField('password2', $password);
        sleep(2);
        $page->pressButton('Submit');
        sleep(2);

        self::logOut($session);

        sleep(2);
        self::logInAsAdmin($session);
    }

    /**
     * Deletes the specified user from REDCap if they exist. This method assumes that the admin account has already been logged into.
     */
    public static function deleteUserIfExists($session, $username)
    {
        $page = $session->getPage();
        $page->clickLink('Control Center');
        sleep(1);

        $page->clickLink('Browse Users');
        sleep(1);
        $page->fillField('user_search', $username);
        $page->pressButton('Search');

        $pageText = $page->getText();

        if (str_contains($pageText, "User information for")) {
            sleep(1);
            $session->getDriver()->executeScript('window.confirm = function(){return true;}');
            $page->pressButton('Delete user from system');
            sleep(2);

        }

        sleep(1);
    }

    /**
     * Logs in to REDCap as the specified user.
     */
    public static function logInAsUser($session, $username, $password)
    {
        $testConfig = new TestConfig(FeatureContext::CONFIG_FILE);
        $baseUrl  = $testConfig->getRedCap()['base_url'];

        $session->visit($baseUrl);

        $page = $session->getPage();

        $page->fillField('username', $username);
        $page->fillField('password', $password);
        $page->pressButton('login_btn');
    }
}
