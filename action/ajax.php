<?php

if(!defined('DOKU_INC')) die();

/**
 * this part of the plugin handles all ajax requests.
 *
 * ajax requests are
 *   - nsexport_start   start the export process @see prepare_dl()
 *   - nsexport_check
 */
class action_plugin_nsexport_ajax extends DokuWiki_Action_Plugin {

    // temporary directory
    public $tmp;

    // ID from the export zip
    public $fileid;

    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call');
    }

    /**
     * route ajax calls to a function
     */
    public function handle_ajax_call(Doku_Event $event, $param) {
        // we don't need this info, but we need this call to populate $conf['plugin']['nsexport']
        $this->getConf('autoexport');
        if ($event->data === 'nsexport_start') {
            $event->preventDefault();
            $this->prepare_dl();
            return;
        }

        if ($event->data === 'nsexport_check') {
            $event->preventDefault();
            $this->check();
            return;
        }
    }

    /**
     * check if the download is ready for download.
     * print 0 on not ready and 1 on ready
     */
    public function check() {
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
        if ($packer === null || !$packer->get_status($fid)) {
            echo '0';
            return;
        }
        echo '1';
    }

    /**
     * @return plugin_nsexport_packer|null
     */
    public function getPacker() {
        $packer_file = DOKU_PLUGIN . 'nsexport/packer/ziphtml/packer.php';
        if (!file_exists($packer_file)) {
            return null;
        }
        require_once $packer_file;
        $packer_class = 'plugin_nsexport_packer_ziphtml';
        $packer = new $packer_class;
        return $packer;
    }

    /**
     * start the download creating process.
     *
     * echos a unique id to check back to the client, build the export
     */
    public function prepare_dl() {
        global $INPUT;

        $pages = $INPUT->arr('pages');


        // turn off error reporting - we don't want any error messages in the output.
//        error_reporting(0);

        // try to ignore user abort.
        ignore_user_abort(true);

        // infinite time limit.
        set_time_limit(0);

        $packer = $this->getPacker();
        if ($packer === null) {
            return false;
        }
        $packer->start_packing($pages);
    }
}
