<?php // -*-php-*-
rcs_id('$Id: WikiAdminRename.php,v 1.3 2004-02-12 13:05:50 rurban Exp $');
/*
 Copyright 2004 $ThePhpWikiProgrammingTeam

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
 * Usage:   <?plugin WikiAdminRename ?> or called via WikiAdminSelect
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * KNOWN ISSUES:
 * Currently we must be Admin.
 * Future versions will support PagePermissions.
 * requires PHP 4.2 so far.
 */
require_once('lib/PageList.php');
require_once('lib/plugin/WikiAdminSelect.php');

class WikiPlugin_WikiAdminRename
extends WikiPlugin_WikiAdminSelect
{
    function getName() {
        return _("WikiAdminRename");
    }

    function getDescription() {
        return _("Rename selected pages.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.3 $");
    }

    function getDefaultArguments() {
        return array(
                     /* Pages to exclude */
                     'exclude'  => '',
                     /* Columns to include in listing */
                     'info'     => 'pagename,mtime',
                     /* How to sort */
                     'sortby'   => 'pagename',
                     );
    }

    function renameHelper($name, $from, $to) {
        return str_replace($from,$to,$name);
    }

    function renamePages(&$dbi, &$request, $pages, $from, $to) {
        $ul = HTML::ul();
        $count = 0;
        foreach ($pages as $name) {
            if ( ($newname = $this->renameHelper($name,$from,$to)) and 
                  $newname != $name and
                  $dbi->renamePage($name,$newname) ) {
                /* not yet implemented for all backends */
                $ul->pushContent(HTML::li(fmt("Renamed page '%s' to '%s'.",$name,WikiLink($newname))));
                $count++;
            } else {
                $ul->pushContent(HTML::li(fmt("Couldn't rename page '%s' to '%s'.", $name, $newname)));
            }
        }
        if ($count) {
            $dbi->touch();
            return HTML($ul,
                        HTML::p(fmt("%s pages have been permanently renamed.",$count)));
        } else {
            return HTML($ul,
                        HTML::p(fmt("No pages renamed.")));
        }
    }
    
    function run($dbi, $argstr, $request) {
        if ($request->getArg('action') != 'browse')
            return $this->disabled("(action != 'browse')");
        
        $args = $this->getArgs($argstr, $request);
        $this->_args = $args;
        if (!empty($args['exclude']))
            $exclude = explodePageList($args['exclude']);
        else
            $exclude = false;


        $p = $request->getArg('p');
        $post_args = $request->getArg('admin_rename');
        $next_action = 'select';
        $pages = array();
        
        if ($p && $request->isPost() && $request->_user->isAdmin()
            && !empty($post_args['rename']) && empty($post_args['cancel'])) {
            // FIXME: error message if not admin.
            if ($post_args['action'] == 'verify') {
                // Real action
                return $this->renamePages($dbi, $request, $p, $post_args['from'], $post_args['to']);
            }

            if ($post_args['action'] == 'select') {
                if (!empty($post_args['from']))
                    $next_action = 'verify';
                foreach ($p as $name) {
                    $pages[$name] = 1;
                }
            }
        }
        if ($next_action == 'select') {
            // List all pages to select from.
            $list = $this->collectPages($pages, $dbi, $args['sortby']);
        }


        $info = 'checkbox';
        if ($args['info'])
            $info .= "," . $args['info'];
        if ($next_action == 'verify') {
            $info = "checkbox,pagename,renamed_pagename";
        }
        $pagelist = new PageList_Selectable($info, $exclude);
        $pagelist->addPageList($pages);

        $header = HTML::p();
        if ($next_action == 'verify') {
            $button_label = _("Yes");
            $header->pushContent(
              HTML::p(HTML::strong(
                                   _("Are you sure you want to permanently rename the selected files?"))));
            $header = $this->renameForm($header, $post_args);
        }
        else {
            $button_label = _("Rename selected pages");
            $header->pushContent(HTML::p(_("Select the pages to rename:")));
            $header = $this->renameForm($header, $post_args);
        }


        $buttons = HTML::p(Button('submit:admin_rename[rename]', $button_label, 'wikiadmin'),
                           Button('submit:admin_rename[cancel]', _("Cancel"), 'button'));

        return HTML::form(array('action' => $request->getPostURL(),
                                'method' => 'post'),
                          $header,
                          $pagelist->getContent(),
                          HiddenInputs($request->getArgs(),
                                        false,
                                        array('admin_rename')),
                          HiddenInputs(array('admin_rename[action]' => $next_action,
                                             'require_authority_for_post' => WIKIAUTH_ADMIN)),
                          $buttons);
    }

    function renameForm(&$header, $post_args) {
        $header->pushContent(_("Rename")." "._("from").': ');
        $header->pushContent(HTML::input(array('name' => 'admin_rename[from]',
                                               'value' => $post_args['from'])));
        $header->pushContent(' '._("to").': ');
        $header->pushContent(HTML::input(array('name' => 'admin_rename[to]',
                                               'value' => $post_args['to'])));
        $header->pushContent(' '._("(no regex, case-sensitive)"));
        $header->pushContent(HTML::p());
        return $header;
    }

}

// $Log: not supported by cvs2svn $
// Revision 1.2  2004/02/12 11:45:11  rurban
// only WikiDB method missing
//
// Revision 1.1  2004/02/11 20:00:16  rurban
// WikiAdmin... series overhaul. Rename misses the db backend methods yet. Chmod + Chwon still missing.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
