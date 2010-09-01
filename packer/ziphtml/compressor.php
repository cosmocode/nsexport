<?php

/**
 * A Compressor zip up a source folder in one file
 */
interface nsexport_compressor {
    function setup(&$plugin);
    function compress($sourceFolder, $destinationFile);
}
