<?php rcs_id('$Id: Theme.php,v 1.19 2002-01-23 09:04:27 carstenklapp Exp $');

require_once('lib/HtmlElement.php');
require_once('lib/ButtonFactory.php');


class Theme {
    function Theme ($theme_name = 'default') {
        $this->_name = $theme_name;
        $themes_dir = defined('PHPWIKI_DIR') ? PHPWIKI_DIR . "/themes" : "themes";
        
        $this->_path  = defined('PHPWIKI_DIR') ? PHPWIKI_DIR . "/" : "";
        $this->_theme = "themes/$theme_name";

        if ($theme_name != 'default')
            $this->_default_theme = new Theme;
    }

    function file ($file) {
        return $this->_path . "$this->_theme/$file";
    }

    function _findFile ($file, $missing_okay = false) {
        if (file_exists($this->_path . "$this->_theme/$file"))
            return "$this->_theme/$file";
        
        // FIXME: this is a short-term hack.  Delete this after all files
        // get moved into themes/...
        if (file_exists($this->_path . $file))
            return $file;
        

        if (isset($this->_default_theme)) {
            return $this->_default_theme->_findFile($file, $missing_okay);
        }
        else if (!$missing_okay) {
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

    //FIXME: PHP 4.1 Warnings
    //lib/Theme.php:84: Notice[8]: The call_user_method() function is deprecated,
    //use the call_user_func variety with the array(&$obj, "method") syntax instead

    function getFormatter ($type, $format) {
        $method = strtolower("get${type}Formatter");
        if (method_exists($this, $method))
            return @call_user_method($method, $this, $format);
        return false;
    }


    ////////////////////////////////////////////////////////////////
    //
    // Links
    //
    ////////////////////////////////////////////////////////////////

    var $_autosplitWikiWords = false;
    
    function setAutosplitWikiWords($autosplit=false) {
        $this->_autosplitWikiWords = $autosplit ? true : false;
    }

    function maybeSplitWikiWord ($wikiword) {
        if ($this->_autosplitWikiWords)
            return split_pagename($wikiword);
        else
            return $wikiword;
    }

    function linkExistingWikiWord($wikiword, $linktext = '', $version = false) {
        if ($version !== false)
            $url = WikiURL($wikiword, array('version' => $version));
        else
            $url = WikiURL($wikiword);

        $link = HTML::a(array('href' => $url));

        if (!empty($linktext)) {
            $link->pushContent($linktext);
            $link->setAttr('class', 'named-wiki');
        }
        else {
            $link->pushContent($this->maybeSplitWikiWord($wikiword));
            $link->setAttr('class', 'wiki');
        }
        return $link;
    }

    function linkUnknownWikiWord($wikiword, $linktext = '') {
        $url = WikiURL($wikiword, array('action' => 'edit'));
        //$link = HTML::span(HTML::a(array('href' => $url), '?'));
        $link = HTML::span($this->makeButton('?', $url));
        
        if (!empty($linktext)) {
            $link->pushContent(HTML::u($linktext));
            $link->setAttr('class', 'named-wikiunknown');
        }
        else {
            $link->pushContent(HTML::u($this->maybeSplitWikiWord($wikiword)));
            $link->setAttr('class', 'wikiunknown');
        }
        
        return $link;
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

    function addButtonAlias ($text, $alias = false) {
        $aliases = &$this->_buttonAliases;

        if (is_array($text))
            $aliases = array_merge($aliases, $text);
        elseif ($alias === false)
            unset($aliases[$text]);
        else
            $aliases[$text] = $alias;
    }

    function getButtonURL ($text) {
        $aliases = &$this->_buttonAliases;
        if (isset($aliases[$text]))
            $text = $aliases[$text];

        $qtext = urlencode($text);
        $url = $this->_findButton("$qtext.png");
        if ($url && strstr($url, '%')) {
            $url = preg_replace('|([^/]+)$|e', 'urlencode("\\1")', $url);
        }
        return $url;
    }

    function _findButton ($button_file) {
        if (!isset($this->_button_path))
            $this->_button_path = $this->_getButtonPath();
        
        foreach ($this->_button_path as $dir) {
            $path = "$this->_theme/$dir/$button_file";
            if (file_exists($this->_path . $path))
                return defined('DATA_PATH') ? DATA_PATH . "/$path" : $path;
        }
        return false;
    }

    function _getButtonPath () {
        $path = array('buttons');

        $button_dir = $this->file("buttons");
        if (!($dir = dir($button_dir))) // Error only in Hawaiian theme, which has no button dir
            return array();
                                        //lib/Theme.php:241: Warning[2]: OpenDir: No such file or directory (errno 2)

        while (($subdir = $dir->read()) !== false) {
            if ($subdir[0] == '.')
                continue;
            if (is_dir("$button_dir/$subdir"))
                $path[] = "buttons/$subdir";
        }
        $dir->close();
        
        return $path;
    }
        
    ////////////////////////////////////////////////////////////////
    //
    // Button style
    //
    ////////////////////////////////////////////////////////////////

    function makeButton ($text, $url, $class = false) {
        // FIXME: don't always try for image button?

        // Special case: URLs like 'submit:preview' generate form
        // submission buttons.
        if (preg_match('/^submit:(.*)$/', $url, $m))
            return $this->makeSubmitButton($text, $m[1], $class);
        
        $imgurl = $this->getButtonURL($text);
        if ($imgurl)
            return new ImageButton($text, $url, $class, $imgurl);
        else
            return new Button($text, $url, $class);
    }

    function makeSubmitButton ($text, $name, $class = false) {
        $imgurl = $this->getButtonURL($text);

        if ($imgurl)
            return new SubmitImageButton($text, $name, $class, $imgurl);
        else
            return new SubmitButton($text, $name, $class);
    }

    function setButtonSeparator($separator) {
        $this->_buttonSeparator = $separator;
    }

    function getButtonSeparator() {
        if (!isset($this->_buttonSeparator))
            $this->setButtonSeparator(" | ");
        
        return $this->_buttonSeparator;
    }

    
    ////////////////////////////////////////////////////////////////
    //
    // CSS
    //
    ////////////////////////////////////////////////////////////////
    
    function _CSSlink($title, $css_file, $media, $is_alt = false) {
        $link = HTML::link(array('rel' 	  => $is_alt ? 'alternate stylesheet' : 'stylesheet',
                                 'title'  => $title,
                                 'type'	  => 'text/css',
                                 'charset'=> CHARSET,
                                 'href'	  => $this->_findData($css_file)));
        if ($media)
            $link->setAttr('media', $media);
        return $link;
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
        $css[] = $this->_defaultCSS;
        if (!empty($this->_alternateCSS))
            foreach ($this->_alternateCSS as $link)
                $css[] = $link;
        return $css;
    }

    function findTemplate ($name) {
        return $this->_path . $this->_findFile("templates/$name.tmpl");
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
