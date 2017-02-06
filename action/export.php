<?php
if(!defined('DOKU_INC')) die();

/**
 * Namespace export : Action component to send compressed ZIP file.
 */
class action_plugin_nsexport_export extends DokuWiki_Action_Plugin {

    public $run = false;

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_UNKNOWN','BEFORE',  $this, 'nsexport');
        $controller->register_hook('ACTION_ACT_PREPROCESS','BEFORE',  $this, 'act');
    }

    public function act(Doku_Event $event , $param) {
        if ($event->data !== 'nsexport') {
            return;
        }
        $event->preventDefault();
        $this->run = true;
    }

    public function nsexport(Doku_Event $event, $param) {
        if (!$this->run) return;

        // stops default action handler
        $event->preventDefault();

        if ($this->getConf('autoexport')){
            $id = ' plugin_nsexport__started';
            echo $this->locale_xhtml('autointro');
        }else{
            $id = '';
            echo $this->locale_xhtml('intro');
        }
        echo '<div class="level1"><p>' .
             $this->getLang('packer___ziphtml___intro') .
             '</p></div>';
        $this->_listPages($id);
    }

    /**
     * Create a list of pages about to be exported within a form
     * to start the export
     */
    public function _listPages($id){
        global $ID;

        $pages = array();
        $base  = dirname(wikiFN($ID));
        search($pages,$base,'search_allpages',array());
        $pages = array_reverse($pages);

        echo '<form class="plugin_nsexport__form' . $id . '"
                    action="'.DOKU_BASE.'lib/plugins/nsexport/export.php"
                    method="post">';
        echo '<p><input type="submit" id="do__export" class="button" value="'.$this->getLang('btn_export').'" />';
        $ns = getNS($ID);
        if(!$ns) $ns = '*';
        printf($this->getLang('inns'), $ns);
        echo '</p>';

        echo '<ul>';
        $num = 0;
        $ns = getNS($ID). ':';
        foreach($pages as $page){
            $id = cleanID($ns . $page['id']);
            echo '<li><div class="li"><input type="checkbox" name="export[]" '
               . 'id="page__'.++$num.'" value="'.hsc($id).'" '
               . 'checked="checked" />&nbsp;<label for="page__'.$num.'">'
               .  hsc($id) . '</label></div></li>';
        }
        echo '</ul>';
        echo '</form>';
    }

    public function tpl_link($return = false) {
        global $ID;
        $caption = hsc($this->getLang('link'));
        return tpl_link(wl($ID, array('do' => 'nsexport')), $caption,
                        'class="action nsexport" rel="nofollow" ' .
                        'title="' . $caption . '"', $return);
    }
}
