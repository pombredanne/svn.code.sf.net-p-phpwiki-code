<?php // -*-php-*-
rcs_id('$Id: AddComment.php,v 1.1 2004-03-12 17:32:41 rurban Exp $');
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
 * This plugin allows user comments attached to a page, similar to WikiBlog.
 * based on WikiBlog, no summary.
 *
 * TODO:
 * For admin user, put checkboxes beside comments to allow for bulk removal.
 *
 * @author: ReiniUrban
 */

include_once("lib/plugin/WikiBlog.php");

class WikiPlugin_AddComment
extends WikiPlugin_WikiBlog
{
    function getName () {
        return _("AddComment");
    }

    function getDescription () {
        return sprintf(_("Show and add comments for %s"),'[pagename]');
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    // Arguments:
    //
    //  page - page where the comment is attached at (default current page)
    //
    //  order - 'normal'  - place in chronological order
    //        - 'reverse' - place in reverse chronological order
    //
    //  mode - 'show'     - only show old comments
    //         'add'      - only show entry box for new comment
    //         'show,add' - show old comments then entry box
    //         'add,show' - show entry box followed by list of comments
    //  jshide - boolean  - quick javascript expansion of the addcomment box

    function getDefaultArguments() {
        return array('page'       => '[pagename]',
                     'order'      => 'normal',
                     'mode'       => 'add,show',
                     'jshide'     => '1',
                     'noheader'   => false
                    );
    }

    function run($dbi, $argstr, &$request, $basepage) {
        $args = $this->getArgs($argstr, $request);
        if (!$args['page'])
            return $this->error("No page specified");

        // Get our form args.
        $comment = $request->getArg("comment");
        $request->setArg('comment', false);
            
        if ($request->isPost() and !empty($comment['addcomment'])) {
            $this->add($request, $comment, 'comment'); // noreturn
        }

        // Now we display previous comments and/or provide entry box
        // for new comments
        $html = HTML();
        foreach (explode(',', $args['mode']) as $show) {
            if (!empty($seen[$show]))
                continue;
            $seen[$show] = 1;
            switch ($show) {
            case 'show':
                $html->pushContent($this->showAll($request, $args, 'comment'));
                break;
            case 'add':
                $html->pushContent($this->showForm($request, $args, 'addcomment'));
                break;
            default:
                return $this->error(sprintf("Bad mode ('%s')", $show));
            }
        }
        return $html;
    }
   
};

// $Log: not supported by cvs2svn $
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