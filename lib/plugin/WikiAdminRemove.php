<?php // -*-php-*-
rcs_id('$Id: WikiAdminRemove.php,v 1.3 2002-08-24 13:18:56 rurban Exp $');
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
 * Displays a url in a seperate frame inside our body.
 * Usage:   <?plugin WikiAdminRemove?>
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * KNOWN ISSUES:
 * Currently we must be Admin.
 * Future versions will support PagePermissions.
 * requires PHP 4.2 so far.
 */ 
// maybe display more attributes with this class...
//require_once('lib/PageList.php'); 

class WikiPlugin_WikiAdminRemove
extends WikiPlugin
{
    function getName() {
        return _("WikiAdminRemove");
    }

    function getDescription() {
        return _("Permanently remove all selected pages.");
    }

    function getDefaultArguments() {
        return array('only'    => '',
                     'exclude' => '',
                     'debug' => false);
    }

    function collectPages(&$list, &$dbi) {
        $allPages = $dbi->getAllPages();
        while ($pagehandle = $allPages->next()) {
            $pagename = $pagehandle->getName();
            if (empty($list[$pagename])) $list[$pagename] = 0;
        }
    }

    function addTableHead(&$table) {
        $row = HTML::tr(HTML::th(_("Select")),
                        HTML::th(_("Name")));
        $table->pushContent(HTML::thead($row));
    }

    function addTableBody(&$list, &$table) {
        $tbody = HTML::tbody();
        foreach ($list as $pagename => $selected) {
            if ($selected) {
                $row = HTML::tr(array('class' => 'oddrow'),
                                HTML::td(HTML::input(array('type' => 'checkbox',
                                                           'name' => "p[$pagename]",
                                                           'value' => $pagename,
                                                           'checked' => '1'))),
                                HTML::td(WikiLink($pagename)));
            } else {
                $row = HTML::tr(array('class' => 'evenrow'),
                                HTML::td(HTML::input(array('type' => 'checkbox',
                                                           'name' => "p[$pagename]",
                                                           'value' => $pagename))),
                                HTML::td(WikiLink($pagename)));
            }
            $tbody->pushContent($row);
        }
        $table->pushContent($tbody);
    }

    function formatTable(&$list, &$dbi) {
        $table = HTML::table(array('cellpadding' => 2,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'class' => 'pagelist'));
        $table->pushContent(HTML::caption(array('align'=>'top'), 
                                          _("Permanently remove all selected pages.")));
        // $this->addTableHead($table);
        $this->addTableBody($list, $table);
        return $table;
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        if (!empty($args['only']))
            $this->only = explode(',',$args['only']);
        if (!empty($args['exclude']))
            $this->only = explode(',',$args['exclude']);
        $this->debug = $args['debug'];
        $this->_list = array();
        // array_multisort($this->_list, SORT_NUMERIC, SORT_DESC);
        $pagename = $request->getArg('pagename');
        // GetUrlToSelf() with all given params
        $uri = $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'];
        $form = HTML::form(array('action' => $uri, 'method' => 'POST'));
	$p = $request->getArgs('p'); // $GLOBALS['HTTP_POST_VARS']['p']
        if ($request->isPost() and $request->_user->isAdmin() and 
            $request->getArg('verify')) {
            // List all to be deleted pages again.
            foreach ($p as $page => $name) {
                $this->_list[$name] = 1;
            }
        } elseif ($request->isPost() and $request->_user->isAdmin() and 
        	  $request->getArg('remove')) {
            // Real delete.
            $ul = HTML::ul();
            foreach ($p as $page => $name) {
                $dbi = $request->getDbh();
                $dbi->deletePage($name);
                $ul->pushContent(HTML::li(fmt("Removed page '%s' succesfully.", $name)));
            }
        } else {
            // List all pages to select from.
            $this->collectPages($this->_list, &$dbi);
        }
        $table = $this->formatTable($this->_list, &$dbi, &$request);
        $form->pushContent($table);
        $form->pushContent(HiddenInputs($GLOBALS['HTTP_GET_VARS'])); // debugging params, ...
        // if (! USE_PATH_INFO ) $form->pushContent(HTML::input(array('type' => 'hidden', 'name' => 'pagename', 'value' => $pagename)));
        if (! $request->getArg('verify')) {
            $form->pushContent(HTML::input(array('type' => 'hidden', 'name' => 'action', 'value' => 'verify')));
            $form->pushContent(Button('submit:verify', _("Remove selected pages"), 'wikiadmin'), 
                               Button('submit:cancel', _("Cancel"), 'button'));
        } else {
            $form->pushContent(HTML::input(array('type' => 'hidden', 'name' => 'action', 'value' => 'remove')));
            $form->pushContent(Button('submit:remove', _("Remove selected pages"), 'wikiadmin'),
                               Button('submit:cancel', _("Cancel"), 'button'));
        }
        if (! $request->getArg('remove')) {
            return $form;
        } else {
            return HTML::div($ul,HTML::p(_('All selected pages have been permanently removed.')));
        }
    }
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>