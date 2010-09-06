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

    function register(&$controller) {
        $controller->register_hook('TPL_ACT_UNKNOWN','BEFORE',  $this, 'nsexport');
        $controller->register_hook('ACTION_ACT_PREPROCESS','BEFORE',  $this, 'act');
    }

    function act(&$event , $param) {
        if ($event->data != 'nsexport') return false;
        $event->preventDefault();
        $this->run = true;
    }

    function nsexport(&$event, $param) {
        if (!$this->run) return;

        // stops default action handler
        $event->preventDefault();

        if ($this->getConf('autoexport')){
            $id = 'id="nsexport__auto"';
            echo $this->locale_xhtml('autointro');
            echo '<p>' .$this->getLang('autointronext'). '</p>';
            echo '<p>' .$this->getLang('pleasewait'). '<img src="'.DOKU_BASE.'lib/images/throbber.gif" /></p>';
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
        $pages = array_reverse($pages);
        echo '<form class="plugin_nsexport__form"
                    action="'.DOKU_BASE.'lib/plugins/nsexport/export.php"
                    method="post" '.$id.'>';
        echo '<p><input type="submit" id="do__export" class="button" value="'.$this->getLang('btn_export').'" />';
        echo $this->getLang('inns');
        $ns = getNS($ID);
        if(!$ns) $ns = '*';
        echo '<strong>'.$ns.'</strong>';
        echo '</p>';
        echo '<ul>';
        $num = 0;
        $ns = getNS($ID);
        foreach($pages as $page){
            $id = cleanID($ns . ':' .$page['id']);
            echo '<li><div class="li"><input type="checkbox" name="export[]" '
               . 'id="page__'.++$num.'" value="'.hsc($id).'" '
               . 'checked="checked" />&nbsp;<label for="page__'.$num.'">'
               .  hsc($id) . '</label></div></li>';
        }
        echo '</ul>';
        echo '</form>';
    }
}
