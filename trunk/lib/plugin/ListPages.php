<?php // -*-php-*-
rcs_id('$Id: ListPages.php,v 1.3 2004-06-28 18:58:18 rurban Exp $');
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

require_once('lib/PageList.php');

/**
 * ListPages - List pages that are explicitly given as the pages argument.
 *
 * Mainly used to see some ratings and recommendations.
 * But also possible to list some Categories or Users.
 *
 * @author: Dan Frankowski
 */
class WikiPlugin_ListPages
extends WikiPlugin
{
    function getName() {
        return _("ListPages");
    }

    function getDescription() {
        return _("List pages that are explicitly given as the pages argument.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.3 $");
    }

    function getDefaultArguments() {
        return array('pages'    => '',
                     'info'     => 'pagename,top3recs',
                     'dimension' => 0,
                     );
    }

    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor

    function run($dbi, $argstr, $request) {
        
        $args = $this->getArgs($argstr, $request);
        extract($args);

        if (in_array('top3recs', split(',', $info))) {

            require_once('lib/wikilens/Buddy.php');
            require_once('lib/wikilens/PageListColumns.php');

            $active_user   = $request->getUser();
            $active_userid = $active_user->_userid;

            // if userids is null or empty, fill it with just the active user
            if (!isset($userids) || !is_array($userids) || !count($userids)) {
                // TKL: moved getBuddies call inside if statement because it was
                // causing the userids[] parameter to be ignored
                if (is_string($active_userid) && strlen($active_userid) && $active_user->isSignedIn()) {
                    $userids = getBuddies($active_userid, $dbi);
                } else {
                    $userids = array();
                    // XXX: this wipes out the category caption...
                    $caption = _("You must be logged in to view ratings.");
                }
            }

            // find out which users we should show ratings for
            $allowed_users = array();
            foreach ($userids as $userid) {
                $user = new RatingsUser($userid);
                if ($user->allow_view_ratings($active_user)) {
                    array_push($allowed_users, $user);
                }
                // PHP's silly references... (the behavior with this line commented
                // out is... odd)
                unset($user);
            }
            //DONE:
            //What we really want is passing the pagelist object to the column
            //$top3 = new _PageList_Column_top3recs('top3recs', _("Top Recommendations"), 
            //                                      'left', 0, $allowed_users);
            $options = array('dimension' => $dimension, 
                             'users' => $allowed_users);
        } else {
            $options = array();
        }

        if (empty($pages))
            return '';

        $pagelist = new PageList($info, false, $options);
        // FIXME: This should be not neccessary with the new custom pagelist columns
        /*
        if (!empty($options)) {
            $pagelist->addColumnObject(new _PageList_Column_top3recs('custom:top3recs', _("Top Recommendations"), 
                                                                     'left', $pagelist));
        }
        */
        foreach (explode(',', $pages) as $key => $pagename) {
            $page = $pages = $dbi->getPage($pagename); 
            $pagelist->addPage($page);
        }

        return $pagelist;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.2  2004/06/18 14:42:17  rurban
// added wikilens libs (not yet merged good enough, some work for DanFr)
//
// Revision 1.1  2004/06/08 13:49:43  rurban
// List pages that are explicitly given as the pages argument, by DanFr
// 

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
