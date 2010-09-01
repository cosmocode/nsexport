<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/html.php');
require_once(DOKU_INC.'inc/io.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/template.php');

/**
 * Namespace export : Action component to send compressed ZIP file.
 */
class action_plugin_nsexport_export extends DokuWiki_Action_Plugin {

    var $run = false;
    var $tmp;

    function getInfo(){
        return confToHash(dirname(__FILE__).'/../info.txt');
    }

    function register(&$controller) {
        $controller->register_hook('TPL_ACT_UNKNOWN','BEFORE',  $this, 'nsexport');
        $controller->register_hook('ACTION_ACT_PREPROCESS','BEFORE',  $this, 'act');
    }

    function nsexport(&$event, $param) {
        if (!$this->run) return;

        // stops default action handler
        $event->stopPropagation();
        $event->preventDefault();

        $this->show();

        return false;
    }

    function act(&$event , $param) {
        if ($event->data != 'nsexport') return false;
        $event->stopPropagation();
        $event->preventDefault();
        $this->run = true;
        return true;
    }

    function show() {
        if ($this->getConf('autoexport')){
            echo $this->locale_xhtml('autointro');
            $id = 'id="nsexport__auto"';
            echo '<p>' .$this->getLang('autointronext'). '</p>';
            echo '<p>' .$this->getLang('pleasewait'). '<img src="'.DOKU_BASE.'lib/images/throbber.gif" />';
        }else{
            $id = '';
            echo $this->locale_xhtml('intro');
        }
        $this->_listPages($id);
    }

    /**
     * Create a list of pages about to be exported within a form
     * to start the export
     */
    function _listPages($id){
        global $ID;

        $pages = array();
        $base  = dirname(wikiFN($ID));
        search($pages,$base,'search_allpages',array());
        echo '<form action="'.DOKU_BASE.'lib/plugins/nsexport/export.php" method="post" '.$id.'>';
        echo '<p><input type="submit" id="do__export" class="button" value="'.$this->getLang('btn_export').'" />';
        echo $this->getLang('inns');
        $ns = getNS($ID);
        if(!$ns) $ns = '*';
        echo '<b>'.$ns.'</b>';
        echo '</p>';
        echo '<ul>';
        $num = 0;
        $ns = getNS($ID);
        foreach($pages as $page){
            $id = cleanID("$ns:".$page['id']);
            $num++;
            echo '<li><div class="li"><input type="checkbox" name="export[]" '
               . 'id="page__'.$num.'" value="'.hsc($id).'" '
               . 'checked="checked" class="edit" />&nbsp;<label for="page__'.$num.'">'
               .  hsc($id) . '</label></div></li>';
        }
        echo '</ul>';
        echo '</form>';
    }

    /**
     * Send the ZIP to the client if it's present.
     */
    function _export_html(){
        global $conf;
        @ignore_user_abort(true);
        $fid = $_REQUEST['zip'];

        if (!is_numeric($fid)) {
            return;
        }

        $filename = $conf['tmpdir'].'/offline-'. $fid .'.zip';
        if (!is_file($filename)) {
            send_redirect(DOKU_BASE . 'doku.php');
        }

    	$zfn = preg_replace('/^([a-z]{1}):/i', '$1:\\', $filename);
        $efn = preg_replace('/^([a-z]{1}):/i', '$1:\\', $this->tmp.'/');

        // send to browser
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="export.zip"');
        header('Expires: 0');
        header('Content-Length: ' . filesize($filename));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Transfer-Encoding: binary');
        header('Pragma: public');
        flush();
        @set_time_limit(0);

        $fh = @fopen($filename, 'rb');
        while (!feof($fh)) {
            echo fread($fh, 1024);
        }
        fclose($fh);
        @unlink($filename);
    }
}
