<?php // -*-php-*-
rcs_id('$Id: BlogJournal.php,v 1.1 2005-10-29 09:03:17 rurban Exp $');
/*
 * Copyright 2005 $ThePhpWikiProgrammingTeam
 */

require_once('lib/plugin/WikiBlog.php');

/**
 * BlogJournal - Include the latest blog entries for the current users blog if signed, 
 *               or the ADMIN_USER's Blog if not.
 * UnfoldSubpages for blogs.
 * Rui called this plugin "JournalLast", but this was written completely independent, 
 * without having seen the src.
 *
 * @author: Reini Urban
 */
class WikiPlugin_BlogJournal
extends WikiPlugin_WikiBlog
{
    function getName() {
        return _("BlogJournal");
    }

    function getDescription() {
        return _("Include latest blog entries for the current or ADMIN user");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array('count'    => 7,
                     'user'     => '',
                     'order'    => 'reverse',        // latest first
                     'month'    => false,
                     'noheader' => 0
                     );
    }

    // "2004-12" => "December 2004"
    function _monthTitle($month){
        //list($year,$mon) = explode("-",$month);
        return strftime("%B %Y", strtotime($month."-01"));
    }

    // "User/Blog/2004-12-13/12:28:50+01:00" => array('month' => "2004-12", ...)
    function _blog($rev_or_page) {
    	$pagename = $rev_or_page->getName();
        if (preg_match("/^(.*Blog)\/(\d\d\d\d-\d\d)-(\d\d)\/(.*)/", $pagename, $m))
            list(,$prefix,$month,$day,$time) = $m;
        return array('pagename' => $pagename,
                     // page (list pages per month) or revision (list months)?
                     //'title' => isa($rev_or_page,'WikiDB_PageRevision') ? $rev_or_page->get('summary') : '',
                     //'monthtitle' => $this->_monthTitle($month),
                     'month'   => $month,
                     'day'   => $day,
                     'time'  => $time,
                     'prefix' => $prefix);
    }

    function _nonDefaultArgs($args) {
    	return array_diff_assoc($args, $this->getDefaultArguments());
    }
    
    function run($dbi, $argstr, &$request, $basepage) {
        if (is_array($argstr)) { // can do with array also.
            $args =& $argstr;
            if (!isset($args['order'])) $args['order'] = 'reverse';
        } else {
            $args = $this->getArgs($argstr, $request);
        }
        $user = $request->getUser();
        if (empty($args['user'])) {
            if ($user->isAuthenticated()) {
                $args['user'] = $user->UserName();
            } else {
                $args['user'] = '';
            }
        }
        if (!$args['user'] or $args['user'] == ADMIN_USER) {
            if (BLOG_EMPTY_DEFAULT_PREFIX)
                $args['user'] = ''; 	    // "Blogs/day" pages 
            else
                $args['user'] = ADMIN_USER; // "Admin/Blogs/day" pages 
        }
        $parent = (empty($args['user']) ? '' : $args['user'] . SUBPAGE_SEPARATOR);

        $sp = HTML::Raw('&middot; ');
        $prefix = $parent . $this->_blogPrefix('wikiblog');
        if ($args['month'])
            $prefix .= (SUBPAGE_SEPARATOR . $args['month']);
        $pages = $dbi->titleSearch(new TextSearchQuery("^".$prefix, true, 'posix'));
        $html = HTML(); $i = 0;
        while (($page = $pages->next()) and $i < $count) {
            $rev = $page->getCurrentRevision(false);
            if ($rev->get('pagetype') != 'wikiblog') continue;
            $i++;
            $blog = $this->_blog($rev);
            $html->pushContent(HTML::h3(WikiLink($page, 'known', $rev->get('summary'))));
            $html->pushContent($rev->getTransformedContent('wikiblog'));
        }
        if ($args['user'] == $user->UserName())
            $html->pushContent(WikiLink(_("WikiBlog"), 'known', "New entry"));
        if (!$i)
            return HTML(HTML::h3(_("No Blog Entries")), $html);
        if (!$args['noheader'])
            return HTML(HTML::h3(sprintf(_("Blog Entries for %s:"), $this->_monthTitle($args['month']))),
                        $html);
        else
            return $html;
    }
};

// $Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>