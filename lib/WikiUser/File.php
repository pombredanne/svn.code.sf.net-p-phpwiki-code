<?php //-*-php-*-
rcs_id('$Id: File.php,v 1.2 2004-12-19 00:58:02 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
 */

class _FilePassUser
extends _PassUser
/**
 * Check users defined in a .htaccess style file
 * username:crypt\n...
 *
 * Preferences are handled in _PassUser
 */
{
    var $_file, $_may_change;

    // This can only be called from _PassUser, because the parent class 
    // sets the pref methods, before this class is initialized.
    function _FilePassUser($UserName='', $prefs=false, $file='') {
        if (!$this->_prefs and isa($this, "_FilePassUser")) {
            if ($prefs) $this->_prefs = $prefs;
            if (!isset($this->_prefs->_method))
              _PassUser::_PassUser($UserName);
        }
        $this->_userid = $UserName;
        // read the .htaccess style file. We use our own copy of the standard pear class.
        //include_once 'lib/pear/File_Passwd.php';
        $this->_may_change = defined('AUTH_USER_FILE_STORABLE') && AUTH_USER_FILE_STORABLE;
        if (empty($file) and defined('AUTH_USER_FILE'))
            $file = AUTH_USER_FILE;
        include_once(dirname(__FILE__)."/pear/File_Passwd.php"); // same style as in main.php
        // "__PHP_Incomplete_Class"
        if (!empty($file) or empty($this->_file) or !isa($this->_file,"File_Passwd"))
            $this->_file = new File_Passwd($file, false, $file.'.lock');
        else
            return false;
        return $this;
    }
 
    function mayChangePass() {
        return $this->_may_change;
    }

    function userExists() {
        if (!$this->isValidName()) {
            return $this->_tryNextUser();
        }
        $this->_authmethod = 'File';
        if (isset($this->_file->users[$this->_userid]))
            return true;
            
        return $this->_tryNextUser();
    }

    function checkPass($submitted_password) {
        if (!$this->isValidName()) {
            trigger_error(_("Invalid username"),E_USER_WARNING);
            return $this->_tryNextPass($submitted_password);
        }
        if (!$this->_checkPassLength($submitted_password)) {
            return WIKIAUTH_FORBIDDEN;
        }
        //include_once 'lib/pear/File_Passwd.php';
        if ($this->_file->verifyPassword($this->_userid, $submitted_password)) {
            $this->_authmethod = 'File';
            $this->_level = WIKIAUTH_USER;
            if ($this->isAdmin()) // member of the Administrators group
                $this->_level = WIKIAUTH_ADMIN;
            return $this->_level;
        }
        
        return $this->_tryNextPass($submitted_password);
    }

    function storePass($submitted_password) {
        if (!$this->isValidName()) {
            return false;
        }
        if ($this->_may_change) {
            $this->_file = new File_Passwd($this->_file->_filename, true, 
                                           $this->_file->_filename.'.lock');
            $result = $this->_file->modUser($this->_userid,$submitted_password);
            $this->_file->close();
            $this->_file = new File_Passwd($this->_file->_filename, false);
            return $result;
        }
        return false;
    }

}

// $Log: not supported by cvs2svn $
// Revision 1.1  2004/11/01 10:43:58  rurban
// seperate PassUser methods into seperate dir (memory usage)
// fix WikiUser (old) overlarge data session
// remove wikidb arg from various page class methods, use global ->_dbi instead
// ...
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
