<?php rcs_id('$Id: Theme.php,v 1.7 2002-01-19 03:23:45 carstenklapp Exp $');

class Theme {
    function Theme ($theme_name) {
        $this->_name = $theme_name;
        $themes_dir = defined('PHPWIKI_DIR') ? PHPWIKI_DIR . "/themes" : "themes";
        $this->_path = array("$themes_dir/$theme_name",
                             "$themes_dir/default");
    }

    function _findFile ($file, $missing_okay = false) {
        foreach ($this->_path as $dir) {
            if (file_exists("$dir/$file"))
                return "$dir/$file";
        }
        // FIXME: this is a short-term hack.  Delete this after all files
        // get moved into themes/...
        if (file_exists($file))
            return $file;
        
        if (!$missing_okay) {
            trigger_error("$file: not found", E_USER_ERROR);
        }
        return false;
    }
    
    function _findData ($file, $missing_okay = false) {
        $path = $this->_findFile($file, $missing_okay);
        if (!$path)
            return false;
        
        if (defined('DATA_PATH'))
            return DATA_PATH . "/$path";
        return $path;
    }

    function requireFile ($file) {
        $path = $this->_findFile($file);
        if (!$path)
            return false;
        return require_once($path);
    }
    
    ////////////////////////////////////////////////////////////////
    //
    // Date and Time formatting
    //
    ////////////////////////////////////////////////////////////////
    
    var $_dateTimeFormat = "%B %e, %Y";
    var $_dateFormat = "%B %e, %Y";

    function setDateFormat ($fs) {
        $this->_dateFormat = $fs;
    }

    function formatDate ($time_t) {
        return strftime($this->_dateFormat, $time_t);
    }

    function setDateTimeFormat ($fs) {
        $this->_dateTimeFormat = $fs;
    }

    function formatDateTime ($time_t) {
        return strftime($this->_dateTimeFormat, $time_t);
    }

    
    function formatTime ($time_t) {
        //FIXME: make 24-hour mode configurable?
        return preg_replace('/^0/', ' ',
                            strtolower(strftime("%I:%M %p", $time_t)));
    }


    ////////////////////////////////////////////////////////////////
    //
    // Hooks for other formatting
    //
    ////////////////////////////////////////////////////////////////

    function getFormatter ($type, $format) {
        $method = strtolower("get${type}Formatter");
        if (method_exists($this, $method))
            return call_user_method($method, $this, $format);
        return false;
    }

    function setWikiMark($wikimark=false) {
        //FIXME: Check for %s in wikimark
        $this->_wikiMark = $wikimark ? $wikimark : "?%s";
    }

    function getWikiMark() {
        if (! @$this->_wikiMark)
            $this->setWikiMark();
        
        return $this->_wikiMark;
    }


    ////////////////////////////////////////////////////////////////
    //
    // Images and Icons
    //
    ////////////////////////////////////////////////////////////////

    function addImageAlias ($alias, $image_name) {
        $this->_imageAliases[$alias] = $image_name;
    }
    
    function getImageURL ($image) {
        $aliases = &$this->_imageAliases;
        
        if (isset($aliases[$image]))
            $image = $aliases[$image];

        // If not extension, default to .png.
        if (!preg_match('/\.\w+$/', $image))
            $image .= '.png';

        // FIXME: this should probably be made to fall back
        //        automatically to .gif, .jpg.
        //        Also try .gif before .png if browser doesn't like png.
        
        return $this->_findData("images/$image", 'missing okay');
    }

    function setLinkIcon($proto, $image = false) {
        if (!$image)
            $image = $proto;
        
        $this->_linkIcons[$proto] = $image;
    }
    
    function getLinkIconURL ($proto) {
        $icons = &$this->_linkIcons;
        if (!empty($icons[$proto]))
            return $this->getImageURL($icons[$proto]);
        elseif (!empty($icons['*']))
            return $this->getImageURL($icons['*']);
        return false;
    }

    // FIXME: need buttonAlias map?
    function getButtonURL ($text) {
        $qtext = urlencode($text);
        // FIXME: search other languages too.
        foreach (array("buttons/en/$qtext.png",
                       "buttons/$qtext.png") as $file) {
            $path = $this->_findData($file, 'missing okay');
            if ($path)
                return $path;
        }
        return false;
    }

    ////////////////////////////////////////////////////////////////
    //
    // Button style
    //
    ////////////////////////////////////////////////////////////////

    function makeButton ($text, $url, $class) {
        // FIXME: don't always try for image button?
        
        $imgurl = $this->getButtonURL($text);
        if ($imgurl)
            return new ImageButton($text, $url, $class, $imgurl);
        else
            return new Button($text, $url, $class);
    }

    function setButtonSeparator($separator=false) {
        $this->_buttonSeparator = $separator ? $separator : " ";
    }

    function getButtonSeparator() {
        if (! @$this->_buttonSeparator)
            $this->setButtonSeparator(" | ");
        
        return $this->_buttonSeparator;
    }

    
    ////////////////////////////////////////////////////////////////
    //
    // CSS
    //
    ////////////////////////////////////////////////////////////////
    
    function _CSSlink($title, $css_file, $media, $is_alt = false) {
        $attr = array('rel' 	=> $is_alt ? 'alternate stylesheet' : 'stylesheet',
                      'title'	=> $title,
                      'type'	=> 'text/css',
                      'charset'	=> CHARSET);
        $attr['href'] = $this->_findData($css_file);
        if ($media)
            $attr['media'] = $media;
        return Element('link', $attr);
    }

    function setDefaultCSS ($title, $css_file, $media = false) {
        if (isset($this->_alternateCSS))
            unset($this->_alternateCSS[$title]);
        $this->_defaultCSS = $this->_CSSlink($title, $css_file, $media);
    }

    function addAlternateCSS ($title, $css_file, $media = false) {
        $this->_alternateCSS[$title] = $this->_CSSlink($title, $css_file, $media, true);
    }
    
    /**
     * @return string HTML for CSS.
     */
    function getCSS () {
        $css = "$this->_defaultCSS\n";
        if (!empty($this->_alternateCSS))
            $css .= join("\n", $this->_alternateCSS) . "\n";
        return $css;
    }

    function findTemplate ($name) {
        return $this->_findFile("templates/$name.tmpl");
    }
};


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
