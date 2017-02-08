<?php

/**
 * USING
 *
 * zip.php <key> <user> <serialized pages> <serialized userinfo[grps]>
 */


if ('cli' !== php_sapi_name()) die();

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
define('NOSESSION',1);
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/parserutils.php');
require_once(DOKU_INC.'inc/cliopts.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/io.php');

$args = Doku_Cli_Opts::readPHPArgv();

if (count($args) !== 5) die(-1);

class nsexport_zip {

    public $tmp;

    public function _addFile($filename, $content) {
        $filename = $this->tmp . "/$filename";
        io_makeFileDir($filename);
        file_put_contents($filename , $content);
    }

    public function recursive_add($base, $dir=''){
        $fh = @opendir("$base/$dir");
        if(!$fh) return;
        while(false !== ($file = readdir($fh))) {
            @set_time_limit(30);
            if($file === '..' || $file[0] === '.') continue;
            if(is_dir("$base/$dir/$file")){
                $this->recursive_add($base,"$dir/$file");
            }else{
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
    public function rmdirr($dirname)
    {
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
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            // Recurse
            $this->rmdirr($dirname . DIRECTORY_SEPARATOR . $entry);
        }

        // Clean up
        $dir->close();
        return rmdir($dirname);
    }

    public function start($key, $user, $pages, $groups) {
        global $conf;
        global $lang;

        $pages = unserialize($pages);

        require_once(DOKU_INC.'inc/HTTPClient.php');
        error_reporting(0);

        // check if the 7ip executable is availible
        // FIXME
        $packer = $this->getConf('packer_ziphtml_zip');
        if (!file_exists($packer) || !is_file($packer))
        {
            return;
        }

        $media = array();
        $tmpdir  = io_mktmpdir();
        if ($tmpdir === false) {
            // no tmpdir
            return;
        }
        $this->tmp = $tmpdir;


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

        foreach($pages as $ID){
            if( auth_aclcheck($ID, $user, $groups) < AUTH_READ ) continue;
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
        foreach($media as $id){
            if( auth_quickaclcheck($id) < AUTH_READ ) continue;
            @set_time_limit(30);
            $this->_addFile('_media/'.str_replace(':','/',$id),io_readFile(mediaFN($id),false));
        }


        // add the merge directory contents
        $this->recursive_add(dirname(__FILE__).'/merge');

        echo basename($this->tmp);

    }

    /**
     * Do the action
     */
    public function _export_html($pages){
        global $conf;
        @ignore_user_abort(true);
        $filename = $conf['tmpdir'].'/offline-'.time().rand(0,99999).'.zip';
        $this->zip = $filename;
        $zfn = preg_replace('/^([a-z]{1}):/i','$1:\\',$filename);
        $efn = preg_replace('/^([a-z]{1}):/i','$1:\\',$this->tmp.'/');

        // send to browser
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="export.zip"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Transfer-Encoding: binary');
        header('Pragma: public');
        flush();
        @set_time_limit(0);

        chdir($efn);
        $zip = $this->getConf('packer_ziphtml_zip');
        $comp = $this->getConf('packer_ziphtml_compress');
        $cmd = "$zip -q -$comp -r - .";
        system($cmd);

        // cleanup
        $this->rmdirr($this->tmp);
    }
}
