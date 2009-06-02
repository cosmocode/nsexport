<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/html.php');

class action_plugin_nsexport extends DokuWiki_Action_Plugin {

    var $run = false;
    function getInfo(){
        return confToHash(dirname(__FILE__).'/info.txt');
    }

    function register(&$controller) {
        $controller->register_hook('TPL_CONTENT_DISPLAY','BEFORE',  $this, 'nsexport');
        $controller->register_hook('ACTION_ACT_PREPROCESS','BEFORE',  $this, 'act');
    }

    function nsexport(&$event, $param) {
        if (!$this->run) return;

        // stops default action handler
        $event->stopPropagation();
        $event->preventDefault();

        $this->show();

                return falsde;
    }

    function act(&$event , $param) {
        if ($event->data != 'nsexport') return false;
        $event->stopPropagation();
        $event->preventDefault();
        $this->run = true;
        return true;
    }

    function show() {
        $this->_listPages();
    }

    /**
     * Create a list of pages about to be exported within a form
     * to start the export
     */
    function _listPages(){
        global $ID;

        $pages = array();
        $base  = dirname(wikiFN($ID));
        search($pages,$base,'search_allpages',array());
        echo '<form action="'.DOKU_BASE.'lib/plugins/nsexport/export.php" method="post">';
        echo '<ul>';
        $num = 0;
        foreach($pages as $page){
            $num++;
            echo '<li><div class="li"><input type="checkbox" name="export[]"
                      id="page__'.$num.'" value="'.hsc($page['id']).'"
                      checked="checked" class="edit" />&nbsp;<label for="page__'.$num.'">'.
                      hsc($page['id']).'</label></div></li>';
        }
        echo '</ul>';
        echo '<input type="submit" class="button" />';
        echo '</form>';
    }

    /**
     * Do the action
     */
    function _export_html($pages){
        global $ID;
        global $conf;
        require_once(DOKU_INC.'inc/ZipLib.class.php');
        require_once(DOKU_INC.'inc/HTTPClient.php');

        $media = array();
        $zip   = new ZipLib();

        // add CSS
        $http  = new DokuHTTPClient();
        $css   = $http->get(DOKU_URL.'lib/exe/css.php?s=all&t='.$conf['template']);
        $zip->add_File($css,'all.css');
        $css   = $http->get(DOKU_URL.'lib/exe/css.php?t='.$conf['template']);
        $zip->add_File($css,'screen.css');
        $css   = $http->get(DOKU_URL.'lib/exe/css.php?s=print&t='.$conf['template']);
        $zip->add_File($css,'print.css');

        unset($html);

        foreach($pages as $ID){
            if( auth_quickaclcheck($item['id']) < AUTH_READ ) continue;
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
            $output .= '</head>'.DOKU_LF;
            $output .= '<body>'.DOKU_LF;
            $output .= '<div class="dokuwiki export">' . DOKU_LF;
            $output .= tpl_toc(true);
            $output .= $html;
            $output .= '</div>';
            $output .= '</body>'.DOKU_LF;
            $output .= '</html>'.DOKU_LF;

            $zip->add_File($output,str_replace(':','/',$ID).'.html');

            $media = array_merge($media,(array) p_get_metadata($ID,'plugin_nsexport'));
        }

        // now embed the media
        $media = array_unique($media);
        foreach($media as $id){
            if( auth_quickaclcheck($id) < AUTH_READ ) continue;
            @set_time_limit(30);

            $zip->add_File(io_readFile(mediaFN($id),false),'_media/'.str_replace(':','/',$id));
        }

        // send to browser
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="export.zip"');
        echo $zip->get_file();
    }
}
