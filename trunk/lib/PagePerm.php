<?php // -*-php-*-
rcs_id('$Id: PagePerm.php,v 1.2 2004-02-08 13:17:48 rurban Exp $');
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

   Permissions maybe inherited its parent pages, and ultimativly the 
   optional master page (".")
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

     The defined main.php actions map to simplier access types here:
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
// Todo: cache result per access and page in session
function requiredAuthorityForPage ($action) {
    global $request;
    // translate action to access
    switch ($action) {
    case 'browse':
    case 'viewsource':
    case 'diff':
    case 'select':
    case 'xmlrpc':
    case 'search':
        $access = 'view'; break;
    case 'zip':
    case 'ziphtml':
        $access = 'dump'; break;
    case 'edit':
        $access = 'edit'; break;
    case 'create':
        $page = $this->getPage();
        $current = $page->getCurrentRevision();
        if ($current->hasDefaultContents())
            $access = 'edit';
        else
            $access = 'view'; 
        break;
    case 'upload':
    case 'dumpserial':
    case 'dumphtml':
    case 'loadfile':
    case 'remove':
    case 'lock':
    case 'unlock':
            $access = 'change'; break;
    default:
        if (isWikiWord($action))
            $access = 'view';
        else
            $access = 'change';
        break;
    }
    return _requiredAuthorityForPagename($access,$request->getArg('pagename'));
}

function _requiredAuthorityForPagename ($access,$pagename) {
    global $request;
    $page = $request->getPage($pagename);
    if (! $page ) {
        $perm = new PagePermission(); // check against default permissions
        return ($perm->isAuthorized($access,$request->_user) === true);
    }
    $perm = getPagePermissions($page);
    $authorized = $perm->isAuthorized($access,$request->_user);
    if ($authorized != -1)
        return $authorized ? $request->_user->_level : WIKIAUTH_FORBIDDEN;
    else
        return _requiredAuthorityForPagename($access,getParentPage($pagename));
}


/*
 * @param  string $pagename   page from which the parent page is searched.
 * @return string parent pagename or the (possibly pseudo) dot-pagename.
 */
function getParentPage($pagename) {
    global $request;
    if (ifSubPage($pagename)) {
        return subPageSlice($pagename,0);
    } else {
        return '.';
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
    return '';
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
        return -1; // undecided
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
        if ($group === ACL_ADMIN)   // WIKI_ADMIN or member of _("Administrators")
            return $user->isAdmin() or
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
        if ($group === ACL_OWNER) {
            $page = $request->getPage();
            return $page->get('author') === $user->UserName();
        }
        if ($group === ACL_CREATOR) {
            $page = $request->getPage();
            $rev = $page->getRevision(1);
            return $rev->get('author') === $user->UserName();
        }
        /* or named groups or usernames */
        return $user->UserName() === $group or
               $member->isMember($group);
    }

    /**
     * returns hash of default permissions.
     * check if the page '.' exists and returns this instead.
     */
    function defaultPerms() {
        //Todo: check for the existance of '.' and take this instead.
        //Todo: honor more index.php auth settings here
        $perm = array('view'   => array(ACL_EVERY => true),
                      'edit'   => array(ACL_EVERY => true),
                      'create' => array(ACL_EVERY => true),
                      'list'   => array(ACL_EVERY => true),
                      'remove' => array(ACL_ADMIN => true,
                                        ACL_OWNER => true),
                      'change' => array(ACL_ADMIN => true,
                                        ACL_OWNER => true));
        if (defined('ZIPDUMP_AUTH') && ZIPDUMP_AUTH)
            $perm['dump'] = array(ACL_ADMIN => true,
                                  ACL_OWNER => true);
        else
            $perm['dump'] = array(ACL_EVERY => true);
        if (defined('REQUIRE_SIGNIN_BEFORE_EDIT') && REQUIRE_SIGNIN_BEFORE_EDIT)
            $perm['edit'] = array(ACL_SIGNIN => true);
        if (defined('ALLOW_ANON_USER') && ! ALLOW_ANON_USER) {
            if (defined('ALLOW_BOGO_USER') && ALLOW_BOGO_USER) {
                $perm['view'] = array(ACL_BOGOUSER => true);
                $perm['edit'] = array(ACL_BOGOUSER => true);
            } elseif (defined('ALLOW_USER_PASSWORDS') && ALLOW_USER_PASSWORDS) {
                $perm['view'] = array(ACL_AUTHENTICATED => true);
                $perm['edit'] = array(ACL_AUTHENTICATED => true);
            } else {
                $perm['view'] = array(ACL_SIGNIN => true);
                $perm['edit'] = array(ACL_SIGNIN => true);
            }
        }
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
// Revision 1.1  2004/02/08 12:29:30  rurban
// initial version, not yet hooked into lib/main.php
//
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
