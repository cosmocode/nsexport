<?php
abstract class plugin_nsexport_packer extends DokuWiki_Plugin {

    public function start_packing($pages) {

        if (!$this->init_packing($pages)) {
            return;
        }

        $this->fileid = time().rand(0,99999);

        // return name to the client
        echo $this->fileid;
        flush();

        foreach($pages as $ID) {
            if( auth_quickaclcheck($ID) < AUTH_READ ) continue;
            @set_time_limit(30);
            $this->pack_page($ID);
        }

        $this->finish_packing();
    }

    public function init_packing($pages) {
        return true;
    }

    public function pack_page($ID) {

    }

    public function finish_packing() {

    }

    protected function check_key($key) {
        return is_numeric($key);
    }

    protected function result_filename() {
        global $conf;
        return $conf['tmpdir'] . '/offline-' . $this->fileid . '.' . $this->ext;
    }

    public function get_status($key) {
        if (!$this->check_key($key)) {
            return;
        }
        $this->fileid = $key;
        return is_file($this->result_filename());
    }

    public function get_pack($key) {
        if (!$this->check_key($key)) {
            return;
        }

        @ignore_user_abort(true);

        $this->fileid = $key;
        $filename = $this->result_filename();
        if (!is_file($filename)) {
            send_redirect(DOKU_BASE . 'doku.php');
        }

        // send to browser
        $mime = mimetype('.' . $this->ext, false);
        header('Content-Type: ' . $mime[1]);
        header('Content-Disposition: attachment; filename="export.' . $this->ext . '"');
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
