<?php // -*-php-*-
rcs_id('$Id: PagePerm.php,v 1.1 2004-02-08 12:29:30 rurban Exp $');
/*
 Copyright 2004 $ThePhpWikiProgrammingTeam

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

/* 
   Permissions per page and action based on current user, 
   ownership and group membership implemented with ACL's (Access Control Lists),
   opposed to the simplier unix like ugo:rwx system.
   The previous system was only based on action and current user. (lib/main.php)

   Permissions maybe inherited its parent pages, and ultimatevily the 
   master page (".")
   For Authentification see WikiUserNew.php, WikiGroup.php and main.php
   Page Permssions are in PhpWiki since v1.3.9 and enabled since v1.4.0

   This file might replace the following functions from main.php:
     Request::_notAuthorized($require_level)
       display the denied message and optionally a login form 
       to gain higher privileges
     Request::getActionDescription($action)
       helper to localize the _notAuthorized message per action, 
       when login is tried.
     Request::getDisallowedActionDescription($action)
       helper to localize the _notAuthorized message per action, 
       when it aborts
     Request::requiredAuthority($action)
       returns the needed user level
       has a hook for plugins on POST
     Request::requiredAuthorityForAction($action)
       just returns the level per action, will be replaced with the 
       action + page pair

     The defined main.php actions map to simplier access types here
       browse => view
       edit   => edit
       create => edit or create
       remove => remove
       rename => change
       store prefs => change
       list in PageList => list
*/

/* Symbolic special ACL groups. Untranslated to be stored in page metadata*/
define('ACL_EVERY',	   '_EVERY');
define('ACL_ANONYMOUS',	   '_ANONYMOUS');
define('ACL_BOGOUSERS',	   '_BOGOUSERS');
define('ACL_SIGNED',	   '_SIGNED');
define('ACL_AUTHENTICATED','_AUTHENTICATED');
define('ACL_ADMIN',	   '_ADMIN');
define('ACL_OWNER',	   '_OWNER');
define('ACL_CREATOR',	   '_CREATOR');

// Walk down the inheritance tree. Collect all permissions until 
// the minimum required level is gained, which is not 
// overruled by more specific forbid rules.
function requiredAuthorityForPage ($action) {
    global $request;
    $current_page = $request->getPage();
    $perm = getPagePermissions($current_page);
    //translate action to access
    $access = $action;
    if ($perm->isAuthorized($access,$request->_user))
        return $request->_user->_level;
    //todo: recurse into parent page
    return WIKIAUTH_FORBIDDEN;
}

/*
 * @param string $pagename   page from which the parent page is searched.
 * @return WikiDB_Page Object parent page or the (possibly pseudo) dot-page handle.
 */
function getParentPage($pagename) {
    if (ifSubPage($pagename)) {
        return $request->getPage(subPageSlice($pagename,0));
    } else {
        return $request->getPage('.');
    }
}

// read the ACL from the page
function getPagePermissions ($page) {
    $hash = $page->get('perm');
    if ($hash)  // hash => object
        return new PagePermission(unserialize($hash));
    else 
        return new PagePermission();
}

// store the ACL in the page
function setPagePermissions ($page,$perm) {
    $perm->store($page);
}

// provide ui helpers to view and change page permissions
function displayPagePermissions () {
    ;
}

/**
 * The ACL object per page. It is stored in a page, but can also 
 * be merged with ACL's from other pages or taken from the master (pseudo) dot-file.
 *
 * A hash of "access" => "requires" pairs.
 *   "access"   is a shortcut for common actions, which map to main.php actions
 *   "requires" required username or groupname or any special group => true or false
 *
 * Define any special rules here, like don't list dot-pages.
 */ 
class PagePermission {

    var $perm;

    function PagePermission($hash = array()) {
        if (is_array($hash) and !empty($hash)) {
            $accessTypes = $this->accessTypes();
            foreach ($hash as $access => $requires) {
                if (in_array($access,$accessTypes))
                    $this->perm->{$access} = $requires;
                else
                    trigger_error(sprintf(_("Unsupported ACL access type %s ignored."),$access),
                                  E_USER_WARNING);
            }
        } else {
            // set default permissions, the so called dot-file acl's
            $this->perm = $this->defaultPerms();
        }
        return $this;
    }

    /**
     * The workhorse to check the user against the current ACL pairs.
     * Must translate the various special groups to the actual users settings 
     * (userid, group membership).
     */
    function isAuthorized($access,$user) {
        if (!empty($this->perm{$access})) {
            foreach ($this->perm{$access} as $group => $bool) {
                if ($this->isMember($user,$group))
                    return $bool;
            }
        }
        return false;
    }

    /**
     * Translate the various special groups to the actual users settings 
     * (userid, group membership).
     */
    function isMember($group) {
        global $request;
        if ($group === ACL_EVERY) return true;
        $member = &WikiGroup::getGroup($request);
        $user = & $request->_user;
        if ($group === ACL_ADMIN) 
            return $user->isAdmin() or // WIKI_ADMIN or member of _("Administrators")
                   $member->isMember(GROUP_ADMIN);
        if ($group === ACL_ANONYMOUS) 
            return ! $user->isSigned();
        if ($group === ACL_BOGOUSERS)
            if (ENABLE_USER_NEW) return isa($user,'_BogoUser');
            else return isWikiWord($user->UserName());
        if ($group === ACL_SIGNED)
            return $user->isSigned();
        if ($group === ACL_AUTHENTICATED)
            return $user->isAuthenticated();
        /* TODO: more special groups:
         ACL_OWNER
         ACL_CREATOR
        */

        /* 
         or named groups:
         or usernames:
        */
        return $user->UserName() === $group or
               $member->isMember($group);
    }

    /**
     * returns hash of default permissions.
     * check if the page '.' exists and returns this instead.
     */
    function defaultPerms() {
        //Todo: check for the existance of '.' and take this instead.
        //Todo: honor index.php auth settings here
        return array('view'   => array(ACL_EVERY => true),
                     'edit'   => array(ACL_EVERY => true),
                     'create' => array(ACL_EVERY => true),
                     'list'   => array(ACL_EVERY => true),
                     'remove' => array(ACL_ADMIN => true,
                                       ACL_OWNER => true),
                     'change' => array(ACL_ADMIN => true,
                                       ACL_OWNER => true),
                     );
    }

    /**
     * returns list of all supported access types.
     */
    function accessTypes() {
        return array_keys($this->defaultPerms());
    }

    /**
     * special permissions for dot-files, beginning with '.'
     * maybe also for '_' files?
     */
    function dotPerms() {
        $def = array(ACL_ADMIN => true,
                     ACL_OWNER => true);
        $perm = array();
        foreach ($this->accessTypes as $access) {
            $perm[$access] = $def;
        }
        return $perm;
    }

    /**
     *  dead code. not needed inside the object. see getPagePermissions($page)
     */
    function retrieve($page) {
        $hash = $page->get('perm');
        if ($hash)  // hash => object
            return new PagePermission(unserialize($hash));
        else 
            return new PagePermission();
    }

    function store($page) {
        // object => hash
        return $page->set('perm',serialize(obj2hash($this->perm)));
    }
}

// $Log: not supported by cvs2svn $
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
