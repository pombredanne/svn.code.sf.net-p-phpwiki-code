<?php // -*-php-*-
rcs_id('$Id: RecentComments.php,v 1.1 2004-05-14 17:33:12 rurban Exp $');

/**
 * List of basepages with recently added comments
 */

require_once("lib/plugin/RecentChanges.php");
require_once("lib/plugin/WikiBlog.php");

class WikiPlugin_RecentComments
extends WikiPlugin_RecentChanges
{
    function getName () {
        return _("RecentComments");
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }
    function getDefaultArguments() {
    	//php-4.0.4pl1 breaks at the parent:: line even if the 
    	// code doesn't reach this line
        //if (!check_php_version(4,0,6))
        $args = WikiPlugin_RecentChanges::getDefaultArguments();
        //else $args = parent::getDefaultArguments();
        $args['type'] = 'RecentComments';
        $args['show_minor'] = false;
        $args['show_all'] = true;
        $args['caption'] = _("Recent Comments");
        return $args;
    }

    function getChanges ($dbi, $args) {
        $changes = $dbi->mostRecent($this->getMostRecentParams($args));

        $show_deleted = $args['show_deleted'];
        if ($show_deleted == 'sometimes')
            $show_deleted = $args['show_minor'];
        if (!$show_deleted)
            $changes = new NonDeletedRevisionIterator($changes, !$args['show_all']);

        // sort out pages with no comments
        $changes = new RecentCommentsRevisionIterator($changes, $dbi);
        return $changes;
    }

    // box is used to display a fixed-width, narrow version with common header.
    // just a numbered list of limit pagenames, without date.
    function box($args = false, $request = false, $basepage = false) {
        if (!$request) $request =& $GLOBALS['request'];
        if (!isset($args['limit'])) $args['limit'] = 15;
        $args['format'] = 'box';
        $args['show_minor'] = false;
        $args['show_major'] = true;
        $args['show_deleted'] = false;
        $args['show_all'] = false;
        $args['days'] = 90;
        return $this->makeBox(WikiLink(_("RecentComments"),'',_("Recent Comments")),
                              $this->format($this->getChanges($request->_dbi, $args), $args));
    }
}

/**
 * List of pages which have comments
 * i.e. sort out all non-commented pages.
 */
class RecentCommentsRevisionIterator extends WikiDB_PageRevisionIterator
{
    function RecentCommentsRevisionIterator ($revisions, &$dbi) {
        $this->_revisions = $revisions;
        $this->_wikidb = $dbi;
        $this->_current = 0;
        $this->_blog = new WikiPlugin_WikiBlog();
    }

    function next () {
    	if (!empty($this->comments) and $this->_current) {
            if (isset($this->comments[$this->_current])) {
                $this->_current++;
                return $this->comments[$this->_current];
            } else {
            	$this->_current = 0;
            }
    	}
        while (($rev = $this->_revisions->next())) {
            $this->comments = $this->_blog->findBlogs($this->_wikidb, $rev->getPageName(), 'comment');
            if ($this->comments) {
                usort($this->comments, array("WikiPlugin_WikiBlog",
                                         "cmp"));
                if (isset($this->comments[$this->_current])) {
                    $this->_current++;
                    return $this->comments[$this->_current];
                }
            } else {
		$this->_current = 0;
            }
    	}
        $this->free();
        return false;
    }

}

// $Log: not supported by cvs2svn $

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>