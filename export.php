<?php
if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
require_once(DOKU_INC.'inc/init.php');
session_write_close();

/** @var action_plugin_nsexport_ajax $plugin */
$plugin = plugin_load('action','nsexport_ajax');
$packer = $plugin->getPacker();
if ($packer !== null) {
    $packer->get_pack($_REQUEST['key']);
}
