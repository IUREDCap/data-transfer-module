<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

use IU\PHPCap\RedCapProject;

/**
 * Class for representing a status of a field mapping.
 *
 *  TODO
 */
class FieldMappingStatus
{
    public const OK         = 'ok';
    public const INCOMPLETE = 'incomplete';
    public const ERROR      = 'error';

    private $status;

    private $errors;

    public function __construct()
    {
        $this->status  = self::OK;

        $this->errors = [];
    }


    /*
     * @param sring $status the status being merged.
     */
    public function mergeStatus($status)
    {
        # Incomplete overrides error - don'r report errors for incomplete rules?????????????????????????
        if ($status === self::INCOMPLETE) {
            $this->status = self::INCOMPLETE;
        } elseif ($status === self::ERROR && $this->status !== self::INCOMPLETE) {
            $this->status = self::ERROR;
        }
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function isOk()
    {
        return $this->status === self::OK;
    }

    public function isIncomplete()
    {
        return $this->status === self::INCOMPLETE;
    }

    public function isError()
    {
        return $this->status === self::ERROR;
    }

    public function addError($error)
    {
        $this->errors[] = $error;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
