<?php // -*-php-*-
rcs_id('$Id: ModeratedPage.php,v 1.2 2004-11-30 17:46:49 rurban Exp $');
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
/**
 * This plugin requires an action page (Default: ModeratedPage)
 * and provides delayed execution of restricted actions, after
 * a special moderators request:
 *   http://mywiki/SomeModeratedPage?action=ModeratedPage&id=kdclcr78431zr43uhrn&pass=approve
 *
 * Author: ReiniUrban
 */

require_once("lib/WikiPlugin.php");

class WikiPlugin_ModeratedPage
extends WikiPlugin
{
    function getName () {
        return _("ModeratedPage");
    }
    function getDescription () {
        return _("Support moderated pages");
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.2 $");
    }
    function getDefaultArguments() {
        return array('page'          => '[pagename]',
                     'moderators'    => false,
                     'require_level' => false,   // 1=bogo
                     'require_access' => 'edit,remove,change',
                     'id'   => '',
                     'pass' => '',
                    );
    }

    function run($dbi, $argstr, &$request, $basepage) {
        $args = $this->getArgs($argstr, $request);

        // handle moderation request from the email
        if (!empty($args['id']) and !empty($args['pass'])) {
            if (!$args['page'])
                return $this->error("No page specified");
	    $page = $dbi->getPage($args['page']);
            $moderation = $page->get("moderation");
            if ($moderation) {
              if (isset($moderation['id']) and $moderation['id'] == $args['id']) {
            	// handle defaults:
                //   approve or reject
                if ($args['pass'] == 'approve')
                    return $this->approve($args, $moderation);
                elseif ($args['pass'] == 'reject')
                    return $this->reject($args, $moderation);
                else
                    return $this->error("Wrong pass ".$args['pass']);
              } else {
                return $this->error("Wrong id");
              }
            }
        }
        return '';
    }

    /**
     * resolve moderators and require_access (not yet) from actionpage plugin argstr
     */
    function resolve_argstr(&$request, $argstr) {
        $args = $this->getArgs($argstr);
        $group = $request->getGroup();
        if (empty($args['moderators'])) {
            $admins = $group->getSpecialMembersOf(GROUP_ADMIN);
            // email or usernames?
            $args['moderators'] = array_merge($admins, array(ADMIN_USER));
        } else { 
            // resolve possible group names
            $moderators = explode(',', $args['moderators']); 
            for ($i=0; $i < count($moderators); $i++) {
                $members = $group->getMembersOf($moderators[$i]);
                if (!empty($members)) {
                    array_splice($moderators, $i, 1, $members);
                }
            }
            if (!$moderators) $moderators = array(ADMIN_USER);
            $args['moderators'] = $moderators;
        }
        //resolve email for $args['moderators']
        $page = $request->getPage();
        $users = array();
        foreach ($args['moderators'] as $userid) {
            $users[$userid] = 0;
        }
        list($args['emails'], $args['moderators']) = $page->getPageChangeEmails(array($page->getName() => $users));
        unset($args['id']);
        unset($args['page']);
        unset($args['pass']);
        return $args;
    }
    
    /**
     * Handle client-side moderation change request.
     * Hook called on the lock action, if moderation metadata already exists.
     */
    function lock_check(&$request, &$page, $moderated) {
        $action_page = $request->getPage(_("ModeratedPage"));
        $status = $this->getSiteStatus($request, $action_page);
        if (is_array($status)) {
            if (!empty($status['emails'])) {
                trigger_error(_("ModeratedPage: No emails for the moderators defined"), E_USER_WARNING);
                return false;
            }
            $page->set('moderation', array('_status' => $status));
            return $this->notice(
                       fmt("ModeratedPage status update:\n  Moderators: '%s'\n  require_access: '%s'", 
                       join(',', $status['moderators']), $status['require_access']));
        } else {
            $page->set('moderation', false);
            return $this->notice(HTML($status,
                        fmt("'%s' is no ModeratedPage anymore.", $page->getName()))); 
        }
    }

    /**
     * Handle client-side moderation change request by the user.
     * Hook called on the lock action, if moderation metadata should be added.
     * Need to store the the plugin args (who, when) in the page meta-data
     */
    function lock_add(&$request, &$page, &$action_page) {
        $status = $this->getSiteStatus($request, $action_page);
        if (is_array($status)) {
            if (!empty($status['emails'])) {
                trigger_error(_("ModeratedPage: No emails for the moderators defined"), E_USER_WARNING);
                return false;
            }
            $page->set('moderation', array('_status' => $status));
            return $this->notice(
                       fmt("ModeratedPage status update: '%s' is now a ModeratedPage.\n  Moderators: '%s'\n  require_access: '%s'", 
                       $page->getName(), join(',', $status['moderators']), $status['require_access']));
        }
        else { // error
            return $status;
        }
    }
    
    function notice($msg) {
    	return HTML::div(array('class' => 'wiki-edithelp'), $msg);
    }

    function generateId() {
        better_srand();
        $s = "";
        for ($i = 1; $i <= 16; $i++) {
            $r = function_exists('mt_rand') ? mt_rand(55, 90) : rand(55, 90);
            $s .= chr($r < 65 ? $r-17 : $r);
        }
        return $s;
    }

    /** 
     * Handle client-side moderation request on any moderated page.
     *   if ($page->get('moderation')) WikiPlugin_ModeratedPage::handler(...);
     * return false if not handled (pass through), true if handled and displayed.
     */
    function handler(&$request, &$page) {
    	$action = $request->getArg('action');
    	$moderated = $page->get('moderated');
    	// cached version, need re-lock of each page to update moderators
    	if (!empty($moderated['_status'])) 
    	    $status = $moderated['_status'];
    	else {
            $action_page = $request->getPage(_("ModeratedPage"));
            $status = $this->getSiteStatus($request, $action_page);
            $moderated['_status'] = $status;
    	}
        if (!empty($status['emails'])) {
            trigger_error(_("ModeratedPage: No emails for the moderators defined"), E_USER_WARNING);
            return true;
        }
        // which action?
    	if ($action == 'edit') {
    	    //$moderated = $page->get('moderated');
    	    $id = $this->generateId();
    	    while (!empty($moderated[$id])) $id = $this->generateId(); // avoid duplicates
    	    $moderated['id'] = $id;
    	    $moderated['data'][$id] = array('args' => $request->getArgs(),
    	                                    'timestamp' => time(),
    	    	          		    'userid' => $request->_user->getId());
            $this->_tokens['CONTENT'] = HTML::div(array('class' => 'wikitext'),
            					  fmt("%s: action forwarded to moderator %s", 
                                                      $action, 
                                                      join(", ", $status['moderators'])
                                                      ));
	    //send email
            $pagename = $page->getName();
            $subject = "[".WIKI_NAME.'] '.$action.': '._("ModeratedPage").' '.$pagename;
            if (mail(join(",", $status['emails']), 
                     $subject, 
                     $action.': '._("ModeratedPage").' '.$pagename."\n"
                     . serialize($moderated['data'][$id])
                     ."\n<".WikiURL($pagename, array('id' => $id,'pass' => 'approve'),1).">"
                     ."\n<".WikiURL($pagename, array('id' => $id,'pass' => 'reject'),1).">\n"
                     )) {
                $page->set('moderated', $moderated);
                return false; // pass thru
            } else {
            	//FIXME: This will msg get lost on the edit redirect
                trigger_error(_("ModeratedPage Notification Error: Couldn't send email"), E_USER_WARNING);
                return true;
            }
    	}
        return false;
    }

    /** 
     * Handle admin-side moderation resolve.
     */
    function approve($args, $moderation) {
        ;
    }
    /** 
     * Handle admin-side moderation resolve.
     */
    function reject($args, $moderation) {
        ;
    }
    
    /**
     * Get the side-wide ModeratedPage status, reading the action-page args.
     * Who are the moderators? What actions should be moderated?
     */
    function getSiteStatus(&$request, &$action_page) {
        $loader = new WikiPluginLoader();
        $rev = $action_page->getCurrentRevision();
        $content = $rev->getPackedContent();
        list($pi) = explode("\n", $content, 2); // plugin ModeratedPage must be first line!
        if ($parsed = $loader->parsePI($pi)) {
            $plugin =& $parsed[1];
            if ($plugin->getName() != _("ModeratedPage"))
                return $this->error(sprintf(_("<?plugin ModeratedPage ... ?> not found in first line of %s"),
                                            $action_page->getName()));
            if (!$action_page->get('locked'))
                return $this->error(sprintf(_("%s is not locked!"),
                                            $action_page->getName()));
            return $plugin->resolve_argstr($request, $parsed[2]);
        } else {
            return $this->error(sprintf(_("<?plugin ModeratedPage ... ?> not found in first line of %s"),
                                        $action_page->getName()));
        }
    }
    
};

// $Log: not supported by cvs2svn $
// Revision 1.1  2004/11/19 19:22:35  rurban
// ModeratePage part1: change status
//

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>