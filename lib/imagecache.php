<?php
/**
 * Copyright © 2002 Johannes Große
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
 * Gets an image from the cache and prints it to the browser.
 * This file belongs to WikiPluginCached.
 * @author  Johannes Große
 * @version 0.8
 */

include_once 'lib/config.php';
require_once 'lib/stdlib.php';
require_once 'lib/Request.php';
require_once 'lib/WikiUser.php';
require_once 'lib/WikiDB.php';
require_once 'lib/WikiPluginCached.php';

// -----------------------------------------------------------------------

function deducePagename($request)
{
    if ($request->getArg('pagename')) {
        return $request->getArg('pagename');
    }

    if (USE_PATH_INFO) {
        $pathinfo = $request->get('PATH_INFO');
        $tail = substr($pathinfo, strlen(PATH_INFO_PREFIX));
        if ($tail != '' and $pathinfo == PATH_INFO_PREFIX . $tail) {
            return $tail;
        }
    } elseif ($this->isPost()) {
        global $HTTP_GET_VARS;
        if (isset($HTTP_GET_VARS['pagename'])) {
            return $HTTP_GET_VARS['pagename'];
        }
    }

    $query_string = $request->get('QUERY_STRING');
    if (preg_match('/^[^&=]+$/', $query_string)) {
        return urldecode($query_string);
    }

    return HOME_PAGE;
}

function deduceUsername()
{
    global $request, $HTTP_ENV_VARS;
    if (!empty($request->args['auth']) and !empty($request->args['auth']['userid'])) {
        return $request->args['auth']['userid'];
    }
    if (!empty($_SERVER['PHP_AUTH_USER'])) {
        return $_SERVER['PHP_AUTH_USER'];
    }
    if (!empty($HTTP_ENV_VARS['REMOTE_USER'])) {
        return $HTTP_ENV_VARS['REMOTE_USER'];
    }

    if ($user = $request->getSessionVar('wiki_user')) {
        $request->_user = $user;
        $request->_user->_authhow = 'session';
        return $user->UserName();
    }
    if ($userid = $request->getCookieVar(getCookieName())) {
        if (!empty($userid) and substr($userid, 0, 2) != 's:') {
            $request->_user->_authhow = 'cookie';
            return $userid;
        }
    }
    return false;
}

/**
 * Initializes PhpWiki and calls the plugin specified in the url to
 * produce an image. Furthermore, allow the usage of Apache's
 * ErrorDocument mechanism in order to make this file only called when
 * image could not be found in the cache.
 * (see doc/README.phpwiki-cache for further information).
 */
function mainImageCache()
{
    $request = new Request();
    // normalize pagename
    $request->setArg('pagename', deducePagename($request));
    $pagename = $request->getArg('pagename');
    $request->_dbi = WikiDB::open($GLOBALS['DBParams']);
    $request->_user = new _AnonUser();
    $request->_prefs =& $request->_user->_prefs;

    // Enable the output of most of the warning messages.
    // The warnings will screw up zip files and setpref though.
    // They will also screw up my images... But I think
    // we should keep them.
    global $ErrorManager;
    $ErrorManager->setPostponedErrorMask(E_NOTICE | E_USER_NOTICE);

    $id = $request->getArg('id');
    $args = $request->getArg('args');
    $request->setArg('action', 'imagecache');
    $cache = new WikiPluginCached();

    if ($id) {
        // this indicates a direct call (script wasn't called as
        // 404 ErrorDocument)
    } else {
        // deduce image id or image args (plugincall) from
        // refering URL

        $uri = $request->get('REDIRECT_URL');
        $query = $request->get('REDIRECT_QUERY_STRING');
        $uri .= $query ? '?' . $query : '';

        if (!$uri) {
            $uri = $request->get('REQUEST_URI');
        }
        if (!$uri) {
            $cache->printError(
                'png',
                'Could not deduce image identifier or creation'
                    . ' parameters. (Neither REQUEST nor REDIRECT'
                    . ' obtained.)'
            );
            return;
        }
        if (!preg_match(':^(.*/)?' . PLUGIN_CACHED_FILENAME_PREFIX . '([^\?/]+)\.img(\?args=([^\?&]*))?$:', $uri, $matches)) {
            $cache->printError('png', "I do not understand this URL: $uri");
            return;
        }

        $request->setArg('id', $matches[2]);
        if ($matches[4]) {
            // md5 args?
            $request->setArg('args', rawurldecode($matches[4]));
        }
        $request->setStatus(200); // No, we do _not_ have an Error 404 :->
    }

    $cache->fetchImageFromCache($request->_dbi, $request, 'png');
}

mainImageCache();
