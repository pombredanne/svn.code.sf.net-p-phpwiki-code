<?php 
rcs_id('$Id: InlineParser.php,v 1.91 2007-06-07 18:56:57 rurban Exp $');
/* Copyright (C) 2002 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright (C) 2004,2005,2006,2007 Reini Urban
 *
 * This file is part of PhpWiki.
 * 
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with PhpWiki; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
/**
 * This is the code which deals with the inline part of the (new-style)
 * wiki-markup.
 *
 * @package Markup
 * @author Geoffrey T. Dairiki, Reini Urban
 */
/**
 */

/**
 * This is the character used in wiki markup to escape characters with
 * special meaning.
 */
define('ESCAPE_CHAR', '~');

require_once(dirname(__FILE__).'/HtmlElement.php');
require_once('lib/CachedMarkup.php');
require_once(dirname(__FILE__).'/stdlib.php');


function WikiEscape($text) {
    return str_replace('#', ESCAPE_CHAR . '#', $text);
}

function UnWikiEscape($text) {
    return preg_replace('/' . ESCAPE_CHAR . '(.)/', '\1', $text);
}

/**
 * Return type from RegexpSet::match and RegexpSet::nextMatch.
 *
 * @see RegexpSet
 */
class RegexpSet_match {
    /**
     * The text leading up the the next match.
     */
    var $prematch;
    /**
     * The matched text.
     */
    var $match;
    /**
     * The text following the matched text.
     */
    var $postmatch;
    /**
     * Index of the regular expression which matched.
     */
    var $regexp_ind;
}

/**
 * A set of regular expressions.
 *
 * This class is probably only useful for InlineTransformer.
 */
class RegexpSet
{
    /** Constructor
     *
     * @param array $regexps A list of regular expressions.  The
     * regular expressions should not include any sub-pattern groups
     * "(...)".  (Anonymous groups, like "(?:...)", as well as
     * look-ahead and look-behind assertions are okay.)
     */
    function RegexpSet ($regexps) {
        assert($regexps);
        $this->_regexps = array_unique($regexps);
        if (!defined('_INLINE_OPTIMIZATION')) define('_INLINE_OPTIMIZATION',0);
    }

    /**
     * Search text for the next matching regexp from the Regexp Set.
     *
     * @param string $text The text to search.
     *
     * @return RegexpSet_match  A RegexpSet_match object, or false if no match.
     */
    function match ($text) {
        return $this->_match($text, $this->_regexps, '*?');
    }

    /**
     * Search for next matching regexp.
     *
     * Here, 'next' has two meanings:
     *
     * Match the next regexp(s) in the set, at the same position as the last match.
     *
     * If that fails, match the whole RegexpSet, starting after the position of the
     * previous match.
     *
     * @param string $text Text to search.
     *
     * @param RegexpSet_match $prevMatch A RegexpSet_match object.
     * $prevMatch should be a match object obtained by a previous
     * match upon the same value of $text.
     *
     * @return RegexpSet_match A RegexpSet_match object, or false if no match.
     */
    function nextMatch ($text, $prevMatch) {
        // Try to find match at same position.
        $pos = strlen($prevMatch->prematch);
        $regexps = array_slice($this->_regexps, $prevMatch->regexp_ind + 1);
        if ($regexps) {
            $repeat = sprintf('{%d}', $pos);
            if ( ($match = $this->_match($text, $regexps, $repeat)) ) {
                $match->regexp_ind += $prevMatch->regexp_ind + 1;
                return $match;
            }
            
        }
        
        // Failed.  Look for match after current position.
        $repeat = sprintf('{%d,}?', $pos + 1);
        return $this->_match($text, $this->_regexps, $repeat);
    }

    // Syntax: http://www.pcre.org/pcre.txt
    //   x - EXTENDED, ignore whitespace
    //   s - DOTALL
    //   A - ANCHORED
    //   S - STUDY
    function _match ($text, $regexps, $repeat) {
        // If one of the regexps is an empty string, php will crash here: 
        // sf.net: Fatal error: Allowed memory size of 8388608 bytes exhausted 
        //         (tried to allocate 634 bytes)
        if (_INLINE_OPTIMIZATION) { // disabled, wrong
	    // So we try to minize memory usage, by looping explicitly,
	    // and storing only those regexp which actually match. 
	    // There may be more than one, so we have to find the longest, 
	    // and match inside until the shortest is empty.
	    $matched = array(); $matched_ind = array();
	    for ($i=0; $i<count($regexps); $i++) {
		if (!trim($regexps[$i])) {
		    trigger_error("empty regexp $i", E_USER_WARNING);
		    continue;
		}
		$pat= "/ ( . $repeat ) ( " . $regexps[$i] . " ) /x";
		if (preg_match($pat, $text, $_m)) {
		    $m = $_m; // FIXME: prematch, postmatch is wrong
		    $matched[] = $regexps[$i];
		    $matched_ind[] = $i;
		    $regexp_ind = $i;
		}
	    }
	    // To overcome ANCHORED:
	    // We could sort by longest match and iterate over these.
	    if (empty($matched)) return false;
        }
        $match = new RegexpSet_match;
        
        // Optimization: if the matches are only "$" and another, then omit "$"
        if (! _INLINE_OPTIMIZATION or count($matched) > 2) {
            assert(!empty($repeat));
            assert(!empty($regexps));
            // We could do much better, if we would know the matching markup for the 
            // longest regexp match:
            $hugepat= "/ ( . $repeat ) ( (" . join(')|(', $regexps) . ") ) /Asx";
            // Proposed premature optimization 1:
            //$hugepat= "/ ( . $repeat ) ( (" . join(')|(', array_values($matched)) . ") ) /Asx";
            if (! preg_match($hugepat, $text, $m)) {
                return false;
            }
            // Proposed premature optimization 1:
            //$match->regexp_ind = $matched_ind[count($m) - 4];
            $match->regexp_ind = count($m) - 4;
        } else {
            $match->regexp_ind = $regexp_ind;
        }
        
        $match->postmatch = substr($text, strlen($m[0]));
        $match->prematch = $m[1];
        $match->match = $m[2];

        /* DEBUGGING */
        if (DEBUG & _DEBUG_PARSER) {
          static $_already_dumped = 0;
          if (!$_already_dumped) {
            var_dump($regexps); 
            if (_INLINE_OPTIMIZATION)
            	var_dump($matched);
            var_dump($matched_inc); 
          }
          $_already_dumped = 1;
          PrintXML(HTML::dl(HTML::dt("input"),
                          HTML::dd(HTML::pre($text)),
                          HTML::dt("regexp"),
                          HTML::dd(HTML::pre($match->regexp_ind, ":", $regexps[$match->regexp_ind])),
                          HTML::dt("prematch"),
                          HTML::dd(HTML::pre($match->prematch)),
                          HTML::dt("match"),
                          HTML::dd(HTML::pre($match->match)),
                          HTML::dt("postmatch"),
                          HTML::dd(HTML::pre($match->postmatch))
                          ));
        }
        return $match;
    }
}



/**
 * A simple markup rule (i.e. terminal token).
 *
 * These are defined by a regexp.
 *
 * When a match is found for the regexp, the matching text is replaced.
 * The replacement content is obtained by calling the SimpleMarkup::markup method.
 */ 
class SimpleMarkup
{
    var $_match_regexp;

    /** Get regexp.
     *
     * @return string Regexp which matches this token.
     */
    function getMatchRegexp () {
        return $this->_match_regexp;
    }

    /** Markup matching text.
     *
     * @param string $match The text which matched the regexp
     * (obtained from getMatchRegexp).
     *
     * @return mixed The expansion of the matched text.
     */
    function markup ($match /*, $body */) {
        trigger_error("pure virtual", E_USER_ERROR);
    }
}

/**
 * A balanced markup rule.
 *
 * These are defined by a start regexp, and an end regexp.
 */ 
class BalancedMarkup
{
    var $_start_regexp;

    /** Get the starting regexp for this rule.
     *
     * @return string The starting regexp.
     */
    function getStartRegexp () {
        return $this->_start_regexp;
    }
    
    /** Get the ending regexp for this rule.
     *
     * @param string $match The text which matched the starting regexp.
     *
     * @return string The ending regexp.
     */
    function getEndRegexp ($match) {
        return $this->_end_regexp;
    }

    /** Get expansion for matching input.
     *
     * @param string $match The text which matched the starting regexp.
     *
     * @param mixed $body Transformed text found between the starting
     * and ending regexps.
     *
     * @return mixed The expansion of the matched text.
     */
    function markup ($match, $body) {
        trigger_error("pure virtual", E_USER_ERROR);
    }
}

class Markup_escape  extends SimpleMarkup
{
    function getMatchRegexp () {
        return ESCAPE_CHAR . '(?: [[:alnum:]]+ | .)';
    }
    
    function markup ($match) {
        assert(strlen($match) >= 2);
        return substr($match, 1);
    }
}

/**
 * [image.jpg size=50% border=5], [image.jpg size=50x30]
 * Support for the following attributes: see stdlib.php:LinkImage()
 *   size=<precent>%, size=<width>x<height>
 *   border=n, align=\w+, hspace=n, vspace=n
 */
function isImageLink($link) {
    if (!$link) return false;
    assert(defined('INLINE_IMAGES'));
    return preg_match("/\\.(" . INLINE_IMAGES . ")$/i", $link)
        or preg_match("/\\.(" . INLINE_IMAGES . ")\s+(size|border|align|hspace|vspace)=/i", $link);
}

function LinkBracketLink($bracketlink) {

    // $bracketlink will start and end with brackets; in between will
    // be either a page name, a URL or both separated by a pipe.
    
    // Strip brackets and leading space
    // FIXME: \n inside [] will lead to errors
    preg_match('/(\#?) \[\s* (?: (.*?) \s* (?<!' . ESCAPE_CHAR . ')(\|) )? \s* (.+?) \s*\]/x',
	       $bracketlink, $matches);
    if (count($matches) < 4) {
    	trigger_error(_("Invalid [] syntax ignored").": ".$bracketlink, E_USER_WARNING);
    	return new Cached_Link;
    }
    list (, $hash, $label, $bar, $rawlink) = $matches;

    $label = UnWikiEscape($label);
    /*
     * Check if the user has typed a explicit URL. This solves the
     * problem where the URLs have a ~ character, which would be stripped away.
     *   "[http:/server/~name/]" will work as expected
     *   "http:/server/~name/"   will NOT work as expected, will remove the ~
     */
    if (   string_starts_with ($rawlink, "http://")
        or string_starts_with ($rawlink, "https://") ) 
    {
        $link = $rawlink;
        // Mozilla Browser URI Obfuscation Weakness 2004-06-14
        //   http://www.securityfocus.com/bid/10532/
        //   goodurl+"%2F%20%20%20."+badurl
        if (preg_match("/%2F(%20)+\./i", $rawlink)) {
            $rawlink = preg_replace("/%2F(%20)+\./i","%2F.",$rawlink);
        }
    } else
        $link  = UnWikiEscape($rawlink);

    /* Relatives links by Joel Schaubert.
     * Recognize [../bla] or [/bla] as relative links, without needing http://
     * but [ /link ] only if SUBPAGE_SEPERATOR is not "/". 
     * Normally /Page links to the subpage /Page.
     */
    if (SUBPAGE_SEPARATOR == '/') {
    	if (preg_match('/^\.\.\//', $link)) {
            return new Cached_ExternalLink($link, $label);
    	}
    } else if (preg_match('/^(\.\.\/|\/)/', $link)) {
        return new Cached_ExternalLink($link, $label);
    }
    // [label|link]
    // if label looks like a url to an image, we want an image link.
    if (isImageLink($label)) {
        $imgurl = $label;
        $intermap = getInterwikiMap();
        if (preg_match("/^" . $intermap->getRegexp() . ":/", $label)) {
            $imgurl = $intermap->link($label);
            $imgurl = $imgurl->getAttr('href');
        } elseif (! preg_match("#^(" . ALLOWED_PROTOCOLS . "):#", $imgurl)) {
            // local theme linkname like 'images/next.gif'.
            global $WikiTheme;
            $imgurl = $WikiTheme->getImageURL($imgurl);
        }
        $label = LinkImage($imgurl, $link);
    }

    if ($hash) {
        // It's an anchor, not a link...
        $id = MangleXmlIdentifier($link);
        return HTML::a(array('name' => $id, 'id' => $id),
                       $bar ? $label : $link);
    }

    if (preg_match("#^(" . ALLOWED_PROTOCOLS . "):#", $link)) {
        // if it's an image, embed it; otherwise, it's a regular link
        if (isImageLink($link) and empty($label)) // patch #1348996 by Robert Litwiniec
            return LinkImage($link, $label);
        else
            return new Cached_ExternalLink($link, $label);
    }
    elseif (substr($link,0,8) == 'phpwiki:')
        return new Cached_PhpwikiURL($link, $label);

    /* Semantic relations and attributes. 
     * Relation and attribute names must be word chars only, no space.
     * Links and Attributes may contain everything. word, nums, units, space, groupsep, numsep, ...
     */
    elseif (preg_match("/^ (\w+) (:[:=]) (.*) $/x", $link) and !isImageLink($link))
        return new Cached_SemanticLink($link, $label);

    /* Do not store the link */    
    elseif (substr($link,0,1) == ':')
        return new Cached_WikiLink($link, $label);

    /*
     * Inline images in Interwiki urls's:
     * [File:my_image.gif] inlines the image,
     * File:my_image.gif shows a plain inter-wiki link,
     * [what a pic|File:my_image.gif] shows a named inter-wiki link to the gif
     * [File:my_image.gif|what a pic] shows a inlimed image linked to the page "what a pic"
     *
     * Note that for simplicity we will accept embedded object tags (non-images) 
     * here also, and seperate them later in LinkImage()
     */
    elseif (strstr($link,':')
            and ($intermap = getInterwikiMap()) 
            and preg_match("/^" . $intermap->getRegexp() . ":/", $link)) 
    {
        // trigger_error("label: $label link: $link", E_USER_WARNING);
        if (empty($label) and isImageLink($link)) {
            // if without label => inlined image [File:xx.gif]
            $imgurl = $intermap->link($link);
            return LinkImage($imgurl->getAttr('href'), $label);
        }
        return new Cached_InterwikiLink($link, $label);
    } else {
        // Split anchor off end of pagename.
        if (preg_match('/\A(.*)(?<!'.ESCAPE_CHAR.')#(.*?)\Z/', $rawlink, $m)) {
            list(,$rawlink,$anchor) = $m;
            $pagename = UnWikiEscape($rawlink);
            $anchor = UnWikiEscape($anchor);
            if (!$label)
                $label = $link;
        }
        else {
            $pagename = $link;
            $anchor = false;
        }
        return new Cached_WikiLink($pagename, $label, $anchor);
    }
}

class Markup_bracketlink  extends SimpleMarkup
{
    var $_match_regexp = "\\#? \\[ .*? [^]\\s] .*? \\]";
    
    function markup ($match) {
        $link = LinkBracketLink($match);
        assert($link->isInlineElement());
        return $link;
    }
}

class Markup_spellcheck extends SimpleMarkup
{
    function Markup_spellcheck () {
	$this->suggestions = $GLOBALS['request']->getArg('suggestions');
    }
    function getMatchRegexp () {
    	if (empty($this->suggestions))
    	    return "(?# false )";
	$words = array_keys($this->suggestions);
        return "(?<= \W ) (?:" . join('|', $words) . ") (?= \W )";
    }
    
    function markup ($match) {
    	if (empty($this->suggestions) or empty($this->suggestions[$match]))
    	    return $match;
        return new Cached_SpellCheck(UnWikiEscape($match), $this->suggestions[$match]);
    }
}

class Markup_searchhighlight extends SimpleMarkup
{
    function Markup_searchhighlight () {
        $result = $GLOBALS['request']->_searchhighlight;
        require_once("lib/TextSearchQuery.php");
        $query = new TextSearchQuery($result['query']);
        $this->hilight_re = $query->getHighlightRegexp();
        $this->engine = $result['engine'];
    }
    function getMatchRegexp () {
        return $this->hilight_re;
    }
    function markup ($match) {
        return new Cached_SearchHighlight(UnWikiEscape($match), $this->engine);
    }
}

class Markup_url extends SimpleMarkup
{
    function getMatchRegexp () {
        return "(?<![[:alnum:]]) (?:" . ALLOWED_PROTOCOLS . ") : [^\s<>\"']+ (?<![ ,.?; \] \) ])";
    }
    
    function markup ($match) {
        return new Cached_ExternalLink(UnWikiEscape($match));
    }
}

class Markup_interwiki extends SimpleMarkup
{
    function getMatchRegexp () {
        $map = getInterwikiMap();
        return "(?<! [[:alnum:]])" . $map->getRegexp(). ": [^:=]\S+ (?<![ ,.?;! \] \) \" \' ])";
    }

    function markup ($match) {
        return new Cached_InterwikiLink(UnWikiEscape($match));
    }
}

class Markup_semanticlink extends SimpleMarkup
{
    var $_match_regexp = "(?:\w+:[:=]\S+)"; // no units seperated by space allowed here

    function markup ($match) {
        return new Cached_SemanticLink(UnWikiEscape($match));
    }
}

class Markup_wikiword extends SimpleMarkup
{
    function getMatchRegexp () {
        global $WikiNameRegexp;
        if (!trim($WikiNameRegexp)) return " " . WIKI_NAME_REGEXP;
        return " $WikiNameRegexp";
    }

    function markup ($match) {
        if (!$match) return false;
        if ($this->_isWikiUserPage($match))
            return new Cached_UserLink($match); //$this->_UserLink($match);
        else
            return new Cached_WikiLink($match);
    }

    // FIXME: there's probably a more useful place to put these two functions    
    function _isWikiUserPage ($page) {
        global $request;
        $dbi = $request->getDbh();
        $page_handle = $dbi->getPage($page);
        if ($page_handle and $page_handle->get('pref'))
            return true;
        else
            return false;
    }

    function _UserLink($PageName) {
        $link = HTML::a(array('href' => $PageName));
        $link->pushContent(PossiblyGlueIconToText('wikiuser', $PageName));
        $link->setAttr('class', 'wikiuser');
        return $link;
    }
}

class Markup_linebreak extends SimpleMarkup
{
    //var $_match_regexp = "(?: (?<! %) %%% (?! %) | <(?:br|BR)> | <(?:br|BR) \/> )";
    var $_match_regexp = "(?: (?<! %) %%% (?! %) | <(?:br|BR)> )";

    function markup ($match) {
        return HTML::br();
    }
}

class Markup_old_emphasis  extends BalancedMarkup
{
    var $_start_regexp = "''|__";

    function getEndRegexp ($match) {
        return $match;
    }
    
    function markup ($match, $body) {
        $tag = $match == "''" ? 'em' : 'strong';
        return new HtmlElement($tag, $body);
    }
}

class Markup_nestled_emphasis extends BalancedMarkup
{
    function getStartRegexp() {
	static $start_regexp = false;

	if (!$start_regexp) {
	    // The three possible delimiters
            // (none of which can be followed by itself.)
	    $i = "_ (?! _)";
	    $b = "\\* (?! \\*)";
	    $tt = "= (?! =)";

	    $any = "(?: ${i}|${b}|${tt})"; // any of the three.

	    // Any of [_*=] is okay if preceded by space or one of [-"'/:]
	    $start[] = "(?<= \\s|^|[-\"'\\/:]) ${any}";

	    // _ or * is okay after = as long as not immediately followed by =
	    $start[] = "(?<= =) (?: ${i}|${b}) (?! =)";
	    // etc...
	    $start[] = "(?<= _) (?: ${b}|${tt}) (?! _)";
	    $start[] = "(?<= \\*) (?: ${i}|${tt}) (?! \\*)";


	    // any delimiter okay after an opening brace ( [{<(] )
	    // as long as it's not immediately followed by the matching closing
	    // brace.
	    $start[] = "(?<= { ) ${any} (?! } )";
	    $start[] = "(?<= < ) ${any} (?! > )";
	    $start[] = "(?<= \\( ) ${any} (?! \\) )";
	    
	    $start = "(?:" . join('|', $start) . ")";
	    
	    // Any of the above must be immediately followed by non-whitespace.
	    $start_regexp = $start . "(?= \S)";
	}

	return $start_regexp;
    }

    function getEndRegexp ($match) {
        $chr = preg_quote($match);
        return "(?<= \S | ^ ) (?<! $chr) $chr (?! $chr) (?= \s | [-)}>\"'\\/:.,;!? _*=] | $)";
    }
    
    function markup ($match, $body) {
        switch ($match) {
        case '*': return new HtmlElement('b', $body);
        case '=': return new HtmlElement('tt', $body);
        case '_': return new HtmlElement('i', $body);
        }
    }
}

class Markup_html_emphasis extends BalancedMarkup
{
    var $_start_regexp = 
        "<(?: b|big|i|small|tt|em|strong|cite|code|dfn|kbd|samp|strike|del|var|sup|sub )>";

    function getEndRegexp ($match) {
        return "<\\/" . substr($match, 1);
    }
    
    function markup ($match, $body) {
        $tag = substr($match, 1, -1);
        return new HtmlElement($tag, $body);
    }
}

class Markup_html_divspan extends BalancedMarkup
{
    var $_start_regexp = 
        "<(?: div|span )(?: \s[^>]*)?>";

    function getEndRegexp ($match) {
    	if (substr($match,1,4) == 'span')
    	    $tag = 'span';
    	else
    	    $tag = 'div';
        return "<\\/" . $tag . '>';
    }
    
    function markup ($match, $body) {
    	if (substr($match,1,4) == 'span')
    	    $tag = 'span';
    	else
    	    $tag = 'div';
    	$rest = substr($match,1+strlen($tag),-1);
    	if (!empty($rest)) {
    	    list($key,$val) = explode("=",$rest);
    	    $args = array($key => $val);
    	} else $args = array();
        return new HtmlElement($tag, $args, $body);
    }
}


class Markup_html_abbr extends BalancedMarkup
{
    //rurban: abbr|acronym need an optional title tag.
    //sf.net bug #728595
    var $_start_regexp = "<(?: abbr|acronym )(?: \stitle=[^>]*)?>";

    function getEndRegexp ($match) {
    	if (substr($match,1,4) == 'abbr')
    	    $tag = 'abbr';
    	else
    	    $tag = 'acronym';
        return "<\\/" . $tag . '>';
    }
    
    function markup ($match, $body) {
    	if (substr($match,1,4) == 'abbr')
    	    $tag = 'abbr';
    	else
    	    $tag = 'acronym';
    	$rest = substr($match,1+strlen($tag),-1);
    	if (!empty($rest)) {
    	    list($key,$val) = explode("=",$rest);
    	    $args = array($key => $val);
    	} else $args = array();
        return new HtmlElement($tag, $args, $body);
    }
}

/** ENABLE_MARKUP_COLOR
 *  See http://www.pmwiki.org/wiki/PmWiki/WikiStyles and
 *      http://www.flexwiki.com/default.aspx/FlexWiki/FormattingRules.html
 */
class Markup_color extends BalancedMarkup {
    // %color=blue% blue text %% and back to normal
    var $_start_regexp = "%color=(?: [^%]*)%";
    var $_end_regexp = "%%";
    
    function markup ($match, $body) {
    	$color = strtoupper(substr($match, 7, -1));
        if (strlen($color) != 7 
            and in_array($color, array('RED', 'BLUE', 'GRAY', 'YELLOW', 'GREEN', 'CYAN', 'BLACK'))) 
	{   // must be a valid color name
            return new HtmlElement('font', array('color' => $color), $body);
        } elseif ((substr($color,0,1) == '#') 
                  and (strspn(substr($color,1),'0123456789ABCDEF') == strlen($color)-1)) {
            return new HtmlElement('font', array('color' => $color), $body);
        } else {
            trigger_error(sprintf(_("unknown color %s ignored"), substr($match, 7, -1)), E_USER_WARNING);
        }
        	
    }
}

// Special version for single-line plugins formatting, 
//  like: '<small>< ?plugin PopularNearby ? ></small>'
class Markup_plugin extends SimpleMarkup
{
    var $_match_regexp = '<\?plugin(?:-form)?\s[^\n]+?\?>';

    function markup ($match) {
	//$xml = new Cached_PluginInvocation($match);
	//$xml->setTightness(true,true);
	return new Cached_PluginInvocation($match);
    }
}

// Special version for plugins in xml syntax 
// <name arg=value>body</name> or <name /> => < ? plugin pluginname arg=value body ? >
// PLUGIN_MARKUP_MAP = "html:RawHtml dot:GraphViz toc:CreateToc amath:AsciiMath richtable:RichTable include:IncludePage tex:TexToPng"
class Markup_xml_plugin extends BalancedMarkup
{
    //var $_start_regexp = "<(?: ".join('|',PLUGIN_MARKUP_MAP)." )(?: \s[^>]*)>";

    function getStartRegexp () {
	global $PLUGIN_MARKUP_MAP;
        static $_start_regexp;
        if ($_start_regexp) return $_start_regexp;
        if (empty($PLUGIN_MARKUP_MAP))
            return '';
        //"<(?: html|dot|toc|amath|richtable|include|tex )(?: \s[^>]*)>"
	$_start_regexp = "<(?: ".join('|',array_keys($PLUGIN_MARKUP_MAP))." )(?: \s[^>]* | / )>";
        return $_start_regexp;
    }
    function getEndRegexp ($match) {
        return "<\\/" . $match . '>';
    }
    function markup ($match, $body) {
	global $PLUGIN_MARKUP_MAP;
        $name = substr($match,2,-2); 
	$vars = '';
        if (preg_match('/^(\S+)\|(.*)$/', $name, $_m)) {
            $name = $_m[1];
            $vars = $_m[2]; //str_replace(' ', '&', $_m[2]);
        }
        if (!isset($PLUGIN_MARKUP_MAP[$name])) {
            trigger_error("No plugin for $name $vars defined.", E_USER_WARNING);
            return "";
        }
        $plugin = $PLUGIN_MARKUP_MAP[$name];
	return new Cached_PluginInvocation("<"."?plugin $plugin $vars $body ?".">");
    }
}

/** ENABLE_MARKUP_TEMPLATE
 *  Template syntax similar to mediawiki
 *  {{template}}
 * => < ? plugin Template page=template ? >
 *  {{template|var=value|...}}
 * => < ? plugin Template page=template var=value ... ? >
 */
class Markup_template_plugin  extends SimpleMarkup
{
    // patch #1732793: allow \n, mult. {{ }} in one line, and single letters
    var $_match_regexp = '\{\{.*?\}\}';
    
    function markup ($match) {
        $page = substr(str_replace("\n", "", $match),2,-2); $vars = '';
        if (preg_match('/^(\S+)\|(.*)$/', $page, $_m)) {
            $page = $_m[1];
            $vars = '"' . preg_replace('/\|/', '" "', $_m[2]) . '"'; 
            $vars = preg_replace('/"(\S+)=([^"]*)"/', '\\1="\\2"', $vars);
        }
        if ($vars)
    	    $s = '<'.'?plugin Template page="'.$page.'" '.$vars.' ?'.'>';
    	else
    	    $s = '<'.'?plugin Template page="' . $page . '" ?'.'>';
	return new Cached_PluginInvocation($s);
    }
}

// "..." => "&#133;"  browser specific display (not cached?)
// Support some HTML::Entities: (C) for copy, --- for mdash, -- for ndash
// TODO: "--" => "&emdash;" browser specific display (not cached?)

class Markup_html_entities  extends SimpleMarkup {
    //var $_match_regexp = '(: \.\.\.|\-\-|\-\-\-|\(C\) )';

    function Markup_html_entities() {
        $this->_entities = array('...'  => '&#133;',
                                 '--'   => '&ndash;',
                                 '---'  => '&mdash;',
                                 '(C)'  => '&copy;',
                                 '&copy;' => '&copy;',
                                 '&trade;'  => '&trade;',
                                 );
        $this->_match_regexp = 
            '(: ' . 
            join('|', array_map('preg_quote', array_keys($this->_entities))) . 
            ' )';
    }
   
    function markup ($match) {
        return HTML::Raw($this->_entities[$match]);
    }
}

class Markup_isonumchars  extends SimpleMarkup {
    var $_match_regexp = '\&\#\d{2,5};';
    
    function markup ($match) {
        return HTML::Raw($match);
    }
}

class Markup_isohexchars extends SimpleMarkup {
    // hexnums, like &#x00A4; <=> &curren;
    var $_match_regexp = '\&\#x[0-9a-fA-F]{2,4};';
    
    function markup ($match) {
        return HTML::Raw($match);
    }
}

// FIXME: Do away with magic phpwiki forms.  (Maybe phpwiki: links too?)
// FIXME: Do away with plugin-links.  They seem not to be used.
//Plugin link

class InlineTransformer
{
    var $_regexps = array();
    var $_markup = array();
    
    function InlineTransformer ($markup_types = false) {
        global $request;
	// We need to extend the inline parsers by certain actions, like SearchHighlight, 
	// SpellCheck and maybe CreateToc.
        if (!$markup_types) {
            $non_default = false;
            $markup_types = array
                ('escape', 'bracketlink', 'url',
                 'interwiki',  'semanticlink', 'wikiword', 'linebreak',
                 'old_emphasis', 'nestled_emphasis',
                 'html_emphasis', 'html_abbr', 'plugin',
                 'isonumchars', 'isohexchars', /*'html_entities'*/
                 );
	    if (DISABLE_MARKUP_WIKIWORD)
                $markup_types = array_remove($markup_types, 'wikiword');

	    $action = $request->getArg('action');
	    if ($action == 'SpellCheck' and $request->getArg('suggestions'))
	    {   // insert it after url
		array_splice($markup_types, 2, 1, array('url','spellcheck'));
	    }
	    if (isset($request->_searchhighlight))
	    {   // insert it after url
		array_splice($markup_types, 2, 1, array('url','searchhighlight'));
                //$request->setArg('searchhighlight', false);
	    }
        } else {
            $non_default = true;
	}
        foreach ($markup_types as $mtype) {
            $class = "Markup_$mtype";
            $this->_addMarkup(new $class);
        }
        if (ENABLE_MARKUP_DIVSPAN and !$non_default)
            $this->_addMarkup(new Markup_html_divspan);
        if (ENABLE_MARKUP_COLOR and !$non_default)
            $this->_addMarkup(new Markup_color);
        if (ENABLE_MARKUP_TEMPLATE and !$non_default)
            $this->_addMarkup(new Markup_template_plugin);
        // This does not work yet
        if (0 and PLUGIN_MARKUP_MAP and !$non_default)
            $this->_addMarkup(new Markup_xml_plugin);
    }

    function _addMarkup ($markup) {
        if (isa($markup, 'SimpleMarkup'))
            $regexp = $markup->getMatchRegexp();
        else
            $regexp = $markup->getStartRegexp();

        assert( !isset($this->_markup[$regexp]) );
        assert( strlen(trim($regexp)) > 0 );
        $this->_regexps[] = $regexp;
        $this->_markup[] = $markup;
    }
        
    function parse (&$text, $end_regexps = array('$')) {
        $regexps = $this->_regexps;

        // $end_re takes precedence: "favor reduce over shift"
        array_unshift($regexps, $end_regexps[0]);
        //array_push($regexps, $end_regexps[0]);
        $regexps = new RegexpSet($regexps);
        
        $input = $text;
        $output = new XmlContent;

        $match = $regexps->match($input);
        
        while ($match) {
            if ($match->regexp_ind == 0) {
                // No start pattern found before end pattern.
                // We're all done!
                if (isset($markup) and is_object($markup) 
                    and isa($markup,'Markup_plugin')) 
                {
                    $current =& $output->_content[count($output->_content)-1];
                    $current->setTightness(true,true);
                }
                $output->pushContent($match->prematch);
                $text = $match->postmatch;
                return $output;
            }

            $markup = $this->_markup[$match->regexp_ind - 1];
            $body = $this->_parse_markup_body($markup, $match->match, 
                                              $match->postmatch, $end_regexps);
            if (!$body) {
                // Couldn't match balanced expression.
                // Ignore and look for next matching start regexp.
                $match = $regexps->nextMatch($input, $match);
                continue;
            }

            // Matched markup.  Eat input, push output.
            // FIXME: combine adjacent strings.
            if (isa($markup, 'SimpleMarkup'))
                $current = $markup->markup($match->match);
            else
                $current = $markup->markup($match->match, $body);
            $input = $match->postmatch;
            if (isset($markup) and is_object($markup) 
                and isa($markup,'Markup_plugin')) 
            {
                $current->setTightness(true,true);
            }
            $output->pushContent($match->prematch, $current);

            $match = $regexps->match($input);
        }

        // No pattern matched, not even the end pattern.
        // Parse fails.
        return false;
    }

    function _parse_markup_body ($markup, $match, &$text, $end_regexps) {
        if (isa($markup, 'SimpleMarkup'))
            return true;        // Done. SimpleMarkup is simple.

        if (!is_object($markup)) return false; // Some error: Should assert
        array_unshift($end_regexps, $markup->getEndRegexp($match));

        // Optimization: if no end pattern in text, we know the
        // parse will fail.  This is an important optimization,
        // e.g. when text is "*lots *of *start *delims *with
        // *no *matching *end *delims".
        $ends_pat = "/(?:" . join(").*(?:", $end_regexps) . ")/xs";
        if (!preg_match($ends_pat, $text))
            return false;
        return $this->parse($text, $end_regexps);
    }
}

class LinkTransformer extends InlineTransformer
{
    function LinkTransformer () {
        $this->InlineTransformer(array('escape', 'bracketlink', 'url',
                                       'semanticlink', 'interwiki', 'wikiword', 
                                       ));
    }
}

class NowikiTransformer extends InlineTransformer
{
    function NowikiTransformer () {
        $this->InlineTransformer
            (array('linebreak',
                   'html_emphasis', 'html_abbr', 'plugin',
                   'isonumchars', 'isohexchars', /*'html_entities',*/
                   ));
    }
}

function TransformInline($text, $markup = 2.0, $basepage=false) {
    static $trfm;
    $action = $GLOBALS['request']->getArg('action');
    if (empty($trfm) or $action == 'SpellCheck') {
        $trfm = new InlineTransformer;
    }
    
    if ($markup < 2.0) {
        $text = ConvertOldMarkup($text, 'inline');
    }

    if ($basepage) {
        return new CacheableMarkup($trfm->parse($text), $basepage);
    }
    return $trfm->parse($text);
}

function TransformLinks($text, $markup = 2.0, $basepage = false) {
    static $trfm;
    
    if (empty($trfm)) {
        $trfm = new LinkTransformer;
    }

    if ($markup < 2.0) {
        $text = ConvertOldMarkup($text, 'links');
    }
    
    if ($basepage) {
        return new CacheableMarkup($trfm->parse($text), $basepage);
    }
    return $trfm->parse($text);
}

/**
 * Transform only html markup and entities.
 */
function TransformInlineNowiki($text, $markup = 2.0, $basepage=false) {
    static $trfm;
    
    if (empty($trfm)) {
        $trfm = new NowikiTransformer;
    }
    if ($basepage) {
        return new CacheableMarkup($trfm->parse($text), $basepage);
    }
    return $trfm->parse($text);
}


// $Log: not supported by cvs2svn $
// Revision 1.90  2007/03/18 17:35:14  rurban
// Fix :DontStoreLink
//
// Revision 1.89  2007/02/17 14:16:28  rurban
// fix color GREY to GRAY
//
// Revision 1.88  2007/01/21 13:15:50  rurban
// Support spaces in attributes and relation links
//
// Revision 1.87  2007/01/20 15:53:51  rurban
// Rewrite of SearchHighlight: through ActionPage and InlineParser
//
// Revision 1.86  2007/01/20 11:25:07  rurban
// add SpellCheck support
//
// Revision 1.85  2007/01/07 18:42:49  rurban
// Add support for non-bracket semantic relation parsing. Assert empty regex (interwikimap?) earlier. Change {{Template||}} vars handling to new style. Stricter interwikimap matching not to find semantic links
//
// Revision 1.84  2007/01/02 13:18:07  rurban
// fix semantic attributes syntax :=, not :-, disable DIVSPAN and PLUGIN_MARKUP_MAP
//
// Revision 1.83  2006/12/22 00:23:24  rurban
// Fix Bug #1540007 "hardened-php issue, crawlers related"
// Broken str_replace with strings > 200 chars
//
// Revision 1.82  2006/12/02 19:53:05  rurban
// Simplify DISABLE_MARKUP_WIKIWORD handling by adding the new function
// stdlib: array_remove(). Hopefully PHP will not add this natively sooner
// or later.
//
// Revision 1.81  2006/11/19 13:52:52  rurban
// improve debug output: regex only once
//
// Revision 1.80  2006/10/12 06:32:30  rurban
// Optionally support new tags <div>, <span> with ENABLE_MARKUP_DIVSPAN (in work)
//
// Revision 1.79  2006/10/08 12:38:11  rurban
// New special interwiki link markup [:LinkTo] without storing the backlink
//
// Revision 1.78  2006/09/03 09:53:52  rurban
// more colors, case-insensitive color names
//
// Revision 1.77  2006/08/25 19:02:02  rurban
// patch #1348996 by Robert Litwiniec: fix show image semantics if label is given
//
// Revision 1.76  2006/08/19 11:02:35  rurban
// add strike and del to html emphasis: Patch #1542894 by Kai Krakow
//
// Revision 1.75  2006/08/15 13:43:10  rurban
// add Markup_xml_plugin (untested) and fix Markup_template_plugin
//
// Revision 1.74  2006/07/23 14:03:18  rurban
// add new feature: DISABLE_MARKUP_WIKIWORD
//
// Revision 1.73  2006/04/15 12:20:36  rurban
// fix relatives links patch by Joel Schaubert for [/
//
// Revision 1.72  2006/03/07 20:43:29  rurban
// relative external link, if no internal subpage. by joel Schaubert
//
// Revision 1.71  2005/11/14 22:31:12  rurban
// add SemanticWeb support
//
// Revision 1.70  2005/10/31 16:45:23  rurban
// added cfg-able markups only for default TextTransformation, not for links and others
//
// Revision 1.69  2005/09/14 05:57:19  rurban
// make ENABLE_MARKUP_TEMPLATE optional
//
// Revision 1.68  2005/09/10 21:24:32  rurban
// optionally support {{Template|vars}} syntax
//
// Revision 1.67  2005/06/06 17:41:20  rurban
// support new ENABLE_MARKUP_COLOR
//
// Revision 1.66  2005/04/23 11:15:49  rurban
// handle allowed inlined objects within INLINE_IMAGES
//
// Revision 1.65  2005/03/27 18:24:17  rurban
// add Log
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