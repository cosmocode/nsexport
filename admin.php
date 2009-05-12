<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');

require_once(DOKU_INC.'inc/search.php');

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_nsexport extends DokuWiki_Admin_Plugin {

    /**
     * return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/info.txt');
    }


    /**
     * return sort order for position in admin menu
     */
    function getMenuSort() {
        return 99;
    }

    /**
     * handle user request
     *
     * Initializes internal vars and handles modifications
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function handle() {
    }

    /**
     * ACL Output function
     *
     * print a table with all significant permissions for the
     * current id
     *
     * @author  Frank Schubert <frank@schokilade.de>
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    function html() {
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
//            $html = p_cached_output(wikiFN($ID,''), 'xhtml');
            $html = p_wiki_xhtml($ID,'',false);
            $this->_html_rewrite($html,$ref);

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
        }

        // send to browser
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="export.zip"');
        echo $zip->get_file();
    }

    function _html_rewrite(&$html,$ref){
        $lbase = rtrim(wl('x'),'x'); // get current base of all internal links
        $lbase = preg_quote($lbase,'/');

        $dbase = substr(ml('x.jpg','',false),0,-5); // get current base of all media detail links
        $dbase = preg_quote($dbase,'/');

die($dbase);

        $mbase = rtrim(ml('x'),'x'); // get current base of all mediafiles
        $mbase = preg_quote($mbase,'/');


        // replace all internal links
        $html = preg_replace_callback(
                    '/ href="'.$lbase.'([^"]+)"/',
                    create_function('$matches',
                       'return admin_plugin_nsexport::_html_cb_link($matches,\''.$ref.'\');'
                    ),
                    $html);

        $html = preg_replace_callback(
                    '/ href="'.$dbase.'([^"]+)"/',
                    create_function('$matches',
                       'return admin_plugin_nsexport::_html_cb_dlink($matches,\''.$ref.'\');'
                    ),
                    $html);


        // replace all media sources
        $html = preg_replace_callback(
                    '/ src="'.$mbase.'([^"]+)"/',
                    create_function('$matches',
                       'return admin_plugin_nsexport::_html_cb_media($matches,\''.$ref.'\');'
                    ),
                    $html);



    }

    function _html_cb_link($matches,$ref){
        $id = str_replace(':','/',$matches[1]).'.html';
        return ' href="'.$ref.$id.'"';
    }

    function _html_cb_dlink($matches,$ref){
        $id = str_replace(':','/',$matches[1]);
        return ' href="'.$ref.'_media/'.$id.'"';
    }

    function _html_cb_media($matches,$ref){
        //FIXME handle external media

        $id = str_replace(':','/',$matches[1]);
        return ' src="'.$ref.'_media/'.$id.'"';
    }

}
