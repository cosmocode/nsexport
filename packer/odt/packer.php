<?php
class plugin_nsexport_packer_odt extends DokuWiki_Plugin {

    function start_packing($pages) {
        global $conf;
        $this->fileid = time().rand(0,99999);

        // return name to the client
        echo $this->fileid;
        flush();

        $Renderer = p_get_renderer('odt');
        if (is_null($Renderer)) {
            return;
        }
        $Renderer->autostyles['Text_20_body__end_pagebreak'] = '
            <style:style style:name="Text_20_body__end_pagebreak"
                         style:family="paragraph"
                         style:parent-style-name="Text_20_body">
                <style:paragraph-properties fo:break-after="page"/>
            </style:style>';

        $Renderer->document_start();
        foreach($pages as $ID) {
            if( auth_quickaclcheck($ID) < AUTH_READ ) continue;

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
                    call_user_func_array(array(&$Renderer, $instruction[0]),$instruction[1]);
                }
            }
        }
        $Renderer->document_end();

        file_put_contents($conf['tmpdir'] . '/offline-' . $this->fileid . '.odt', $Renderer->doc);

    }

    function get_status($key) {
        global $conf;
        return is_file($conf['tmpdir'] . '/offline-' . $key . '.odt');
    }

    function get_pack($key) {
        global $conf;
        @ignore_user_abort(true);

        if (!is_numeric($key)) {
            return;
        }

        $filename = $conf['tmpdir'].'/offline-'. $key .'.odt';
        if (!is_file($filename)) {
            send_redirect(DOKU_BASE . 'doku.php');
        }

        // send to browser
        header('Content-Type: application/vnd.oasis.opendocument.text');
        header('Content-Disposition: attachment; filename="export.odt"');
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
