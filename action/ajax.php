<?php

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_PLUGIN.'nsexport/compressor.php');
require_once(DOKU_PLUGIN.'nsexport/compress/ziplib.php');

require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/html.php');
require_once(DOKU_INC.'inc/io.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/template.php');
require_once(DOKU_INC.'inc/HTTPClient.php');

/**
 * this part of the plugin handles all ajax requests.
 *
 * ajax requests are
 *   - nsexport_start   start the export process @see prepare_dl()
 *   - nsexport_check
 */
class action_plugin_nsexport_ajax extends DokuWiki_Action_Plugin {

    // temporary directory
    var $tmp;

    // ID from the export zip
    var $fileid;

    function register(&$controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call');
    }

    /**
     * route ajax calls to a function
     */
    function handle_ajax_call(&$event, $param) {

        // ignore unexpected calls
        if ( !in_array( $event->data, array('nsexport_start', 'nsexport_check') ) ) return;

        // stop other event calls
        $event->preventDefault();

        if ($event->data === 'nsexport_start') {
            $this->prepare_dl();
        } elseif ($event->data === 'nsexport_check') {
            $this->check();
        }

    }

    /**
     * check if the download is ready for download.
     * print 0 on not ready and 1 on ready
     */
    function check() {
        global $conf;
        $fid = $_REQUEST['key'];

        if (!is_numeric($fid)) {
            echo '0';
            return;
        }
        if (!$fid) {
            echo '0';
            return;
        }
        if (!is_file($conf['tmpdir'] . '/offline-' . $fid . '.zip')) {
            echo '0';
            return;
        }
        echo '1';
    }

    /**
     * start the download creating process.
     *
     * echos a unique id to check back to the client, build the export
     */
    function prepare_dl() {
        global $USERINFO;
        global $ID;
        global $conf;

        // requested namespaces
        $pages = $_REQUEST['export'];

        // turn off error reporting - we don't want any error messages in the output.
//        error_reporting(0);

        // try to ignore user abort.
        ignore_user_abort(true);

        // infinite time limit.
        set_time_limit(0);

        // check if the zip executable is availible
        $packer = $this->getConf('zip');
        if (!file_exists($packer) || !is_file($packer)) {
            return;
        }

        // prepare some basic settings
        $media = array();
        $tmpdir  = io_mktmpdir();
        if ($tmpdir === false) {
            // no tmpdir
            return;
        }
        $this->fileid = time().rand(0,99999);

        // return name to the client
        echo $this->fileid;
        flush();

        $this->tmp = $tmpdir;

        // begin data collecting
        // add CSS
        $http  = new DokuHTTPClient();
        $css   = $http->get(DOKU_URL.'lib/exe/css.php?s=all&t='.$conf['template']);
        $this->_addFile('all.css',$css);
        $css   = $http->get(DOKU_URL.'lib/exe/css.php?t='.$conf['template']);
        $this->_addFile('screen.css',$css);
        $css   = $http->get(DOKU_URL.'lib/exe/css.php?s=print&t='.$conf['template']);
        $this->_addFile('print.css',$css);
        $css   = io_readFile(dirname(__FILE__).'/export.css',false);
        $this->_addFile('export.css',$css);

        unset($html);

        foreach($pages as $ID) {
            if( auth_quickaclcheck($ID) < AUTH_READ ) continue;
            @set_time_limit(30);

            // create relative path to top directory
            $deep = substr_count($ID,':');
            $ref  = '';
            for($i=0; $i<$deep; $i++) $ref .= '../';

            // create the output
            $html = p_cached_output(wikiFN($ID,''), 'nsexport_xhtml');

            $output  = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"'.DOKU_LF;
            $output .= ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'.DOKU_LF;
            $output .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="'.$conf['lang'].'"'.DOKU_LF;
            $output .= ' lang="'.$conf['lang'].'" dir="'.$lang['direction'].'">' . DOKU_LF;
            $output .= '<head>'.DOKU_LF;
            $output .= '  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'.DOKU_LF;
            $output .= '  <title>'.$ID.'</title>'.DOKU_LF;
            $output .= '  <link rel="stylesheet" media="all" type="text/css" href="'.$ref.'all.css" />'.DOKU_LF;
            $output .= '  <link rel="stylesheet" media="screen" type="text/css" href="'.$ref.'screen.css" />'.DOKU_LF;
            $output .= '  <link rel="stylesheet" media="print" type="text/css" href="'.$ref.'print.css" />'.DOKU_LF;
            $output .= '  <link rel="stylesheet" media="all" type="text/css" href="'.$ref.'export.css" />'.DOKU_LF;
            $output .= '</head>'.DOKU_LF;
            $output .= '<body>'.DOKU_LF;
            $output .= '<div class="dokuwiki export">' . DOKU_LF;
            $output .= tpl_toc(true);
            $output .= $html;
            $output .= '</div>';
            $output .= '</body>'.DOKU_LF;
            $output .= '</html>'.DOKU_LF;

            $this->_addFile(str_replace(':','/',$ID).'.html',$output);
            $media = array_merge($media,(array) p_get_metadata($ID,'plugin_nsexport'));
        }

        // now embed the media
        $media = array_map('cleanID',$media);
        $media = array_unique($media);
        foreach($media as $id) {
            if( auth_quickaclcheck($id) < AUTH_READ ) continue;
            @set_time_limit(30);
            $this->_addFile('_media/'.str_replace(':','/',$id),io_readFile(mediaFN($id),false));
        }

        // add the merge directory contents
        $this->recursive_add(dirname(__FILE__).'/merge');

        // finished data collecting
        $to = $conf['tmpdir'] . '/wrk-' . $this->fileid . '.zip';

        // append wiki export
        $zipper = new nsexport_ziplib();
        $zipper->setup($this);
        $zipper->compress($this->tmp, $to);

        // cleanup
        $this->rmdirr($this->tmp);

        // rename so ajax can find it
        rename($to,$conf['tmpdir'] . '/offline-' . $this->fileid . '.zip');
    }

    /**
     * add a single file.
     *
     * @param $filename     filename to store
     * @param $content      the file content
     */
    function _addFile($filename, $content) {
        $filename = $this->tmp . "/$filename";
        io_makeFileDir($filename);
        file_put_contents($filename , $content);
    }

    /**
     * add a whole dir with subdirs.
     */
    function recursive_add($base,$dir='') {
        $fh = @opendir("$base/$dir");
        if(!$fh) return;
        while(false !== ($file = readdir($fh))) {
            @set_time_limit(30);
            if($file == '..' || $file[0] == '.') continue;
            if(is_dir("$base/$dir/$file")) {
                $this->recursive_add($base,"$dir/$file");
            }else {
                $this->_addFile("$dir/$file",io_readFile(ltrim("$base/$dir/$file",'/'),false));
            }
        }
        closedir($fh);
    }

    /**
     * Delete a file, or a folder and its contents (recursive algorithm)
     *
     * @author      Aidan Lister <aidan@php.net>
     * @version     1.0.3
     * @link        http://aidanlister.com/repos/v/function.rmdirr.php
     * @param       string   $dirname    Directory to delete
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    function rmdirr($dirname) {
        // Sanity check
        if (!file_exists($dirname)) {
            return false;
        }

        // Simple delete for a file
        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }

        // Loop through the folder
        $dir = dir($dirname);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Recurse
            $this->rmdirr($dirname . DIRECTORY_SEPARATOR . $entry);
        }

        // Clean up
        $dir->close();
        return rmdir($dirname);
    }
}
