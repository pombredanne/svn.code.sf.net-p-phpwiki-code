<?php // -*-php-*-
rcs_id('$Id: WikiAdminRemove.php,v 1.9 2003-02-26 22:27:22 dairiki Exp $');
/*
 Copyright 2002 $ThePhpWikiProgrammingTeam

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
 * Usage:   <?plugin WikiAdminRemove?>
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * KNOWN ISSUES:
 * Currently we must be Admin.
 * Future versions will support PagePermissions.
 * requires PHP 4.2 so far.
 */
// maybe display more attributes with this class...
require_once('lib/PageList.php');

class WikiPlugin_WikiAdminRemove
extends WikiPlugin
{
    function getName() {
        return _("WikiAdminRemove");
    }

    function getDescription() {
        return _("Permanently remove all selected pages.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.9 $");
    }

    function getDefaultArguments() {
        return array(
                     /*
                      * Show only pages which have been 'deleted' this
                      * long (in days).  (negative or non-numeric
                      * means show all pages, even non-deleted ones.)
                      *
                      * FIXME: could use a better name.
                      */
                     'min_age' => 0,

                     /*
                      * Automatically check the checkboxes for files
                      * which have been 'deleted' this long (in days).
                      *
                      * FIXME: could use a better name.
                      */
                     'max_age' => 31,

                     /* Pages to exclude */
                     'exclude'  => '',

                     /* Columns to include in listing */
                     'info'     => '',

                     /* How to sort */
                     'sortby'   => ''
                     );
    }

    function collectPages(&$list, &$dbi) {
        extract($this->_args);

        $now = time();
        
        $allPages = $dbi->getAllPages('include_deleted');
        while ($pagehandle = $allPages->next()) {
            $pagename = $pagehandle->getName();
            $current = $pagehandle->getCurrentRevision();
            if ($current->getVersion() < 1)
                continue;       // No versions in database

            $empty = $current->hasDefaultContents();
            if ($empty) {
                $age = ($now - $current->get('mtime')) / (24 * 3600.0);
                $checked = $age >= $max_age;
            }
            else {
                $age = 0;
                $checked = false;
            }

            if ($age > $min_age) {
                if (empty($list[$pagename]))
                    $list[$pagename] = $checked;
            }
        }
    }

    function removePages(&$request, $pages) {
        $ul = HTML::ul();
        $dbi = $request->getDbh();
        foreach ($pages as $name) {
            $dbi->deletePage($name);
            $ul->pushContent(HTML::li(fmt("Removed page '%s' succesfully.", $name)));
        }
        $dbi->touch();
        return HTML($ul,
                    HTML::p(_('All selected pages have been permanently removed.')));
    }
    
    function run($dbi, $argstr, $request) {
        if ($request->getArg('action') != 'browse')
            return $this->disabled("(action != 'browse')");
        
        $args = $this->getArgs($argstr, $request);
        if (!is_numeric($args['min_age']))
            $args['min_age'] = -1;
        $this->_args = $args;
        
        if (!empty($args['exclude']))
            $exclude = explodePageList($args['exclude']);
        else
            $exclude = false;


        $p = $request->getArg('p');
        $post_args = $request->getArg('admin_remove');

        $next_action = 'select';
        $pages = array();
        
        if ($p && $request->isPost() && $request->_user->isAdmin()
            && !empty($post_args['remove']) && empty($post_args['cancel'])) {
            // FIXME: error message if not admin.
            if ($post_args['action'] == 'verify') {
                // Real delete.
                return $this->removePages($request, $p);
            }

            if ($post_args['action'] == 'select') {
                $next_action = 'verify';
                foreach ($p as $name) {
                    $pages[$name] = 1;
                }
            }
        }
        if ($next_action == 'select') {
            // List all pages to select from.
            $list = $this->collectPages($pages, $dbi);
        }


        $info = 'checkbox';
        if ($args['info'])
            $info .= "," . $args['info'];
        $pagelist = new PageList_Selectable($info, $exclude);
        $pagelist->addPageList($pages);

        $header = HTML::p();
        if ($next_action == 'verify') {
            $button_label = _("Permanently remove selected pages");
            $header->pushContent(HTML::strong(
                _("Are you sure you want to permanently remove the selected files?")));
        }
        else {
            $button_label = _("Remove selected pages");
            if ($args['min_age'] >= 0) {
                $header->pushContent(
                    fmt("Listing pages which have been deleted at least %s days.",
                        $args['min_age']));
            }
            else {
                $header->pushContent(_("Listing all pages."));
            }
            
            if ($args['max_age'] >= 0) {
                $header->pushContent(
                    " ",
                    fmt("(Automatically checking pages which have been deleted at least %s days.)",
                        $args['max_age']));
            }
        }


        $buttons = HTML::p(Button('submit:admin_remove[remove]', $button_label, 'wikiadmin'),
                           Button('submit:admin_remove[cancel]', _("Cancel"), 'button'));

        return HTML::form(array('action' => $request->getPostURL(),
                                'method' => 'post'),

                          $header,
                          
                          $pagelist->getContent(),

                          HiddenInputs($request->getArgs(),
                                        false,
                                        array('admin_remove')),

                          HiddenInputs(array('admin_remove[action]' => $next_action,
                                             'require_authority_for_post' => WIKIAUTH_ADMIN)),
                          $buttons);
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.8  2003/02/17 17:23:59  dairiki
// Disable plugin unless action='browse'.
//
// Add a header to the output, and adjust the HTML formatting a bit.
//
// Revision 1.7  2003/02/17 06:06:33  dairiki
// Refactor & code cleanup.
//
// Added two new plugin arguments:
//
//   min_age - only display pages which have been "deleted" for at
//             least this many days.  (Use min_age=none to get all
//             pages, even non-deleted ones listed.)
//
//   max_age - automatically check the checkboxes of pages which
//             have been "deleted" this many days or more.
//
// ("Deleted" means the current version of the page is empty.
// For the most part, PhpWiki treats these "deleted" pages as
// if they didn't exist --- but, of course, the PageHistory is
// still available, allowing old versions of the page to be restored.)
//
// Revision 1.6  2003/02/16 19:47:17  dairiki
// Update WikiDB timestamp when editing or deleting pages.
//
// Revision 1.5  2003/01/18 22:14:28  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
