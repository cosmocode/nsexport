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

    public $_media = array();

    public function _relTop(){
        // relative URLs we need
        global $ID;
        $deep = substr_count($ID,':');
        $ref  = '';
        for($i=0; $i<$deep; $i++) $ref .= '../';
        return $ref;
    }

    public function _localMedia($src){
        // rewrite local media and move to zip
        if(!preg_match('/^\w+:\/\//',$src)){
            $this->_media[] = $src;

            $ref = $this->_relTop();
            $src = $ref.'_media/'.str_replace(':','/',$src);
        }
        return $src;
    }

    /**
     * Store all referenced media in metadata
     */
    public function document_end(){
        global $ID;
        parent::document_end();

        $this->_media = array_unique($this->_media);
        p_set_metadata($ID,array('plugin_nsexport'=>$this->_media));
    }


    /**
     * Rewrite all internal links to local html files
     */
    public function internallink($id, $name = null, $search=null, $returnonly=false, $linktype='content') {
        global $conf;
        global $ID;
        // default name is based on $id as given
        $default = $this->_simpleTitle($id);

        $ref = $this->_relTop();

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
        if ($id === $ID) {
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

    /**
     * Renders internal and external media
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public function _media ($src, $title=null, $align=null, $width=null,
                            $height=null, $cache=null, $render = true) {
        $ret = '';
        $src = $this->_localMedia($src);

        list($ext,$mime,$dl) = mimetype($src);
        if(substr($mime,0,5) === 'image'){
            // first get the $title
            if (!is_null($title)) {
                $title  = $this->_xmlEntities($title);
            }elseif($ext === 'jpg' || $ext === 'jpeg'){
                //try to use the caption from IPTC/EXIF
                require_once(DOKU_INC.'inc/JpegMeta.php');
                $jpeg = new JpegMeta(mediaFN($src));
                if($jpeg !== false) $cap = $jpeg->getTitle();
                if($cap){
                    $title = $this->_xmlEntities($cap);
                }
            }
            if (!$render) {
                // if the picture is not supposed to be rendered
                // return the title of the picture
                if (!$title) {
                    // just show the sourcename
                    $title = $this->_xmlEntities(basename(noNS($src)));
                }
                return $title;
            }
            //add image tag
            $ret .= '<img src="'.$src.'"';
            $ret .= ' class="media'.$align.'"';

            // make left/right alignment for no-CSS view work (feeds)
            if($align === 'right') $ret .= ' align="right"';
            if($align === 'left')  $ret .= ' align="left"';

            if ($title) {
                $ret .= ' title="' . $title . '"';
                $ret .= ' alt="'   . $title .'"';
            }else{
                $ret .= ' alt=""';
            }

            if ( !is_null($width) )
                $ret .= ' width="'.$this->_xmlEntities($width).'"';

            if ( !is_null($height) )
                $ret .= ' height="'.$this->_xmlEntities($height).'"';

            $ret .= ' />';

        }elseif($mime === 'application/x-shockwave-flash'){
            if (!$render) {
                // if the flash is not supposed to be rendered
                // return the title of the flash
                if (!$title) {
                    // just show the sourcename
                    $title = basename(noNS($src));
                }
                return $this->_xmlEntities($title);
            }

            $att = array();
            $att['class'] = "media$align";
            if($align === 'right') $att['align'] = 'right';
            if($align === 'left')  $att['align'] = 'left';
            $ret .= html_flashobject($src,$width,$height,
                                     array('quality' => 'high'),
                                     null,
                                     $att,
                                     $this->_xmlEntities($title));
        }elseif($title){
            // well at least we have a title to display
            $ret .= $this->_xmlEntities($title);
        }else{
            // just show the sourcename
            $ret .= $this->_xmlEntities(basename(noNS($src)));
        }

        return $ret;
    }

    public function internalmedia ($src, $title = null, $align = null, $width = null,
                                   $height = null, $cache = null, $linking = null, $return = false) {
        global $ID;
        list($src,$hash) = explode('#',$src,2);
        resolve_mediaid(getNS($ID),$src, $exists);

        $lsrc = $this->_localMedia($src);
        $noLink = false;
        $render = ($linking === 'linkonly') ? false : true;
        $link = $this->_getMediaLinkConf($src, $title, $align, $width, $height, $cache, $render);
        $link['url'] = $lsrc;

        list($ext,$mime,$dl) = mimetype($src);
        if(substr($mime,0,5) === 'image' && $render){
        }elseif($mime === 'application/x-shockwave-flash' && $render){
            // don't link flash movies
            $noLink = true;
        }else{
            // add file icons
            $class = preg_replace('/[^_\-a-z0-9]+/i','_',$ext);
            $link['class'] .= ' mediafile mf_'.$class;
        }

        if($hash) $link['url'] .= '#'.$hash;

        //markup non existing files
        if (!$exists)
          $link['class'] .= ' wikilink2';

        //output formatted
        if ($linking === 'nolink' || $noLink) $this->doc .= $link['name'];
        else $this->doc .= $this->_formatLink($link);

    }


}

