<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

/** @var \IU\DataTransfer\DataTransfer $module */

#---------------------------------------------
# Check that the user has access permission
#---------------------------------------------
$module->checkAdminPagePermission();


require_once __DIR__ . '/../../vendor/autoload.php';

use IU\DataTransfer\Version;

use IU\DataTransfer\AdminConfig;
use IU\DataTransfer\Filter;
use IU\DataTransfer\DataTransfer;

$selfUrl   = $module->getUrl(DataTransfer::ADMIN_INFO_PAGE);

$configUrl     = $module->getUrl(DataTransfer::ADMIN_HOME_PAGE);

$cronDetailUrl = $module->getUrl(DataTransfer::SCHEDULE_DETAIL_PAGE);

$adminConfig = $module->getAdminConfig();

?>

<?php #require_once APP_PATH_DOCROOT . 'ControlCenter/header.php'; ?>

<?php
#---------------------------------------------
# Include REDCap's control center page header
#---------------------------------------------
ob_start();
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$buffer = ob_get_clean();
$cssFile = $module->getUrl('resources/data-transfer.css');
$link = '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" media="all">';
$buffer = str_replace('</head>', "    " . $link . "\n</head>", $buffer);
echo $buffer;
?>

<h4><img style="margin-right: 7px;" src="<?php echo APP_PATH_IMAGES ?>table_gear.png" alt="">Data Transfer Admin</h4>


<?php

$module->renderAdminPageContentHeader($selfUrl, $error, $warning, $success);

?>

<div style="float: right;">
Version <?php echo IU\DataTransfer\Version::RELEASE_NUMBER; ?> &nbsp;
<!-- Fix this after code moved to (non-Indiana University) GitHub:
(<a href="https://github.iu.edu/ABITC/data-transfer-module/releases" target="_blank">Release History</a>)
-->
</div>

<div style="clear: both;"></div>


<h5 style="font-weight: bold;">Overview</h5>

<p>
The Data Transfer external module transfers data between REDCap projects. Users create
data transfer configurations in REDCap projects that export data from or import data to the 
project containing the configuration. Data can be exported to or imported from a remote
REDCap project using an API token for REDCap's API.
</p>

<p>
Data transfers can be activated in the following ways:
    <ul>
        <li><b>Manual Transfer.</b></li>
        <li><b>On Form Save Transfer.</b>
        Users can configure transfers so that data is transferred from a form when it is saved.</li>
        <li><b>Scheduled Transfer.</b> Users can set up a schedule for a data transfer that will
        run the transfer recurringly on the days and times specified.</li>
    </ul>
</p>

<hr />


<h5 style="font-weight: bold;">Admin Pages</h5>

The Data Transfer external module has the following admin pages:
<ul>
    <li><a href="<?php echo $configUrl;?>" style="font-weight: bold;">Config</a>
        - Admin Data Transfer configuration with information on the number
          and time of scheduled data transfers.
    </li>
    <li><a href="<?php echo $cronDetailUrl;?>" style="font-weight: bold;">Schedule Detail</a>
    - Detailed information on scheduled data transfers for a specific day and hour
      that will be processed by the data transfer cron process.
    </li>
</ul>    

<?php require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php'; ?>
