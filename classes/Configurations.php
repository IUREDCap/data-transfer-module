<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

/**
 * Class for data transfer configuration
 */
class Configurations
{
    private $configurationMap;   # Map from configuration name to Configuration object

    public function __construct()
    {
        $this->configurationMap = array();
    }

    public function addConfiguration($name, $configuration)
    {
        if (array_key_exists($name, $this->configurationMap)) {
            throw new \Exception("Configuration \"{$name}\" already exists.");
        }

        $this->configurationMap[$name] = $configuration;
        ksort($this->configurationMap);
    }

    public function setConfiguration($name, $configuration, $username, $projectUsers, $isSuperUser)
    {
        if (!array_key_exists($name, $this->configurationMap)) {
            throw new \Exception("Configuration \"{$name}\" does not exist.");
        }

        if (!$configuration->mayBeModifiedByUser($username, $projectUsers, $isSuperUser)) {
            $message = "User \"{username}\" does not have permission to modify configuration \"{$name}\".";
            throw new \Exception($message);
        }

        $this->configurationMap[$name] = $configuration;
        ksort($this->configurationMap);
    }

    public function getConfigurationNames()
    {
        return array_keys($this->configurationMap);
    }

    /**
     * @return array that maps configuration names to Configuration objects.
     */
    public function getConfigurationMap()
    {
        ksort($this->configurationMap);
        return $this->configurationMap;
    }

    public function getConfiguration($name)
    {
        $configuration = $this->configurationMap[$name] ?? null;
        return $configuration;
    }


    public function getExportOnFormSaveConfigurations($module, $form = null, $eventId = null)
    {
        $exportOnFormSaveConfigurations = new Configurations();

        foreach ($this->configurationMap as $configName => $config) {
            if ($config->getDirection() === Configuration::DIRECTION_EXPORT) {
                if ($config->getExportOnFormSave()) {
                    # Configuration is an export config with
                    # "export on form save" set to true
                    if (!empty($form)) {
                        $sourceProject = $config->getSourceProject($module);

                        $fieldMap = $config->getFieldMapObject();
                        $fieldMap = $fieldMap->simplify($module, $config, false);

                        if ($sourceProject->isLongitudinal()) {
                            if (empty($eventId)) {
                                throw new \Exception("No event specified for form \"{$form}\".\n");
                            }

                            $uniqueEventName = $sourceProject->getUniqueEventNameFromEventId($eventId);
                            $fieldMap->filterMappings($form, $uniqueEventName);
                            if (!empty($fieldMap->getMappings())) {
                                $exportOnFormSaveConfigurations->addConfiguration($configName, $config);
                            }
                        } else {
                            # Non-longitudinal project
                            $fieldMap->filterMappings($form);

                            if (!empty($fieldMap->getMappings())) {
                                $exportOnFormSaveConfigurations->addConfiguration($configName, $config);
                            }
                        }
                    } else {
                        # No form specified, return all "export on form save" configurations
                        $exportOnFormSaveConfigurations->addConfiguration($configName, $config);
                    }
                }
            }
        }

        return $exportOnFormSaveConfigurations;
    }


    public function copyConfiguration($fromConfigName, $toConfigName, $username, $projectUsers)
    {
        if (array_key_exists($toConfigName, $this->configurationMap)) {
            $message = "Configuration \"{$fromConfigName}\" cannot be copied to new configuration"
                . " \"{$toConfigName}\", because configuration \"{$toConfigName}\" already exists.";
            throw new \Exception($message);
        }

        $fromConfig = $this->configurationMap[$fromConfigName];
        $toConfig = clone $fromConfig;

        $toConfig->setName($toConfigName);
        if ($username !== $fromConfig->getOwner()) {
            $toConfig->setOwner($username);
            $toConfig->setApiToken('');
        }

        $this->configurationMap[$toConfigName] = $toConfig;
    }

    public function renameConfiguration($fromConfigName, $toConfigName, $username, $projectUsers, $isSuperUser)
    {
        if (array_key_exists($toConfigName, $this->configurationMap)) {
            $message = "Configuration \"{$fromConfigName}\" cannot be renamed to"
                . " \"{$toConfigName}\", because configuration \"{$toConfigName}\" already exists.";
            throw new \Exception($message);
        }

        $fromConfig = $this->configurationMap[$fromConfigName];

        if (!$fromConfig->mayBeRenamedByUser($username, $projectUsers, $isSuperUser)) {
            $message = "User \"{username}\" does not have permission to rename configuration \"{$fromConfigName}\".";
            throw new \Exception($message);
        }

        $fromConfig->setName($toConfigName);
        if ($username !== $fromConfig->getOwner()) {
            $fromConfig->setOwner($username);
            $fromConfig->setApiToken('');
        }

        $this->configurationMap[$toConfigName] = $fromConfig;
        unset($this->configurationMap[$fromConfigName]);
    }

    public function deleteConfiguration($name, $username, $projectUsers, $isSuperUser)
    {
        if (array_key_exists($name, $this->configurationMap)) {
            $config = $this->configurationMap[$name];
            if (!$config->mayBeDeletedByUser($username, $projectUsers, $isSuperUser)) {
                $message = "User \"{username}\" does not have permission to delete configuration \"{$name}\".";
                throw new \Exception($message);
            }

            unset($this->configurationMap[$name]);
        }
    }


    /**
     * Converts this object to a JSON string representation.
     */
    public function toJson()
    {
        $json = "{\n";

        $indent = "  ";

        foreach ($this->configurationMap as $configName => $config) {
            $json .= $indent . "\"{$configName}\": {\n";

            $json .= $config->toJson($indent . "  ");

            if ($configName === array_key_last($this->configurationMap)) {
                $json .= $indent . "}\n";
            } else {
                $json .= $indent . "},\n";
            }
        }

        $json .= "}\n";

        return $json;
    }

    /**
     * Sets this object from a JSON string.
     */
    public function setFromJson($json)
    {
        $this->configurationMap = array();

        try {
            $configs = [];
            if (!empty($json)) {
                $associative = true;
                $depth = 24;
                $flags = JSON_THROW_ON_ERROR;
                $configs = json_decode($json, $associative, $depth, $flags);
            }
        } catch (\Exception $exception) {
            $message = "Could not decode configurations JSON: " . $exception->getMessage();
            $jsonError = json_last_error() ?? "";
            if (!empty($jsonError)) {
                $message .= ": " . $jsonError;
            }
            throw new \Exception($message, 1, $exception);
        }

        $vars = get_object_vars($this);

        if (!empty($configs)) {
            foreach ($configs as $configName => $configJsonObj) {
                $config = new Configuration();

                # error_log("New config created for {$configName}\n", 3, __DIR__ . '/../json.log');
                # error_log(print_r($configJson, true), 3, __DIR__ . '/../json.log');

                if (!empty($configJsonObj)) {
                    $config->setFromJsonObj($configJsonObj);
                    #foreach ($configJson as $var => $value) {
                        # error_log("{$var}\n", 3, __DIR__ . '/../json.log');
                        #if (property_exists($config, $var)) {
                        #    $setter = 'set' . ucfirst($var);
                        #    if (method_exists($config, $setter)) {
                        #        $config->$setter($value);
                        #    }
                        #}
                    #}
                }

                $this->addConfiguration($configName, $config);
            }
        }

        # return $obj;
        return $this;
    }
}
