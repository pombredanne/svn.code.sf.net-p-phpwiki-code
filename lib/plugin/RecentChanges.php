<?php // -*-php-*-
rcs_id('$Id: RecentChanges.php,v 1.41 2002-01-28 18:59:14 dairiki Exp $');
/**
 */

        
class _RecentChanges_Formatter
{
    var $_absurls = false;
    
    function _RecentChanges_Formatter ($rc_args) {
        $this->_args = $rc_args;
        $this->_diffargs = array('action' => 'diff');

        if (!$rc_args['show_minor'])
            $this->_diffargs['previous'] = 'major';
    }

    function include_versions_in_URLs() {
        return (bool) $this->_args['show_all'];
    }
    
    function date ($rev) {
        global $Theme;
        return $Theme->formatDate($rev->get('mtime'));
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
        return WikiURL(_("PageHistory"),
                       array('page' => $page->getName()),
                       $this->_absurls);
    }

    function pageURL ($rev) {
        $params = array();
        if ($this->include_versions_in_URLs())
            $params['version'] = $rev->getVersion();
        $page = $rev->getPage();
        return WikiURL($page->getName(), $params, $this->_absurls);
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
}

class _RecentChanges_HtmlFormatter
extends _RecentChanges_Formatter
{
    function diffLink ($rev) {
        global $Theme;
        return $Theme->makeButton(_("(diff)"), $this->diffURL($rev), 'wiki-rc-action');
    }

    function pageLink ($rev) {
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
            return $Theme->linkExistingWikiWord($page->getName(), false, $version);
        else
            return $Theme->linkUnknownWikiWord($page->getName(), false, $version);
    }
    
    function authorLink ($rev) {
        $author = $rev->get('author');
        if ( $this->authorHasPage($author) ) {
            global $Theme;
            return $Theme->LinkExistingWikiWord($author);
        } else
            return $author;
    }

    function summaryAsHTML ($rev) {
        if ( !($summary = $this->summary($rev)) )
            return '';
        return  HTML::strong( array('class' => 'wiki-summary'),
                              "[",
                              do_transform($summary, 'LinkTransform'),
                              "]");
    }
        
    function rss_icon () {
        global $request, $Theme;

        $rss_url = $request->getURLtoSelf(array('format' => 'rss'));
        return $Theme->makeButton("RSS", $rss_url, 'rssicion');
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
            
        if ($limit > 0) {
            if ($days > 0)
                $desc = fmt("The %d most recent %s during the past %.1f days are listed below.",
                            $limit, $edits, $days);
            else
                $desc = fmt("The %d most recent %s are listed below.",
                            $limit, $edits);
        }
        else {
            if ($days > 0)
                $desc = fmt("The most recent %s during the past %.1f days are listed below.",
                            $edits, $days);
            else
                $desc = fmt("All %s are listed below.", $edits);
        }
        return $desc;
    }

        
    function title () {
        extract($this->_args);
        return array($show_minor ? _("RecentEdits") : _("RecentChanges"),
                     ' ',
                     $this->rss_icon());
    }

    function format ($changes) {
        $html = HTML(HTML::h2(false, $this->title()));
        if (($desc = $this->description()))
            $html->pushContent(HTML::p(false, $desc));
        
        $last_date = '';
        $lines = false;
        
        while ($rev = $changes->next()) {
            if (($date = $this->date($rev)) != $last_date) {
                if ($lines)
                    $html->pushContent($lines);
                $html->pushContent(HTML::h3($date));
                $lines = HTML::ul();
                $last_date = $date;
            }
            $lines->pushContent($this->format_revision($rev));
        }
        if ($lines)
            $html->pushContent($lines);
        return $html;
    }

    function format_revision ($rev) {
        $class = 'rc-' . $this->importance($rev);
        
        return HTML::li(array('class' => $class),
                        $this->diffLink($rev), ' ',
                        $this->pageLink($rev), ' ',
                        $this->time($rev), ' ',
                        ($this->importance($rev)=='minor') ? _("(minor edit)") ." " : '',
                        $this->summaryAsHTML($rev),
                        ' ... ',
                        $this->authorLink($rev));
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
        $page = $rev->getPage();
        return WikiURL($page->getName(),
                       array('version' => $rev->getVersion()),
                       'absurl');
    }
    
    function format ($changes) {
        include_once('lib/RssWriter.php');
        $rss = new RssWriter;

        
        $rss->channel($this->channel_properties());

        if (($props = $this->image_properties()))
            $rss->image($props);
        if (($props = $this->textinput_properties()))
            $rss->textinput($props);

        while ($rev = $changes->next()) {
            $rss->addItem($this->item_properties($rev),
                          $this->pageURI($rev));
        }

        $rss->finish();
        printf("\n<!-- Generated by PhpWiki:\n%s-->\n", $GLOBALS['RCS_IDS']);

        global $request;        // FIXME
        $request->finish();     // NORETURN!!!!
    }
    
    function image_properties () {
        global $Theme;

        $img_url = $Theme->getImageURL('logo');
        if (!$img_url)
            return false;
        
        return array('title' => WIKI_NAME,
                     'link' => WikiURL(HomePage, false, 'absurl'),
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
                     'dc:description' => _("RecentChanges"),
                     'link' => $rc_url,
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
        
        return array( 'title'		=> split_pagename($pagename),
                      'description'	=> $this->summary($rev),
                      'link'		=> $this->pageURL($rev),
                      'dc:date'		=> $this->time($rev),
                      'dc:contributor'	=> $rev->get('author'),
                      'wiki:version'	=> $rev->getVersion(),
                      'wiki:importance' => $this->importance($rev),
                      'wiki:status'	=> $this->status($rev),
                      'wiki:diff'	=> $this->diffURL($rev),
                      'wiki:history'	=> $this->historyURL($rev)
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

    function getDefaultArguments() {
        return array('days'		=> 2,
                     'show_minor'	=> false,
                     'show_major'	=> true,
                     'show_all'		=> false,
                     'show_deleted'	=> 'sometimes',
                     'limit'		=> false,
                     'format'		=> false,
                     'daylist'          => false,
                     'caption'          => ''
                     );
    }

    function getArgs ($argstr, $request, $defaults = false) {
        $args = WikiPlugin::getArgs($argstr, $request, $defaults);

        if ($request->getArg('action') != 'browse')
            $args['format'] = false; // default -> HTML
        
        if ($args['format'] == 'rss' && empty($args['limit']))
            $args['limit'] = 15; // Fix default value for RSS.

        return $args;
    }
        
    function getMostRecentParams ($args) {
        extract($args);

        $params = array('include_minor_revisions' => $show_minor,
                        'exclude_major_revisions' => !$show_major,
                        'include_all_revisions' => !empty($show_all));

        if ($limit > 0)
            $params['limit'] = $limit;

        if ($days > 0.0)
            $params['since'] = time() - 24 * 3600 * $days;

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
            else
                $fmt_class = '_RecentChanges_HtmlFormatter';
        }
        
        $fmt = new $fmt_class($args);
        return $fmt->format($changes);
    }

    function run ($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        
        if (! $args['daylist']) {
            // Display RecentChanges
            //
            // Hack alert: format() is a NORETURN for rss formatters.
            return $this->format($this->getChanges($dbi, $args), $args);
        } else {
            // Display days selection buttons
            extract($args);

            $daysarray = explode(",", $daylist);

            // Defaults
            $url_show_minor = "";
            $url_show_all = "";

            // RecentEdits args
            if (($show_minor == 1)||($show_minor == true))
                $url_show_minor = "&show_minor=1";
            if (($show_all == 1)||($show_all == true))
                $url_show_all = "&show_all=1";
            // Custom caption
            if (! $caption) {
                if ($url_show_minor)
                    $caption = _("Show changes for:");
                else {
                    if ($url_show_all)
                        $caption = _("Show all changes for:");
                    else
                        $caption = _("Show minor edits for:");
                }
            }

            $b = new buttonSet();
            $b->caption = $caption;

            foreach ($daysarray as $daynum) {

                if ($daynum == 1)
                    $label = _("1 day");
                elseif ($daynum < 1)
                    $label = "..."; //alldays
                else
                    $label = sprintf(_("%s days"), $daynum);

                // Build the button's url
                $b->addButton($label, "RecentChanges?days=" .$daynum
                                      .$url_show_minor .$url_show_all,
                              'wiki-rc-action');
            }
            return HTML::div(array('class'=>'wiki-rc-action'), $b->getContent());
        }
    }
};


class buttonSet {
    function buttonSet() {
        $this->caption = "";
        $this->content = "";
        $this->_b = array();
    }

    function addButton($label, $url, $action) {
        global $Theme;
        $this->_b[] = $Theme->makeButton($label, $url, $action);
    }

    function getContent() {
        if (empty($this->content))
            $this->_generateContent();
        return $this->content;
    }

    function _generateContent() {
        $this->content = HTML::p($this->caption . " ");
        // Avoid an extraneous ButtonSeparator
        $this->content->pushContent(array_shift($this->_b));

        global $Theme;
        foreach ($this->_b as $button) {
            $this->content->pushContent($Theme->getButtonSeparator());
            $this->content->pushContent($button);
        }
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
