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
 * Class for interacting with the user "Field Map" page.
 */
class FieldMapPage
{
    public static function addFieldMapping($session, $mapping)
    {
        sleep(2);

        $page = $session->getPage();

        sleep(1);

        $page->clickLink("Field Map");

        Util::waitForAndPressButton($session, 'Add field mapping');

        sleep(1);

        $mapTable = $page->find('css', 'table#dtm-field-map-table');

        $lastRow = $mapTable->find('xpath', '/tbody/tr[position()=last()]');

        $tds = $lastRow->findAll('css', 'td');

        sleep(1);

        $exclude = $mapping['exclude'];

        if (!$exclude) {
            #---------------------------------
            # Set source mapping selects
            #---------------------------------
            $sourceEventSelect = ($tds[1])->find('css', 'select');
            $sourceFormSelect  = ($tds[2])->find('css', 'select');
            $sourceFieldSelect = ($tds[3])->find('css', 'select');

            if (!empty($mapping['sourceEvent'])) {
                $sourceEventSelect->selectOption($mapping['sourceEvent']);
            }

            if (!empty($mapping['sourceForm'])) {
                $sourceFormSelect->selectOption($mapping['sourceForm']);
            }

            if (!empty($mapping['sourceField'])) {
                $sourceFieldSelect->selectOption($mapping['sourceField']);
            }
        }

        sleep(1);

        #---------------------------------
        # Set destination mapping selects
        #---------------------------------
        $destinationEventSelect = ($tds[5])->find('css', 'select');
        $destinationFormSelect  = ($tds[6])->find('css', 'select');
        $destinationFieldSelect = ($tds[7])->find('css', 'select');
        $destinationExclude     = ($tds[8])->find('css', 'input');

        if (!empty($mapping['destinationEvent'])) {
            $destinationEventSelect->selectOption($mapping['destinationEvent']);
        }

        if (!empty($mapping['destinationForm'])) {
            $destinationFormSelect->selectOption($mapping['destinationForm']);
        }

        if (!empty($mapping['destinationField'])) {
            $destinationFieldSelect->selectOption($mapping['destinationField']);
        }

        if ($exclude) {
            $destinationExclude->check();
        }

        $page->pressButton("Save");

        sleep(3);
    }
}
