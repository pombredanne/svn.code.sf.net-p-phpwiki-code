<?php // -*-php-*-
rcs_id('$Id: WikiBlog.php,v 1.7 2003-11-17 16:23:55 carstenklapp Exp $');
/*
 Copyright 2002, 2003 $ThePhpWikiProgrammingTeam
 
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
 * Author: MichaelVanDam, major refactor by JeffDairiki
 */

require_once('lib/TextSearchQuery.php');

/**
 * This plugin shows 'blogs' (comments/news) associated with a
 * particular page and provides an input form for adding a new blog.
 *
 * HINTS/COMMENTS:
 *
 * To have comments show up on a separate page:
 * On TopPage, use
 *   <?plugin WikiBlog mode=add?>
 * Create TopPage/Comments with:
 *   <?plugin WikiBlog page=TopPage mode=show?>
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
                            "\$Revision: 1.7 $");
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
        $args = $this->getArgs($argstr, $request);
        if (!$args['page'])
            return $this->error("No page specified");

        // Get our form args.
        $blog = $request->getArg("blog");
        $request->setArg('blog', false);
            
        if ($request->isPost() and !empty($blog['addblog'])) {
            $this->addBlog($request, $blog); // noreturn
        }

        // Now we display previous comments and/or provide entry box
        // for new comments
        $html = HTML();
        foreach (explode(',', $args['mode']) as $show) {
            if (!empty($seen[$show]))
                continue;
            $seen[$show] = 1;
                
            switch ($show) {
            case 'show':
                $html->pushContent($this->showBlogs($request, $args));
                break;
            case 'add':
                $html->pushContent($this->showBlogForm($request, $args));
                break;
            default:
                return $this->error(sprintf("Bad mode ('%s')", $show));
            }
        }
        return $html;
    }


    function addBlog (&$request, $blog) {
        if (!($parent = $blog['page']))
            $request->finish("No page specified for blog.");

        $user = $request->getUser();
        $now = time();
        $dbi = $request->getDbh();
        
        /*
         * Page^H^H^H^H Blog meta-data
         *
         * This is info that won't change for each revision.
         * Nevertheless, it's now stored in the revision meta-data.
         * Several reasons:
         *  o It's more convenient to have all information required
         *    to render a page revision in the revision meta-data.
         *  o We can avoid a race condition, since version meta-data
         *    updates are atomic with the version creation.
         */

        $blog_meta = array('ctime' => $now,
                           'creator' => $user->getId(),
                           'creator_id' => $user->getAuthenticatedId(),
                           );
        

        // Version meta-data
        $summary = trim($blog['summary']);
        $version_meta = array('author' => $blog_meta['creator'],
                              'author_id' => $blog_meta['creator_id'],
                              'markup' => 2.0,   // assume new markup
                              'summary' => $summary ? $summary : _("New comment."),
                              'mtime' => $now,
                              'pagetype' => 'wikiblog',
                              'wikiblog' => $blog_meta,
                              );

        // Comment body.
        $body = trim($blog['body']);

        $saved = false;
        while (!$saved) {
            // Generate the page name.  For now, we use the format:
            //   Rootname/Blog-YYYYMMDDHHMMSS
            // This gives us natural chronological order when sorted
            // alphabetically.
            // a unique name is found.

            // change to ISO formatted time? 2003-01-11T24:03:02
            // Yes, I think that's a good idea. -- JeffDairiki.

            //$time = strftime ('%Y%m%d%H%M%S', $now);
            $time = Iso8601DateTime();
            $p = $dbi->getPage($parent . SUBPAGE_SEPARATOR . "Blog" . SUBPAGE_SEPARATOR . str_replace("T", SUBPAGE_SEPARATOR, "$time"));
            $pr = $p->getCurrentRevision();

            // Version should be zero.  If not, page already exists
            // so increment timestamp and try again.
            if ($pr->getVersion() > 0) {
                $now++;
                continue;
            }
            

            // FIXME: there's a slight, but currently unimportant
            // race condition here.  If someone else happens to
            // have just created a blog with the same name,
            // we'll have locked it before we discover that the name
            // is taken.
            /*
             * FIXME:  For now all blogs are locked.  It would be
             * nice to allow only the 'creator' to edit by default.
             */
            $p->set('locked', true); //lock by default
            $saved = $p->save($body, 1, $version_meta);

            $now++;
        }
        
        $dbi->touch();
        $request->redirect($request->getURLtoSelf()); // noreturn

        // FIXME: when submit a comment from preview mode,
        // adds the comment properly but jumps to browse mode.
        // Any way to jump back to preview mode???
    }

    function showBlogs (&$request, $args) {
        // FIXME: currently blogSearch uses WikiDB->titleSearch to
        // get results, so results are in alphabetical order.
        // When PageTypes fully implemented, could have smarter
        // blogSearch implementation / naming scheme.
        
        $dbi = $request->getDbh();

        $parent = $args['page'];
        $blogs = $this->findBlogs($dbi, $parent);
        $html = HTML();

        if ($blogs) {
            if ($args['order'] == 'reverse')
                $blogs = array_reverse($blogs);
            
            if (!$args['noheader'])
                $html->pushContent(HTML::h2(array('class' => 'wikiblog-heading'),
                                            fmt("Comments on %s:", WikiLink($parent))));
            foreach ($blogs as $rev) {
                if (!$rev->get('wikiblog')) {
                    // Ack! this is an old-style blog with data ctime in page meta-data.
                    $content = $this->_transformOldFormatBlog($rev);
                }
                else
                    $content = $rev->getTransformedContent('wikiblog');

                $html->pushContent($content);
            }
            
        }
        return $html;
    }

    function _transformOldFormatBlog($rev) {
        $page = $rev->getPage();

        foreach (array('ctime', 'creator', 'creator_id') as $key)
            $blog_meta[$key] = $page->get($key);
        $meta = $rev->getMetaData();
        $meta['wikiblog'] = $blog_meta;
        return new TransformedText($page, $rev->getPackedContent(), $meta, 'wikiblog');
    }

    function findBlogs (&$dbi, $parent) {
        $prefix = $parent . SUBPAGE_SEPARATOR;
        $pfxlen = strlen($prefix);
            require_once('lib/TextSearchQuery.php');
        $pages = $dbi->titleSearch(new TextSearchQuery ($prefix));

        $blogs = array();
        while ($page = $pages->next()) {
            // FIXME:
            // Verify that it is a blog page.  If not, go to next page.
            // When we proper blogSearch implementation this will not
            // be necessary. 
            $name = $page->getName();
            if (substr($name, 0, $pfxlen) != $prefix)
                continue;
            $current = $page->getCurrentRevision();
//use only pagetype
            if (/*preg_match("/^Blog-([[:digit:]]{14})$/", substr($name, $pfxlen))
                or */$current->get('pagetype') == 'wikiblog') {
                $blogs[] = $current;
            }
        }
        return $blogs;
    }
    
    function showBlogForm (&$request, $args) {
        // Show blog-entry form.
        return new Template('blogform', $request,
                            array('PAGENAME' => $args['page']));
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.6  2003/02/21 04:20:09  dairiki
// Big refactor. Formatting now done by the stuff in PageType.php.
// Split the template into two separate ones: one for the add comment form,
// one for comment display.
//
// Revision 1.5  2003/02/16 19:47:17  dairiki
// Update WikiDB timestamp when editing or deleting pages.
//
// Revision 1.4  2003/01/11 22:23:00  carstenklapp
// More refactoring to use templated output. Use page meta "summary" field.
//
// Revision 1.3  2003/01/06 02:29:02  carstenklapp
// New: use blog.tmpl template to format output. Some cosmetic
// issues, it mostly works but code still needs cleanup. Added
// getVersion() for PluginManager.
//

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>