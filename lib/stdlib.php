<?php //rcs_id('$Id: stdlib.php,v 1.137 2003-02-18 23:13:40 dairiki Exp $');

/*
  Standard functions for Wiki functionality
    WikiURL($pagename, $args, $get_abs_url)
    IconForLink($protocol_or_url)
    LinkURL($url, $linktext)
    LinkImage($url, $alt)

    MakeWikiForm ($pagename, $args, $class, $button_text)
    SplitQueryArgs ($query_args)
    LinkPhpwikiURL($url, $text)
    LinkBracketLink($bracketlink)
    ExtractWikiPageLinks($content)
    ConvertOldMarkup($content)
    
    class Stack { push($item), pop(), cnt(), top() }

    split_pagename ($page)
    NoSuchRevision ($request, $page, $version)
    TimezoneOffset ($time, $no_colon)
    Iso8601DateTime ($time)
    Rfc2822DateTime ($time)
    CTime ($time)
    __printf ($fmt)
    __sprintf ($fmt)
    __vsprintf ($fmt, $args)
    better_srand($seed = '')
    count_all($arg)
    isSubPage($pagename)
    subPageSlice($pagename, $pos)
    explodePageList($input, $perm = false)

  function: LinkInterWikiLink($link, $linktext)
  moved to: lib/interwiki.php
  function: linkExistingWikiWord($wikiword, $linktext, $version)
  moved to: lib/Theme.php
  function: LinkUnknownWikiWord($wikiword, $linktext)
  moved to: lib/Theme.php
  function: UpdateRecentChanges($dbi, $pagename, $isnewpage) 
  gone see: lib/plugin/RecentChanges.php
*/

/**
 * This is the character used in wiki markup to escape characters with
 * special meaning.
 */
define('ESCAPE_CHAR', '~');

/**
 * Convert string to a valid XML identifier.
 *
 * XML 1.0 identifiers are of the form: [A-Za-z][A-Za-z0-9:_.-]*
 *
 * We would like to have, e.g. named anchors within wiki pages
 * names like "Table of Contents" --- clearly not a valid XML
 * fragment identifier.
 *
 * This function implements a one-to-one map from {any string}
 * to {valid XML identifiers}.
 *
 * It does this by
 * converting all bytes not in [A-Za-z0-9:_-],
 * and any leading byte not in [A-Za-z] to 'xbb.',
 * where 'bb' is the hexadecimal representation of the
 * character.
 *
 * As a special case, the empty string is converted to 'empty.'
 *
 * @param string $str
 * @return string
 */
function MangleXmlIdentifier($str) 
{
    if (!$str)
        return 'empty.';
    
    return preg_replace('/[^-_:A-Za-z0-9]|(?<=^)[^A-Za-z]/e',
                        "'x' . sprintf('%02x', ord('\\0')) . '.'",
                        $str);
}
    

/**
 * Generates a valid URL for a given Wiki pagename.
 * @param mixed $pagename If a string this will be the name of the Wiki page to link to.
 * 			  If a WikiDB_Page object function will extract the name to link to.
 * 			  If a WikiDB_PageRevision object function will extract the name to link to.
 * @param array $args 
 * @param boolean $get_abs_url Default value is false.
 * @return string The absolute URL to the page passed as $pagename.
 */
function WikiURL($pagename, $args = '', $get_abs_url = false) {
    $anchor = false;
    
    if (is_object($pagename)) {
        if (isa($pagename, 'WikiDB_Page')) {
            $pagename = $pagename->getName();
        }
        elseif (isa($pagename, 'WikiDB_PageRevision')) {
            $page = $pagename->getPage();
            $args['version'] = $pagename->getVersion();
            $pagename = $page->getName();
        }
        elseif (isa($pagename, 'WikiPageName')) {
            $anchor = $pagename->anchor;
            $pagename = $pagename->name;
        }
    }
    
    if (is_array($args)) {
        $enc_args = array();
        foreach  ($args as $key => $val) {
            if (!is_array($val)) // ugly hack for getURLtoSelf() which also takes POST vars
              $enc_args[] = urlencode($key) . '=' . urlencode($val);
        }
        $args = join('&', $enc_args);
    }

    if (USE_PATH_INFO) {
        $url = $get_abs_url ? SERVER_URL . VIRTUAL_PATH . "/" : "";
        $url .= preg_replace('/%2f/i', '/', rawurlencode($pagename));
        if ($args)
            $url .= "?$args";
    }
    else {
        $url = $get_abs_url ? SERVER_URL . SCRIPT_NAME : basename(SCRIPT_NAME);
        $url .= "?pagename=" . rawurlencode($pagename);
        if ($args)
            $url .= "&$args";
    }
    if ($anchor)
        $url .= "#" . MangleXmlIdentifier($anchor);
    return $url;
}

/**
 * Generates icon in front of links.
 *
 * @param string $protocol_or_url URL or protocol to determine which icon to use.
 *
 * @return HtmlElement HtmlElement object that contains data to create img link to
 * icon for use with url or protocol passed to the function. False if no img to be
 * displayed.
 */
function IconForLink($protocol_or_url) {
    global $Theme;
    if ($filename_suffix = false) {
        // display apache style icon for file type instead of protocol icon
        // - archive: unix:gz,bz2,tgz,tar,z; mac:dmg,dmgz,bin,img,cpt,sit; pc:zip;
        // - document: html, htm, text, txt, rtf, pdf, doc
        // - non-inlined image: jpg,jpeg,png,gif,tiff,tif,swf,pict,psd,eps,ps
        // - audio: mp3,mp2,aiff,aif,au
        // - multimedia: mpeg,mpg,mov,qt
    } else {
        list ($proto) = explode(':', $protocol_or_url, 2);
        $src = $Theme->getLinkIconURL($proto);
        if ($src)
            return HTML::img(array('src' => $src, 'alt' => $proto, 'class' => 'linkicon', 'border' => 0));
        else
            return false;
    }
}

/**
 * Glue icon in front of text.
 *
 * @param string $protocol_or_url Protocol or URL.  Used to determine the
 * proper icon.
 * @param string $text The text.
 * @return XmlContent.
 */
function PossiblyGlueIconToText($proto_or_url, $text) {
    $icon = IconForLink($proto_or_url);
    if ($icon) {
        preg_match('/^\s*(\S*)(.*?)\s*$/', $text, $m);
        list (, $first_word, $tail) = $m;
        $text = HTML::span(array('style' => 'white-space: nowrap'),
                           $icon, $first_word);
        if ($tail)
            $text = HTML($text, $tail);
    }
    return $text;
}

/**
 * Determines if the url passed to function is safe, by detecting if the characters
 * '<', '>', or '"' are present.
 *
 * @param string $url URL to check for unsafe characters.
 * @return boolean True if same, false else.
 */
function IsSafeURL($url) {
    return !ereg('[<>"]', $url);
}

/**
 * Generates an HtmlElement object to store data for a link.
 *
 * @param string $url URL that the link will point to.
 * @param string $linktext Text to be displayed as link.
 * @return HtmlElement HtmlElement object that contains data to construct an html link.
 */
function LinkURL($url, $linktext = '') {
    // FIXME: Is this needed (or sufficient?)
    if(! IsSafeURL($url)) {
        $link = HTML::strong(HTML::u(array('class' => 'baduri'),
                                     _("BAD URL -- remove all of <, >, \"")));
    }
    else {
        if (!$linktext)
            $linktext = preg_replace("/mailto:/A", "", $url);
        
        $link = HTML::a(array('href' => $url),
                        PossiblyGlueIconToText($url, $linktext));
        
    }
    $link->setAttr('class', $linktext ? 'namedurl' : 'rawurl');
    return $link;
}


function LinkImage($url, $alt = false) {
    // FIXME: Is this needed (or sufficient?)
    if(! IsSafeURL($url)) {
        $link = HTML::strong(HTML::u(array('class' => 'baduri'),
                                     _("BAD URL -- remove all of <, >, \"")));
    }
    else {
        if (empty($alt))
            $alt = $url;
        $link = HTML::img(array('src' => $url, 'alt' => $alt));
    }
    $link->setAttr('class', 'inlineimage');
    return $link;
}



class Stack {
    var $items = array();
    var $size = 0;
    
    function push($item) {
        $this->items[$this->size] = $item;
        $this->size++;
        return true;
    }  
    
    function pop() {
        if ($this->size == 0) {
            return false; // stack is empty
        }  
        $this->size--;
        return $this->items[$this->size];
    }  
    
    function cnt() {
        return $this->size;
    }  
    
    function top() {
        if($this->size)
            return $this->items[$this->size - 1];
        else
            return '';
    }
    
}  
// end class definition


function MakeWikiForm ($pagename, $args, $class, $button_text = '') {
    // HACK: so as to not completely break old PhpWikiAdministration pages.
    trigger_error("MagicPhpWikiURL forms are no longer supported.  "
                  . "Use the WikiFormPlugin instead.", E_USER_NOTICE);

    global $request;
    $loader = new WikiPluginLoader;
    @$action = (string)$args['action'];
    return $loader->expandPI("<?plugin WikiForm action=$action ?>", $request);
}

function SplitQueryArgs ($query_args = '') 
{
    $split_args = split('&', $query_args);
    $args = array();
    while (list($key, $val) = each($split_args))
        if (preg_match('/^ ([^=]+) =? (.*) /x', $val, $m))
            $args[$m[1]] = $m[2];
    return $args;
}

function LinkPhpwikiURL($url, $text = '') {
    $args = array();
    
    if (!preg_match('/^ phpwiki: ([^?]*) [?]? (.*) $/x', $url, $m)) {
        return HTML::strong(array('class' => 'rawurl'),
                            HTML::u(array('class' => 'baduri'),
                                    _("BAD phpwiki: URL")));
    }

    if ($m[1])
        $pagename = urldecode($m[1]);
    $qargs = $m[2];
    
    if (empty($pagename) &&
        preg_match('/^(diff|edit|links|info)=([^&]+)$/', $qargs, $m)) {
        // Convert old style links (to not break diff links in
        // RecentChanges).
        $pagename = urldecode($m[2]);
        $args = array("action" => $m[1]);
    }
    else {
        $args = SplitQueryArgs($qargs);
    }

    if (empty($pagename))
        $pagename = $GLOBALS['request']->getArg('pagename');

    if (isset($args['action']) && $args['action'] == 'browse')
        unset($args['action']);
    
    /*FIXME:
      if (empty($args['action']))
      $class = 'wikilink';
      else if (is_safe_action($args['action']))
      $class = 'wikiaction';
    */
    if (empty($args['action']) || is_safe_action($args['action']))
        $class = 'wikiaction';
    else {
        // Don't allow administrative links on unlocked pages.
        $page = $GLOBALS['request']->getPage();
        if (!$page->get('locked'))
            return HTML::span(array('class' => 'wikiunsafe'),
                              HTML::u(_("Lock page to enable link")));
        $class = 'wikiadmin';
    }
    
    // FIXME: ug, don't like this
    if (preg_match('/=\d*\(/', $qargs))
        return MakeWikiForm($pagename, $args, $class, $text);
    if (!$text)
        $text = HTML::span(array('class' => 'rawurl'), $url);

    return HTML::a(array('href'  => WikiURL($pagename, $args),
                         'class' => $class),
                   $text);
}

/**
 * A class to assist in parsing wiki pagenames.
 *
 * Now with subpages and anchors, parsing and passing around
 * pagenames is more complicated.  This should help.
 */
class WikiPagename
{
    /** Short name for page.
     *
     * This is the value of $name passed to the constructor.
     */
    var $shortName;

    /** The full page name.
     *
     * This is the full name of the page (without anchor).
     */
    var $name;
    
    /** The anchor.
     *
     * This is the referenced anchor within the page, or the empty string.
     */
    var $anchor;
    
    /** Constructor
     *
     * @param string $name Page name.
     * This can be a relative subpage name (like '/SubPage'), and can also
     * include an anchor (e.g. 'SandBox#anchorname' or just '#anchor').
     *
     * If you want to include the character '#' within the page name,
     * you can escape it with ~.  (The escape character doesn't work for '/').
     */
    function WikiPageName($name, $basename) {
 	$this->shortName = $this->unescape($name);

        if ($name[0] == SUBPAGE_SEPARATOR or $name[0] == '#')
            $name = $this->_pagename($basename) . $name;
	
	$split = preg_split("/\s*(?<!" . ESCAPE_CHAR . ")#\s*/", $name, 2);
        if (count($split) > 1)
	    list ($name, $anchor) = $split;
	else
	    $anchor = '';

	$this->name = $this->unescape($name);
	$this->anchor = $this->unescape($anchor);
    }

    function escape($page) {
	return str_replace('#', ESCAPE_CHAR . '#', $page);
    }

    function unescape($page) {
	return preg_replace('/' . ESCAPE_CHAR . '(.)/', '\1', $page);
    }

    function _pagename($page) {
	if (isa($page, 'WikiDB_Page'))
	    return $page->getName();
        elseif (isa($page, 'WikiDB_PageRevision'))
	    return $page->getPageName();
        elseif (isa($page, 'WikiPageName'))
	    return $page->name;
	assert(is_string($page));
	return $page;
    }
}

function LinkBracketLink($bracketlink) {
    global $request, $AllowedProtocols, $InlineImages;

    include_once("lib/interwiki.php");
    $intermap = InterWikiMap::GetMap($request);
    
    // $bracketlink will start and end with brackets; in between will
    // be either a page name, a URL or both separated by a pipe.
    
    // strip brackets and leading space
    preg_match('/(\#?) \[\s* (?: (.+?) \s* (?<!' . ESCAPE_CHAR . ')(\|) )? \s* (.+?) \s*\]/x',
	       $bracketlink, $matches);
    list (, $hash, $label, $bar, $link) = $matches;

    $wikipage = new WikiPageName($link, $request->getPage());
    $label = WikiPageName::unescape($label);
    $link = WikiPageName::unescape($link);

    // if label looks like a url to an image, we want an image link.
    if (preg_match("/\\.($InlineImages)$/i", $label)) {
        $imgurl = $label;
        if (! preg_match("#^($AllowedProtocols):#", $imgurl)) {
            // linkname like 'images/next.gif'.
            global $Theme;
            $imgurl = $Theme->getImageURL($linkname);
        }
        $label = LinkImage($imgurl, $link);
    }

    if ($hash) {
        // It's an anchor, not a link...
        $id = MangleXmlIdentifier($link);
        return HTML::a(array('name' => $id, 'id' => $id),
                       $bar ? $label : $link);
    }

    $dbi = $request->getDbh();
    if ($dbi->isWikiPage($wikipage->name)) {
        return WikiLink($wikipage, 'known', $label);
    }
    elseif (preg_match("#^($AllowedProtocols):#", $link)) {
        // if it's an image, embed it; otherwise, it's a regular link
        if (preg_match("/\\.($InlineImages)$/i", $link))
            // no image link, just the src. see [img|link] above
            return LinkImage($link, $label);
        else
            return LinkURL($link, $label);
    }
    elseif (preg_match("/^phpwiki:/", $link))
        return LinkPhpwikiURL($link, $label);
    elseif (preg_match("/^" . $intermap->getRegexp() . ":/", $link))
        return $intermap->link($link, $label);
    else {
        return WikiLink($wikipage, 'unknown', $label);
    }
}

/**
 * Extract internal links from wiki page.
 *
 * @param mixed $content The raw wiki-text, either as
 * an array of lines or as one big string.
 *
 * @return array List of the names of pages linked to.
 */
function ExtractWikiPageLinks($content) {
    list ($wikilinks,) = ExtractLinks($content);
    return $wikilinks;
}      

/**
 * Extract external links from a wiki page.
 *
 * @param mixed $content The raw wiki-text, either as
 * an array of lines or as one big string.
 *
 * @return array List of the names of pages linked to.
 */
function ExtractExternalLinks($content) {
    list (, $urls) = ExtractLinks($content);
    return $urls;
}      

/**
 * Extract links from wiki page.
 *
 * FIXME: this should be done by the transform code.
 *
 * @param mixed $content The raw wiki-text, either as
 * an array of lines or as one big string.
 *
 * @return array List of two arrays.  The first contains
 * the internal links (names of pages linked to), the second
 * contains external URLs linked to.
 */
function ExtractLinks($content) {
    include_once('lib/interwiki.php');
    global $request, $WikiNameRegexp, $AllowedProtocols;
    
    if (is_string($content))
        $content = explode("\n", $content);
    
    $wikilinks = array();
    $urls = array();
    
    foreach ($content as $line) {
        // remove plugin code
        $line = preg_replace('/<\?plugin\s+\w.*?\?>/', '', $line);
        // remove escaped '['
        $line = str_replace('[[', ' ', $line);
        // remove footnotes
        $line = preg_replace('/\[\d+\]/', ' ', $line);
        
        // bracket links (only type wiki-* is of interest)
        $numBracketLinks = preg_match_all("/\[\s*([^\]|]+\|)?\s*(\S.*?)\s*\]/",
                                          $line, $brktlinks);
        for ($i = 0; $i < $numBracketLinks; $i++) {
            $link = LinkBracketLink($brktlinks[0][$i]);
            $class = $link->getAttr('class');
            if (preg_match('/^(named-)?wiki(unknown)?$/', $class)) {
                if ($brktlinks[2][$i][0] == SUBPAGE_SEPARATOR) {
                    $wikilinks[$request->getArg('pagename') . $brktlinks[2][$i]] = 1;
                } else {
                    $wikilinks[$brktlinks[2][$i]] = 1;
                }
            }
            elseif (preg_match('/^(namedurl|rawurl|(named-)?interwiki)$/', $class)) {
                $urls[$brktlinks[2][$i]] = 1;
            }
            $line = str_replace($brktlinks[0][$i], '', $line);
        }
        
        // Raw URLs
        preg_match_all("/!?\b($AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]/",
                       $line, $link);
        foreach ($link[0] as $url) {
            if ($url[0] <> '!') {
                $urls[$url] = 1;
            }
            $line = str_replace($url, '', $line);
        }

        // Interwiki links
        $map = InterWikiMap::GetMap($request);
        $regexp = pcre_fix_posix_classes("!?(?<![[:alnum:]])") 
            . $map->getRegexp() . ":[^\\s.,;?()]+";
        preg_match_all("/$regexp/", $line, $link);
        foreach ($link[0] as $interlink) {
            if ($interlink[0] <> '!') {
                $link = $map->link($interlink);
                $urls[$link->getAttr('href')] = 1;
            }
            $line = str_replace($interlink, '', $line);
        }

        // BumpyText old-style wiki links
        if (preg_match_all("/!?$WikiNameRegexp/", $line, $link)) {
            for ($i = 0; isset($link[0][$i]); $i++) {
                if($link[0][$i][0] <> '!') {
                    if ($link[0][$i][0] == SUBPAGE_SEPARATOR) {
                        $wikilinks[$request->getArg('pagename') . $link[0][$i]] = 1;
                    } else {
                        $wikilinks[$link[0][$i]] = 1;
                    }
                }
            }
        }
    }
    return array(array_keys($wikilinks), array_keys($urls));
}      


/**
 * Convert old page markup to new-style markup.
 *
 * @param string $text Old-style wiki markup.
 *
 * @param string $markup_type
 * One of: <dl>
 * <dt><code>"block"</code>  <dd>Convert all markup.
 * <dt><code>"inline"</code> <dd>Convert only inline markup.
 * <dt><code>"links"</code>  <dd>Convert only link markup.
 * </dl>
 *
 * @return string New-style wiki markup.
 *
 * @bugs Footnotes don't work quite as before (esp if there are
 *   multiple references to the same footnote.  But close enough,
 *   probably for now....
 */
function ConvertOldMarkup ($text, $markup_type = "block") {

    static $subs;
    static $block_re;
    
    if (empty($subs)) {
        /*****************************************************************
         * Conversions for inline markup:
         */

        // escape tilde's
        $orig[] = '/~/';
        $repl[] = '~~';

        // escape escaped brackets
        $orig[] = '/\[\[/';
        $repl[] = '~[';

        // change ! escapes to ~'s.
        global $AllowedProtocols, $WikiNameRegexp, $request;
        include_once('lib/interwiki.php');
        $map = InterWikiMap::GetMap($request);
        $bang_esc[] = "(?:$AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]";
        $bang_esc[] = $map->getRegexp() . ":[^\\s.,;?()]+"; // FIXME: is this really needed?
        $bang_esc[] = $WikiNameRegexp;
        $orig[] = '/!((?:' . join(')|(', $bang_esc) . '))/';
        $repl[] = '~\\1';

        $subs["links"] = array($orig, $repl);

        // Escape '<'s
        //$orig[] = '/<(?!\?plugin)|(?<!^)</m';
        //$repl[] = '~<';
        
        // Convert footnote references.
        $orig[] = '/(?<=.)(?<!~)\[\s*(\d+)\s*\]/m';
        $repl[] = '#[|ftnt_ref_\\1]<sup>~[[\\1|#ftnt_\\1]~]</sup>';

        // Convert old style emphases to HTML style emphasis.
        $orig[] = '/__(.*?)__/';
        $repl[] = '<strong>\\1</strong>';
        $orig[] = "/''(.*?)''/";
        $repl[] = '<em>\\1</em>';

        // Escape nestled markup.
        $orig[] = '/^(?<=^|\s)[=_](?=\S)|(?<=\S)[=_*](?=\s|$)/m';
        $repl[] = '~\\0';
        
        // in old markup headings only allowed at beginning of line
        $orig[] = '/!/';
        $repl[] = '~!';

        $subs["inline"] = array($orig, $repl);

        /*****************************************************************
         * Patterns which match block markup constructs which take
         * special handling...
         */

        // Indented blocks
        $blockpats[] = '[ \t]+\S(?:.*\s*\n[ \t]+\S)*';

        // Tables
        $blockpats[] = '\|(?:.*\n\|)*';

        // List items
        $blockpats[] = '[#*;]*(?:[*#]|;.*?:)';

        // Footnote definitions
        $blockpats[] = '\[\s*(\d+)\s*\]';

        // Plugins
        $blockpats[] = '<\?plugin(?:-form)?\b.*\?>\s*$';

        // Section Title
        $blockpats[] = '!{1,3}[^!]';

        $block_re = ( '/\A((?:.|\n)*?)(^(?:'
                      . join("|", $blockpats)
                      . ').*$)\n?/m' );
        
    }
    
    if ($markup_type != "block") {
        list ($orig, $repl) = $subs[$markup_type];
        return preg_replace($orig, $repl, $text);
    }
    else {
        list ($orig, $repl) = $subs['inline'];
        $out = '';
        while (preg_match($block_re, $text, $m)) {
            $text = substr($text, strlen($m[0]));
            list (,$leading_text, $block) = $m;
            $suffix = "\n";
            
            if (strchr(" \t", $block[0])) {
                // Indented block
                $prefix = "<pre>\n";
                $suffix = "\n</pre>\n";
            }
            elseif ($block[0] == '|') {
                // Old-style table
                $prefix = "<?plugin OldStyleTable\n";
                $suffix = "\n?>\n";
            }
            elseif (strchr("#*;", $block[0])) {
                // Old-style list item
                preg_match('/^([#*;]*)([*#]|;.*?:) */', $block, $m);
                list (,$ind,$bullet) = $m;
                $block = substr($block, strlen($m[0]));
                
                $indent = str_repeat('     ', strlen($ind));
                if ($bullet[0] == ';') {
                    //$term = ltrim(substr($bullet, 1));
                    //return $indent . $term . "\n" . $indent . '     ';
                    $prefix = $ind . $bullet;
                }
                else
                    $prefix = $indent . $bullet . ' ';
            }
            elseif ($block[0] == '[') {
                // Footnote definition
                preg_match('/^\[\s*(\d+)\s*\]/', $block, $m);
                $footnum = $m[1];
                $block = substr($block, strlen($m[0]));
                $prefix = "#[|ftnt_${footnum}]~[[${footnum}|#ftnt_ref_${footnum}]~] ";
            }
            elseif ($block[0] == '<') {
                // Plugin.
                // HACK: no inline markup...
                $prefix = $block;
                $block = '';
            }
            elseif ($block[0] == '!') {
                // Section heading
                preg_match('/^!{1,3}/', $block, $m);
                $prefix = $m[0];
                $block = substr($block, strlen($m[0]));
            }
            else {
                // AAck!
                assert(0);
            }

            $out .= ( preg_replace($orig, $repl, $leading_text)
                      . $prefix
                      . preg_replace($orig, $repl, $block)
                      . $suffix );
        }
        return $out . preg_replace($orig, $repl, $text);
    }
}


/**
 * Expand tabs in string.
 *
 * Converts all tabs to (the appropriate number of) spaces.
 *
 * @param string $str
 * @param integer $tab_width
 * @return string
 */
function expand_tabs($str, $tab_width = 8) {
    $split = split("\t", $str);
    $tail = array_pop($split);
    $expanded = "\n";
    foreach ($split as $hunk) {
        $expanded .= $hunk;
        $pos = strlen(strrchr($expanded, "\n")) - 1;
        $expanded .= str_repeat(" ", ($tab_width - $pos % $tab_width));
    }
    return substr($expanded, 1) . $tail;
}

/**
 * Split WikiWords in page names.
 *
 * It has been deemed useful to split WikiWords (into "Wiki Words") in
 * places like page titles. This is rumored to help search engines
 * quite a bit.
 *
 * @param $page string The page name.
 *
 * @return string The split name.
 */
function split_pagename ($page) {
    
    if (preg_match("/\s/", $page))
        return $page;           // Already split --- don't split any more.
    
    // FIXME: this algorithm is Anglo-centric.
    static $RE;
    if (!isset($RE)) {
        // This mess splits between a lower-case letter followed by
        // either an upper-case or a numeral; except that it wont
        // split the prefixes 'Mc', 'De', or 'Di' off of their tails.
        $RE[] = '/([[:lower:]])((?<!Mc|De|Di)[[:upper:]]|\d)/';
        // This the single-letter words 'I' and 'A' from any following
        // capitalized words.
	$sep = preg_quote(SUBPAGE_SEPARATOR, '/');
        $RE[] = "/(?<= |${sep}|^)([AI])([[:upper:]][[:lower:]])/";
        // Split numerals from following letters.
        $RE[] = '/(\d)([[:alpha:]])/';
        
        foreach ($RE as $key => $val)
            $RE[$key] = pcre_fix_posix_classes($val);
    }

    foreach ($RE as $regexp) {
	$page = preg_replace($regexp, '\\1 \\2', $page);
    }
    return $page;
}

function NoSuchRevision (&$request, $page, $version) {
    $html = HTML(HTML::h2(_("Revision Not Found")),
                 HTML::p(fmt("I'm sorry.  Version %d of %s is not in the database.",
                             $version, WikiLink($page, 'auto'))));
    include_once('lib/Template.php');
    GeneratePage($html, _("Bad Version"), $page->getCurrentRevision());
    $request->finish();
}


/**
 * Get time offset for local time zone.
 *
 * @param $time time_t Get offset for this time. Default: now.
 * @param $no_colon boolean Don't put colon between hours and minutes.
 * @return string Offset as a string in the format +HH:MM.
 */
function TimezoneOffset ($time = false, $no_colon = false) {
    if ($time === false)
        $time = time();
    $secs = date('Z', $time);

    if ($secs < 0) {
        $sign = '-';
        $secs = -$secs;
    }
    else {
        $sign = '+';
    }
    $colon = $no_colon ? '' : ':';
    $mins = intval(($secs + 30) / 60);
    return sprintf("%s%02d%s%02d",
                   $sign, $mins / 60, $colon, $mins % 60);
}


/**
 * Format time in ISO-8601 format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time in ISO-8601 format.
 */
function Iso8601DateTime ($time = false) {
    if ($time === false)
        $time = time();
    $tzoff = TimezoneOffset($time);
    $date  = date('Y-m-d', $time);
    $time  = date('H:i:s', $time);
    return $date . 'T' . $time . $tzoff;
}

/**
 * Format time in RFC-2822 format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time in RFC-2822 format.
 */
function Rfc2822DateTime ($time = false) {
    if ($time === false)
        $time = time();
    return date('D, j M Y H:i:s ', $time) . TimezoneOffset($time, 'no colon');
}

/**
 * Format time in RFC-1123 format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time in RFC-1123 format.
 */
function Rfc1123DateTime ($time = false) {
    if ($time === false)
        $time = time();
    return gmdate('D, d M Y H:i:s \G\M\T', $time);
}

/** Parse date in RFC-1123 format.
 *
 * According to RFC 1123 we must accept dates in the following
 * formats:
 *
 *   Sun, 06 Nov 1994 08:49:37 GMT  ; RFC 822, updated by RFC 1123
 *   Sunday, 06-Nov-94 08:49:37 GMT ; RFC 850, obsoleted by RFC 1036
 *   Sun Nov  6 08:49:37 1994       ; ANSI C's asctime() format
 *
 * (Though we're only allowed to generate dates in the first format.)
 */
function ParseRfc1123DateTime ($timestr) {
    $timestr = trim($timestr);
    if (preg_match('/^ \w{3},\s* (\d{1,2}) \s* (\w{3}) \s* (\d{4}) \s*'
                   .'(\d\d):(\d\d):(\d\d) \s* GMT $/ix',
                   $timestr, $m)) {
        list(, $mday, $mon, $year, $hh, $mm, $ss) = $m;
    }
    elseif (preg_match('/^ \w+,\s* (\d{1,2})-(\w{3})-(\d{2}|\d{4}) \s*'
                       .'(\d\d):(\d\d):(\d\d) \s* GMT $/ix',
                       $timestr, $m)) {
        list(, $mday, $mon, $year, $hh, $mm, $ss) = $m;
        if ($year < 70) $year += 2000;
        elseif ($year < 100) $year += 1900;
    }
    elseif (preg_match('/^\w+\s* (\w{3}) \s* (\d{1,2}) \s*'
                       .'(\d\d):(\d\d):(\d\d) \s* (\d{4})$/ix',
                       $timestr, $m)) {
        list(, $mon, $mday, $hh, $mm, $ss, $year) = $m;
    }
    else {
        // Parse failed.
        return false;
    }

    $time = strtotime("$mday $mon $year ${hh}:${mm}:${ss} GMT");
    if ($time == -1)
        return false;           // failed
    return $time;
}

/**
 * Format time to standard 'ctime' format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time.
 */
function CTime ($time = false)
{
    if ($time === false)
        $time = time();
    return date("D M j H:i:s Y", $time);
}



/**
 * Internationalized printf.
 *
 * This is essentially the same as PHP's built-in printf
 * with the following exceptions:
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * Example:
 *
 * In php code, use:
 * <pre>
 *    __printf("Differences between versions %s and %s of %s",
 *             $new_link, $old_link, $page_link);
 * </pre>
 *
 * Then in locale/po/de.po, one can reorder the printf arguments:
 *
 * <pre>
 *    msgid "Differences between %s and %s of %s."
 *    msgstr "Der Unterschiedsergebnis von %3$s, zwischen %1$s und %2$s."
 * </pre>
 *
 * (Note that while PHP tries to expand $vars within double-quotes,
 * the values in msgstr undergo no such expansion, so the '$'s
 * okay...)
 *
 * One shouldn't use reordered arguments in the default format string.
 * Backslashes in the default string would be necessary to escape the
 * '$'s, and they'll cause all kinds of trouble....
 */ 
function __printf ($fmt) {
    $args = func_get_args();
    array_shift($args);
    echo __vsprintf($fmt, $args);
}

/**
 * Internationalized sprintf.
 *
 * This is essentially the same as PHP's built-in printf with the
 * following exceptions:
 *
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * @see __printf
 */ 
function __sprintf ($fmt) {
    $args = func_get_args();
    array_shift($args);
    return __vsprintf($fmt, $args);
}

/**
 * Internationalized vsprintf.
 *
 * This is essentially the same as PHP's built-in printf with the
 * following exceptions:
 *
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * @see __printf
 */ 
function __vsprintf ($fmt, $args) {
    $fmt = gettext($fmt);
    // PHP's sprintf doesn't support variable with specifiers,
    // like sprintf("%*s", 10, "x"); --- so we won't either.
    
    if (preg_match_all('/(?<!%)%(\d+)\$/x', $fmt, $m)) {
        // Format string has '%2$s' style argument reordering.
        // PHP doesn't support this.
        if (preg_match('/(?<!%)%[- ]?\d*[^- \d$]/x', $fmt))
            // literal variable name substitution only to keep locale
            // strings uncluttered
            trigger_error(sprintf(_("Can't mix '%s' with '%s' type format strings"),
                                  '%1\$s','%s'), E_USER_WARNING); //php+locale error
        
        $fmt = preg_replace('/(?<!%)%\d+\$/x', '%', $fmt);
        $newargs = array();
        
        // Reorder arguments appropriately.
        foreach($m[1] as $argnum) {
            if ($argnum < 1 || $argnum > count($args))
                trigger_error(sprintf(_("%s: argument index out of range"), 
                                      $argnum), E_USER_WARNING);
            $newargs[] = $args[$argnum - 1];
        }
        $args = $newargs;
    }
    
    // Not all PHP's have vsprintf, so...
    array_unshift($args, $fmt);
    return call_user_func_array('sprintf', $args);
}


class fileSet {
    /**
     * Build an array in $this->_fileList of files from $dirname.
     * Subdirectories are not traversed.
     *
     * (This was a function LoadDir in lib/loadsave.php)
     * See also http://www.php.net/manual/en/function.readdir.php
     */
    function getFiles() {
        return $this->_fileList;
    }

    function _filenameSelector($filename) {
        if (! $this->_pattern)
            return true;
        else {
            return glob_match ($this->_pattern, $filename, $this->_case);
        }
    }

    function fileSet($directory, $filepattern = false) {
        $this->_fileList = array();
        $this->_pattern = $filepattern;
        $this->_case = !isWindows();
        $this->_pathsep = '/';

        if (empty($directory)) {
            trigger_error(sprintf(_("%s is empty."), 'directoryname'),
                          E_USER_NOTICE);
            return; // early return
        }

        @ $dir_handle = opendir($dir=$directory);
        if (empty($dir_handle)) {
            trigger_error(sprintf(_("Unable to open directory '%s' for reading"),
                                  $dir), E_USER_NOTICE);
            return; // early return
        }

        while ($filename = readdir($dir_handle)) {
            if ($filename[0] == '.' || filetype($dir . $this->_pathsep . $filename) != 'file')
                continue;
            if ($this->_filenameSelector($filename)) {
                array_push($this->_fileList, "$filename");
                //trigger_error(sprintf(_("found file %s"), $filename),
                //                      E_USER_NOTICE); //debugging
            }
        }
        closedir($dir_handle);
    }
};

// File globbing

// expands a list containing regex's to its matching entries
class ListRegexExpand {
    var $match, $list, $index, $case_sensitive;
    function ListRegexExpand (&$list, $match, $case_sensitive = true) {
    	$this->match = str_replace('/','\/',$match);
    	$this->list = &$list;
    	$this->case_sensitive = $case_sensitive;	
    }
    function listMatchCallback ($item, $key) {
    	if (preg_match('/' . $this->match . ($this->case_sensitive ? '/' : '/i'), $item)) {
	    unset($this->list[$this->index]);
            $this->list[] = $item;
        }
    }
    function expandRegex ($index, &$pages) {
    	$this->index = $index;
    	array_walk($pages, array($this, 'listMatchCallback'));
        return $this->list;
    }
}

// convert fileglob to regex style
function glob_to_pcre ($glob) {
    $re = preg_replace('/\./', '\\.', $glob);
    $re = preg_replace(array('/\*/','/\?/'), array('.*','.'), $glob);
    if (!preg_match('/^[\?\*]/',$glob))
        $re = '^' . $re;
    if (!preg_match('/[\?\*]$/',$glob))
        $re = $re . '$';
    return $re;
}

function glob_match ($glob, $against, $case_sensitive = true) {
    return preg_match('/' . glob_to_pcre($glob) . ($case_sensitive ? '/' : '/i'), $against);
}

function explodeList($input, $allnames, $glob_style = true, $case_sensitive = true) {
    $list = explode(',',$input);
    // expand wildcards from list of $allnames
    if (preg_match('/[\?\*]/',$input)) {
        for ($i = 0; $i < sizeof($list); $i++) {
            $f = $list[$i];
            if (preg_match('/[\?\*]/',$f)) {
            	reset($allnames);
            	$expand = new ListRegexExpand(&$list, $glob_style ? glob_to_pcre($f) : $f, $case_sensitive);
            	$expand->expandRegex($i, &$allnames);
            }
        }
    }
    return $list;
}

// echo implode(":",explodeList("Test*",array("xx","Test1","Test2")));

function explodePageList($input, $perm = false) {
    // expand wildcards from list of all pages
    if (preg_match('/[\?\*]/',$input)) {
        $dbi = $GLOBALS['request']->_dbi;
        $allPagehandles = $dbi->getAllPages($perm);
        while ($pagehandle = $allPagehandles->next()) {
            $allPages[] = $pagehandle->getName();
        }
        return explodeList($input, &$allPages);
    } else {
        return explode(',',$input);
    }
}

// Class introspections

/** Determine whether object is of a specified type.
 *
 * @param $object object An object.
 * @param $class string Class name.
 * @return bool True iff $object is a $class
 * or a sub-type of $class. 
 */
function isa ($object, $class) 
{
    $lclass = strtolower($class);

    return is_object($object)
        && ( get_class($object) == strtolower($lclass)
             || is_subclass_of($object, $lclass) );
}

/** Determine whether (possible) object has method.
 *
 * @param $object mixed Object
 * @param $method string Method name
 * @return bool True iff $object is an object with has method $method.
 */
function can ($object, $method) 
{
    return is_object($object) && method_exists($object, strtolower($method));
}

/** Hash a value.
 *
 * This is used for generating ETags.
 */
function hash ($x) {
    if (is_scalar($x)) {
        return $x;
    }
    elseif (is_array($x)) {            
        ksort($x);
        return md5(serialize($x));
    }
    elseif (is_object($x)) {
        return $x->hash();
    }
    trigger_error("Can't hash $x", E_USER_ERROR);
}

    
/**
 * Seed the random number generator.
 *
 * better_srand() ensures the randomizer is seeded only once.
 * 
 * How random do you want it? See:
 * http://www.php.net/manual/en/function.srand.php
 * http://www.php.net/manual/en/function.mt-srand.php
 */
function better_srand($seed = '') {
    static $wascalled = FALSE;
    if (!$wascalled) {
        $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
        srand($seed);
        $wascalled = TRUE;
        //trigger_error("new random seed", E_USER_NOTICE); //debugging
    }
}

/**
 * Recursively count all non-empty elements 
 * in array of any dimension or mixed - i.e. 
 * array('1' => 2, '2' => array('1' => 3, '2' => 4))
 * See http://www.php.net/manual/en/function.count.php
 */
function count_all($arg) {
    // skip if argument is empty
    if ($arg) {
        //print_r($arg); //debugging
        $count = 0;
        // not an array, return 1 (base case) 
        if(!is_array($arg))
            return 1;
        // else call recursively for all elements $arg
        foreach($arg as $key => $val)
            $count += count_all($val);
        return $count;
    }
}

function isSubPage($pagename) {
    return (strstr($pagename, SUBPAGE_SEPARATOR));
}

function subPageSlice($pagename, $pos) {
    $pages = explode(SUBPAGE_SEPARATOR,$pagename);
    $pages = array_slice($pages,$pos,1);
    return $pages[0];
}

// $Log: not supported by cvs2svn $
// Revision 1.136  2003/02/18 21:52:07  dairiki
// Fix so that one can still link to wiki pages with # in their names.
// (This was made difficult by the introduction of named tags, since
// '[Page #1]' is now a link to anchor '1' in page 'Page'.
//
// Now the ~ escape for page names should work: [Page ~#1].
//
// Revision 1.135  2003/02/18 19:17:04  dairiki
// split_pagename():
//     Bug fix. 'ThisIsABug' was being split to 'This IsA Bug'.
//     Cleanup up subpage splitting code.
//
// Revision 1.134  2003/02/16 19:44:20  dairiki
// New function hash().  This is a helper, primarily for generating
// HTTP ETags.
//
// Revision 1.133  2003/02/16 04:50:09  dairiki
// New functions:
// Rfc1123DateTime(), ParseRfc1123DateTime()
// for converting unix timestamps to and from strings.
//
// These functions produce and grok the time strings
// in the format specified by RFC 2616 for use in HTTP headers
// (like Last-Modified).
//
// Revision 1.132  2003/01/04 22:19:43  carstenklapp
// Bugfix UnfoldSubpages: "Undefined offset: 1" error when plugin invoked
// on a page with no subpages (explodeList(): array 0-based, sizeof 1-based).
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
