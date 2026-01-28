<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer\WebTests;

/**
 * Test Configuration class. Instances of this class are created
 * using a .ini configuration file.
 */
class TestConfig
{
    private $properties;

    private $test;
    private $redCap;
    private $admin;
    private $user;
    private $user2;
    private $projects;

    /**
     * @param string $file path to file containing test configuration.
     */
    public function __construct($file)
    {
        $this->projects = array();

        $processSections = true;
        $properties = parse_ini_file($file, $processSections);

        foreach ($properties as $name => $value) {
            $matches = array();
            if ($name === 'test') {
                $this->test = $value;
            } elseif ($name === 'redcap') {
                $this->redCap = $value;
            } elseif ($name === 'admin') {
                $this->admin = $value;
            } elseif ($name === 'user') {
                $this->user = $value;
            } elseif ($name === 'user2') {
                $this->user2 = $value;
            } elseif (preg_match('/^project_(.*)$/', $name, $matches) === 1) {
                $projectName = $matches[1];
                $this->projects[$projectName] = $value;
            }
        }

        $this->properties = $properties;
    }

    public function getProperty($section, $name)
    {
        $value = null;
        if (!empty($this->properties) && array_key_exists($section, $this->properties)) {
            if (array_key_exists($name, $this->properties[$section])) {
                $value = $this->properties[$section][$name];
            }
        }
        return $value;
    }

    public function getTest()
    {
        return $this->test;
    }

    public function getDownloadDir()
    {
        return $this->test['download_dir'];
    }

    public function getRedCap()
    {
        return $this->redCap;
    }

    public function getBaseUrl()
    {
        return $this->redCap['base_url'];
    }

    public function getApiUrl()
    {
        return $this->redCap['api_url'];
    }

    public function getAdmin()
    {
        return $this->admin;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getUserUsername()
    {
        return $this->user['username'];
    }

    public function getUserPassword()
    {
        return $this->user['password'];
    }

    public function getUser2()
    {
        return $this->user2;
    }

    public function getUser2Username()
    {
        return $this->user2['username'];
    }

    public function getUser2Password()
    {
        return $this->user2['password'];
    }

    public function getProjects()
    {
        return $this->projects;
    }

    public function getProject($name)
    {
        return $this->projects[$name];
    }

    public function getProjectTitle($name)
    {
        return $this->projects[$name]['title'];
    }

    public function getProjectID($name)
    {
        return $this->projects[$name]['pid'];
    }

    public function getProjectApiToken($name)
    {
        return $this->projects[$name]['api_token'];
    }

    public function setProject($name, $project)
    {
        $this->etlConfigs[$name] = $project;
    }
}
