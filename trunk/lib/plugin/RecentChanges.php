<?php // -*-php-*-
rcs_id('$Id: RecentChanges.php,v 1.17 2002-01-05 11:46:03 carstenklapp Exp $');
/**
 */



class _RecentChanges_Formatter
{
    var $_absurls = false;
    
    function _RecentChanges_Formatter ($rc_args) {
        $this->_args = $rc_args;
        $this->_diffargs = array('action' => 'diff');
        
        if ($rc_args['show_major'] && !$rc_args['show_minor'])
            $this->_diffargs['previous'] = 'major';
    }

    function include_versions_in_URLs() {
        return (bool) $this->_args['show_all'];
    }
    
    function date ($rev) {
        return strftime($GLOBALS['dateformat'], $rev->get('mtime'));
    }

    function time ($rev) {
        return strtolower(strftime("%l:%M %p", $rev->get('mtime')));
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
    
    function authorURL($author) {
        global $WikiNameRegexp, $dbi;

        if (preg_match("/^$WikiNameRegexp\$/", $author) && $dbi->isWikiPage($author))
            return WikiURL($author);
        return false;
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
        return QElement('a', array('href' => $this->diffURL($rev), 'class' => 'wikiaction'),
                        _("(diff)"));
    }

    function pageLink ($rev) {
        $page = $rev->getPage();
        return QElement('a', array('href' => $this->pageURL($rev), 'class' => 'wiki'),
                        $page->getName());
    }
    
    function authorLink ($rev) {
        $author = $rev->get('author');
        if ( ($url = $this->authorURL($author)) )
            return QElement('a', array('href' => $url, 'class' => 'wiki'), $author);
        else
            return htmlspecialchars($author);
    }


    function rss_icon () {
        global $request, $rssicon;

        $rss_url = $request->getURLtoSelf(array('format' => 'rss'));
        if (empty($rssicon))
            $rssicon = 'images/rss.png';
        return Element('a', array('href' => $rss_url),
                       Element('img', array('src' => DataURL($rssicon),
                                            'alt' => _("RSS available"),
                                            'class' => 'rssicon')));
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
                $desc = sprintf(_("The %d most recent %s during the past %.1f days are listed below."),
                               $limit, $edits, $days);
            else
                $desc = sprintf(_("The %d most recent %s are listed below."),
                               $limit, $edits);
        }
        else {
            if ($days > 0)
                $desc = sprintf(_("The most recent %s during the past %.1f days are listed below."),
                               $edits, $days);
            else
                $desc = sprintf(_("All %s are listed below."), $edits);
        }
        return htmlspecialchars($desc);
    }

        
    function title () {
        extract($this->_args);
        return htmlspecialchars( $show_minor ? _("RecentEdits") : _("RecentChanges") ) . "\n" . $this->rss_icon();
    }

    function format ($changes) {
        $html[] = Element('h2', $this->title());
        if (($desc = $this->description()))
            $html[] = Element('p', $desc);
        
        $last_date = '';
        $lines = array();
        
        while ($rev = $changes->next()) {
            if (($date = $this->date($rev)) != $last_date) {
                if ($lines) {
                    $html[] = Element('ul', join("\n", $lines));
                    $lines = array();
                }
                $html[] = QElement('h3', $date);
                $last_date = $date;
            }
            
            $lines[] = $this->format_revision($rev);
        }
        if ($lines)
            $html[] = Element('ul', join("\n", $lines));
        return join("\n", $html) . "\n";
    }
    
    function format_revision ($rev) {
        if ( ($summary = $this->summary($rev)) ) {
            $summary = do_transform($summary, 'LinkTransform');
            $summary = Element('b', array('class' => 'wiki-summary'), "[$summary]");
        }
        
        $class = 'rc-' . $this->importance($rev);
        
        return Element('li', array('class' => $class),
                       implode(' ', array( $this->diffLink($rev),
                                           $this->pageLink($rev),
                                           $this->time($rev),
                                           $summary,
                                           '...',
                                           $this->authorLink($rev) )));
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
        ExitWiki();             // NORETURN!!!!
    }
    
    function image_properties () {
        return array('title' => WIKI_NAME,
                     'link' => WikiURL(_("HomePage"), false, 'absurl'),
                     'url' => DataURL($GLOBALS['logo']));
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
                     'limit'		=> false,
                     'format'		=> false);
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
        return $dbi->mostRecent($this->getMostRecentParams($args));
    }

    function format ($changes, $args) {
        if ($args['format'] == 'rss')
            $fmt = new _RecentChanges_RssFormatter($args);
        else
            $fmt = new _RecentChanges_HtmlFormatter($args);
        return $fmt->format($changes);
    }
    
        
    function run ($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        // Hack alert: format() is a NORETURN for rss formatters.
        return $this->format($this->getChanges($dbi, $args), $args);
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
