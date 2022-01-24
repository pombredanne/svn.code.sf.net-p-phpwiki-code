<?php
/**
 * Copyright © 2002,2004 $ThePhpWikiProgrammingTeam
 * Copyright © 2008-2009 Marc-Etienne Vargenau, Alcatel-Lucent
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

/**
 * Usage:   <<WikiAdminRemove>>
 * Author:  Reini Urban
 *
 * KNOWN ISSUES:
 * Currently we must be Admin.
 * Future versions will support PagePermissions.
 */
// maybe display more attributes with this class...
require_once 'lib/PageList.php';
require_once 'lib/plugin/WikiAdminSelect.php';

class WikiPlugin_WikiAdminRemove
    extends WikiPlugin_WikiAdminSelect
{
    function getDescription()
    {
        return _("Remove selected pages").".";
    }

    protected function collectPages(&$list, &$dbi, $sortby, $limit = 0, $exclude = '')
    {

        $allPages = $dbi->getAllPages('include_empty', $sortby, $limit);
        while ($pagehandle = $allPages->next()) {
            $pagename = $pagehandle->getName();
            $current = $pagehandle->getCurrentRevision();
            if ($current->getVersion() < 1) {
                continue; // No versions in database
            }
            if (empty($list[$pagename])) {
                $list[$pagename] = false;
            }
        }
        return $list;
    }

    private function removePages($request, $pages)
    {
        $result = HTML::div();
        $ul = HTML::ul();
        $dbi = $request->getDbh();
        $count = 0;
        foreach ($pages as $name) {
            $name = str_replace(array('%5B', '%5D'), array('[', ']'), $name);
            if (mayAccessPage('remove', $name)) {
                $dbi->deletePage($name);
                $ul->pushContent(HTML::li(fmt("Removed page “%s” successfully.", $name)));
                $count++;
            } else {
                $ul->pushContent(HTML::li(fmt("Didn't remove page “%s”. Access denied.", $name)));
            }
        }
        if ($count) {
            $dbi->touch();
            $result->setAttr('class', 'feedback');
            if ($count == 1) {
                $result->pushContent(HTML::p(_("One page has been removed:")));
            } else {
                $result->pushContent(HTML::p(fmt("%d pages have been removed:", $count)));
            }
        } else {
            $result->setAttr('class', 'error');
            $result->pushContent(HTML::p(_("No pages removed.")));
        }
        $result->pushContent($ul);
        return $result;
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    function run($dbi, $argstr, &$request, $basepage)
    {
        if ($request->getArg('action') != 'browse') {
            if ($request->getArg('action') != __("PhpWikiAdministration")."/".__("Remove")) {
                return $this->disabled(_("Plugin not run: not in browse mode"));
            }
        }

        $args = $this->getArgs($argstr, $request);
        $this->_args = $args;
        $this->preSelectS($args, $request);

        $p = $request->getArg('p');
        if (!$p) {
            $p = $this->_list;
        }
        $post_args = $request->getArg('admin_remove');
        $next_action = 'select';
        $pages = array();
        if ($p && $request->isPost() &&
            !empty($post_args['remove']) && empty($post_args['cancel'])
        ) {
            // without individual PagePermissions:
            if (!ENABLE_PAGEPERM and !$request->_user->isAdmin()) {
                $request->_notAuthorized(WIKIAUTH_ADMIN);
                $this->disabled(_("You must be an administrator to use this plugin."));
            }
            if ($post_args['action'] == 'verify') {
                // Real delete.
                return $this->removePages($request, array_keys($p));
            }
            if ($post_args['action'] == 'select') {
                $next_action = 'verify';
                foreach ($p as $name => $c) {
                    $name = str_replace(array('%5B', '%5D'), array('[', ']'), $name);
                    $pages[$name] = $c;
                }
            }
        } elseif ($p && is_array($p) && !$request->isPost()) { // from WikiAdminSelect
            $next_action = 'verify';
            foreach ($p as $name => $c) {
                $name = str_replace(array('%5B', '%5D'), array('[', ']'), $name);
                $pages[$name] = $c;
            }
            $request->setArg('p', false);
        }
        if ($next_action == 'select') {
            // List all pages to select from.
            $pages = $this->collectPages($pages, $dbi, $args['sortby'], $args['limit'], $args['exclude']);
        }

        $header = HTML::fieldset();
        if ($next_action == 'verify') {
            $pagelist = new PageList_Unselectable($args['info'], $args['exclude'],
                array('types' =>
                array('remove'
                => new PageList_Column_remove('remove', _("Remove")))));
            $pagelist->addPageList($pages);
            $button_label = _("Yes");
            $header->pushContent(HTML::legend(_("Confirm removal")));
            $header->pushContent(HTML::p(HTML::strong(
                    _("Are you sure you want to remove the following pages?"))));
        } else {
            $pagelist = new PageList_Selectable($args['info'], $args['exclude'],
                array('types' =>
                array('remove'
                => new PageList_Column_remove('remove', _("Remove")))));
            $pagelist->addPageList($pages);
            $button_label = _("Remove selected pages");
            $header->pushContent(HTML::legend(_("Select the pages to remove")));
        }

        $buttons = HTML::p(Button('submit:admin_remove[remove]', $button_label, 'wikiadmin'),
                           HTML::raw("&nbsp;&nbsp;"),
                           Button('submit:admin_remove[cancel]', _("Cancel"), 'button'));
        $header->pushContent($buttons);

        return HTML::form(array('action' => $request->getPostURL(),
                'method' => 'post'),
            $header,
            $pagelist->getContent(),
            HiddenInputs($request->getArgs(),
                false,
                array('admin_remove')),
            HiddenInputs(array('admin_remove[action]' => $next_action,
                'require_authority_for_post' => WIKIAUTH_ADMIN)));
    }
}

class PageList_Column_remove extends _PageList_Column
{
    function _getValue($page_handle, $revision_handle)
    {
        return Button(array('action' => 'remove'), _("Remove"),
            $page_handle->getName());
    }
}
