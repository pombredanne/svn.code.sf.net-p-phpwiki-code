<?php rcs_id('$Id: Theme.php,v 1.64 2003-02-24 22:06:14 dairiki Exp $');

require_once('lib/HtmlElement.php');


/**
 * Make a link to a wiki page (in this wiki).
 *
 * This is a convenience function.
 *
 * @param mixed $page_or_rev
 * Can be:<dl>
 * <dt>A string</dt><dd>The page to link to.</dd>
 * <dt>A WikiDB_Page object</dt><dd>The page to link to.</dd>
 * <dt>A WikiDB_PageRevision object</dt><dd>A specific version of the page to link to.</dd>
 * </dl>
 *
 * @param string $type
 * One of:<dl>
 * <dt>'unknown'</dt><dd>Make link appropriate for a non-existant page.</dd>
 * <dt>'known'</dt><dd>Make link appropriate for an existing page.</dd>
 * <dt>'auto'</dt><dd>Either 'unknown' or 'known' as appropriate.</dd>
 * <dt>'button'</dt><dd>Make a button-style link.</dd>
 * <dt>'if_known'</dt><dd>Only linkify if page exists.</dd>
 * </dl>
 * Unless $type of of the latter form, the link will be of class 'wiki', 'wikiunknown',
 * 'named-wiki', or 'named-wikiunknown', as appropriate.
 *
 * @param mixed $label (string or XmlContent object)
 * Label for the link.  If not given, defaults to the page name.
 *
 * @return XmlContent The link
 */
function WikiLink ($page_or_rev, $type = 'known', $label = false) {
    global $Theme, $request;

    if ($type == 'button') {
        return $Theme->makeLinkButton($page_or_rev, $label);
    }

    $version = false;
    
    if (isa($page_or_rev, 'WikiDB_PageRevision')) {
        $version = $page_or_rev->getVersion();
        $page = $page_or_rev->getPage();
        $pagename = $page->getName();
        $wikipage = $pagename;
        $exists = true;
    }
    elseif (isa($page_or_rev, 'WikiDB_Page')) {
        $page = $page_or_rev;
        $pagename = $page->getName();
        $wikipage = $pagename;
    }
    elseif (isa($page_or_rev, 'WikiPageName')) {
        $wikipage = $page_or_rev;
        $pagename = $wikipage->name;
    }
    else {
        $wikipage = new WikiPageName($page_or_rev, $request->getPage());
        $pagename = $wikipage->name;
    }
    

    if ($type == 'auto' or $type == 'if_known') {
        if (isset($page)) {
            $current = $page->getCurrentRevision();
            $exists = ! $current->hasDefaultContents();
        }
        else {
	    $dbi = $request->getDbh();
            $exists = $dbi->isWikiPage($wikipage->name);
        }
    }
    elseif ($type == 'unknown') {
        $exists = false;
    }
    else {
        $exists = true;
    }

    // FIXME: this should be somewhere else, if really needed.
    // WikiLink makes A link, not a string of fancy ones.
    // (I think that the fancy split links are just confusing.)
    // Todo: test external ImageLinks http://some/images/next.gif
    if (isa($wikipage, 'WikiPageName') and !$label 
        and strchr(substr($wikipage->shortName,1), SUBPAGE_SEPARATOR)) {
        $parts = explode(SUBPAGE_SEPARATOR, $wikipage->shortName);
        $last_part = array_pop($parts);
        $sep = '';
        $link = HTML::span();
        foreach ($parts as $part) {
            $path[] = $part;
            $parent = join(SUBPAGE_SEPARATOR, $path);
            if ($part)
                $link->pushContent($Theme->linkExistingWikiWord($parent, $sep . $part));
            $sep = SUBPAGE_SEPARATOR;
        }
        if ($exists)
            $link->pushContent($Theme->linkExistingWikiWord($wikipage, $sep . $last_part, $version));
        else
            $link->pushContent($Theme->linkUnknownWikiWord($wikipage, $last_part));
        return $link;
    }

    if ($exists) {
        return $Theme->linkExistingWikiWord($wikipage, $label, $version);
    }
    elseif ($type == 'if_known') {
        if (!$label && isa($wikipage, 'WikiPageName'))
            $label = $wikipage->shortName;
        return HTML($label ? $label : $pagename);
    }
    else {
        return $Theme->linkUnknownWikiWord($wikipage, $label);
    }
}



/**
 * Make a button.
 *
 * This is a convenience function.
 *
 * @param $action string
 * One of <dl>
 * <dt>[action]</dt><dd>Perform action (e.g. 'edit') on the selected page.</dd>
 * <dt>[ActionPage]</dt><dd>Run the actionpage (e.g. 'BackLinks') on the selected page.</dd>
 * <dt>'submit:'[name]</dt><dd>Make a form submission button with the given name.
 *      ([name] can be blank for a nameless submit button.)</dd>
 * <dt>a hash</dt><dd>Query args for the action. E.g.<pre>
 *      array('action' => 'diff', 'previous' => 'author')
 * </pre></dd>
 * </dl>
 *
 * @param $label string
 * A label for the button.  If ommited, a suitable default (based on the valued of $action)
 * will be picked.
 *
 * @param $page_or_rev mixed
 * Which page (& version) to perform the action on.
 * Can be one of:<dl>
 * <dt>A string</dt><dd>The pagename.</dd>
 * <dt>A WikiDB_Page object</dt><dd>The page.</dd>
 * <dt>A WikiDB_PageRevision object</dt><dd>A specific version of the page.</dd>
 * </dl>
 * ($Page_or_rev is ignored for submit buttons.)
 */
function Button ($action, $label = false, $page_or_rev = false) {
    global $Theme;

    if (!is_array($action) && preg_match('/submit:(.*)/A', $action, $m))
        return $Theme->makeSubmitButton($label, $m[1], $class = $page_or_rev);
    else
        return $Theme->makeActionButton($action, $label, $page_or_rev);
}


class Theme {
    var $HTML_DUMP_SUFFIX = '';
    function Theme ($theme_name = 'default') {
        $this->_name = $theme_name;
        $themes_dir = defined('PHPWIKI_DIR') ? PHPWIKI_DIR . "/themes" : "themes";

        $this->_path  = defined('PHPWIKI_DIR') ? PHPWIKI_DIR . "/" : "";
        $this->_theme = "themes/$theme_name";

        if ($theme_name != 'default')
            $this->_default_theme = new Theme;

        $this->_css = array();
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
            trigger_error("$file: not found", E_USER_NOTICE);
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

    // Note:  Windows' implemetation of strftime does not include certain
	// format specifiers, such as %e (for date without leading zeros).  In
	// general, see:
	// http://msdn.microsoft.com/library/default.asp?url=/library/en-us/vclib/html/_crt_strftime.2c_.wcsftime.asp
	// As a result, we have to use %d, and strip out leading zeros ourselves.

    var $_dateFormat = "%B %d, %Y";
    var $_timeFormat = "%I:%M %p";

    var $_showModTime = true;

    /**
     * Set format string used for dates.
     *
     * @param $fs string Format string for dates.
     *
     * @param $show_mod_time bool If true (default) then times
     * are included in the messages generated by getLastModifiedMessage(),
     * otherwise, only the date of last modification will be shown.
     */
    function setDateFormat ($fs, $show_mod_time = true) {
        $this->_dateFormat = $fs;
        $this->_showModTime = $show_mod_time;
    }

    /**
     * Set format string used for times.
     *
     * @param $fs string Format string for times.
     */
    function setTimeFormat ($fs) {
        $this->_timeFormat = $fs;
    }

    /**
     * Format a date.
     *
     * Any time zone offset specified in the users preferences is
     * taken into account by this method.
     *
     * @param $time_t integer Unix-style time.
     *
     * @return string The date.
     */
    function formatDate ($time_t) {
        global $request;
        
        $offset_time = $time_t + 3600 * $request->getPref('timeOffset');
        // strip leading zeros from date elements (ie space followed by zero)
        return preg_replace('/ 0/', ' ', 
                            strftime($this->_dateFormat, $offset_time));
    }

    /**
     * Format a date.
     *
     * Any time zone offset specified in the users preferences is
     * taken into account by this method.
     *
     * @param $time_t integer Unix-style time.
     *
     * @return string The time.
     */
    function formatTime ($time_t) {
        //FIXME: make 24-hour mode configurable?
        global $request;
        $offset_time = $time_t + 3600 * $request->getPref('timeOffset');
        return preg_replace('/^0/', ' ',
                            strtolower(strftime($this->_timeFormat, $offset_time)));
    }

    /**
     * Format a date and time.
     *
     * Any time zone offset specified in the users preferences is
     * taken into account by this method.
     *
     * @param $time_t integer Unix-style time.
     *
     * @return string The date and time.
     */
    function formatDateTime ($time_t) {
        return $this->formatDate($time_t) . ' ' . $this->formatTime($time_t);
    }

    /**
     * Format a (possibly relative) date.
     *
     * If enabled in the users preferences, this method might
     * return a relative day (e.g. 'Today', 'Yesterday').
     *
     * Any time zone offset specified in the users preferences is
     * taken into account by this method.
     *
     * @param $time_t integer Unix-style time.
     *
     * @return string The day.
     */
    function getDay ($time_t) {
        global $request;
        
        if ($request->getPref('relativeDates') && ($date = $this->_relativeDay($time_t))) {
            return ucfirst($date);
        }
        return $this->formatDate($time_t);
    }
    
    /**
     * Format the "last modified" message for a page revision.
     *
     * @param $revision object A WikiDB_PageRevision object.
     *
     * @param $show_version bool Should the page version number
     * be included in the message.  (If this argument is omitted,
     * then the version number will be shown only iff the revision
     * is not the current one.
     *
     * @return string The "last modified" message.
     */
    function getLastModifiedMessage ($revision, $show_version = 'auto') {
        global $request;

        // dates >= this are considered invalid.
        if (! defined('EPOCH'))
            define('EPOCH', 0); // seconds since ~ January 1 1970

        $mtime = $revision->get('mtime');
        if ($mtime <= EPOCH)
            return fmt("Never edited.");

        if ($show_version == 'auto')
            $show_version = !$revision->isCurrent();

        if ($request->getPref('relativeDates') && ($date = $this->_relativeDay($mtime))) {
            if ($this->_showModTime)
                $date =  sprintf(_("%s at %s"),
                                 $date, $this->formatTime($mtime));
            
            if ($show_version)
                return fmt("Version %s, saved %s.", $revision->getVersion(), $date);
            else
                return fmt("Last edited %s.", $date);
        }

        if ($this->_showModTime)
            $date = $this->formatDateTime($mtime);
        else
            $date = $this->formatDate($mtime);
        
        if ($show_version)
            return fmt("Version %s, saved on %s.", $revision->getVersion(), $date);
        else
            return fmt("Last edited on %s.", $date);
    }
    
    function _relativeDay ($time_t) {
        global $request;
        $offset = 3600 * $request->getPref('timeOffset');

        $now = time() + $offset;
        $today = localtime($now, true);
        $time = localtime($time_t + $offset, true);

        if ($time['tm_yday'] == $today['tm_yday'] && $time['tm_year'] == $today['tm_year'])
            return _("today");
        
        // Note that due to daylight savings chages (and leap seconds), $now minus
        // 24 hours is not guaranteed to be yesterday.
        $yesterday = localtime($now - (12 + $today['tm_hour']) * 3600, true);
        if ($time['tm_yday'] == $yesterday['tm_yday'] && $time['tm_year'] == $yesterday['tm_year'])
            return _("yesterday");

        return false;
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
        global $request;

        if ($version !== false)
            $url = WikiURL($wikiword, array('version' => $version));
        else
            $url = WikiURL($wikiword);

        // Extra steps for dumping page to an html file.
        // FIXME: shouldn't this be in WikiURL?
        if ($this->HTML_DUMP_SUFFIX) {
            // urlencode for pagenames with accented letters
            $url = rawurlencode($url);
            $url = preg_replace('/^\./', '%2e', $url);
            $url .= $this->HTML_DUMP_SUFFIX;
        }

        $link = HTML::a(array('href' => $url));

        if (isa($wikiword, 'WikiPageName'))
            $default_text = $wikiword->shortName;
        else
            $default_text = $wikiword;
        
        if (!empty($linktext)) {
            $link->pushContent($linktext);
            $link->setAttr('class', 'named-wiki');
            $link->setAttr('title', $this->maybeSplitWikiWord($default_text));
        }
        else {
            $link->pushContent($this->maybeSplitWikiWord($default_text));
            $link->setAttr('class', 'wiki');
        }
        if ($request->getArg('frame'))
            $link->setAttr('target', '_top');
        return $link;
    }

    function linkUnknownWikiWord($wikiword, $linktext = '') {
        global $request;

        // Get rid of anchors on unknown wikiwords
        if (isa($wikiword, 'WikiPageName')) {
            $default_text = $wikiword->shortName;
            $wikiword = $wikiword->name;
        }
        else {
            $default_text = $wikiword;
        }
        
        $url = WikiURL($wikiword, array('action' => 'edit'));
        //$link = HTML::span(HTML::a(array('href' => $url), '?'));
        $button = $this->makeButton('?', $url);
        $button->addTooltip(sprintf(_("Edit: %s"), $wikiword));
        $link = HTML::span($button);


        if (!empty($linktext)) {
            $link->pushContent(HTML::u($linktext));
            $link->setAttr('class', 'named-wikiunknown');
        }
        else {
            $link->pushContent(HTML::u($this->maybeSplitWikiWord($default_text)));
            $link->setAttr('class', 'wikiunknown');
        }
        if ($request->getArg('frame'))
            $link->setAttr('target', '_top');

        return $link;
    }

    ////////////////////////////////////////////////////////////////
    //
    // Images and Icons
    //
    ////////////////////////////////////////////////////////////////
    var $_imageAliases = array();
    var $_imageAlt = array();

    /**
     *
     * (To disable an image, alias the image to <code>false</code>.
     */
    function addImageAlias ($alias, $image_name) {
        $this->_imageAliases[$alias] = $image_name;
    }

    function addImageAlt ($alias, $alt_text) {
        $this->_imageAlt[$alias] = $alt_text;
    }
    function getImageAlt ($alias) {
        return $this->_imageAlt[$alias];
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

        $path = $this->_findData("images/$image", 'missing okay');
        if (!$path) // search explicit images/ or button/ links also
            return $this->_findData("$image", 'missing okay');
       	else 
            return $path;	
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
        if (!$url) {// Jeff complained about png not supported everywhere. This is not PC
            $url = $this->_findButton("$qtext.gif");
            if ($url && strstr($url, '%')) {
                $url = preg_replace('|([^/]+)$|e', 'urlencode("\\1")', $url);
            }
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
     * currently selected version of the current page.
     * (Or anotherpage or version, if you want...)
     *
     * @param $action string The action to perform (e.g. 'edit', 'lock').
     * This can also be the name of an "action page" like 'LikePages'.
     * Alternatively you can give a hash of query args to be applied
     * to the page.
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
    function makeLinkButton ($page_or_rev, $label = false) {
        extract($this->_get_name_and_rev($page_or_rev));

        $args = $version ? array('version' => $version) : false;

        return $this->makeButton($label ? $label : $this->maybeSplitWikiWord($pagename), 
                                 WikiURL($pagename, $args), 'wiki');
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
            case 'edit':   return _("Edit");
            case 'diff':   return _("Diff");
            case 'logout': return _("Sign Out");
            case 'login':  return _("Sign In");
            case 'lock':   return _("Lock Page");
            case 'unlock': return _("Unlock Page");
            case 'remove': return _("Remove Page");
            default:
                // I don't think the rest of these actually get used.
                // 'setprefs'
                // 'upload' 'dumpserial' 'loadfile' 'zip'
                // 'save' 'browse'
                return gettext(ucfirst($action));
        }
    }

    //----------------------------------------------------------------
    var $_buttonSeparator = "\n | ";

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
    // Notes:
    //
    // Based on testing with Galeon 1.2.7 (Mozilla 1.2):
    // Automatic media-based style selection (via <link> tags) only
    // seems to work for the default style, not for alternate styles.
    //
    // Doing
    //
    //  <link rel="stylesheet" type="text/css" href="phpwiki.css" />
    //  <link rel="stylesheet" type="text/css" href="phpwiki-printer.css" media="print" />
    //
    // works to make it so that the printer style sheet get used
    // automatically when printing (or print-previewing) a page
    // (but when only when the default style is selected.)
    //
    // Attempts like:
    //
    //  <link rel="alternate stylesheet" title="Modern"
    //        type="text/css" href="phpwiki-modern.css" />
    //  <link rel="alternate stylesheet" title="Modern"
    //        type="text/css" href="phpwiki-printer.css" media="print" />
    //
    // Result in two "Modern" choices when trying to select alternate style.
    // If one selects the first of those choices, one gets phpwiki-modern
    // both when browsing and printing.  If one selects the second "Modern",
    // one gets no CSS when browsing, and phpwiki-printer when printing.
    //
    // The Real Fix?
    // =============
    //
    // We should probably move to doing the media based style
    // switching in the CSS files themselves using, e.g.:
    //
    //  @import url(print.css) print;
    //
    ////////////////////////////////////////////////////////////////

    function _CSSlink($title, $css_file, $media, $is_alt = false) {
        // Don't set title on default style.  This makes it clear to
        // the user which is the default (i.e. most supported) style.
        $link = HTML::link(array('rel'     => $is_alt ? 'alternate stylesheet' : 'stylesheet',
                                 'type'    => 'text/css',
                                 'charset' => CHARSET,
                                 'href'    => $this->_findData($css_file)));
        if ($is_alt)
            $link->setAttr('title', $title);

        if ($media) 
            $link->setAttr('media', $media);
        
        return $link;
    }

    /** Set default CSS source for this theme.
     *
     * To set styles to be used for different media, pass a
     * hash for the second argument, e.g.
     *
     * $theme->setDefaultCSS('default', array('' => 'normal.css',
     *                                        'print' => 'printer.css'));
     *
     * If you call this more than once, the last one called takes
     * precedence as the default style.
     *
     * @param string $title Name of style (currently ignored, unless
     * you call this more than once, in which case, some of the style
     * will become alternate (rather than default) styles, and then their
     * titles will be used.
     *
     * @param mixed $css_files Name of CSS file, or hash containing a mapping
     * between media types and CSS file names.  Use a key of '' (the empty string)
     * to set the default CSS for non-specified media.  (See above for an example.)
     */
    function setDefaultCSS ($title, $css_files) {
        if (!is_array($css_files))
            $css_files = array('' => $css_files);
        // Add to the front of $this->_css
        unset($this->_css[$title]);
        $this->_css = array_merge(array($title => $css_files), $this->_css);
    }

    /** Set alternate CSS source for this theme.
     *
     * @param string $title Name of style.
     * @param string $css_files Name of CSS file.
     */
    function addAlternateCSS ($title, $css_files) {
        if (!is_array($css_files))
            $css_files = array('' => $css_files);
        $this->_css[$title] = $css_files;
    }

    /**
        * @return string HTML for CSS.
     */
    function getCSS () {
        $css = array();
        $is_alt = false;
        foreach ($this->_css as $title => $css_files) {
            aksort($css_files); // move $css_files[''] to front.
            foreach ($css_files as $media => $css_file) {
                $css[] = $this->_CSSlink($title, $css_file, $media, $is_alt);
                if ($is_alt) break;
                
            }
            $is_alt = true;
        }
        return HTML($css);
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
        global $request;
        $this->HtmlElement('a', array('href' => $url));
        if ($class)
            $this->setAttr('class', $class);
        if ($request->getArg('frame'))
            $this->setAttr('target', '_top');
        $this->pushContent($GLOBALS['Theme']->maybeSplitWikiWord($text));
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
        $this->HtmlElement('input', array('type'  => 'image',
                                          'src'   => $img_url,
                                          'value' => $text,
                                          'alt'   => $text));
        if ($name)
            $this->setAttr('name', $name);
        if ($class)
            $this->setAttr('class', $class);
    }

};

// $Log: not supported by cvs2svn $
// Revision 1.63  2003/02/23 03:37:05  dairiki
// Stupid typo/bug fix.
//
// Revision 1.62  2003/02/21 04:14:52  dairiki
// New WikiLink type 'if_known'.  This gives linkified name if page
// exists, otherwise, just plain text.
//
// Revision 1.61  2003/02/18 21:52:05  dairiki
// Fix so that one can still link to wiki pages with # in their names.
// (This was made difficult by the introduction of named tags, since
// '[Page #1]' is now a link to anchor '1' in page 'Page'.
//
// Now the ~ escape for page names should work: [Page ~#1].
//
// Revision 1.60  2003/02/15 01:59:47  dairiki
// Theme::getCSS():  Add Default-Style HTTP(-eqiv) header in attempt
// to fix default stylesheet selection on some browsers.
// For details on the Default-Style header, see:
//  http://home.dairiki.org/docs/html4/present/styles.html#h-14.3.2
//
// Revision 1.59  2003/01/04 22:30:16  carstenklapp
// New: display a "Never edited." message instead of an invalid epoch date.
//

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
