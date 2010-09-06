<?php
require_once DOKU_PLUGIN.'nsexport/packer/packer.php';

class plugin_nsexport_packer_odt extends plugin_nsexport_packer {
    protected $ext = 'odt';

    function init_packing($pages) {
        $this->Renderer = p_get_renderer('odt');
        if (is_null($this->Renderer)) {
            return false;
        }
        $this->Renderer->autostyles['Text_20_body__end_pagebreak'] = '
            <style:style style:name="Text_20_body__end_pagebreak"
                         style:family="paragraph"
                         style:parent-style-name="Text_20_body">
                <style:paragraph-properties fo:break-after="page"/>
            </style:style>';

        $this->Renderer->document_start();
        return true;
    }

    function pack_page($ID) {
        $instructions = p_cached_instructions(wikiFN($ID,''),false,$ID);
        for ($i = count($instructions) - 1 ; $i >= 0 ; --$i) {
            if ($instructions[$i][0] === 'p_open') {
                $instructions[$i][1] = array('Text_20_body__end_pagebreak');
                break;
            }
        }
        foreach ( $instructions as $instruction ) {
            // Execute the callback against the Renderer
            if (!in_array($instruction[0], array('document_start', 'document_end'))) {
                call_user_func_array(array(&$this->Renderer, $instruction[0]),
                                     $instruction[1]);
            }
        }
    }

    function finish_packing() {
        $this->Renderer->document_end();

        global $conf;
        file_put_contents($this->result_filename(), $this->Renderer->doc);
    }
}
