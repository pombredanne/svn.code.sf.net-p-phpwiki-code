<?php rcs_id('$Id: Theme.php,v 1.25 2002-01-28 18:49:08 dairiki Exp $');

require_once('lib/HtmlElement.php');

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
            return $this->{$method}($format);
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

    /**
     *
     * (To disable an image, alias the image to <code>false</code>.
     */
    function addImageAlias ($alias, $image_name) {
        $this->_imageAliases[$alias] = $image_name;
    }
    
    function getImageURL ($image) {
        $aliases = &$this->_imageAliases;
        
        if (isset($aliases[$image])) {
            $image = $aliases[$image];
            if (!$image)
                return false;
        }

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
        $button_dir = $this->file("buttons");
        if (!file_exists($button_dir) || !is_dir($button_dir))
            return array();

        $path = array('buttons');

        $dir = dir($button_dir);
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

    /**
     * Make button to perform action.
     *
     * This constructs a button which performs an action on the
     * currently selected version of the current page.  (Or another
     * page or version, if you want...)
     *
     * @param $action string The action to perform (e.g. 'edit', 'lock').
     * This can also be the name of an "action page" like 'LikePages'.
     *
     * @param $label string Textual label for the button.  If left empty,
     * a suitable name will be guessed.
     *
     * @param $page_or_rev mixed  The page to link to.  This can be
     * given as a string (the page name), a WikiDB_Page object, or as
     * WikiDB_PageRevision object.  If given as a WikiDB_PageRevision
     * object, the button will link to a specific version of the
     * designated page, otherwise the button links to the most recent
     * version of the page.
     *
     * @return object A Button object.
     */
    function makeActionButton ($action, $label = false, $page_or_rev = false) {
        extract($this->_get_name_and_rev($page_or_rev));

        if (is_array($action)) {
            $attr = $action;
            $action = isset($attr['action']) ? $attr['action'] : 'browse';
        }
        else
            $attr['action'] = $action;

        $class = is_safe_action($action) ? 'wikiaction' : 'wikiadmin';
        if (!$label)
            $label = $this->_labelForAction($action);

        if ($version)
            $attr['version'] = $version;

        if ($action == 'browse')
            unset($attr['action']);

        return $this->makeButton($label, WikiURL($pagename, $attr), $class);
    }

    /**
     * Make a "button" which links to a wiki-page.
     *
     * These are really just regular WikiLinks, possibly
     * disguised (e.g. behind an image button) by the theme.
     *
     * This method should probably only be used for links
     * which appear in page navigation bars, or similar places.
     *
     * Use linkExistingWikiWord, or LinkWikiWord for normal links.
     *
     * @param $page_or_rev mixed The page to link to.  This can be
     * given as a string (the page name), a WikiDB_Page object, or as
     * WikiDB_PageRevision object.  If given as a WikiDB_PageRevision
     * object, the button will link to a specific version of the
     * designated page, otherwise the button links to the most recent
     * version of the page.
     *
     * @return object A Button object.
     */
    function makeLinkButton ($page_or_rev) {
        extract($this->_get_name_and_rev($page_or_rev));
        
        $args = $version ? array('version' => $version) : false;
        
        return $this->makeButton($pagename, WikiURL($pagename, $args), 'wiki');
    }

    function _get_name_and_rev ($page_or_rev) {
        $version = false;
        
        if (empty($page_or_rev)) {
            global $request;
            $pagename = $request->getArg("pagename");
            $version = $request->getArg("version");
        }
        elseif (is_object($page_or_rev)) {
            if (isa($page_or_rev, 'WikiDB_PageRevision')) {
                $rev = $page_or_rev;
                $page = $rev->getPage();
                $version = $rev->getVersion();
            }
            else {
                $page = $page_or_rev;
            }
            $pagename = $page->getName();
        }
        else {
            $pagename = (string) $page_or_rev;
        }
        return compact('pagename', 'version');
    }

    function _labelForAction ($action) {
        switch ($action) {
        case 'edit':	return _("Edit");
        case 'diff':	return _("Diff");
        case 'logout':	return _("Sign Out");
        case 'login':	return _("Sign In");
        case 'lock':	return _("Lock Page");
        case 'unlock':	return _("Unlock Page");
        case 'remove':	return _("Remove Page");
        default:
            // I don't think the rest of these actually get used.
            // 'setprefs'
            // 'upload' 'dumpserial' 'loadfile' 'zip'
            // 'save' 'browse'
            return ucfirst($action);
        }
    }
 
    //----------------------------------------------------------------
    var $_buttonSeparator = ' | ';
    
    function setButtonSeparator($separator) {
        $this->_buttonSeparator = $separator;
    }

    function getButtonSeparator() {
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
        $css = HTML($this->_defaultCSS);
        if (!empty($this->_alternateCSS))
            $css->pushContent($this->_alternateCSS);
        return $css;
    }

    function findTemplate ($name) {
        return $this->_path . $this->_findFile("templates/$name.tmpl");
    }
};


/**
 * A class representing a clickable "button".
 *
 * In it's simplest (default) form, a "button" is just a link associated
 * with some sort of wiki-action.
 */
class Button extends HtmlElement {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $url string The url (href) for the button.
     * @param $class string The CSS class for the button.
     */
    function Button ($text, $url, $class = false) {
        $this->HtmlElement('a', array('href' => $url));
        if ($class)
            $this->setAttr('class', $class);
        $this->pushContent($text);
    }

};


/**
 * A clickable image button.
 */
class ImageButton extends Button {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $url string The url (href) for the button.
     * @param $class string The CSS class for the button.
     * @param $img_url string URL for button's image.
     * @param $img_attr array Additional attributes for the &lt;img&gt; tag.
     */
    function ImageButton ($text, $url, $class, $img_url, $img_attr = false) {
        $this->HtmlElement('a', array('href' => $url));
        if ($class)
            $this->setAttr('class', $class);
 
        if (!is_array($img_attr))
            $img_attr = array();
        $img_attr['src'] = $img_url;
        $img_attr['alt'] = $text;
        $img_attr['class'] = 'wiki-button';
        $img_attr['border'] = 0;
        $this->pushContent(HTML::img($img_attr));
    }
};

/**
 * A class representing a form <samp>submit</samp> button.
 */
class SubmitButton extends HtmlElement {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $name string The name of the form field.
     * @param $class string The CSS class for the button.
     */
    function SubmitButton ($text, $name = false, $class = false) {
        $this->HtmlElement('input', array('type' => 'submit',
                                          'value' => $text));
        if ($name)
            $this->setAttr('name', $name);
        if ($class)
            $this->setAttr('class', $class);
    }

};


/**
 * A class representing an image form <samp>submit</samp> button.
 */
class SubmitImageButton extends SubmitButton {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $name string The name of the form field.
     * @param $class string The CSS class for the button.
     * @param $img_url string URL for button's image.
     * @param $img_attr array Additional attributes for the &lt;img&gt; tag.
     */
    function SubmitImageButton ($text, $name = false, $class = false, $img_url) {
        $this->HtmlElement('input', array('type' => 'image',
                                          'src' => $img_url,
                                          'value' => $text,
                                          'alt' => $text));
        if ($name)
            $this->setAttr('name', $name);
        if ($class)
            $this->setAttr('class', $class);
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
