<?php

/**
 * A Compressor zip up a source folder in one file
 */
interface nsexport_compressor {
    public function setup(&$plugin);
    public function compress($sourceFolder, $destinationFile);
}
