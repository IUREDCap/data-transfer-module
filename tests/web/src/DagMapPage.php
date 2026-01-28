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
 * Class for interacting with the user "DAG Map" configuration page.
 */
class DagMapPage
{
    public static function setNoDagTransfer($session)
    {
        $page = $session->getPage();
        $page->clickLink("Configure");
        $page->clickLink("DAG Map");
        Util::waitForAndFillField($session, 'dagOption', 'dagNoTransfer');
        $page->pressButton("Save");
    }

    public static function setDagMappingOption($session)
    {
        $page = $session->getPage();
        $page->clickLink("Configure");
        $page->clickLink("DAG Map");
        Util::waitForAndFillField($session, 'dagOption', 'dagMapping');
        $page->pressButton("Save");
    }

    public static function setDagMapping($session, $sourceDag, $destinationDag)
    {
        $page = $session->getPage();
        $page->clickLink("Configure");
        $page->clickLink("DAG Map");

        $dagMapTable = Util::waitForElement($session, 'dagMapTable');
        $trs = $dagMapTable->findAll('css', 'tr');
        for ($i = 1; $i < count($trs); $i++) {
            $tr = $trs[$i];
            $tds = $tr->findAll('css', 'td');

            $sourceTd        = $tds[0];
            $destinationTd   = $tds[2];

            if ($sourceDag === $sourceTd->getText()) {
                $destinationSelect = $destinationTd->find('css', 'select');
                $destinationSelect->selectOption($destinationDag);
            }
        }

        Util::waitForAndPressButton($session, "Save");
    }

    public static function setDagMappingExclude($session, $sourceDag)
    {
        $page = $session->getPage();
        $page->clickLink("Configure");
        $page->clickLink("DAG Map");

        $dagMapTable = Util::waitForElement($session, 'dagMapTable');
        $trs = $dagMapTable->findAll('css', 'tr');
        for ($i = 1; $i < count($trs); $i++) {
            $tr = $trs[$i];
            $tds = $tr->findAll('css', 'td');

            $sourceTd  = $tds[0];
            $excludeTd = $tds[1];

            if ($sourceDag === $sourceTd->getText()) {
                $excludeCheckbox = $excludeTd->find('css', 'input');
                $excludeCheckbox->check();
            }
        }

        Util::waitForAndPressButton($session, "Save");
    }
}
