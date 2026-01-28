<?php

#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

namespace IU\DataTransfer;

class AdminConfig implements \JsonSerializable
{
    public const KEY = 'admin-config';
    public const DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public const ALLOW_ON_DEMAND    = 'allowOnDemand';
    public const ALLOW_CRON         = 'allowCron';
    public const ALLOWED_CRON_TIMES = 'allowedCronTimes';

    /** @var boolean indicates if SSL verification should be done for local REDCap */
    private $sslVerify;

    private $allowOnDemand;  // Allow the ETL process to be run on demand

    private $allowCron;
    private $allowedCronTimes;


    public const MAX_SCHEDULE_HOURS = 'maxScheduleHours';
    /** @var integer the maxium number of hours that transfers can be scheduled per day for a single configuration */
    private $maxScheduleHours;


    public function __construct()
    {
        $this->allowOnDemand = false;
        $this->allowCron     = true;

        # Default to 24 max schedule hours, i.e., data ttansfers can be scheduled every hour of the day
        $this->maxScheduleHours = 24;

        $this->allowedCronTimes = array();
        foreach (range(0, 6) as $day) {
            $this->allowedCronTimes[$day] = array();
            foreach (range(0, 23) as $hour) {
                $this->allowedCronTimes[$day][$hour] = true;
                #if ($day === 0 || $day === 6 || $hour < 8 || $hour > 17) {
                #    $this->allowedCronTimes[$day][$hour] = true;
                #} else {
                #    $this->allowedCronTimes[$day][$hour] = false;
                #}
            }
        }

        $this->sslVerify = true;
    }


    public function fromJson($json)
    {
        if (!empty($json)) {
            $object = json_decode($json, true);
            foreach (get_object_vars($this) as $var => $value) {
                $this->$var = $object[$var];
            }
        }
    }

    public function toJson()
    {
        $json = json_encode($this);
        return $json;
    }


    /**
     * Sets admin configuration properties from a map that uses the
     * property keys for this class.
     */
    public function set($properties)
    {
        # Set allowed cron times
        if (array_key_exists(self::ALLOWED_CRON_TIMES, $properties)) {
            $times = $properties[self::ALLOWED_CRON_TIMES];
            if (is_array($times)) {
                for ($row = 0; $row < count($this->allowedCronTimes); $row++) {
                    if (array_key_exists($row, $times)) {
                        $dayTimes = $times[$row];
                        if (is_array($dayTimes)) {
                            for ($col = 0; $col < count($this->allowedCronTimes[$row]); $col++) {
                                if (array_key_exists($col, $dayTimes)) {
                                    $this->allowedCronTimes[$row][$col] = true;
                                } else {
                                    $this->allowedCronTimes[$row][$col] = false;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (array_key_exists(self::MAX_SCHEDULE_HOURS, $properties)) {
            $value = (int) $properties[self::MAX_SCHEDULE_HOURS];
            $value = min($value, 24);  # Make sure number is no greater than 24
            $value = max($value, 1);   # Make sure number is no less than 1
            $this->maxScheduleHours = (int) $properties[self::MAX_SCHEDULE_HOURS];
        }

        # Set flag that indicates if users can run jobs on demand
        #if (array_key_exists(self::ALLOW_ON_DEMAND, $properties)) {
        #    $this->allowOnDemand = true;
        #} else {
        #    $this->allowOnDemand = false;
        #}

        # Set flag that indicates if cron (scheduled) jobs can be run by users
        #if (array_key_exists(self::ALLOW_CRON, $properties)) {
        #    $this->allowCron = true;
        #} else {
        #    $this->allowCron = false;
        #}
    }


    public function jsonSerialize(): mixed
    {
        return (object) get_object_vars($this);
    }

    /**
     * Returns the time values for cron/scheduling configuration.
     * Each value represents a 1-hour time range as follows:
     * 0 => 12:00am to 1:00am (0:00 to 1:00)
     * 1 => 1:00am to 2:00am
     * 2 => 2:00am to 3:00am
     * ...
     * 22 => 10:00pm to 11:00am (22:00 to 23:00)
     * 23 => 11:00pm to 12:00am (23:00 to 24:00)
     */
    public function getTimes()
    {
        return range(0, 23);
    }

    public function getHtmlTimeLabel($time)
    {
        $label = '';
        $startTime = $time;
        $endTime   = $time + 1;

        if ($startTime < 12) {
            $startTimeSuffix = 'am';
            if ($startTime == 0) {
                $startTime = 12;
            }
        } else {
            $startTimeSuffix = 'pm';
        }

        if ($startTime > 12) {
            $startTime -= 12;
        }

        if ($startTime < 10) {
            $startTime = "&nbsp;" . $startTime;
        }

        if ($endTime < 12 || $endTime == 24) {
            $endTimeSuffix = 'am';
        } else {
            $endTimeSuffix = 'pm';
        }

        if ($endTime > 12) {
            $endTime -= 12;
        }

        if ($endTime < 10) {
            $endTime = "&nbsp;" . $endTime;
        }

        $label = "{$startTime}{$startTimeSuffix}&nbsp;-&nbsp;{$endTime}{$endTimeSuffix}";

        return $label;
    }

    public function getTimeLabels()
    {
        $labels = array();
        $labelNumbers = range(0, 23);
        for ($i = 0; $i < count($labelNumbers); $i++) {
            $start = $i;
            $end   = $i + 1;

            $startSuffix = 'am';
            if ($start >= 12) {
                $startSuffix = 'pm';
                if ($start > 12) {
                    $start -= 12;
                }
            }
            if ($start === 0) {
                $start = 12;
            }

            $endSuffix = 'am';
            if ($end >= 12) {
                if ($end < 24) {
                    $endSuffix = 'pm';
                }

                if ($end > 12) {
                    $end -= 12;
                }
            }

            $start .= $startSuffix;

            $end .= $endSuffix;

            $labels[$i] = $start . ' - ' . $end;
        }
        return $labels;
    }

    public function getAllowOnDemand()
    {
        return $this->allowOnDemand;
    }

    public function getAllowCron()
    {
        return $this->allowCron;
    }

    public function isAllowedCronTime($day, $time)
    {
        $isAllowed = $this->allowedCronTimes[$day][$time];
        return $isAllowed;
    }

    public function getMaxScheduleHours()
    {
        return $this->maxScheduleHours;
    }
}
