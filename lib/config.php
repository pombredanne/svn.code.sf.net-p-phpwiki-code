<?php
/**
 * Copyright © 2000-2001 Arno Hollosi
 * Copyright © 2000-2001 Steve Wainstead
 * Copyright © 2001-2003 Jeff Dairiki
 * Copyright © 2002-2002 Carsten Klapp
 * Copyright © 2002-2002 Lawrence Akka
 * Copyright © 2002,2004-2009 Reini Urban
 * Copyright © 2008-2014 Marc-Etienne Vargenau, Alcatel-Lucent
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

/*
 * NOTE: The settings here should probably not need to be changed.
 * The user-configurable settings have been moved to IniConfig.php
 */

if (!defined("LC_ALL")) {
    define("LC_ALL", 0);
    define("LC_CTYPE", 2);
}
// debug flags:
define ('_DEBUG_VERBOSE', 1); // verbose msgs and add validator links on footer
define ('_DEBUG_PAGELINKS', 2); // list the extraced pagelinks at the top of each pages
define ('_DEBUG_PARSER', 4); // verbose parsing steps
define ('_DEBUG_TRACE', 8); // test php memory usage, prints php debug backtraces
define ('_DEBUG_INFO', 16);
define ('_DEBUG_APD', 32); // APD tracing/profiling
define ('_DEBUG_LOGIN', 64); // verbose login debug-msg (settings and reason for failure)
define ('_DEBUG_SQL', 128); // force check db, force optimize, print some debugging logs
define ('_DEBUG_REMOTE', 256); // remote debug into subrequests (xmlrpc, ajax, wikiwyg, ...)
// or test local SearchHighlight.
// internal links have persistent ?start_debug=1

function isCGI()
{
    return (substr(php_sapi_name(), 0, 3) == 'cgi' and
        isset($GLOBALS['HTTP_ENV_VARS']['GATEWAY_INTERFACE']) and
            @preg_match('/CGI/', $GLOBALS['HTTP_ENV_VARS']['GATEWAY_INTERFACE']));
}

function update_locale($loc)
{
    if ($loc == 'C' or $loc == 'en') {
        return;
    }

    switch ($loc) {
        case "de":
            $loc = "de_DE";
            break;
        case "es":
            $loc = "es_ES";
            break;
        case "fr":
            $loc = "fr_FR";
            break;
        case "it":
            $loc = "it_IT";
            break;
        case "ja":
            $loc = "ja_JP";
            break;
        case "nl":
            $loc = "nl_NL";
            break;
        case "sv":
            $loc = "sv_SE";
            break;
        case "zh":
            $loc = "zh_CN";
            break;
    }
    // First try with UTF-8 locale, both syntaxes
    $res = setlocale(LC_ALL, $loc.".utf8");
    if ($res !== false) {
        return;
    }
    $res = setlocale(LC_ALL, $loc.".UTF-8");
    if ($res !== false) {
        return;
    }
    // If it fails, try with no encoding
    setlocale(LC_ALL, $loc);
}

function deduce_script_name()
{
    $s = &$_SERVER;
    $script = @$s['SCRIPT_NAME'];
    if (empty($script) or $script[0] != '/') {
        // Some places (e.g. Lycos) only supply a relative name in
        // SCRIPT_NAME, but give what we really want in SCRIPT_URL.
        if (!empty($s['SCRIPT_URL']))
            $script = $s['SCRIPT_URL'];
    }
    return $script;
}

function IsProbablyRedirectToIndex()
{
    // This might be a redirect to the DirectoryIndex,
    // e.g. REQUEST_URI = /dir/?some_action got redirected
    // to SCRIPT_NAME = /dir/index.php

    // In this case, the proper virtual path is still
    // $SCRIPT_NAME, since pages appear at
    // e.g. /dir/index.php/HomePage.

    $requri = preg_replace('/\?.*$/', '', $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']);
    $requri = preg_quote($requri, '%');
    return preg_match("%^${requri}[^/]*$%", $GLOBALS['HTTP_SERVER_VARS']['SCRIPT_NAME']);
}

function getUploadFilePath()
{

    if (defined('UPLOAD_FILE_PATH')) {
        if (string_ends_with(UPLOAD_FILE_PATH, "/")
            or string_ends_with(UPLOAD_FILE_PATH, "\\")
        ) {
            return UPLOAD_FILE_PATH;
        } else {
            return UPLOAD_FILE_PATH . "/";
        }
    }
    return defined('PHPWIKI_DIR')
        ? PHPWIKI_DIR . "/uploads/"
        : realpath(dirname(__FILE__) . "/../uploads/")."/";
}

function getUploadDataPath()
{
    if (defined('UPLOAD_DATA_PATH')) {
        return string_ends_with(UPLOAD_DATA_PATH, "/")
            ? UPLOAD_DATA_PATH : UPLOAD_DATA_PATH . "/";
    }
    if (defined('DATA_PATH') && (DATA_PATH != '')) {
        return SERVER_URL . (string_ends_with(DATA_PATH, "/") ? '' : "/") . DATA_PATH . '/uploads/';
    } else {
        return SERVER_URL . '/uploads/';
    }
}
