<?php

class nsexport_ziplib implements nsexport_compressor {

    var $plugin = null;

    function setup(&$plugin) {
    }

    function compress($sourceFolder, $destinationFile) {
        global $conf;
        chdir($sourceFolder);
        $zip  = $conf['plugin']['nsexport']['packer____ziphtml____zip'];
        $comp = $conf['plugin']['nsexport']['packer____ziphtml____compress'];
        $cmd  = "$zip -q -$comp -r -u $destinationFile .";
        system($cmd);
    }
}
