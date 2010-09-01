<?php
if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/auth/basic.class.php'); //strange behavior at evobus
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/template.php');
require_once(DOKU_INC.'inc/common.php');
session_write_close();

require_once(DOKU_INC.'inc/pluginutils.php');

$plugin = plugin_load('action','nsexport_ajax');
$packer = $plugin->getPacker();
if (!is_null($packer)) $packer->get_pack($_REQUEST['key']);
