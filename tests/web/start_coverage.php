<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

require_once __DIR__.'/vendor/autoload.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Driver\Selector;

$codeCoverageId = null;
if (array_key_exists('data-transfer-code-coverage-id', $_COOKIE)) {
    $codeCoverageId = $_COOKIE['data-transfer-code-coverage-id'];
}


if (!empty($codeCoverageId)) {
    $filter = new Filter;

    # Included files and directories
    $filter->includeFile(__DIR__ . '/../../DataTransfer.php');

    $filter->includeFiles(glob(__DIR__ . '/../../classes/*.php'));

    $filter->includeFiles(glob(__DIR__ . '/../../web/*.php'));

    # This doesn't work:
    # $filter->includeFiles(__DIR__ . '/../../../../../redcap/redcap_v15.0.22/DataEntry/index.php');

    # Excluded files
    # $filter->excludeFile(__DIR__.'/../../web/test.php');


    $selector = new Selector;

    $coverage = new CodeCoverage($selector->forLineCoverage($filter), $filter);

    $coverage->start($codeCoverageId);
}
