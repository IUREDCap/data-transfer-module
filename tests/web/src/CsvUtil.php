<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer\WebTests;

/**
 * Utility class for CSV
 */
class CsvUtil
{
    /**
     * Converts the specified CSV file to a 2-dimensional array.
     *
     * @param string $csvfile the path to the CSV file to convert.
     */
    public static function csvFileToArray($csvFile)
    {
        $values = [];
        $file = fopen($csvFile, 'r');

        $length = null;
        $separator = ",";
        $enclosure = "\"";
        $escape = "\\";

        while (($line = fgetcsv($file, $length, $separator, $enclosure, $escape)) !== FALSE) {
            $values[] = $line;
        }
        fclose($file);

        return $values;
    }
}
