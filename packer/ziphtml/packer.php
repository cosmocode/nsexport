<?php
require_once DOKU_PLUGIN.'nsexport/packer/ziphtml/compressor.php';
require_once DOKU_PLUGIN.'nsexport/packer/ziphtml/compress/ziplib.php';
require_once DOKU_PLUGIN.'nsexport/packer/ziphtml/renderer.php';
require_once DOKU_PLUGIN.'nsexport/packer/packer.php';

class plugin_nsexport_packer_ziphtml extends plugin_nsexport_packer {
    public $ext = 'zip';

    public function init_packing($pages) {
        global $conf;
        // early check if the zip executable is available
        $packer = $conf['plugin']['nsexport']['packer_ziphtml_zip'];
        if (!file_exists($packer) || !is_file($packer)) {
            return false;
        }

        // prepare some basic settings
        $this->tmp  = io_mktmpdir();
        if ($this->tmp === false) {
            // no tmpdir
            return false;
        }

        $this->media = array();

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

        return parent::init_packing($pages);
    }

    public function pack_page($ID_PAGE) {
        global $conf;
        global $lang;
        global $ID;

        $ID = $ID_PAGE;

        // create relative path to top directory
        $deep = substr_count($ID,':');
        $ref  = '';
        for($i=0; $i<$deep; $i++) $ref .= '../';

        // create the output
        $this->Renderer = new renderer_plugin_nsexport_xhtml;

        $this->Renderer->smileys = getSmileys();
        $this->Renderer->entities = getEntities();
        $this->Renderer->acronyms = getAcronyms();
        $this->Renderer->interwiki = getInterwiki();

        $instructions = p_cached_instructions(wikiFN($ID,''),false,$ID);
        foreach ( $instructions as $instruction ) {
            // Execute the callback against the Renderer
            call_user_func_array(array(&$this->Renderer, $instruction[0]),
                                 $instruction[1]);
        }
        $html = $this->Renderer->doc;

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
        $this->media = array_merge($this->media, $this->Renderer->_media);
    }

    public function finish_packing() {
        global $conf;

        // now embed the media
        $this->media = array_map('cleanID',$this->media);
        $this->media = array_unique($this->media);
        foreach($this->media as $id) {
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
        rename($to, $this->result_filename());
    }

    /**
     * add a single file.
     *
     * @param string $filename     filename to store
     * @param string $content      the file content
     */
    public function _addFile($filename, $content) {
        $filename = $this->tmp . "/$filename";
        io_makeFileDir($filename);
        file_put_contents($filename , $content);
    }

    /**
     * add a whole dir with subdirs.
     */
    public function recursive_add($base, $dir='') {
        $fh = @opendir("$base/$dir");
        if(!$fh) return;
        while(false !== ($file = readdir($fh))) {
            @set_time_limit(30);
            if($file === '..' || $file[0] === '.') continue;
            if(is_dir("$base/$dir/$file")) {
                $this->recursive_add($base,"$dir/$file");
            }else {
                $this->_addFile("$dir/$file",io_readFile("$base/$dir/$file",false));
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
    public function rmdirr($dirname) {
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

}
