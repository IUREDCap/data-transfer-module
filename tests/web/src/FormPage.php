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
 * Class for interacting with REDCap's form editing page.
 */
class FormPage
{
    /**
     */
    public static function goToForm($session, $recordId, $event, $form, $instance)
    {
        $page = $session->getPage();

        /*
        $page->clickLink("Record Status Dashboard");

        $div = $page->findById("sub-nav");

        if (!empty($div)) {
            # Longitudinal with multiple arms
            print "DIV: " . $div->getHtml() . "\n";
            $links = $div->findAll('css', 'a');
            foreach ($links as $link) {
                print "    LINK: " . $link->getHtml() . "\n";
            }

        } else {
            # Non-longitudinal (classic) or no arms?
        }
         */

        $page->clickLink("Add / Edit Records");

        Util::waitForAndSelectOption($session, 'record', $recordId);

        sleep(2);

        $page = $session->getPage();

        $table = Util::waitForElement($session, 'event_grid_table');

        if (empty($table)) {
            throw new \Exception("Could not find table containing forms for editing.\n");
        }

        sleep(4);

        $tds = $table->findAll('css', 'td');
        $formTs = null;
        foreach ($tds as $td) {
            if ($td->getText() === $form) {
                $formTd = $td;
                break;
            }
        }

        $formLink = $formTd->find('xpath', '/following-sibling::td[1]/a');

        if (empty($formLink)) {
            throw new \Exception("Could not find link for form \"{$form}\".");
        }
        $formLink->click();
    }
}
