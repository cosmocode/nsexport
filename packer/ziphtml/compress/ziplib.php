<?php

class nsexport_ziplib implements nsexport_compressor {

    public $plugin = null;

    public function setup(&$plugin) {
    }

    public function compress($sourceFolder, $destinationFile) {
        global $conf;
        chdir($sourceFolder);
        $zip  = $conf['plugin']['nsexport']['packer_ziphtml_zip'];
        $comp = $conf['plugin']['nsexport']['packer_ziphtml_compress'];
        $cmd  = "$zip -q -$comp -r -u $destinationFile .";
        system($cmd);
    }
}
