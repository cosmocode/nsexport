<?php

class nsexport_ziplib implements nsexport_compressor {

    var $plugin = null;

    function setup(&$plugin) {
        $this->plugin =& $plugin;
    }

    function compress($sourceFolder, $destinationFile) {
        chdir($sourceFolder);
        $zip  = $this->plugin->getConf('zip');
        $comp = $this->plugin->getConf('compress');
        $cmd  = "$zip -q -$comp -r -u $destinationFile .";
        system($cmd);
    }
}
