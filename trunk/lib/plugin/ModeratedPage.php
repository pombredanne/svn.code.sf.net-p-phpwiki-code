<?php // -*-php-*-
rcs_id('$Id: ModeratedPage.php,v 1.1 2004-11-19 19:22:35 rurban Exp $');
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
                            "\$Revision: 1.1 $");
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
     * resolve moderators and require_access from actionpage plugin argstr
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
            $moderators = explode(',',$args['moderators']); 
            for ($i=0; $i<count($moderators); $i++) {
                $members = $group->getMembersOf($moderators[$i]);
                if (!empty($members)) {
                    array_splice($moderators,$i,1,$members);
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
     * Handle moderation change request by the user.
     * Hook called on the lock action, if moderation metadata already exists.
     */
    function lock_check(&$request, &$page, $moderated) {
        $action_page = $request->getPage(_("ModeratedPage"));
        $old_moderation = $this->getStatus($request, $page, $action_page);
        if (is_array($old_moderation)) {
            $page->set('moderation', $moderation);
            return $this->notice(
                       fmt("ModeratedPage status update:\n  Moderators: '%s'\n  require_access: '%s'", 
                       join(',',$moderation['moderators']), $moderation['require_access']));
        } else {
            $page->set('moderation', false);
            return $this->notice(HTML($old_moderation,
                        fmt("'%s' is no ModeratedPage anymore.", $page->getName()))); 
        }
    }

    /**
     * Handle moderation change request by the user.
     * Hook called on the lock action, if moderation metadata should be added.
     * Need to store the the plugin args (who, when) in the page meta-data
     */
    function lock_add(&$request, &$page, &$action_page) {
        $moderation = $this->getStatus($request, $page, $action_page);
        if (is_array($moderation)) {
            $page->set('moderation', $moderation);
            return $this->notice(
                       fmt("ModeratedPage status update: '%s' is now a ModeratedPage.\n  Moderators: '%s'\n  require_access: '%s'", 
                       $page->getName(), join(',',$moderation['moderators']), $moderation['require_access']));
        }
        else { // error
            return $moderation;
        }
    }
    
    function notice($msg) {
    	return HTML::div(array('class' => 'wiki-edithelp'), $msg);
    }

    function getStatus(&$request, &$page, &$action_page) {
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

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>