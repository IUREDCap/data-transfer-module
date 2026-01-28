<?php
#-------------------------------------------------------
# Copyright (C) 2025 The Trustees of Indiana University
# SPDX-License-Identifier: BSD-3-Clause
#-------------------------------------------------------

/** @var \IU\DataTransfer $module */

require_once __DIR__ . '/../vendor/autoload.php';

use IU\DataTransfer\Filter;
use IU\DataTransfer\Help;

$module->checkUserPagePermission(USERID);

#------------------------------------------------------
# Get and process the help topic
#------------------------------------------------------
$topic = Filter::sanitizeButtonLabel($_GET['topic']);
if (empty($topic)) {
    $topic = Filter::sanitizeButtonLabel($_POST['topic']);
}

?>


<?php
require_once APP_PATH_DOCROOT . 'Config/init_global.php';



$htmlPage = new HtmlPage();
$htmlPage->PrintHeaderExt();
?>
<div style="text-align:right;float:right;">
    <img src="<?php echo APP_PATH_IMAGES . "redcap-logo.png"; ?>" alt="REDCap"/>
</div>
<div style="clear: both">

<?php

if (Help::isValidTopic($topic)) {
    echo '<h3 style="color: #286090;">' . Filter::escapeForHtml(Help::getTitle($topic)) . "</h3>\n";
    echo Help::getHelp($topic);
} else {
    echo 'No help was found for topic "' . Filter::escapeForHtml($topic) . '".';
}

?>

<style type="text/css">#footer { display: block; }</style>
<?php
$htmlPage->PrintFooterExt();
