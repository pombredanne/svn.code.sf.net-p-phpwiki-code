<?php
rcs_id('$Id: WikiGroup.php,v 1.10 2004-02-03 09:45:39 rurban Exp $');
/*
 Copyright 2003, 2004 $ThePhpWikiProgrammingTeam

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

// for now we provide no default memberhsip method. this might change.
// (!defined('GROUP_METHOD')) define('GROUP_METHOD', "WIKIPAGE");

if (!defined('GROUP_METHOD') or !in_array(GROUP_METHOD,array('NONE','WIKIPAGE','DB','FILE','LDAP')))
    trigger_error(_("No or unsupported GROUP_METHOD defined"), E_USER_WARNING);

/**
 * WikiGroup is an abstract class to provide the base functions for determining
 * group membership.
 * 
 * WikiGroup is an abstract class with three functions:
 * <ol><li />Provide the static method getGroup with will return the proper
 *         subclass.
 *     <li />Provide an interface for subclasses to implement.
 *     <li />Provide fallover methods (with error msgs) if not impemented in subclass.
 * </ol>
 * Do not ever instantiate this class use: $group = &WikiGroup::getGroup($request);
 * This will instantiate the proper subclass.
 * @author Joby Walker <zorloc@imperium.org>
 */ 
class WikiGroup{
    /** User name */
    var $username;
    /** The global WikiRequest object */
    var $request;
    /** Array of groups $username is confirmed to belong to */
    var $membership;
    
    /**
     * Initializes a WikiGroup object which should never happen.  Use:
     * $group = &WikiGroup::getGroup($request);
     * @param object $request The global WikiRequest object -- ignored.
     */ 
    function WikiGroup(&$request){    
        return;
    }

    /**
     * Gets the current username and erases $this->membership if is different than
     * the stored $this->username
     * @return string Current username.
     */ 
    function _getUserName(){
        $request = &$this->request;
        $user = $request->getUser();
        $username = $user->getID();
        if ($username != $this->username) {
            $this->membership = array();
            $this->username = $username;
        }
        return $username;
    }
    
    /**
     * Static method to return the WikiGroup subclass used in this wiki.  Controlled
     * by the constant GROUP_METHOD.
     * @param object $request The global WikiRequest object.
     * @return object Subclass of WikiGroup selected via GROUP_METHOD.
     */ 
    function getGroup($request){
        switch (GROUP_METHOD){
            case "NONE": 
                return new GroupNone($request);
                break;
            case "WIKIPAGE":
                return new GroupWikiPage($request);
                break;
            case "DB":
                return new GroupDB($request);
                break;
            case "FILE": 
                return new GroupFile($request);
                break;
            /*
            case "LDAP": 
                return new GroupLDAP($request);
                break;
            */
            default:
                trigger_error(_("No or unsupported GROUP_METHOD defined"), E_USER_WARNING);
                return new WikiGroup($request);
        }
    }

    /**
     * Determines if the current user is a member of a group.
     * 
     * This method is an abstraction.  The group is ignored, an error is sent, and
     * false (not a member of the group) is returned.
     * @param string $group Name of the group to check for membership (ignored).
     * @return boolean True if user is a member, else false (always false).
     */ 
    function isMember($group){
        trigger_error("Method 'isMember' not implemented in this GROUP_METHOD", 
                      E_USER_WARNING);
        return false;
    }
    
    /**
     * Determines all of the groups of which the current user is a member.
     * 
     * This method is an abstraction.  An error is sent and an empty 
     * array is returned.
     * @return array Array of groups to which the user belongs (always empty).
     */ 
    function getAllGroupsIn(){
        trigger_error("Method 'getAllGroupsIn' not implemented in this GROUP_METHOD",
                      E_USER_WARNING);
        return array();
    }

    /**
     * Determines all of the members of a particular group.
     * 
     * This method is an abstraction.  The group is ignored, an error is sent, 
     * and an empty array is returned
     * @param string $group Name of the group to get the full membership list of.
     * @return array Array of usernames that have joined the group (always empty).
     */ 
    function getMembersOf($group){
        trigger_error("Method 'getMembersof' not implemented in this GROUP_METHOD", 
                      E_USER_WARNING);
        return array();
    }

    /**
     * Add the current or specified user to a group.
     * 
     * This method is an abstraction.  The group and user are ignored, an error 
     * is sent, and false (not added) is always returned.
     * @param string $group User added to this group.
     * @param string $user Username to add to the group (default = current user).
     * @return bool On true user was added, false if not.
     */ 
    function setMemberOf($group, $user = false){
        trigger_error("Method 'setMemberOf' not implemented in this GROUP_METHOD", 
                      E_USER_WARNING);
        return false;
    }
    
    /**
     * Remove the current or specified user to a group.
     * 
     * This method is an abstraction.  The group and user are ignored, and error
     * is sent, and false (not removed) is always returned.
     * @param string $group User removed from this group.
     * @param string $user Username to remove from the group (default = current user).
     * @return bool On true user was removed, false if not.
     */ 
    function removeMemberOf($group, $user = false){
        trigger_error("Method 'removeMemberOf' not implemented in this GROUP_METHOD", 
                      E_USER_WARNING);
        return false;
    }
}

/**
 * GroupNone disables all Group funtionality
 * 
 * All of the GroupNone functions return false or empty values to indicate failure or 
 * no results.  Use GroupNone if group controls are not desired.
 * @author Joby Walker <zorloc@imperium.org>
 */ 
class GroupNone extends WikiGroup{

    /**
     * Constructor
     * 
     * Ignores the parameter provided.
     * @param object $request The global WikiRequest object - ignored.
     */ 
    function GroupNone(&$request){
        return;
    }    

    /**
     * Determines if the current user is a member of a group.
     * 
     * The group is ignored and false (not a member of the group) is returned.
     * @param string $group Name of the group to check for membership (ignored).
     * @return boolean True if user is a member, else false (always false).
     */ 
    function isMember($group){
        return false;
    }
    
    /**
     * Determines all of the groups of which the current user is a member.
     * 
     * The group is ignored and an empty array (a member of no groups) is returned.
     * @param string $group Name of the group to check for membership (ignored).
     * @return array Array of groups to which the user belongs (always empty).
     */ 
    function getAllGroupsIn(){
        return array();
    }

    /**
     * Determines all of the members of a particular group.
     * 
     * The group is ignored and an empty array (a member of no groups) is returned.
     * @param string $group Name of the group to check for membership (ignored).
     * @return array Array of groups user belongs to (always empty).
     */ 
    function getMembersOf($group){
        return array();
    }

}

/**
 * GroupWikiPage provides group functionality via pages within the Wiki.
 * 
 * GroupWikiPage is the Wiki way of managing a group.  Every group will have 
 * a page. To modify the membership of the group, one only needs to edit the 
 * membership list on the page.
 * @author Joby Walker <zorloc@imperium.org>
 */ 
class GroupWikiPage extends WikiGroup{
    
    /**
     * Constructor
     * 
     * Initiallizes the three superclass instance variables
     * @param object $request The global WikiRequest object.
     */ 
    function GroupWikiPage(&$request){
        $this->request = &$request;
        $this->username = null;
        $this->membership = array();
    }

    /**
     * Determines if the current user is a member of a group.
     * 
     * To determine membership in a particular group, this method checks the 
     * superclass instance variable $membership to see if membership has 
     * already been determined.  If not, then the group page is parsed to 
     * determine membership.
     * @param string $group Name of the group to check for membership.
     * @return boolean True if user is a member, else false.
     */ 
    function isMember($group){
        $request = $this->request;
        $username = $this->_getUserName();
        if (isset($this->membership[$group])) {
            return $this->membership[$group];
        }
        $group_page = $request->getPage($group);
        if ($this->_inGroupPage($group_page)) {
            $this->membership[$group] = true;
            return true;
        }
        $this->membership[$group] = false;
        return false;
    }
    
    /**
    * Private method to take a WikiDB_Page and parse to determine if the
    * current_user is a member of the group.
    * @param object $group_page WikiDB_Page object for the group's page
    * @return boolean True if user is a member, else false.
    * @access private
    */
    function _inGroupPage($group_page){
        $group_revision = $group_page->getCurrentRevision();
        if ($group_revision->hasDefaultContents()) {
            $group = $group_page->getName();
            trigger_error(sprintf(_("Group %s does not exist"),$group), E_USER_WARNING);
            return false;
        }
        $contents = $group_revision->getContent();
        $match = '/^\s*[\*\#]+\s*' . $username . '\s*$/';
        foreach($contents as $line){
            if (preg_match($match, $line)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Determines all of the groups of which the current user is a member.
     * 
     * Checks the root Group page ('CategoryGroup') for the list of all groups, 
     * then checks each group to see if the current user is a member.
     * @param string $group Name of the group to check for membership.
     * @return array Array of groups to which the user belongs.
     */ 
    function getAllGroupsIn(){
        $request = &$this->request;
        $username = $this->_getUserName();
        $membership = array();
        $dbh = &$request->getDbh();
        $master_page = $request->getPage('CategoryGroup');
        $master_list = $master_page->getLinks(true);
        while($group_page = $master_list->next()){
            if ($this->_inGroupPage($group_page)) {
                $group = $group_page->getName();
                $membership[$group] = true;
            } else {
                $membership[$group] = false;
            }
        }
        $this->membership = $membership;
        return $membership;
    }

    /**
     * Determines all of the members of a particular group.
     * 
     * Checks a group's page to return all the current members.  Currently this
     * method is disabled and triggers an error and returns an empty array.
     * @param string $group Name of the group to get the full membership list of.
     * @return array Array of usernames that have joined the group (always empty).
     */ 
    function getMembersOf($group){
        trigger_error("GroupWikiPage::getMembersof is not yet implimented",
                      E_USER_WARNING);
        return array();
        /*
        * Waiting for a reliable way to check if a string is a username.
        $request = $this->request;
        $user = $this->user;
        $group_page = $request->getPage($group);
        $group_revision = $group_page->getCurrentRevision();
        if ($group_revision->hasDefaultContents()) {
            trigger_error("Group $group does not exist", E_USER_WARNING);
            return false;
        }
        $contents = $group_revision->getContent();
        $match = '/^(\s*[\*\#]+\s*)(\w+)(\s*)$/';
        $members = array();
        foreach($contents as $line){
            $matches = array();
            if(preg_match($match, $line, $matches)){
                $members[] = $matches[2];
            }
        }
        return $members;
        */
    }
}

/**
 * GroupDb is configured by $DbAuthParams[] statements
 * 
 * @author ReiniUrban
 */ 
class GroupDb extends WikiGroup {
    
    /**
     * Constructor
     * 
     * @param object $request The global WikiRequest object.
     */ 
    function GroupDb(&$request){
        $this->request = &$request;
        $this->username = null;
        $this->membership = array();

        if (empty($DBAuthParams['group_members']) or 
            empty($DBAuthParams['user_groups']) or
            empty($DBAuthParams['is_member'])) {
            trigger_error(_("No GROUP_DB SQL statements defined"), E_USER_WARNING);
            return false;
        }
        $dbh = _PassUser::getAuthDbh();
        $this->_is_member = $dbh->prepare(preg_replace(array('"$userid"','"$groupname"'),array('?','?'),$DBAuthParams['is_member']));
        $this->_group_members = $dbh->prepare(preg_replace('"$groupname"','?',$DBAuthParams['group_members']));
        $this->_user_groups = $dbh->prepare(preg_replace('"$userid"','?',$DBAuthParams['user_groups']));
    }

    /**
     * Determines if the current user is a member of a group.
     * 
     * To determine membership in a particular group, this method checks the 
     * superclass instance variable $membership to see if membership has 
     * already been determined.  If not, then the group page is parsed to 
     * determine membership.
     * @param string $group Name of the group to check for membership.
     * @return boolean True if user is a member, else false.
     */ 
    function isMember($group) {
        $request = $this->request;
        $username = $this->_getUserName();
        if (isset($this->membership[$group])) {
            return $this->membership[$group];
        }
        $dbh = _PassUser::getAuthDbh();
        $db_result = $dbh->execute($this->_is_member,$username,$group);
        if ($db_result->numRows() > 0) {
            $this->membership[$group] = true;
            return true;
        }
        $this->membership[$group] = false;
        return false;
    }
    
    /**
     * Determines all of the groups of which the current user is a member.
     * 
     * then checks each group to see if the current user is a member.
     * @param string $group Name of the group to check for membership.
     * @return array Array of groups to which the user belongs.
     */ 
    function getAllGroupsIn(){
        $request = &$this->request;
        $username = $this->_getUserName();
        $membership = array();

        $dbh = _PassUser::getAuthDbh();
        $db_result = $dbh->execute($this->_user_groups,$username);
        if ($db_result->numRows() > 0) {
            while (list($group) = $db_result->fetchRow()) {
                $membership[] = $group;
            }
        }
        $this->membership = $membership;
        return $membership;
    }

    /**
     * Determines all of the members of a particular group.
     * 
     * Checks a group's page to return all the current members.  Currently this
     * method is disabled and triggers an error and returns an empty array.
     * @param string $group Name of the group to get the full membership list of.
     * @return array Array of usernames that have joined the group.
     */ 
    function getMembersOf($group){
        $request = &$this->request;
        $username = $this->_getUserName();
        $members = array();

        $dbh = _PassUser::getAuthDbh();
        $db_result = $dbh->execute($this->_group_members,$group);
        if ($db_result->numRows() > 0) {
            while (list($userid) = $db_result->fetchRow()) {
                $members[] = $userid;
            }
        }
        return $members;
    }
}

/**
 * GroupFile is configured by AUTH_GROUP_FILE
 * groupname: user1 user2 ...
 * 
 * @author ReiniUrban
 */ 
class GroupFile extends WikiGroup {
    
    /**
     * Constructor
     * 
     * @param object $request The global WikiRequest object.
     */ 
    function GroupFile(&$request){
        $this->request = &$request;
        $this->username = null;
        $this->membership = array();

        if (!defined('AUTH_GROUP_FILE')) {
            trigger_error(_("AUTH_GROUP_FILE not defined"), E_USER_WARNING);
            return false;
        }
        if (!file_exists(AUTH_GROUP_FILE)) {
            trigger_error(sprintf(_("Cannot open AUTH_GROUP_FILE %s"), AUTH_GROUP_FILE), E_USER_WARNING);
            return false;
        }
        require 'lib/pear/File_Passwd.php';
        $this->_file = File_Passwd($file);
    }

    /**
     * Determines if the current user is a member of a group.
     * 
     * To determine membership in a particular group, this method checks the 
     * superclass instance variable $membership to see if membership has 
     * already been determined.  If not, then the group file is parsed to 
     * determine membership.
     * @param string $group Name of the group to check for membership.
     * @return boolean True if user is a member, else false.
     */ 
    function isMember($group) {
        $request = $this->request;
        $username = $this->_getUserName();
        if (isset($this->membership[$group])) {
            return $this->membership[$group];
        }

        foreach ($this->_file->users[] as $g => $u) {
            $users = explode(' ',$u);
            if (in_array($username,$users)) {
                $this->membership[$group] = true;
                return true;
            }
        }
        $this->membership[$group] = false;
        return false;
    }
    
    /**
     * Determines all of the groups of which the current user is a member.
     * 
     * then checks each group to see if the current user is a member.
     * @param string $group Name of the group to check for membership.
     * @return array Array of groups to which the user belongs.
     */ 
    function getAllGroupsIn(){
        $request = &$this->request;
        $username = $this->_getUserName();
        $membership = array();

        foreach ($this->_file->users[] as $group => $u) {
            $users = explode(' ',$u);
            if (in_array($username,$users)) {
                $this->membership[$group] = true;
                $membership[] = $group;
            }
        }
        $this->membership = $membership;
        return $membership;
    }

    /**
     * Determines all of the members of a particular group.
     * 
     * Checks a group's page to return all the current members.  Currently this
     * method is disabled and triggers an error and returns an empty array.
     * @param string $group Name of the group to get the full membership list of.
     * @return array Array of usernames that have joined the group.
     */ 
    function getMembersOf($group){
        $request = &$this->request;
        $username = $this->_getUserName();
        $members = array();

        if (!empty($this->_file->users[$group])) {
            return explode(' ',$this->_file->users[$group]);
        }
        return $members;
    }
}

/**
 * Ldap is configured in index.php
 * 
 * @author ReiniUrban
 */ 
class GroupLdap extends WikiGroup {
    
    /**
     * Constructor
     * 
     * @param object $request The global WikiRequest object.
     */ 
    function GroupLdap(&$request){
        $this->request = &$request;
        $this->username = null;
        $this->membership = array();

        if (!defined("LDAP_AUTH_HOST")) {
            trigger_error(_("LDAP_AUTH_HOST not defined"), E_USER_WARNING);
            return false;
        }
        if (! function_exists('ldap_open')) {
            dl("ldap".DLL_EXT);
            if (! function_exists('ldap_open')) {
                trigger_error(_("No LDAP in this PHP version"), E_USER_WARNING);
                return false;
            }
        }
    }

    /**
     * Determines if the current user is a member of a group.
     * Not ready yet!
     * 
     * @param string $group Name of the group to check for membership.
     * @return boolean True if user is a member, else false.
     */ 
    function isMember($group) {
        if (isset($this->membership[$group])) {
            return $this->membership[$group];
        }
        $request = $this->request;
        $username = $this->_getUserName();
        if ($ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
            $r = @ldap_bind($ldap); 		    // this is an anonymous bind
            $st_search = "uid=$username member=$group";
            $sr = ldap_search($ldap, LDAP_BASE_DN,
                              "$st_search");
            $info = ldap_get_entries($ldap, $sr);
            if ($info["count"] > 0) {
                ldap_close($ldap);
                $this->membership[$group] = true;
                return true;
            }
        }
        $this->membership[$group] = false;
        return false;
    }
    
    /**
     * Determines all of the groups of which the current user is a member.
     * Not ready yet!
     *
     * @param string $group Name of the group to check for membership.
     * @return array Array of groups to which the user belongs.
     */ 
    function getAllGroupsIn(){
        $request = &$this->request;
        $username = $this->_getUserName();
        $membership = array();

        if ($ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
            $r = @ldap_bind($ldap); 		    // this is an anonymous bind
            $st_search = "uid=$username";
            $sr = ldap_search($ldap, LDAP_BASE_DN,
                              "$st_search");
            $info = ldap_get_entries($ldap, $sr); // there may be more hits with this userid. try every
            for ($i = 0; $i < $info["count"]; $i++) {
                $dn = $info[$i]["member"];
                if ($r = @ldap_bind($ldap, $dn, $group)) {
                    $membership[] = $group;
                }
            }
        }
        ldap_close($ldap);
        $this->membership = $membership;
        return $membership;
    }

    /**
     * Determines all of the members of a particular group.
     * 
     * Checks a group's page to return all the current members.  Currently this
     * method is disabled and triggers an error and returns an empty array.
     * @param string $group Name of the group to get the full membership list of.
     * @return array Array of usernames that have joined the group.
     */ 
    function getMembersOf($group){
        $request = &$this->request;
        $username = $this->_getUserName();
        $members = array();
        /*
        $dbh = _PassUser::getAuthDbh();
        $db_result = $dbh->execute($this->_group_members,$group);
        if ($db_result->numRows() > 0) {
            while (list($userid) = $db_result->fetchRow()) {
                $members[] = $userid;
            }
        }
        */
        return $members;
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.9  2004/02/01 09:14:11  rurban
// Started with Group_Ldap (not yet ready)
// added new _AuthInfo plugin to help in auth problems (warning: may display passwords)
// fixed some configurator vars
// renamed LDAP_AUTH_SEARCH to LDAP_BASE_DN
// changed PHPWIKI_VERSION from 1.3.8a to 1.3.8pre
// USE_DB_SESSION defaults to true on SQL
// changed GROUP_METHOD definition to string, not constants
// changed sample user DBAuthParams from UPDATE to REPLACE to be able to
//   create users. (Not to be used with external databases generally, but
//   with the default internal user table)
//
// fixed the IndexAsConfigProblem logic. this was flawed:
//   scripts which are the same virtual path defined their own lib/main call
//   (hmm, have to test this better, phpwiki.sf.net/demo works again)
//
// Revision 1.8  2004/01/27 23:23:39  rurban
// renamed ->Username => _userid for consistency
// renamed mayCheckPassword => mayCheckPass
// fixed recursion problem in WikiUserNew
// fixed bogo login (but not quite 100% ready yet, password storage)
//
// Revision 1.7  2004/01/26 16:52:40  rurban
// added GroupDB and GroupFile classes
//
// Revision 1.6  2003/12/07 19:29:11  carstenklapp
// Code Housecleaning: fixed syntax errors. (php -l *.php)
//
// Revision 1.5  2003/02/22 20:49:55  dairiki
// Fixes for "Call-time pass by reference has been deprecated" errors.
//
// Revision 1.4  2003/01/21 04:02:39  zorloc
// Added Log entry and page footer.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>