<?php //-*-php-*-
rcs_id('$Id: MailNotify.php,v 1.2 2006-12-23 11:50:45 rurban Exp $');

/**
 * Handle the pagelist pref[notifyPages] logic for users
 * and notify => hash ( page => (userid => userhash) ) for pages.
 * Generate notification emails.
 *
 * We add WikiDB handlers and register ourself there:
 *   onDeletePage, onRenamePage
 * Administrative actions:
 *   [Watch] WatchPage - add a page, or delete watch handlers into the users 
 *                       pref[notifyPages] slot.
 *   My WatchList      - view or edit list/regex of pref[notifyPages].
 *
 * Helper functions:
 *   getPageChangeEmails
 *   MailAdmin
 *   ? handle emailed confirmation links (EmailSignup, ModeratedPage)
 *
 * @package MailNotify
 * @author  Reini Urban
 */

class MailNotify {

    function MailNotify($pagename) {
	$this->pagename = $pagename; /* which page */
        $this->emails  = array();    /* to whch addresses */
        $this->userids = array();    /* corresponding array of displayed names, 
                                      dont display the email addersses */
        /* from: from which the mail appears to be */
        $this->from = $this->fromId();
    }

    function fromId() {
        global $request;
        return $request->_user->getId() . '@' .  $request->get('REMOTE_HOST');
    }

    function userEmail($userid, $doverify = true) {
        global $request;
        $u = $request->getUser();
        if ($u->UserName() == $userid) { // lucky: current user
            $prefs = $u->getPreferences();
            $email = $prefs->get('email');
            // do a dynamic emailVerified check update
            if ($doverify and !$request->_prefs->get('emailVerified'))
                $email = '';
        } else {  // not current user
            if (ENABLE_USER_NEW) {
                $u = WikiUser($userid);
                $u->getPreferences();
                $prefs = &$u->_prefs;
            } else {
                $u = new WikiUser($request, $userid);
                $prefs = $u->getPreferences();
            }
            $email = $prefs->get('email');
            if ($doverify and !$prefs->get('emailVerified')) {
                $email = '';
            }
        }
        return $email;
    }

    /**
     * getPageChangeEmails($notify)
     * @param  $notify: hash ( page => (userid => userhash) )
     * @return array
     *         unique array of ($emails, $userids)
     */
    function getPageChangeEmails($notify) {
        global $request;
        $emails = array(); $userids = array();
        foreach ($notify as $page => $users) {
            if (glob_match($page, $this->pagename)) {
                foreach ($users as $userid => $user) {
                    if (!$user) { // handle the case for ModeratePage: 
                        	  // no prefs, just userid's.
                        $emails[] = $this->userEmail($userid, false);
                        $userids[] = $userid;
                    } else {
                        if (!empty($user['verified']) and !empty($user['email'])) {
                            $emails[]  = $user['email'];
                            $userids[] = $userid;
                        } elseif (!empty($user['email'])) {
                            // do a dynamic emailVerified check update
                            $email = $this->userEmail($userid, true);
                            if ($email) {
                                $notify[$page][$userid]['verified'] = 1;
                                $request->_dbi->set('notify', $notify);
                                $emails[] = $email;
                                $userids[] = $userid;
                            }
                        }
                        // ignore verification
                        /*
                        if (DEBUG) {
                            if (!in_array($user['email'],$emails))
                                $emails[] = $user['email'];
                        }
                        */
                    }
                }
            }
        }
        $this->emails = array_unique($emails);
        $this->userids = array_unique($userids);
        return array($this->emails, $this->userids);
    }

    function sendMail($subject, $content) {
        global $request;
        $emails = $this->emails;
        $from = $this->from;
        if (mail(array_shift($emails),
                 "[".WIKI_NAME."] ".$subject, 
                 $subject."\n".$content,
                 "From: $from\r\nBcc: ".join(',', $emails)
                 )) 
        {
            trigger_error(sprintf(_("PageChange Notification of %s sent to %s"),
                                  $this->pagename, join(',',$this->userids)), E_USER_NOTICE);
            return true;
        } else {
            trigger_error(sprintf(_("PageChange Notification of %s Error: Couldn't send to %s"),
                                  $this->pagename, join(',',$this->userids)), E_USER_WARNING);
            return false;
        }
    }

    /**
     * Send udiff for a changed page to multiple users.
     * See rename and remove methods also
     */
    function sendPageChangeNotification(&$wikitext, $version, &$meta) {

        global $request;

        if (@is_array($request->_deferredPageChangeNotification)) {
            // collapse multiple changes (loaddir) into one email
            $request->_deferredPageChangeNotification[] = 
                array($this->pagename, $this->emails, $this->userids);
            return;
        }
        $backend = &$this->_wikidb->_backend;
        //$backend = &$request->_dbi->_backend;
        $subject = _("Page change").' '.urlencode($this->pagename);
        $previous = $backend->get_previous_version($this->pagename, $version);
        if (!isset($meta['mtime'])) $meta['mtime'] = time();
        if ($previous) {
            $difflink = WikiURL($this->pagename, array('action'=>'diff'), true);
            $dbh = &$request->getDbh();
            $cache = &$dbh->_wikidb->_cache;
            //$cache = &$request->_dbi->_cache;
            $content = explode("\n", $wikitext);
            $prevdata = $cache->get_versiondata($this->pagename, $previous, true);
            if (empty($prevdata['%content']))
                $prevdata = $backend->get_versiondata($this->pagename, $previous, true);
            $other_content = explode("\n", $prevdata['%content']);
            
            include_once("lib/difflib.php");
            $diff2 = new Diff($other_content, $this_content);
            //$context_lines = max(4, count($other_content) + 1,
            //                     count($this_content) + 1);
            $fmt = new UnifiedDiffFormatter(/*$context_lines*/);
            $content  = $this->pagename . " " . $previous . " " . 
                Iso8601DateTime($prevdata['mtime']) . "\n";
            $content .= $this->pagename . " " . $version . " " .  
                Iso8601DateTime($meta['mtime']) . "\n";
            $content .= $fmt->format($diff2);
            
        } else {
            $difflink = WikiURL($this->pagename,array(),true);
            $content = $this->pagename . " " . $version . " " .  
                Iso8601DateTime($meta['mtime']) . "\n";
            $content .= _("New page");
        }
        $editedby = sprintf(_("Edited by: %s"), $this->from);
        //$editedby = sprintf(_("Edited by: %s"), $meta['author']);
        $this->sendMail($subject, 
                        $editedby."\n".$difflink."\n\n".$content);
    }

    /** 
     * support mass rename / remove (not yet tested)
     */
    function sendPageRenameNotification ($to, &$meta) {
        global $request;

        if (@is_array($request->_deferredPageRenameNotification)) {
            $request->_deferredPageRenameNotification[] = 
                array($this->pagename, $to, $meta, $this->emails, $this->userids);
        } else {
            $pagename = $this->pagename;
            //$editedby = sprintf(_("Edited by: %s"), $meta['author']) . ' ' . $meta['author_id'];
            $editedby = sprintf(_("Edited by: %s"), $this->from);
            $subject = sprintf(_("Page rename %s to %s"), urlencode($pagename), urlencode($to));
            $link = WikiURL($to, true);
            $this->sendMail($subject, 
                            $editedby."\n".$link."\n\n"."Renamed $pagename to $to");
        }
    }

    /**
     * The handlers:
     */
    function onChangePage (&$wikidb, &$wikitext, $version, &$meta) {
        $result = true;
	if (!isa($GLOBALS['request'],'MockRequest')) {
	    $notify = $wikidb->_wikidb->get('notify');
            /* Generate notification emails? */
	    if (!empty($notify) and is_array($notify)) {
                if (empty($this->pagename))
                    $this->pagename = $meta['pagename'];
		//TODO: defer it (quite a massive load if you MassRevert some pages).
		//TODO: notification class which catches all changes,
		//  and decides at the end of the request what to mail. (type, page, who, what, users, emails)
		// could be used for PageModeration and RSS2 Cloud xml-rpc also.
                $this->getPageChangeEmails($notify);
                if (!empty($this->emails)) {
                    $result = $this->sendPageChangeNotification($wikitext, $version, $meta);
                }
	    }
	}
	return $result;
    }

    function onDeletePage (&$wikidb, $pagename) {
        $result = true;
	/* Generate notification emails? */
	if (! $wikidb->isWikiPage($pagename) and !isa($GLOBALS['request'],'MockRequest')) {
	    $notify = $wikidb->get('notify');
	    if (!empty($notify) and is_array($notify)) {
		//TODO: deferr it (quite a massive load if you remove some pages).
		//TODO: notification class which catches all changes,
		//  and decides at the end of the request what to mail. (type, page, who, what, users, emails)
		// could be used for PageModeration and RSS2 Cloud xml-rpc also.
		$page = new WikiDB_Page($wikidb, $pagename);
		$page->getPageChangeEmails($notify);
		if (!empty($this->emails)) {
		    $editedby = sprintf(_("Removed by: %s"), $this->from); // Todo: host_id
		    $emails = join(',', $emails);
		    $subject = sprintf(_("Page removed %s"), urlencode($pagename));
                    $result = $this->sendMail($subject, 
                                              $editedby."\n"."Deleted $pagename"."\n\n".$content);
		}
	    }
	}
	//How to create a RecentChanges entry with explaining summary? Dynamically
	/*
	  $page = $this->getPage($pagename);
	  $current = $page->getCurrentRevision();
	  $meta = $current->_data;
	  $version = $current->getVersion();
	  $meta['summary'] = _("removed");
	  $page->save($current->getPackedContent(), $version + 1, $meta);
	*/
	return $result;
    }

    function onRenamePage (&$wikidb, $oldpage, $new_pagename) {
        $result = true;
	if (!isa($GLOBALS['request'], 'MockRequest')) {
	    $notify = $wikidb->get('notify');
	    if (!empty($notify) and is_array($notify)) {
		$this->getPageChangeEmails($notify);
		if (!empty($this->emails)) {
		    $newpage = $wikidb->getPage($new_pagename);
		    $current = $newpage->getCurrentRevision();
		    $meta = $current->_data;
                    $this->pagename = $oldpage;
		    $result = $this->sendPageRenameNotification($new_pagename, $meta);
		}
	    }
	}
    }
}


// $Log: not supported by cvs2svn $
// Revision 1.1  2006/12/22 17:59:55  rurban
// Move mailer functions into seperate MailNotify.php
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
