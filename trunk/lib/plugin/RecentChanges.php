<?php // -*-php-*-
rcs_id('$Id: RecentChanges.php,v 1.80 2003-11-27 15:17:01 carstenklapp Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 */
class _RecentChanges_Formatter
{
    var $_absurls = false;

    function _RecentChanges_Formatter ($rc_args) {
        $this->_args = $rc_args;
        $this->_diffargs = array('action' => 'diff');

        if ($rc_args['show_minor'] || !$rc_args['show_major'])
            $this->_diffargs['previous'] = 'minor';

        // PageHistoryPlugin doesn't have a 'daylist' arg.
        if (!isset($this->_args['daylist']))
            $this->_args['daylist'] = false;
    }

    function include_versions_in_URLs() {
        return (bool) $this->_args['show_all'];
    }

    function date ($rev) {
        global $Theme;
        return $Theme->getDay($rev->get('mtime'));
    }

    function time ($rev) {
        global $Theme;
        return $Theme->formatTime($rev->get('mtime'));
    }

    function diffURL ($rev) {
        $args = $this->_diffargs;
        if ($this->include_versions_in_URLs())
            $args['version'] = $rev->getVersion();
        $page = $rev->getPage();
        return WikiURL($page->getName(), $args, $this->_absurls);
    }

    function historyURL ($rev) {
        $page = $rev->getPage();
        return WikiURL($page, array('action' => _("PageHistory")),
                       $this->_absurls);
    }

    function pageURL ($rev) {
        return WikiURL($this->include_versions_in_URLs() ? $rev : $rev->getPage(),
                       '', $this->_absurls);
    }

    function authorHasPage ($author) {
        global $WikiNameRegexp, $request;
        $dbi = $request->getDbh();
        return preg_match("/^$WikiNameRegexp\$/", $author) && $dbi->isWikiPage($author);
    }

    function authorURL ($author) {
        return $this->authorHasPage() ? WikiURL($author) : false;
    }


    function status ($rev) {
        if ($rev->hasDefaultContents())
            return 'deleted';
        $page = $rev->getPage();
        $prev = $page->getRevisionBefore($rev->getVersion());
        if ($prev->hasDefaultContents())
            return 'new';
        return 'updated';
    }

    function importance ($rev) {
        return $rev->get('is_minor_edit') ? 'minor' : 'major';
    }

    function summary($rev) {
        if ( ($summary = $rev->get('summary')) )
            return $summary;

        switch ($this->status($rev)) {
            case 'deleted':
                return _("Deleted.");
            case 'new':
                return _("New page.");
            default:
                return '';
        }
    }

    function setValidators($most_recent_rev) {
        $rev = $most_recent_rev;
        $validators = array('RecentChanges-top' =>
                            array($rev->getPageName(), $rev->getVersion()),
                            '%mtime' => $rev->get('mtime'));
        global $request;
        $request->appendValidators($validators);
    }
}

class _RecentChanges_HtmlFormatter
extends _RecentChanges_Formatter
{
    function diffLink ($rev) {
        global $Theme;
        return $Theme->makeButton(_("(diff)"), $this->diffURL($rev), 'wiki-rc-action');
    }

    function historyLink ($rev) {
        global $Theme;
        return $Theme->makeButton(_("(hist)"), $this->historyURL($rev), 'wiki-rc-action');
    }

    function pageLink ($rev, $link_text=false) {
        $page = $rev->getPage();
        global $Theme;
        if ($this->include_versions_in_URLs()) {
            $version = $rev->getVersion();
            $exists = !$rev->hasDefaultContents();
        }
        else {
            $version = false;
            $cur = $page->getCurrentRevision();
            $exists = !$cur->hasDefaultContents();
        }
        if ($exists)
            return $Theme->linkExistingWikiWord($page->getName(), $link_text, $version);
        else
            return $Theme->linkUnknownWikiWord($page->getName(), $link_text);
    }

    function authorLink ($rev) {
        $author = $rev->get('author');
        if ( $this->authorHasPage($author) ) {
            return WikiLink($author);
        } else
            return $author;
    }

    function summaryAsHTML ($rev) {
        if ( !($summary = $this->summary($rev)) )
            return '';
        return  HTML::strong( array('class' => 'wiki-summary'),
                              "[",
                              TransformLinks($summary, $rev->get('markup'), $rev->getPageName()),
                              "]");
    }

    function rss_icon () {
        global $request, $Theme;

        $rss_url = $request->getURLtoSelf(array('format' => 'rss'));
        return HTML::small(array('style' => 'font-weight:normal;vertical-align:middle;'), $Theme->makeButton("RSS", $rss_url, 'rssicon'));
    }

    function description () {
        extract($this->_args);
        // FIXME: say something about show_all.
        if ($show_major && $show_minor)
            $edits = _("edits");
        elseif ($show_major)
            $edits = _("major edits");
        else
            $edits = _("minor edits");

        if ($timespan = $days > 0) {
            if (intval($days) != $days)
                $days = sprintf("%.1f", $days);
        }
        $lmt = abs($limit);
        /**
         * Depending how this text is split up it can be tricky or
         * impossible to translate with good grammar. So the seperate
         * strings for 1 day and %s days are necessary in this case
         * for translating to multiple languages, due to differing
         * overlapping ideal word cutting points.
         *
         * en: day/days "The %d most recent %s [during (the past] day) are listed below."
         * de: 1 Tag    "Die %d j�ngste %s [innerhalb (von des letzten] Tages) sind unten aufgelistet."
         * de: %s days  "Die %d j�ngste %s [innerhalb (von] %s Tagen) sind unten aufgelistet."
         *
         * en: day/days "The %d most recent %s during [the past] (day) are listed below."
         * fr: 1 jour   "Les %d %s les plus r�centes pendant [le dernier (d'une] jour) sont �num�r�es ci-dessous."
         * fr: %s jours "Les %d %s les plus r�centes pendant [les derniers (%s] jours) sont �num�r�es ci-dessous."
         */
        if ($limit > 0) {
            if ($timespan) {
                if (intval($days) == 1)
                    $desc = fmt("The %d most recent %s during the past day are listed below.",
                                $limit, $edits);
                else
                    $desc = fmt("The %d most recent %s during the past %s days are listed below.",
                                $limit, $edits, $days);
            } else
                $desc = fmt("The %d most recent %s are listed below.",
                            $limit, $edits);
        }
        elseif ($limit < 0) {  //$limit < 0 means we want oldest pages
            if ($timespan) {
                if (intval($days) == 1)
                    $desc = fmt("The %d oldest %s during the past day are listed below.",
                                $lmt, $edits);
                else
                    $desc = fmt("The %d oldest %s during the past %s days are listed below.",
                                $lmt, $edits, $days);
            } else
                $desc = fmt("The %d oldest %s are listed below.",
                            $lmt, $edits);
        }

        else {
            if ($timespan) {
                if (intval($days) == 1)
                    $desc = fmt("The most recent %s during the past day are listed below.",
                                $edits);
                else
                    $desc = fmt("The most recent %s during the past %s days are listed below.",
                                $edits, $days);
            } else
                $desc = fmt("All %s are listed below.", $edits);
        }
        return HTML::p(false, $desc);
    }


    function title () {
        extract($this->_args);
        return array($show_minor ? _("RecentEdits") : _("RecentChanges"),
                     ' ',
                     $this->rss_icon(),
                     $this->sidebar_link());
    }

    function empty_message () {
        return _("No changes found");
    }
    
        
    function sidebar_link() {
        extract($this->_args);
        $pagetitle = $show_minor ? _("RecentEdits") : _("RecentChanges");

        global $request;
        $sidebarurl = WikiURL($pagetitle, array('format' => 'sidebar'), 'absurl');

        $addsidebarjsfunc =
            "function addPanel() {\n"
            ."    window.sidebar.addPanel (\"" . sprintf("%s - %s", WIKI_NAME, $pagetitle) . "\",\n"
            ."       \"$sidebarurl\",\"\");\n"
            ."}\n";
        $jsf = JavaScript($addsidebarjsfunc);

        global $Theme;
        $sidebar_button = $Theme->makeButton("sidebar", 'javascript:addPanel();', 'sidebaricon');
        $addsidebarjsclick = asXML(HTML::small(array('style' => 'font-weight:normal;vertical-align:middle;'), $sidebar_button));
        $jsc = JavaScript("if ((typeof window.sidebar == 'object') &&\n"
                                ."    (typeof window.sidebar.addPanel == 'function'))\n"
                                ."   {\n"
                                ."       document.write('$addsidebarjsclick');\n"
                                ."   }\n"
                                );
        return HTML(new RawXML("\n"), $jsf, new RawXML("\n"), $jsc);
    }

    function format ($changes) {
        include_once('lib/InlineParser.php');
        
        $html = HTML(HTML::h2(false, $this->title()));
        if (($desc = $this->description()))
            $html->pushContent($desc);
        
        if ($this->_args['daylist'])
            $html->pushContent(new DayButtonBar($this->_args));

        $last_date = '';
        $lines = false;
        $first = true;

        while ($rev = $changes->next()) {
            if (($date = $this->date($rev)) != $last_date) {
                if ($lines)
                    $html->pushContent($lines);
                $html->pushContent(HTML::h3($date));
                $lines = HTML::ul();
                $last_date = $date;

            }
            $lines->pushContent($this->format_revision($rev));

            if ($first)
                $this->setValidators($rev);
            $first = false;
        }
        if ($lines)
            $html->pushContent($lines);
        if ($first)
            $html->pushContent(HTML::p(array('class' => 'rc-empty'),
                                       $this->empty_message()));
        
        return $html;
    }

    function format_revision ($rev) {
        $args = &$this->_args;

        $class = 'rc-' . $this->importance($rev);

        $time = $this->time($rev);
        if (! $rev->get('is_minor_edit'))
            $time = HTML::strong(array('class' => 'pageinfo-majoredit'), $time);

        $line = HTML::li(array('class' => $class));


        if ($args['difflinks'])
            $line->pushContent($this->diffLink($rev), ' ');

        if ($args['historylinks'])
            $line->pushContent($this->historyLink($rev), ' ');

        $line->pushContent($this->pageLink($rev), ' ',
                           $time, ' ',
                           $this->summaryAsHTML($rev),
                           ' ... ',
                           $this->authorLink($rev));
        return $line;
    }
}


class _RecentChanges_SideBarFormatter
extends _RecentChanges_HtmlFormatter
{
    function description () {
        //omit description
    }
    function rss_icon () {
        //omit rssicon
    }
    function title () {
        //title click opens the normal RC or RE page in the main browser frame
        extract($this->_args);
        $titlelink = WikiLink($show_minor ? _("RecentEdits") : _("RecentChanges"));
        $titlelink->setAttr('target', '_content');
        return HTML($this->logo(), $titlelink);
    }
    function logo () {
        //logo click opens the HomePage in the main browser frame
        global $Theme;
        $img = HTML::img(array('src' => $Theme->getImageURL('logo'),
                               'border' => 0,
                               'align' => 'right',
                               'width' => 32
                               ));
        $linkurl = WikiLink(HOME_PAGE, false, $img);
        $linkurl->setAttr('target', '_content');
        return $linkurl;
    }

    function authorLink ($rev) {
        $author = $rev->get('author');
        if ( $this->authorHasPage($author) ) {
            $linkurl = WikiLink($author);
            $linkurl->setAttr('target', '_content'); // way to do this using parent::authorLink ??
            return $linkurl;
        } else
            return $author;
    }
    function diffLink ($rev) {
        $linkurl = parent::diffLink($rev);
        $linkurl->setAttr('target', '_content');
        return $linkurl;
    }
    function historyLink ($rev) {
        $linkurl = parent::historyLink($rev);
        $linkurl->setAttr('target', '_content');
        return $linkurl;
    }
    function pageLink ($rev) {
        $linkurl = parent::pageLink($rev);
        $linkurl->setAttr('target', '_content');
        return $linkurl;
    }
    // Overriding summaryAsHTML, because there is no way yet to
    // return summary as transformed text with
    // links setAttr('target', '_content') in Mozilla sidebar.
    // So for now don't create clickable links inside summary
    // in the sidebar, or else they target the sidebar and not the
    // main content window.
    function summaryAsHTML ($rev) {
        if ( !($summary = $this->summary($rev)) )
            return '';
        return HTML::strong(array('class' => 'wiki-summary'),
                                "[",
                                /*TransformLinks(*/$summary,/* $rev->get('markup')),*/
                                "]");
    }


    function format ($changes) {
        $this->_args['daylist'] = false; //only 1 day for Mozilla sidebar
        $html = _RecentChanges_HtmlFormatter::format ($changes);
        $html = HTML::div(array('class' => 'wikitext'), $html);
        global $request;
        $request->discardOutput();
        
        printf("<?xml version=\"1.0\" encoding=\"%s\"?>\n", CHARSET);
        printf('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"');
        printf('  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">');
        printf('<html xmlns="http://www.w3.org/1999/xhtml">');

        printf("<head>\n");
        extract($this->_args);
        $title = WIKI_NAME . $show_minor ? _("RecentEdits") : _("RecentChanges");
        printf("<title>" . $title . "</title>\n");
        global $Theme;
        $css = $Theme->getCSS();
        $css->PrintXML();
        printf("</head>\n");

        printf("<body class=\"sidebar\">\n");
        $html->PrintXML();
        printf("\n</body>\n");
        printf("</html>\n");

        $request->finish(); // cut rest of page processing short
    }
}


class _RecentChanges_RssFormatter
extends _RecentChanges_Formatter
{
    var $_absurls = true;

    function time ($rev) {
        return Iso8601DateTime($rev->get('mtime'));
    }

    function pageURI ($rev) {
        return WikiURL($rev, '', 'absurl');
    }

    function format ($changes) {
        
        include_once('lib/RssWriter.php');
        $rss = new RssWriter;


        $rss->channel($this->channel_properties());

        if (($props = $this->image_properties()))
            $rss->image($props);
        if (($props = $this->textinput_properties()))
            $rss->textinput($props);

        $first = true;
        while ($rev = $changes->next()) {
            $rss->addItem($this->item_properties($rev),
                          $this->pageURI($rev));
            if ($first)
                $this->setValidators($rev);
            $first = false;
        }

        global $request;
        $request->discardOutput();
        $rss->finish();
        printf("\n<!-- Generated by PhpWiki:\n%s-->\n", $GLOBALS['RCS_IDS']);

        // Flush errors in comment, otherwise it's invalid XML.
        global $ErrorManager;
        if (($errors = $ErrorManager->getPostponedErrorsAsHTML()))
            printf("\n<!-- PHP Warnings:\n%s-->\n", AsXML($errors));

        $request->finish();     // NORETURN!!!!
    }

    function image_properties () {
        global $Theme;

        $img_url = AbsoluteURL($Theme->getImageURL('logo'));
        if (!$img_url)
            return false;

        return array('title' => WIKI_NAME,
                     'link' => WikiURL(HOME_PAGE, false, 'absurl'),
                     'url' => $img_url);
    }

    function textinput_properties () {
        return array('title' => _("Search"),
                     'description' => _("Title Search"),
                     'name' => 's',
                     'link' => WikiURL(_("TitleSearch"), false, 'absurl'));
    }

    function channel_properties () {
        global $request;

        $rc_url = WikiURL($request->getArg('pagename'), false, 'absurl');

        return array('title' => WIKI_NAME,
                     'link' => $rc_url,
                     'description' => _("RecentChanges"),
                     'dc:date' => Iso8601DateTime(time()));

        /* FIXME: other things one might like in <channel>:
         * sy:updateFrequency
         * sy:updatePeriod
         * sy:updateBase
         * dc:subject
         * dc:publisher
         * dc:language
         * dc:rights
         * rss091:language
         * rss091:managingEditor
         * rss091:webmaster
         * rss091:lastBuildDate
         * rss091:copyright
         */
    }




    function item_properties ($rev) {
        $page = $rev->getPage();
        $pagename = $page->getName();

        return array( 'title'           => split_pagename($pagename),
                      'description'     => $this->summary($rev),
                      'link'            => $this->pageURL($rev),
                      'dc:date'         => $this->time($rev),
                      'dc:contributor'  => $rev->get('author'),
                      'wiki:version'    => $rev->getVersion(),
                      'wiki:importance' => $this->importance($rev),
                      'wiki:status'     => $this->status($rev),
                      'wiki:diff'       => $this->diffURL($rev),
                      'wiki:history'    => $this->historyURL($rev)
                      );
    }
}

class NonDeletedRevisionIterator extends WikiDB_PageRevisionIterator
{
    /** Constructor
     *
     * @param $revisions object a WikiDB_PageRevisionIterator.
     */
    function NonDeletedRevisionIterator ($revisions, $check_current_revision = true) {
        $this->_revisions = $revisions;
        $this->_check_current_revision = $check_current_revision;
    }

    function next () {
        while (($rev = $this->_revisions->next())) {
            if ($this->_check_current_revision) {
                $page = $rev->getPage();
                $check_rev = $page->getCurrentRevision();
            }
            else {
                $check_rev = $rev;
            }
            if (! $check_rev->hasDefaultContents())
                return $rev;
        }
        $this->free();
        return false;
    }

    function free () {
        $this->_revisions->free();
    }
}

class WikiPlugin_RecentChanges
extends WikiPlugin
{
    function getName () {
        return _("RecentChanges");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.80 $");
    }

    function managesValidators() {
        // Note that this is a bit of a fig.
        // We set validators based on the most recently changed page,
        // but this fails when the most-recent page is deleted.
        // (Consider that the Last-Modified time will decrease
        // when this happens.)

        // We might be better off, leaving this as false (and junking
        // the validator logic above) and just falling back to the
        // default behavior (handled by WikiPlugin) of just using
        // the WikiDB global timestamp as the mtime.

        // Nevertheless, for now, I leave this here, mostly as an
        // example for how to use appendValidators() and managesValidators().
        
        return true;
    }
            
    function getDefaultArguments() {
        return array('days'         => 2,
                     'show_minor'   => false,
                     'show_major'   => true,
                     'show_all'     => false,
                     'show_deleted' => 'sometimes',
                     'limit'        => false,
                     'format'       => false,
                     'daylist'      => false,
                     'difflinks'    => true,
                     'historylinks' => false,
                     'caption'      => ''
                     );
    }

    function getArgs ($argstr, $request, $defaults = false) {
        $args = WikiPlugin::getArgs($argstr, $request, $defaults);

        $action = $request->getArg('action');
        if ($action != 'browse' && ! $request->isActionPage($action))
            $args['format'] = false; // default -> HTML

        if ($args['format'] == 'rss' && empty($args['limit']))
            $args['limit'] = 15; // Fix default value for RSS.

        if ($args['format'] == 'sidebar' && empty($args['limit']))
            $args['limit'] = 1; // Fix default value for sidebar.

        return $args;
    }

    function getMostRecentParams ($args) {
        extract($args);

        $params = array('include_minor_revisions' => $show_minor,
                        'exclude_major_revisions' => !$show_major,
                        'include_all_revisions' => !empty($show_all));

        if ($limit != 0)
            $params['limit'] = $limit;

        if ($days > 0.0)
            $params['since'] = time() - 24 * 3600 * $days;
        elseif ($days < 0.0)
            $params['since'] = 24 * 3600 * $days - time();


        return $params;
    }

    function getChanges ($dbi, $args) {
        $changes = $dbi->mostRecent($this->getMostRecentParams($args));

        $show_deleted = $args['show_deleted'];
        if ($show_deleted == 'sometimes')
            $show_deleted = $args['show_minor'];

        if (!$show_deleted)
            $changes = new NonDeletedRevisionIterator($changes, !$args['show_all']);

        return $changes;
    }

    function format ($changes, $args) {
        global $Theme;
        $format = $args['format'];

        $fmt_class = $Theme->getFormatter('RecentChanges', $format);
        if (!$fmt_class) {
            if ($format == 'rss')
                $fmt_class = '_RecentChanges_RssFormatter';
            elseif ($format == 'rss091') {
                include_once "lib/RSSWriter091.php";
                $fmt_class = '_RecentChanges_RssFormatter091';
            }
            elseif ($format == 'sidebar')
                $fmt_class = '_RecentChanges_SideBarFormatter';
            else
                $fmt_class = '_RecentChanges_HtmlFormatter';
        }

        $fmt = new $fmt_class($args);
        return $fmt->format($changes);
    }

    function run ($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);

        // HACKish: fix for SF bug #622784  (1000 years of RecentChanges ought
        // to be enough for anyone.)
        $args['days'] = min($args['days'], 365000);
        
        // Hack alert: format() is a NORETURN for rss formatters.
        return $this->format($this->getChanges($dbi, $args), $args);
    }
};


class DayButtonBar extends HtmlElement {

    function DayButtonBar ($plugin_args) {
        $this->HtmlElement('p', array('class' => 'wiki-rc-action'));

        // Display days selection buttons
        extract($plugin_args);

        // Custom caption
        if (! $caption) {
            if ($show_minor)
                $caption = _("Show minor edits for:");
            elseif ($show_all)
                $caption = _("Show all changes for:");
            else
                $caption = _("Show changes for:");
        }

        $this->pushContent($caption, ' ');

        global $Theme;
        $sep = $Theme->getButtonSeparator();

        $n = 0;
        foreach (explode(",", $daylist) as $days) {
            if ($n++)
                $this->pushContent($sep);
            $this->pushContent($this->_makeDayButton($days));
        }
    }

    function _makeDayButton ($days) {
        global $Theme, $request;

        if ($days == 1)
            $label = _("1 day");
        elseif ($days < 1)
            $label = "..."; //alldays
        else
            $label = sprintf(_("%s days"), abs($days));

        $url = $request->getURLtoSelf(array('action' => 'browse', 'days' => $days));

        return $Theme->makeButton($label, $url, 'wiki-rc-action');
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.79  2003/04/29 14:34:20  dairiki
// Bug fix: "add sidebar" link didn't work when USE_PATH_INFO was false.
//
// Revision 1.78  2003/03/04 01:55:05  dairiki
// Fix to ensure absolute URL for logo in RSS recent changes.
//
// Revision 1.77  2003/02/27 23:23:38  dairiki
// Fix my breakage of CSS and sidebar RecentChanges output.
//
// Revision 1.76  2003/02/27 22:48:44  dairiki
// Fixes invalid HTML generated by PageHistory plugin.
//
// (<noscript> is block-level and not allowed within <p>.)
//
// Revision 1.75  2003/02/22 21:39:05  dairiki
// Hackish fix for SF bug #622784.
//
// (The root of the problem is clearly a PHP bug.)
//
// Revision 1.74  2003/02/21 22:52:21  dairiki
// Make sure to interpret relative links (like [/Subpage]) in summary
// relative to correct basepage.
//
// Revision 1.73  2003/02/21 04:12:06  dairiki
// Minor fixes for new cached markup.
//
// Revision 1.72  2003/02/17 02:19:01  dairiki
// Fix so that PageHistory will work when the current revision
// of a page has been "deleted".
//
// Revision 1.71  2003/02/16 20:04:48  dairiki
// Refactor the HTTP validator generation/checking code.
//
// This also fixes a number of bugs with yesterdays validator mods.
//
// Revision 1.70  2003/02/16 05:09:43  dairiki
// Starting to fix handling of the HTTP validator headers, Last-Modified,
// and ETag.
//
// Last-Modified was being set incorrectly (but only when DEBUG was not
// defined!)  Setting a Last-Modified without setting an appropriate
// Expires: and/or Cache-Control: header results in browsers caching
// the page unconditionally (for a certain period of time).
// This is generally bad, since it means people don't see updated
// page contents right away --- this is particularly confusing to
// the people who are editing pages since their edits don't show up
// next time they browse the page.
//
// Now, we don't allow caching of pages without revalidation
// (via the If-Modified-Since and/or If-None-Match request headers.)
// (You can allow caching by defining CACHE_CONTROL_MAX_AGE to an
// appropriate value in index.php, but I advise against it.)
//
// Problems:
//
//   o Even when request is aborted due to the content not being
//     modified, we currently still do almost all the work involved
//     in producing the page.  So the only real savings from all
//     this logic is in network bandwidth.
//
//   o Plugins which produce "dynamic" output need to be inspected
//     and made to call $request->addToETag() and
//     $request->setModificationTime() appropriately, otherwise the
//     page can change without the change being detected.
//     This leads to stale pages in cache again...
//
// Revision 1.69  2003/01/18 22:01:43  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
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
