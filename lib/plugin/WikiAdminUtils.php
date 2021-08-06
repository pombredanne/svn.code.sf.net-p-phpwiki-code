<?php
/**
 * Copyright © 2003,2004,2006 $ThePhpWikiProgrammingTeam
 * Copyright © 2009 Marc-Etienne Vargenau, Alcatel-Lucent
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
 * valid actions:
 * purge-cache
 * purge-bad-pagenames
 * purge-empty-pages
 * email-verification
 * db-check
 * db-rebuild
 */

class WikiPlugin_WikiAdminUtils
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Miscellaneous utility functions for the Administrator.");
    }

    function getDefaultArguments()
    {
        return array('action' => '',
            'label' => '',
        );
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
        $args = $this->getArgs($argstr, $request);
        $args['action'] = strtolower($args['action']);
        extract($args);

        if (!$action) {
            $this->error("No action specified");
        }
        if (!($default_label = $this->_getLabel($action))) {
            return HTML::div(array('class' => "error"), fmt("Bad action requested: %s", $action));
        }
        if ($request->getArg('action') != 'browse') {
            return $this->disabled(_("Plugin not run: not in browse mode"));
        }

        $posted = $request->getArg('wikiadminutils');

        if ($request->isPost() and $posted['action'] == $action) { // a different form. we might have multiple
            $user = $request->getUser();
            if (!$user->isAdmin()) {
                $request->_notAuthorized(WIKIAUTH_ADMIN);
                return $this->error(_("You must be an administrator to use this plugin."));
            }
            return $this->do_action($request, $posted);
        }
        if (empty($label))
            $label = $default_label;

        return $this->_makeButton($request, $args, $label);
    }

    protected function _makeButton($request, $args, $label)
    {
        $args['return_url'] = $request->getURLtoSelf();
        return HTML::form(array('action' => $request->getPostURL(),
                'method' => 'post'),
            HTML::p(Button('submit:', $label, 'wikiadmin')),
            HiddenInputs($args, 'wikiadminutils'),
            HiddenInputs(array('require_authority_for_post' =>
            WIKIAUTH_ADMIN)),
            HiddenInputs($request->getArgs(), false, array('action')));
    }

    function do_action($request, $args)
    {
        $method = strtolower('_do_' . str_replace('-', '_', $args['action']));
        if (!method_exists($this, $method))
            return $this->error("Bad action $method");

        $message = call_user_func(array(&$this, $method), $request, $args);

        // If needed, clean URL of previous message, remove '?' and after
        $return_url = $args['return_url'];
        if (strpos($return_url, '?')) {
            $return_url = substr($return_url, 0, strpos($return_url, '?'));
        }
        $url = WikiURL($return_url, array('warningmsg' => $message));

        return $request->redirect($url);
    }

    private function _getLabel($action)
    {
        $labels = array('purge-cache' => _("Purge Markup Cache"),
            'purge-bad-pagenames' => _("Purge all Pages With Invalid Names"),
            'purge-empty-pages' => _("Purge all empty, unreferenced Pages"),
            'email-verification' => _("E-mail Verification"),
            'db-check' => _("Check Wiki Database"),
            'db-rebuild' => _("Rebuild Wiki Database")
        );
        return @$labels[$action];
    }

    private function _do_purge_cache($request, $args)
    {
        $dbi = $request->getDbh();
        $pages = $dbi->getAllPages('include_empty'); // Do we really want the empty ones too?
        while (($page = $pages->next())) {
            $page->set('_cached_html', false);
        }
        return _("Markup cache purged!");
    }

    private function _do_purge_bad_pagenames($request, $args)
    {
        // FIXME: this should be moved into WikiDB::normalize() or something...
        $dbi = $request->getDbh();
        $count = 0;
        $list = HTML::ol(array('class' => 'align-left'));
        $pages = $dbi->getAllPages('include_empty'); // Do we really want the empty ones too?
        while (($page = $pages->next())) {
            $pagename = $page->getName();
            $wpn = new WikiPageName($pagename);
            if (!$wpn->isValid()) {
                $dbi->purgePage($pagename);
                $list->pushContent(HTML::li($pagename));
                $count++;
            }
        }
        $pages->free();
        if (!$count) {
            return _("No pages with bad names had to be deleted.");
        } else {
            return HTML(fmt("Deleted %d pages with invalid names:", $count),
                HTML::div(array('class' => 'align-left'), $list));
        }
    }

    /**
     * Purge all non-referenced empty pages. Mainly those created by bad link extraction.
     *
     * @param WikiRequest $request
     * @param array $args
     * @return string|XmlContent
     */
    private function _do_purge_empty_pages($request, $args)
    {
        $dbi = $request->getDbh();
        $count = 0;
        $notpurgable = 0;
        $list = HTML::ol(array('class' => 'align-left'));
        $pages = $dbi->getAllPages('include_empty');
        while (($page = $pages->next())) {
            if (!$page->exists()
                and ($links = $page->getBackLinks('include_empty'))
                    and !$links->next()
            ) {
                $pagename = $page->getName();
                if ($pagename == 'global_data' or $pagename == '.') continue;
                if ($dbi->purgePage($pagename))
                    $list->pushContent(HTML::li($pagename . ' ' . _("[purged]")));
                else {
                    $list->pushContent(HTML::li($pagename . ' ' . _("[not purgable]")));
                    $notpurgable++;
                }
                $count++;
            }
        }
        $pages->free();
        if (!$count)
            return _("No empty, unreferenced pages were found.");
        else
            return HTML(fmt("Deleted %d unreferenced pages:", $count),
                HTML::div(array('class' => 'align-left'), $list),
                ($notpurgable ?
                    fmt("The %d not-purgable pages/links are links in some page(s). You might want to edit them.",
                        $notpurgable)
                    : ''));
    }

    private function _do_db_check($request, $args)
    {
        longer_timeout(180);
        $dbh = $request->getDbh();
        $result = $dbh->_backend->check($args);
        if ($result) {
            return _("Database check was successful.");
        } else {
            return _("Database check failed.");
        }
    }

    private function _do_db_rebuild($request, $args)
    {
        longer_timeout(240);
        $dbh = $request->getDbh();
        $result = $dbh->_backend->rebuild($args);
        if ($result) {
            return _("Database rebuild was successful.");
        } else {
            return _("Database rebuild failed.");
        }
    }

    // pagelist with enable/disable button
    private function _do_email_verification($request, &$args)
    {
        $dbi = $request->getDbh();
        $pagelist = new PageList('pagename', array(), $args);
        //$args['return_url'] = 'action=email-verification-verified';
        $email = new _PageList_Column_email('email', _("E-mail"), 'left');
        $emailVerified = new _PageList_Column_emailVerified('emailVerified',
            _("Verification Status"), 'center');
        $pagelist->_columns[0]->_heading = _("Username");
        $pagelist->_columns[] = $email;
        $pagelist->_columns[] = $emailVerified;
        //This is the best method to find all users (Db and PersonalPage)
        $current_user = $request->_user;
        if (empty($args['verify'])) {
            $group = $request->getGroup();
            $allusers = $group->_allUsers();
        } else {
            if (!empty($args['user']))
                $allusers = array_keys($args['user']);
            else
                $allusers = array();
        }
        foreach ($allusers as $username) {
            $user = WikiUser($username);
            $prefs = $user->getPreferences();
            if ($prefs->get('email')) {
                if (!$prefs->get('userid'))
                    $prefs->set('userid', $username);
                if (!empty($pagelist->_rows))
                    $group = (int)(count($pagelist->_rows) / $pagelist->_group_rows);
                else
                    $group = 0;
                $class = ($group % 2) ? 'oddrow' : 'evenrow';
                $row = HTML::tr(array('class' => $class));
                $page_handle = $dbi->getPage($username);
                $row->pushContent($pagelist->_columns[0]->format($pagelist,
                    $page_handle, $page_handle));
                $row->pushContent($email->format($pagelist, $prefs, $page_handle));
                if (!empty($args['verify'])) {
                    $prefs->_prefs['email']->set('emailVerified',
                        empty($args['verified'][$username]) ? 0 : true);
                    $user->setPreferences($prefs);
                }
                $row->pushContent($emailVerified->format($pagelist, $prefs, $args['verify']));
                $pagelist->_rows[] = $row;
            }
        }
        $request->_user = $current_user;
        if (!empty($args['verify']) or empty($pagelist->_rows)) {
            return HTML($pagelist->_generateTable());
        } elseif (!empty($pagelist->_rows)) {
            $args['verify'] = 1;
            $args['return_url'] = $request->getURLtoSelf();
            return HTML::form(array('action' => $request->getPostURL(),
                    'method' => 'post'),
                HiddenInputs($args, 'wikiadminutils'),
                HiddenInputs(array('require_authority_for_post' =>
                WIKIAUTH_ADMIN)),
                HiddenInputs($request->getArgs()),
                $pagelist->_generateTable(),
                HTML::p(Button('submit:', _("Change Verification Status"), 'wikiadmin'),
                        HTML::raw('&nbsp;&nbsp;'),
                        Button('cancel', _("Cancel")))
            );
        }
    return HTML::raw('');
    }
}

require_once 'lib/PageList.php';

class _PageList_Column_email
    extends _PageList_Column
{
    function _getValue($page_handle, $revision_handle)
    {
        return $page_handle->get('email');
    }
}

class _PageList_Column_emailVerified
    extends _PageList_Column
{
    function _getValue($page_handle, $revision_handle)
    {
        $name = $page_handle->get('userid');
        $input = HTML::input(array('type' => 'checkbox',
            'name' => 'wikiadminutils[verified][' . $name . ']',
            'value' => 1));
        if ($page_handle->get('emailVerified'))
            $input->setAttr('checked', '1');
        if ($revision_handle)
            $input->setAttr('disabled', '1');
        return HTML($input, HTML::input
        (array('type' => 'hidden',
            'name' => 'wikiadminutils[user][' . $name . ']',
            'value' => $name)));
    }
}
