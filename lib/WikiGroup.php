<?php
rcs_id('$Id: WikiGroup.php,v 1.2 2003-01-17 21:11:31 zorloc Exp $')
/*
 Copyright 2002 $ThePhpWikiProgrammingTeam

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
    function WikiGroup($request){    
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
        switch(GROUP_METHOD){
            case GROUP_NONE: 
                return new GroupNone(&$request);
                break;
            case GROUP_WIKIPAGE: 
                return new GroupWikiPage(&$request);
                break;
#            case GROUP_DB: 
#                return new GroupDB(&$user, &$request);
#                break;
#            case GROUP_LDAP: 
#                return new GroupLDAP(&$user, &$request);
#                break;
            default:
                trigger_error("No GROUP_METHOD defined", E_USER_WARNING);
                return new WikiGroup(&$request);
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
    function GroupNone($request){
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
    function GroupWikiPage($request){
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
        if ($this->membership[$group]) {
            return true;
        }
        $group_page = $request->getPage($group);
        if ($this->_inGroupPage($group_page)) {
            $this->membership[$group] = true;
            return true;
        }
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
            trigger_error("Group $group does not exist", E_USER_WARNING);
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
     * Checks the root Group page ('CategoryGroups') for the list of all groups, 
     * then checks each group to see if the current user is a member.
     * @param string $group Name of the group to check for membership.
     * @return array Array of groups to which the user belongs.
     */ 
    function getAllGroupsIn(){
        $request = &$this->request;
        $username = $this->_getUserName();
        $membership = array();
        $dbh = &$request->getDbh();
        $master_page = $request->getPage('CategeoryGroups');
        $master_list = $master_page->getLinks(true);
        while($group_page = $master_list->next()){
            if ($this->_inGroupPage($group_page)) {
                $group = $group_page->getName();
                $membership[$group] = true;
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

?>