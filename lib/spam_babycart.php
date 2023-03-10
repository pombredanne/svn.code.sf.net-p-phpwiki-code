<?php
/**
 * Copyright © 2004 Reini Urban
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
 * Author: Bob Apthorpe <apthorpe+babycart@cynistar.net>
 * Proof-of-concept PHP fragment to flag blog/wiki spam
 *
 * URL: <http://www.cynistar.net/~apthorpe/code/babycart/babycart.html>
 * INSTALL:
 *   cpan Blog::SpamAssassin
 *   copy contrib/babycart to /usr/local/bin/
 */

function check_babycart(&$text, $ip)
{
    /**
      * @var WikiRequest $request
      */
    global $request;

    // $X_babycart = '/usr/bin/perl /home/apthorpe/pjx/babycart/babycart';
    // cygwin:
    if (!defined('BABYCART_PATH')) {
        define('BABYCART_PATH', '/usr/local/bin/babycart');
    }
    // cygwin:
    //$X_babycart = 'n:/bin/perl /usr/local/bin/babycart';

    $comment = "IP: $ip\n";
    $subject = $request->getArg('pagename');
    $comment .= "SUBJECT: $subject\n";
    $comment .= "END_COMMENT_METADATA\n";
    $comment .= $text;

    $descriptorspec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
    $process = proc_open(BABYCART_PATH, $descriptorspec, $pipes);
    $error = '';
    if (is_resource($process)) {
        // $pipes now looks like this:
        // 0 => writeable handle connected to child stdin
        // 1 => readable handle connected to child stdout
        // Any error output will be appended to $pipes[2]

        // Send comment out for analysis
        fwrite($pipes[0], $comment);
        fclose($pipes[0]);

        // Get response from stdout (should be one line)
        $response = '';
        while (!feof($pipes[1])) {
            $response .= fgets($pipes[1], 1024);
        }
        fclose($pipes[1]);

        // Get error from stderr (should be empty)
        $error = '';
        while (!feof($pipes[2])) {
            $error .= fgets($pipes[2], 1024);
        }
        fclose($pipes[2]);

        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        proc_close($process);

        // Interpret results and yield judgment

        // print "Response: $response\n";
        // split into status, note, score, rules...
        if ($response) {
            if (substr($response, 0, 2) == 'OK') {
                return false;
            }
            /*
             response fields are:
             0 - verdict (OK or SUSPICIOUS)
             1 - note (additional info on verdict, whether tests ran, etc.)
             2 - numeric score; anything greater than 5-7 is suspect
             3 - comma-delimited list of rules hit (may be empty)
            */
            return explode(',', $response, 4);
        }
    }
    trigger_error("Couldn't process " . BABYCART_PATH . ".\n" . $error, E_USER_WARNING);
    return -1; // process error
}
