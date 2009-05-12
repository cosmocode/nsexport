<?php
/**
 * Renderer for XHTML output
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

// we inherit from the XHTML renderer instead directly of the base renderer
require_once DOKU_INC.'inc/parser/xhtml.php';

/**
 * The Renderer
 */
class renderer_plugin_nsexport_xhtml extends Doku_Renderer_xhtml {

    /**
     * Rewrite all internal links to local html files
     */
    function internallink($id, $name = NULL, $search=NULL,$returnonly=false,$linktype='content') {
        global $conf;
        global $ID;
        // default name is based on $id as given
        $default = $this->_simpleTitle($id);

        // relative URLs we need
        $deep = substr_count($ID,':');
        $ref  = '';
        for($i=0; $i<$deep; $i++) $ref .= '../';

        // now first resolve and clean up the $id
        resolve_pageid(getNS($ID),$id,$exists);
        $name = $this->_getLinkTitle($name, $default, $isImage, $id, $linktype);
        if ( !$isImage ) {
            if ( $exists ) {
                $class='wikilink1';
                // fixme check if this a exported page, if not skip it

            } else {
                // doesn't exist? skip it
                $this->cdata($name);
                return;
            }
        } else {
            $class='media';
        }

        //keep hash anchor
        list($id,$hash) = explode('#',$id,2);
        if(!empty($hash)) $hash = $this->_headerToLink($hash);

        //prepare for formating
        $link['target'] = $conf['target']['wiki'];
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        // highlight link to current page
        if ($id == $ID) {
            $link['pre']    = '<span class="curid">';
            $link['suf']    = '</span>';
        }
        $link['more']   = '';
        $link['class']  = $class;
        $link['url']    = $ref.str_replace(':','/',$id).'.html';
        $link['name']   = $name;
        $link['title']  = $id;

        //keep hash
        if($hash) $link['url'].='#'.$hash;

        //output formatted
        $this->doc .= $this->_formatLink($link);
    }

}

