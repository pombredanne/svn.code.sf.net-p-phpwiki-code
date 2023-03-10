<?php
/**
 * Copyright © 2001-2003 Jeff Dairiki
 * Copyright © 2001-2002 Carsten Klapp
 * Copyright © 2004-2007 Reini Urban
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
 *
 * Things which must be done and defined before anything else.
 */

define('PHPWIKI_VERSION', '1.6.2');

// A new php-5.1.x feature: Turn off php-5.1.x auto_globals_jit = On, or use this mess below.
if (empty($GLOBALS['HTTP_SERVER_VARS'])) {
    $GLOBALS['HTTP_SERVER_VARS'] =& $_SERVER;
    $GLOBALS['HTTP_ENV_VARS'] =& $_ENV;
    $GLOBALS['HTTP_GET_VARS'] =& $_GET;
    $GLOBALS['HTTP_POST_VARS'] =& $_POST;
    $GLOBALS['HTTP_SESSION_VARS'] =& $_SESSION;
    $GLOBALS['HTTP_COOKIE_VARS'] =& $_COOKIE;
    $GLOBALS['HTTP_REQUEST_VARS'] =& $_REQUEST;
}
unset($k);

// If your php was compiled with --enable-trans-sid it tries to
// add a PHPSESSID query argument to all URL strings when cookie
// support isn't detected in the client browser.  For reasons
// which aren't entirely clear (PHP bug) this screws up the URLs
// generated by PhpWiki.  Therefore, transparent session ids
// should be disabled.  This next line does that.
//
// (At the present time, you will not be able to log-in to PhpWiki,
// unless your browser supports cookies.)
@ini_set('session.use_trans_sid', 0);

if (defined('DEBUG') and (DEBUG & 8) and extension_loaded("xdebug")) {
    xdebug_start_trace("trace"); // on Dbgp protocol add 2
    xdebug_enable();
}

// Used for debugging purposes
class DebugTimer
{
    public function __construct()
    {
        $this->_start = $this->microtime();
        // Function 'posix_times' does not exist on Windows
        if (function_exists('posix_times')) {
            $this->_times = posix_times();
        }
    }

    /**
     * @param  string $which One of 'real', 'utime', 'stime', 'cutime', 'sutime'
     * @param array $now
     * @return float  Seconds.
     */
    public function getTime($which = 'real', $now = array())
    {
        if ($which == 'real') {
            return $this->microtime() - $this->_start;
        }

        if (isset($this->_times)) {
            if (empty($now)) {
                $now = posix_times();
            }
            $ticks = $now[$which] - $this->_times[$which];
            return $ticks / $this->_CLK_TCK();
        }

        return 0.0; // Not available.
    }

    public function getStats()
    {
        if (!isset($this->_times)) {
            // posix_times() not available.
            return sprintf("real: %.3f", $this->getTime());
        }
        $now = posix_times();
        return sprintf(
            "real: %.3f, user: %.3f, sys: %.3f",
            $this->getTime('real'),
            $this->getTime('utime', $now),
            $this->getTime('stime', $now)
        );
    }

    public function _CLK_TCK()
    {
        // FIXME: this is clearly not always right.
        // But how to figure out the right value?
        return 100.0;
    }

    public function microtime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}

$RUNTIMER = new DebugTimer();
require_once(dirname(__FILE__) . '/ErrorManager.php');
require_once(dirname(__FILE__) . '/WikiCallback.php');

// FIXME: deprecated
function ExitWiki($errormsg = false)
{
    global $request;
    static $in_exit = 0;

    if (is_object($request) and method_exists($request, "finish")) {
        $request->finish($errormsg);
    } // NORETURN

    if ($in_exit) {
        exit;
    }

    $in_exit = true;

    global $ErrorManager;
    $ErrorManager->flushPostponedErrors();

    if (!empty($errormsg)) {
        PrintXML(HTML::br(), $errormsg);
        print "\n</body></html>";
    }
    exit;
}

if (!defined('DEBUG') or (defined('DEBUG') and DEBUG > 2)) {
    $ErrorManager->setPostponedErrorMask(E_ALL); // ignore all errors
    $ErrorManager->setFatalHandler(new WikiFunctionCb('ExitWiki'));
} else {
    $ErrorManager->setPostponedErrorMask(E_USER_NOTICE | E_NOTICE);
}
