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
 * Class for interacting with the user "Data Transfer Configurations" page.
 */
class ConfigurationsPage
{
    public const CONFIG_COLUMN   = 3;
    public const SCHEDULE_COLUMN = 4;
    public const COPY_COLUMN     = 5;
    public const RENAME_COLUMN   = 6;
    public const DELETE_COLUMN   = 7;

    public static function followConfiguration($session, $configName)
    {
        $page = $session->getPage();

        # Find the table row where the first element matches the config name, and then get the
        # 2nd column element and click it
        $element = $page->find(
            "xpath",
            "//tr/td[text()='".$configName."']/following-sibling::td[" . self::CONFIG_COLUMN . "]/a"
        );
        # print ($element->getHtml());
        $element->click();
    }


    public static function copyConfiguration($session, $configName, $copyToConfigName)
    {
        $page = $session->getPage();

        # Find the table row where the first element matches the config name,
        # and then click on the copy button
        $element = $page->find(
            "xpath",
            "//tr/td[text()='".$configName."']/following-sibling::td[" . self::COPY_COLUMN . "]"
        );
        $element->click();

        # Handle confirmation dialog
        $page->fillField("copyToConfigName", $copyToConfigName);
        $page->pressButton("Copy config");
    }

    public static function renameConfiguration($session, $configName, $renameNewConfigName)
    {
        $page = $session->getPage();

        # Find the table row where the first element matches the config name, and then
        # click on the rename column button
        $element = $page->find(
            "xpath",
            "//tr/td[text()='".$configName."']/following-sibling::td[" .self::RENAME_COLUMN . "]"
        );
        $input = $element->find('css', 'input');

        if (!empty($input)) {
            $input->click();

            # Handle confirmation dialog
            $page->fillField("renameNewConfigName", $renameNewConfigName);
            $page->pressButton("Rename config");
        }
    }


    /**
     * Deletes the specified config from the ETL configs list page
     *
     * @param string $configName the name of the config to delete.
     */
    public static function deleteConfiguration($session, $configName, $ifExists = false)
    {
        $page = $session->getPage();

        # Find the table row where the first element matches the config name,
        # and then click on the delete button
        $element = $page->find(
            "xpath",
            "//tr/td[text()='".$configName."']/following-sibling::td[" . self::DELETE_COLUMN . "]/input"
        );

        if ($ifExists && !isset($element)) {
            ;
        } else {
            $element->click();

            $page = $session->getPage();

            # Handle confirmation dialog
            $page->pressButton("Delete configuration");
        }
    }

    public static function deleteConfigurationIfExists($session, $configName)
    {
        $page = $session->getPage();

        # Find the table row where the first element matches the config name,
        # and then click on the delete button
        $element = $page->find(
            "xpath",
            "//tr/td[text()='".$configName."']/following-sibling::td[" . self::DELETE_COLUMN . "]/input"
        );

        if (isset($element)) {
            # print "Delete button for configuration \"{$configName}\" found: {$element->getHtml()}\n";

            $element->click();

            sleep(2);

            # Handle confirmation dialog
            $page->pressButton("Delete configuration");

            sleep(2);
        }
    }

    public static function addConfiguration($session, $configName)
    {
        $page = $session->getPage();
        $page->fillField('configurationName', $configName);
        $page->pressButton("Add");
    }
}
