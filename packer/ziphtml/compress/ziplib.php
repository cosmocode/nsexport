<?php

class nsexport_ziplib implements nsexport_compressor {

    var $plugin = null;

    function setup(&$plugin) {
    }

    function compress($sourceFolder, $destinationFile) {
        global $conf;
        chdir($sourceFolder);
        $zip  = $conf['plugin']['nsexport']['packer']['ziphtml']['zip'];
        $comp = $conf['plugin']['nsexport']['packer']['ziphtml']['compress'];
        $cmd  = "$zip -q -$comp -r -u $destinationFile .";
        system($cmd);
    }
}
