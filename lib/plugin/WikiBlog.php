<?php // -*-php-*-
rcs_id('$Id: WikiBlog.php,v 1.3 2003-01-06 02:29:02 carstenklapp Exp $');
/**
 * Author: MichaelVanDam
 */

require_once('lib/TextSearchQuery.php');
//require_once('lib/plugin/IncludePage.php');

/**
 * This plugin shows 'blogs' (comments/news) associated with a
 * particular page and provides an input form for adding a new blog.
 *
 * TODO:
 *
 * It also works as an action-page if you create a page called 'WikiBlog'
 * containing this plugin.  This allows adding comments to any page
 * by linking "PageName?action=WikiBlog".  Maybe a nice feature in
 * lib/displaypage.php would be to automatically check if there are
 * blogs for the given page, then provide a link to them somewhere on
 * the page.  Or maybe this just creates a huge mess...
 *
 * Maybe it would be a good idea to ENABLE blogging of only certain
 * pages by setting metadata or something...?  If a page is non-bloggable
 * the plugin is ignored (perhaps with a warning message).
 *
 * Should blogs be by default filtered out of RecentChanges et al???
 *
 * Think of better name for this module: Blog? WikiLog? WebLog? WikiDot?
 *
 * Have other 'styles' for the plugin?... e.g. 'quiet'.  Display only
 * 'This page has 23 associated comments. Click here to view / add.'
 *
 * For admin user, put checkboxes beside comments to allow for bulk removal.
 *
 * Permissions for who can add blogs?  Display entry box only if
 * user meets these requirements...?
 *
 * Code cleanup: break into functions, use templates (or at least remove CSS)
 *
 */


class WikiPlugin_WikiBlog
extends WikiPlugin
{
    function getName () {
        return _("WikiBlog");
    }

    function getDescription () {
        return sprintf(_("Show and add blogs for %s"),'[pagename]');
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.3 $");
    }

    // Arguments:
    //
    //  page - page which is blogged to (default current page)
    //
    //  order - 'normal' - place in chronological order
    //        - 'reverse' - place in reverse chronological order
    //
    //  mode - 'show' - only show old blogs
    //         'add' - only show entry box for new blog
    //         'show,add' - show old blogs then entry box
    //         'add,show' - show entry box followed by old blogs
    //
    // TODO:
    //
    // - arguments to allow selection of time range to display
    // - arguments to display only XX blogs per page (can this 'paging'
    //    co-exist with the wiki??  difficult)
    // - arguments to allow comments outside this range to be
    //    display as e.g. June 2002 archive, July 2002 archive, etc..
    // - captions for 'show' and 'add' sections


    function getDefaultArguments() {
        return array('page'       => '[pagename]',
                     'order'      => 'normal',
                     'mode'       => 'show,add',
                     'noheader'   => false
                    );
    }


    function run($dbi, $argstr, $request) {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);
        if (!$page)
            return '';


        // Look at arguments to see if blog was submitted.  If so,
        // process this before displaying anything.

        if ($request->getArg('addblog')) {

            // TODO: change args to blog[page], blog[summary], blog[body] etc

            $blog_page = $request->getArg('blog_page');


            // Generate the page name.  For now, we use the format:
            //   Rootname/Blog-YYYYMMDDHHMMSS
            // This gives us natural chronological order when sorted
            // alphabetically.
            // This is inside a loop because there is a small
            // chance that another user could add a blog with
            // the same timestamp.  If such a conflict is
            // detected, increment timestamp by 1 second until
            // a unique name is found.

            $now = time();

            $p = false;  // will store our page
            $time = false; // will store our timestamp

            $saved = false;
            while (!$saved) {

                $time = strftime ('%Y%m%d%H%M%S', $now);

                $p = $dbi->getPage($blog_page . SUBPAGE_SEPARATOR . "Blog-$time");

                $pr = $p->getCurrentRevision();

                // Version should be zero.  If not, page already exists
                // so increment timestamp and try again.

                if ($pr->getVersion() > 0) $now ++;
                else $saved = true;

            }


            // Generate meta-data for page
            // This is info that won't change for each revision:
            //   ctime (time of creation), creator, and creator_id.
            // Create-date is really the relevant date for a blog,
            // not the last-modified date.

            // FIXME:  For now all blogs are locked.  It would be
            // nice to allow only the 'creator' to edit by default.

            $user = $request->getUser();
            $user_id = $user->getId();
            $user_auth_id = $user->getAuthenticatedId();

            $p->set ('ctime', $now);
            $p->set ('creator', $user_id);
            $p->set ('creator_id', $user_auth_id);
            $p->set ('locked', true);  // lock by default
            $p->set ('pagetype', 'wikiblog');

            // Generate meta-data for page revision

            $meta = array('author' => $user_id,
                          'author_id' => $user_auth_id,
                          'markup' => 2.0,   // assume new markup
                          'summary' => _("New comment."),
                         );

            // FIXME: For now the page format is:
            //
            //   __Summary__
            //   Body
            //
            // This helps during editing (using standard editor)
            // when the page/revision metadata is not available
            // to the user.  If we had a pagetype-specific editor
            // then we could put the Summary into meta-data and
            // still make it available for editing.
            // Also, it helps for now during rendering because we
            // don't need to create a new PageType class just yet...

            // FIXME: move summary into the page's summary field
            // PageType now displays the summary field when viewing
            // an individual blog page
            $summary = trim($request->getArg('blog_summary'));
            $body = trim($request->getArg('blog_body'));

            $content = '';

            if ($summary) {
                $content .= '__' . $summary . '__%%%' . "\n";
            }

            $content .= $body;

            $pr = $p->createRevision($pr->getVersion()+1,
                                     $content,
                                     $meta,
                                     ExtractWikiPageLinks($content));

            // FIXME: Detect if !$pr ??  How to handle errors?


            // Save was successful.  Unset all the arguments and
            // redirect to page that user was viewing before.
            // Unsetting arguments will prevent double-submitting
            // problem and will clear out the text-boxes.

            $request->setArg('addblog', false);
            $request->setArg('blog_page', false);
            $request->setArg('blog_body', false);
            $request->setArg('blog_summary', false);

            $url = $request->getURLtoSelf();
            $request->redirect($url);

            // FIXME: when submit a comment from preview mode,
            // adds the comment properly but jumps to browse mode.
            // Any way to jump back to preview mode???


            // The rest of the output will not be seen due to
            // the redirect.

        }


        // Now we display previous comments and/or provide entry box
        // for new comments

        $showblogs = true;
        $showblogform = true;

        switch ($mode) {

            case 'show':
                $showblogform = false;

            case 'add':
                $showblogs = false;

            case 'show,add':
            case 'add,show':
            default:

            // TODO: implement ordering show,add vs add,show !

        }


        $html = HTML();

        if ($showblogs) {
            $html->pushContent($this->showBlogs ($dbi, $request, $page, $order));
        }

        if ($showblogform) {
            $html->pushContent($this->showBlogForm ($dbi, $request, $page));
        }

        return $html;

    }


    function showBlogs ($dbi, $request, $page, $order) {

        // Display comments:

        // FIXME: currently blogSearch uses WikiDB->titleSearch to
        // get results, so results are in alphabetical order.
        // When PageTypes fully implemented, could have smarter
        // blogSearch implementation / naming scheme.

        $pages = $dbi->blogSearch($page, $order);

        $all_comments = HTML();

        while ($p = $pages->next()) {

            // FIXME:
            // Verify that it is a blog page.  If not, go to next page.
            // When we proper blogSearch implementation this will not
            // be necessary.  Non-blog pages will not be returned by
            // blogSearch.

            $name = $p->getName();
            // If page contains '/', we must escape them
            // FIXME: only works for '/' SUBPAGE_SEPARATOR
            $escpage = preg_replace ("/\//", '\/', $page);
            if (!preg_match("/^$escpage\/Blog-([[:digit:]]{14})$/", $name, $matches))
                continue;

            // Display contents:

            // TODO: ultimately have a function in PageType to handle
            // display of blog entries...

            // If we want to use IncludePage plugin to display blog:
            // $i = new WikiPlugin_IncludePage;
            // $html->pushContent($i->run($dbi, "page=$name", $request));

            global $WikiNameRegexp;
            global $Theme;

            $ctime = $p->get('ctime');
            $ctime = $Theme->formatDateTime($ctime);

            $creator = $p->get('creator');
            $creator_id = $p->get('creator_id');
            $creator_orig = $creator;

            if (preg_match("/^$WikiNameRegexp\$/", $creator)
                               && $dbi->isWikiPage($creator))
                $creator = WikiLink($creator);

            $pr = $p->getCurrentRevision();

            $modified = ($pr->getVersion() > 1);

            $mtime = $pr->get('mtime');
            $mtime = $Theme->formatDateTime($mtime);

            $author    = $pr->get('author');
            $author_id = $pr->get('author_id');
            $author_orig = $author;

            $summary = $pr->get('summary');

            if (preg_match("/^$WikiNameRegexp\$/", $author)
                               && $dbi->isWikiPage($author))
                $author = WikiLink($author);

            $content = $pr->getPackedContent();

            $browseaction = WikiURL($name, array('action'=>'browse'));
            $editaction = WikiURL($name, array('action'=>'edit'));
            $removeaction = WikiURL($name,array('action'=>'remove'));

            $browselink = HTML::a(array('href'=>$browseaction, 'class'=>'wikiaction'), 'Browse');
            $editlink = HTML::a(array('href'=>$editaction, 'class'=>'wikiadmin'),'Edit');
            $removelink = HTML::a(array('href'=>$removeaction, 'class'=>'wikiadmin'), 'Delete');

            $one_comment = HTML();

            // FIXME if necessary:
            $creator_id_string = (strcmp($creator_orig,$creator_id) == 0) ? '' : ' (' . $creator_id . ')';

            $one_comment->pushContent(HTML::tr(array('bgcolor'=>'#CCCCFF'),
               HTML::td(array('align'=>'left'), HTML::strong($ctime)),
               HTML::td(array('align'=>'right'),
                 HTML::strong($creator , $creator_id_string))
            ));


            $transformedContent = TransformInline($content);

            $one_comment->pushContent(HTML::tr(array('bgcolor'=>'#EEEEEE'),HTML::td(array('colspan'=>2),$transformedContent)));

            // FIXME if necessary:
            $author_id_string = (strcmp($author_orig,$author_id) == 0) ? '' : ' (' . $author_id . ')';

            if ($modified) {
                $one_comment->pushContent(HTML::tr(array('bgcolor'=>'#EEEEFF'),
                   HTML::td(array('align'=>'right','colspan'=>'2'),HTML::small('Comment modified on ',$mtime,  ' by ', $author , $author_id_string))));
            }

            // FIXME: for now we just show all links on all entries.
            // This should be customizable, or at least show only
            // 'edit' 'remove' for admin and creator.
/*
            $one_comment->pushContent(HTML::tr(HTML::td(array('colspan'=>2),
               $browselink, ' ', $editlink, ' ', $removelink)));
*/
/*
            $all_comments->pushContent(HTML::div(array('margin-bottom'=>'330px', 'margin-left'=>'1.0em', 'margin-right'=>'1.0em'),HTML::table(array('border'=>0,'align'=>'center','width'=>'96%'),HTML::tr(HTML::td($one_comment))),HTML::br(),HTML::br()));
*/
            // $args set here to be available as variables in the template
            $args['noheader'] = $this->_args['noheader']; // should always be false for
                                                          // subsequent comments when there
                                                          // is more than one comment
            $args['pagelink'] = WikiLink($page);
            $args['pagename'] = $page;
            $args['browselink'] = $browselink;
            $args['editlink'] = $editlink;
            $args['removelink'] = $removelink;
            $args['authorlink'] = $author;
            $args['author'] = $p->get('creator'); //fixme
            $args['summary'] = $summary;
            $args['comment'] = $transformedContent;
            $args['date'] = $ctime;
            $args['datemodified'] = $mtime;
            $args['authormodified'] = $pr->get('author');
            $template = new Template('blog', $request, $args);
            $all_comments->pushContent(($template));
        }

        //$header = HTML::h4('Comments on ', WikiLink($page), ':');
        //return HTML($header,$all_comments);
        return $all_comments;

    }


    function showBlogForm ($dbi, $request, $page) {

        // Show blog-entry form.

        $hiddenfield = HTML::input(array('type'=>'hidden',
                                         'name'=>'blog_page',
                                         'value'=>$page));

        $summaryfield = HTML::input(array('class'=>'wikitext',
                                        'type'=>'text',
                                        'size'=>60,
                                        'maxlength' => 256,
                                        'name' => 'blog_summary',
                                        'value' => ''));

        $contentfield = HTML::textarea(array('class'=>'wikiedit',
                                           'rows'=>5,
                                           'cols'=>60,
                                           'name' => 'blog_body',
                                           'wrap'=>'virtual'),
                                     '');

        $buttontext = 'Submit';
        $class = false;
        $button = Button('submit:addblog', $buttontext, $class);

        $action = $request->getURLtoSelf();

        $form = HTML::form(array('action' => $action,
                                 'method' => 'post'), $hiddenfield,
                HTML::table(array('align'=>'center'),HTML::tr(array('bgcolor'=>'#EEEEEE'),HTML::th('Summary:'), HTML::td($summaryfield)),
                            HTML::tr(array('bgcolor'=>'#EEEEEE'),HTML::th('Comment:'), HTML::td($contentfield)),
                            HTML::tr(HTML::th(),HTML::td(array('align'=>'center'),$button))
                ));

        $header = HTML::h4('Add new comment:');
        return HTML($header, $form);

    }


};

// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>