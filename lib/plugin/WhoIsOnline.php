<?php
/**
 * Copyright © 2004 $ThePhpWikiProgrammingTeam
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
 * Show summary information of the current user sessions.
 * We support two modes: summary and detail. The optional page argument
 * links to the page with the other mode.
 *
 * Formatting and idea borrowed from postnuke. Requires USE_DB_SESSION.
 * Works with PearDB and dba DbSessions.
 *
 * Author: Reini Urban
 */

class WikiPlugin_WhoIsOnline extends WikiPlugin
{
    public function getDescription()
    {
        return _("Show summary information of the current user sessions.");
    }

    public function getDefaultArguments()
    {
        // two modes: summary and detail, page links to the page with the other mode
        return array(
            'mode' => 'summary', // or "detail"
            'pagename' => '[pagename]', // refer to the page with the other mode
            'allow_detail' => false, // if false, page is ignored
            'dispose_admin' => false,
        );
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return Template
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        global $WikiTheme;

        $request->setArg('nocache', 1);
        $args = $this->getArgs($argstr, $request);
        extract($args);

        if (!is_bool($allow_detail)) {
            if (($allow_detail == '0') || ($allow_detail == 'false')) {
                $allow_detail = false;
            } elseif (($allow_detail == '1') || ($allow_detail == 'true')) {
                $allow_detail = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "allow_detail"));
            }
        }

        if (!is_bool($dispose_admin)) {
            if (($dispose_admin == '0') || ($dispose_admin == 'false')) {
                $dispose_admin = false;
            } elseif (($dispose_admin == '1') || ($dispose_admin == 'true')) {
                $dispose_admin = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "dispose_admin"));
            }
        }

        // use the "online.tmpl" template
        // todo: check which arguments are really needed in the template.
        $stats = $this->getStats($dbi, $request, $args['mode']);
        if ($src = $WikiTheme->getImageURL("whosonline")) {
            $img = HTML::img(array('src' => $src, 'alt' => $this->getName()));
        } else {
            $img = '';
        }
        $other = array();
        $other['ONLINE_ICON'] = $img;
        return new Template('online', $request, array_merge($args, $stats, $other));
    }

    /**
     * box is used to display a fixed-width, narrow version with common header
     * just the number of online users.
     *
     * @param string $args
     * @param WikiRequest $request
     * @param string $basepage
     * @return HTML
     */
    public function box($args = '', $request = null, $basepage = '')
    {
        if (!$request) {
            $request =& $GLOBALS['request'];
        }
        $stats = $this->getStats($request->_dbi, $request);
        return $this->makeBox(
            _("Who is Online"),
            HTML(HTML::ul(HTML::li(
                WikiLink(
                    _("WhoIsOnline"),
                    'auto',
                    sprintf(
                        ngettext("%d online user", "%d online users", $stats['NUM_USERS']),
                        $stats['NUM_USERS']
                    )
                )
            )))
        );
    }

    // check the current sessions
    public function getStats($dbi, $request, $mode = 'summary')
    {
        $num_pages = 0;
        $num_users = 0;
        $page_iter = $dbi->getAllPages();
        while ($page = $page_iter->next()) {
            if ($page->isUserPage()) {
                $num_users++;
            }
            $owner = $page->getOwner();
            // We count only user pages
            if ($owner !== ADMIN_USER) {
                $num_pages++;
            }
        }
        //get session data from database
        $num_online = 0;
        $num_guests = 0;
        $num_registered = 0;
        $registered = array();
        $guests = array();
        $admins = array();
        $uniquenames = array();
        $sess_time = ini_get('session.gc_maxlifetime'); // in seconds
        if (!$sess_time) {
            $sess_time = 24 * 60;
        }
        if (isset($request->_dbsession)) { // only SQL backends
            $dbsession =& $request->_dbsession;
            if (method_exists($dbsession->_backend, "gc")) {
                $dbsession->_backend->gc($sess_time);
            }
            $sessions = $dbsession->currentSessions();
            //$num_online = count($sessions);
            $guestname = _("Guest");
            foreach ($sessions as $row) {
                $data = $row['wiki_user'];
                $date = $row['date'];
                //Todo: Security issue: Expose REMOTE_ADDR?
                //      Probably only to WikiAdmin
                $ip = $row['ip'];
                if (empty($date)) {
                    continue;
                }
                $num_online++;
                $user = @unserialize($data);
                if (!empty($user) and !is_a($user, "__PHP_incomplete_Class")) {
                    // if "__PHP_incomplete_Class" try to avoid notice
                    $userid = @$user->_userid;
                    $level = @$user->_level;
                    if ($mode == 'summary' and in_array($userid, $uniquenames)) {
                        continue;
                    }
                    $uniquenames[] = $userid;
                    $page = _("<unknown>"); // where is he?
                    $action = 'browse';
                    $objvars = array_keys(get_object_vars($user));
                    if (in_array('action', $objvars)) {
                        $action = @$user->action;
                    }
                    if (in_array('page', $objvars)) {
                        $page = @$user->page;
                    }
                    if ($level and $userid) { // registered or guest or what?
                        //FIXME: htmlentitities name may not be called here. but where then?
                        $num_registered++;
                        $registered[] = array('name' => $userid,
                            'date' => $date,
                            'action' => $action,
                            'page' => $page,
                            'level' => $level,
                            'ip' => $ip,
                            'x' => 'x');
                        if ($user->_level == WIKIAUTH_ADMIN) {
                            $admins[] = $registered[count($registered) - 1];
                        }
                    } else {
                        $num_guests++;
                        $guests[] = array('name' => $guestname,
                            'date' => $date,
                            'action' => $action,
                            'page' => $page,
                            'level' => $level,
                            'ip' => $ip,
                            'x' => 'x');
                    }
                } else {
                    $num_guests++;
                    $guests[] = array('name' => $guestname,
                        'date' => $date,
                        'action' => '',
                        'page' => '',
                        'level' => 0,
                        'ip' => $ip,
                        'x' => 'x');
                }
            }
        }
        $num_users = $num_guests + $num_registered;

        //TODO: get and sets max stats in global_data
        //$page = $dbi->getPage($request->getArg('pagename'));
        $stats = array();
        $stats['max_online_num'] = 0;
        if ($stats = $dbi->get('stats')) {
            if ($num_users > $stats['max_online_num']) {
                $stats['max_online_num'] = $num_users;
                $stats['max_online_time'] = time();
                $dbi->set('stats', $stats);
            }
        } else {
            $stats = array();
            $stats['max_online_num'] = $num_users;
            $stats['max_online_time'] = time();
            $dbi->set('stats', $stats);
        }
        return array('SESSDATA_BOOL' => !empty($dbsession),
            'NUM_PAGES' => $num_pages,
            'NUM_USERS' => $num_users,
            'NUM_ONLINE' => $num_online,
            'NUM_REGISTERED' => $num_registered,
            'NUM_GUESTS' => $num_guests,
            'NEWEST_USER' => '', // todo
            'MAX_ONLINE_NUM' => $stats['max_online_num'],
            'MAX_ONLINE_TIME' => $stats['max_online_time'],
            'REGISTERED' => $registered,
            'ADMINS' => $admins,
            'GUESTS' => $guests,
            'SESSION_TIME' => sprintf(_("%d minutes"), $sess_time / 60),
        );
    }
}
