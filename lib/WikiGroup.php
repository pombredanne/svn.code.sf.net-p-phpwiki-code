<?php
rcs_id('$Id: WikiGroup.php,v 1.13 2004-03-08 18:17:09 rurban Exp $');
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

// For now we provide no default memberhsip method. This might change.
// (!defined('GROUP_METHOD')) define('GROUP_METHOD', "WIKIPAGE");

if (!defined('GROUP_METHOD') or 
    !in_array(GROUP_METHOD,
              array('NONE','WIKIPAGE','DB','FILE','LDAP')))
    trigger_error(_("No or unsupported GROUP_METHOD defined"), E_USER_WARNING);
    
/* Special group names for ACL */    
define('GROUP_EVERY',		_("Every"));
define('GROUP_ANONYMOUS',	_("Anonymous Users"));
define('GROUP_BOGOUSERS',	_("Bogo Users"));
define('GROUP_HASHOMEPAGE',     _("HasHomePage"));
define('GROUP_SIGNED',		_("Signed Users"));
define('GROUP_AUTHENTICATED',	_("Authenticated Users"));
define('GROUP_ADMIN',		_("Administrators"));
define('GROUP_OWNER',		_("Owner"));
define('GROUP_CREATOR',	   	_("Creator"));

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
            case "LDAP": 
                return new GroupLDAP($request);
                break;
            default:
                trigger_error(_("No or unsupported GROUP_METHOD defined"), E_USER_WARNING);
                return new WikiGroup($request);
        }
    }

    /* ACL PagePermissions will need those special groups based on the User status only */
    function specialGroup($group){
    	return in_array($group,$this->specialGroups());
    }

    function specialGroups(){
    	return array(
                     GROUP_EVERY,
                     GROUP_ANONYMOUS,
                     GROUP_BOGOUSERS,
                     GROUP_SIGNED,
                     GROUP_AUTHENTICATED,
                     GROUP_ADMIN);
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
    	if ($this->specialGroup($group)) {
            $request = &$this->request;
            $user = $request->getUser();
            switch ($group) {
            case GROUP_EVERY: 		return true;
            case GROUP_ANONYMOUS: 	return ! $user->isSignedIn();
            case GROUP_BOGOUSERS: 	return isa($user,'_BogoUser');
            case GROUP_SIGNED:    	return $user->isSignedIn();
            case GROUP_AUTHENTICATED: 	return $user->isAuthenticated();
            case GROUP_ADMIN:		return $user->isAdmin();
            default:
                trigger_error(__sprintf("Undefined method %s for special group %s",
                                        'isMember',$group),
                              E_USER_WARNING);
            }
        } else {
            trigger_error(__sprintf("Method '%s' not implemented in this GROUP_METHOD %s",
                                    'isMember',GROUP_METHOD),
                          E_USER_WARNING);
        }
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
        trigger_error(__sprintf("Method '%s' not implemented in this GROUP_METHOD %s",
                                'getAllGroupsIn',GROUP_METHOD),
                      E_USER_WARNING);
        return array();
    }

    function _allUsers() {
    	static $users = array();
    	if (!empty($users))
    		return $users;
    		
        /* WikiPage users: */
        $dbh = $this->request->getDbh();
        $page_iter = $dbh->getAllPages();
        while ($page = $page_iter->next()) {
            if ($page->isUserPage())
                $users[] = $page->_pagename;
        }

        /* WikiDB users from prefs (not from users): */
        $dbi = _PassUser::getAuthDbh();
        if ($GLOBALS['DBAuthParams']['pref_select']) {
            //get prefs table
            $sql = str_replace(' prefs ',' userid ',$GLOBALS['DBAuthParams']['pref_select']);
            $sql = preg_replace('/WHERE.*/i','',$sql);
            if ($GLOBALS['DBParams']['dbtype'] == 'ADODB') {
                $db_result = $dbi->Execute($sql);
                $users = array_merge($users,$db_result->GetArray());
            } elseif ($GLOBALS['DBParams']['dbtype'] == 'SQL') {
                $users = array_merge($users,$dbi->getCol($sql));
            }
        }
        return $users;
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
    	if ($this->specialGroup($group)) {
            $request = &$this->request;
            $all = $this->_allUsers();
            $users = array();
            switch ($group) {
            case GROUP_EVERY: 		
                return $all;
            case GROUP_ANONYMOUS: 	
                return $users;
            case GROUP_BOGOUSERS: 	
                foreach ($all as $u) {
                    if (isWikiWord($user)) $users[] = $u;
                }
                return $users;
            case GROUP_SIGNED:    	
                foreach ($all as $u) {
                    $user = WikiUser($u);
                    if ($user->isSignedIn()) $users[] = $u;
                }
                return $users;
            case GROUP_AUTHENTICATED:
                foreach ($all as $u) {
                    $user = WikiUser($u);
                    if ($user->isAuthenticated()) $users[] = $u;
                }
                return $users;
            case GROUP_ADMIN:		
                foreach ($all as $u) {
                    $user = WikiUser($u);
                    if ($user->isAdmin()) $users[] = $u;
                }
                return $users;
            default:
                trigger_error(__sprintf("Method '%s' not implemented in this GROUP_METHOD %s",
                                        'getMembersOf',GROUP_METHOD),
                              E_USER_WARNING);
            }
        }
        trigger_error(__sprintf("Method '%s' not implemented in this GROUP_METHOD %s",
                                'getMembersOf',GROUP_METHOD),
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
        trigger_error(__sprintf("Method '%s' not implemented in this GROUP_METHOD %s",
                                'setMemberOf',GROUP_METHOD),
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
        trigger_error(__sprintf("Method '%s' not implemented in this GROUP_METHOD %s",
                                'removeMemberOf',GROUP_METHOD),
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
    	if ($this->specialGroup($group)) {
            return WikiGroup::isMember($group);
        } else {
            return false;
        }
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
     * Initializes the three superclass instance variables
     * @param object $request The global WikiRequest object.
     */ 
    function GroupWikiPage(&$request){
        $this->request = &$request;
        $this->username = $this->_getUserName();
        //$this->username = null;
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
    	if ($this->specialGroup($group))
            return WikiGroup::isMember($group);

        $request = $this->request;
        //$username = $this->_getUserName();
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
        $match = '/^\s*[\*\#]+\s*' . $this->username . '\s*$/';
        foreach ($contents as $line){
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
        //$username = $this->_getUserName();
        $membership = array();

    	$specialgroups = $this->specialGroups();
        foreach ($specialgroups as $group) {
            $this->membership[$group] = $this->isMember($group);
        }

        $dbh = &$request->getDbh();
        $master_page = $request->getPage(_("CategoryGroup"));
        $master_list = $master_page->getLinks(true);
        while ($group_page = $master_list->next()){
            $group = $group_page->getName();
            $this->membership[$group] = $this->_inGroupPage($group_page);
        }
        foreach ($this->membership as $g => $bool) {
            if ($bool) $membership[] = $g;
        }
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
    	if ($this->specialGroup($group))
            return WikiGroup::getMembersOf($group);

        trigger_error("GroupWikiPage::getMembersOf is not yet implimented",
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
    
    var $_is_member, $_group_members, $_user_groups;
    /**
     * Constructor
     * 
     * @param object $request The global WikiRequest object.
     */ 
    function GroupDb(&$request){
    	global $DBAuthParams;
        $this->request = &$request;
        $this->username = $this->_getUserName();
        //$this->membership = array();

        if (empty($DBAuthParams['group_members']) or 
            empty($DBAuthParams['user_groups']) or
            empty($DBAuthParams['is_member'])) {
            trigger_error(_("No or not enough GROUP_DB SQL statements defined"), E_USER_WARNING);
            return false;
        }
        _PassUser::getAuthDbh();
        $this->_is_member = $this->_auth_dbi->prepare(str_replace(array('"$userid"','"$groupname"'),array('?','?'),$DBAuthParams['is_member']));
        $this->_group_members = $this->_auth_dbi->prepare(str_replace('"$groupname"','?',$DBAuthParams['group_members']));
        $this->_user_groups = $this->_auth_dbi->prepare(str_replace('"$userid"','?',$DBAuthParams['user_groups']));
    }

    /**
     * Determines if the current user is a member of a database group.
     * 
     * @param string $group Name of the group to check for membership.
     * @return boolean True if user is a member, else false.
     */ 
    function isMember($group) {
    	if ($this->specialGroup($group))
            return WikiGroup::isMember($group);

        $request = $this->request;
        $username = $this->_getUserName();
        if (isset($this->membership[$group])) {
            return $this->membership[$group];
        }
        $dbh = _PassUser::getAuthDbh();
        $db_result = $dbh->execute($this->_is_member,array($username,$group));
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

    	$specialgroups = $this->specialGroups();
        foreach ($specialgroups as $group) {
            if ($this->isMember($group)) {
                $membership[] = $group;
            }
        }
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
    	if ($this->specialGroup($group))
            return WikiGroup::isMember($group);

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
        $username = $this->_getUserName();
        $membership = array();

    	$specialgroups = $this->specialGroups();
        foreach ($specialgroups as $group) {
            if ($this->isMember($group)) {
                $this->membership[$group] = true;
                $membership[] = $group;
            }
        }
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
     * Return all the current members.
     * @param string $group Name of the group to get the full membership list of.
     * @return array Array of usernames that have joined the group.
     */ 
    function getMembersOf($group){
    	if ($this->specialGroup($group)) {
            trigger_error(__sprintf("Undefined method %s for special group %s",
                                    'getMembersOf',$group),
                          E_USER_WARNING);
        } else {
            if (!empty($this->_file->users[$group])) {
                return explode(' ',$this->_file->users[$group]);
            }
            return array();
        }
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
        if (! function_exists('ldap_connect')) {
            dl("ldap".DLL_EXT);
            if (! function_exists('ldap_connect')) {
                trigger_error(_("No LDAP in this PHP version"), E_USER_WARNING);
                return false;
            }
        }
        if (!defined("LDAP_BASE_DN"))
            define("LDAP_BASE_DN",'');
        $this->base_dn = LDAP_BASE_DN;
        if (strstr("ou=",LDAP_BASE_DN))
            $this->base_dn =  preg_replace("/(ou=\w+,)?()/","\$2",LDAP_BASE_DN);
    }

    /**
     * Determines if the current user is a member of a group.
     * Not ready yet!
     * 
     * @param string $group Name of the group to check for membership.
     * @return boolean True if user is a member, else false.
     */ 
    function isMember($group) {
    	if ($this->specialGroup($group))
            return WikiGroup::isMember($group);

        if (isset($this->membership[$group])) {
            return $this->membership[$group];
        }
        $request = $this->request;
        $username = $this->_getUserName();

        $this->membership[$group] = in_array($username,$this->getMembersOf($group));
        return $this->membership[$group];
    }
    
    /**
     * Determines all of the groups of which the current user is a member.
     *
     * @param string $group Name of the group to check for membership.
     * @return array Array of groups to which the user belongs.
     */ 
    function getAllGroupsIn(){
        $request = &$this->request;
        $username = $this->_getUserName();
        $membership = array();

    	$specialgroups = $this->specialGroups();
        foreach ($specialgroups as $group) {
            if ($this->isMember($group)) {
                $this->membership[$group] = true;
                $membership[] = $group;
            }
        }
        if ($ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
            $r = @ldap_bind($ldap); 		    // this is an anonymous bind
            $sr = ldap_search($ldap, "ou=Users,".$this->base_dn,"uid=$username");
            $info = ldap_get_entries($ldap, $sr);
            for ($i = 0; $i < $info["count"]; $i++) {
            	if ($info[$i]["gidnumber"]["count"]) {
                  $gid = $info[$i]["gidnumber"][0];
                  $sr2 = ldap_search($ldap, "ou=Groups,".$this->base_dn,"gidNumber=$gid");
                  $info2 = ldap_get_entries($ldap, $sr2);
                  if ($info2["count"])
                    $membership[] =  $info2[0]["cn"][0];
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
     * Return all the members of the given group. LDAP just returns the gid of each user
     * @param string $group Name of the group to get the full membership list of.
     * @return array Array of usernames that have joined the group.
     */ 
    function getMembersOf($group){
        $members = array();
        if ($ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
            $r = @ldap_bind($ldap); 		    // this is an anonymous bind
            $sr = ldap_search($ldap, "ou=Groups,".$this->base_dn,"cn=$group");
            $info = ldap_get_entries($ldap, $sr);
            for ($i = 0; $i < $info["count"]; $i++) {
                $gid = $info[$i]["gidnumber"][0];
                $sr2 = ldap_search($ldap, "ou=Users,".$this->base_dn,"gidNumber=$gid");
                $info2 = ldap_get_entries($ldap, $sr2);
                for ($j = 0; $j < $info2["count"]; $j++) {
                    $members[] = $info2[$j]["cn"][0];
                }
            }
        }
        ldap_close($ldap);
        return $members;
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.12  2004/02/23 21:30:25  rurban
// more PagePerm stuff: (working against 1.4.0)
//   ACL editing and simplification of ACL's to simple rwx------ string
//   not yet working.
//
// Revision 1.11  2004/02/07 10:41:25  rurban
// fixed auth from session (still double code but works)
// fixed GroupDB
// fixed DbPassUser upgrade and policy=old
// added GroupLdap
//
// Revision 1.10  2004/02/03 09:45:39  rurban
// LDAP cleanup, start of new Pref classes
//
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