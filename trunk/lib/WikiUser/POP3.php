<?php //-*-php-*-
rcs_id('$Id: POP3.php,v 1.1 2004-11-01 10:43:58 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
 */

class _POP3PassUser
extends _IMAPPassUser {
/**
 * Define the var POP3_AUTH_HOST in config/config.ini
 * Preferences are handled in _PassUser
 */
    function checkPass($submitted_password) {
        if (!$this->isValidName()) {
            return $this->_tryNextPass($submitted_password);
        }
        $userid = $this->_userid;
        $pass = $submitted_password;
        $host = defined('POP3_AUTH_HOST') ? POP3_AUTH_HOST : 'localhost:110';
        if (defined('POP3_AUTH_PORT'))
            $port = POP3_AUTH_PORT;
        elseif (strstr($host,':')) {
            list(,$port) = split(':',$host);
        } else {
            $port = 110;
        }
        $retval = false;
        $fp = fsockopen($host, $port, $errno, $errstr, 10);
        if ($fp) {
            // Get welcome string
            $line = fgets($fp, 1024);
            if (! strncmp("+OK ", $line, 4)) {
                // Send user name
                fputs($fp, "user $userid\n");
                // Get response
                $line = fgets($fp, 1024);
                if (! strncmp("+OK ", $line, 4)) {
                    // Send password
                    fputs($fp, "pass $pass\n");
                    // Get response
                    $line = fgets($fp, 1024);
                    if (! strncmp("+OK ", $line, 4)) {
                        $retval = true;
                    }
                }
            }
            // quit the connection
            fputs($fp, "quit\n");
            // Get the sayonara message
            $line = fgets($fp, 1024);
            fclose($fp);
        } else {
            trigger_error(_("Couldn't connect to %s","POP3_AUTH_HOST ".$host.':'.$port),
                          E_USER_WARNING);
        }
        $this->_authmethod = 'POP3';
        if ($retval) {
            $this->_level = WIKIAUTH_USER;
        } else {
            $this->_level = WIKIAUTH_ANON;
        }
        return $this->_level;
    }
}

// $Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>