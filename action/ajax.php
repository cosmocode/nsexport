<?php

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/html.php');
require_once(DOKU_INC.'inc/io.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/template.php');
require_once(DOKU_INC.'inc/HTTPClient.php');

/**
 * this part of the plugin handles all ajax requests.
 *
 * ajax requests are
 *   - nsexport_start   start the export process @see prepare_dl()
 *   - nsexport_check
 */
class action_plugin_nsexport_ajax extends DokuWiki_Action_Plugin {

    // temporary directory
    var $tmp;

    // ID from the export zip
    var $fileid;

    function register(&$controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call');
    }

    /**
     * route ajax calls to a function
     */
    function handle_ajax_call(&$event, $param) {
        $map = array('nsexport_start' => 'prepare_dl',
                     'nsexport_check' => 'check');

        // ignore unexpected calls
        if (!isset($map[$event->data])) return;

        call_user_func(array($this, $map[$event->data]));

        // stop other event calls
        $event->preventDefault();
    }

    /**
     * check if the download is ready for download.
     * print 0 on not ready and 1 on ready
     */
    function check() {
        $fid = $_REQUEST['key'];

        if (!is_numeric($fid)) {
            echo '0';
            return;
        }
        if (!$fid) {
            echo '0';
            return;
        }
        $packer = $this->getPacker();
        if (is_null($packer) || !$packer->get_status($fid)) {
            echo '0';
            return;
        }
        echo '1';
    }

    function getPacker() {
        $packer_file = DOKU_PLUGIN . 'nsexport/packer/' . $this->getConf('usepacker') . '/packer.php';
        if (!file_exists($packer_file)) {
            return null;
        }
        require_once $packer_file;
        $packer_class = 'plugin_nsexport_packer_' . $this->getConf('usepacker');
        $packer = new $packer_class;
        return $packer;
    }

    /**
     * start the download creating process.
     *
     * echos a unique id to check back to the client, build the export
     */
    function prepare_dl() {
        global $USERINFO;
        global $ID;
        global $conf;

        // requested namespaces
        $pages = $_REQUEST['export'];

        // turn off error reporting - we don't want any error messages in the output.
//        error_reporting(0);

        // try to ignore user abort.
        ignore_user_abort(true);

        // infinite time limit.
        set_time_limit(0);

        $packer = $this->getPacker();
        if (is_null($packer)) {
            return false;
        }
        $packer->start_packing($pages);
    }
}
