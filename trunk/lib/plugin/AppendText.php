<?php // -*-php-*-
rcs_id('$Id: AppendText.php,v 1.3 2004-11-25 13:56:23 rurban Exp $');
/*
Copyright 2004 Pascal Giard <evilynux@gmail.com>

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
 * Append text to an existing page.
 *
 * See http://sourceforge.net/mailarchive/forum.php?thread_id=6028698&forum_id=4517 
 * why not to use "text" as parameter. Nasty mozilla bug with mult. radio rows.
 */
class WikiPlugin_AppendText
extends WikiPlugin
{
    function getName() {
        return _("AppendText");
    }

    function getDescription() {
        return _("Append text to any page in this wiki.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.3 $");
    }

    function getDefaultArguments() {
        return array('page'     => '[pagename]',
                     's'        => '',  // Text to append.
                     'before'   => '',  // Add before (ignores after if defined)
                     'after'    => '',  // Add after line beginning with this
                     );
    }

    function _fallback($addtext, $oldtext, $notfound, &$message) {
        $message->pushContent(sprintf(_("%s not found. Appending at the end.\n"), $notfound));
        return $oldtext . "\n" . $addtext;
    }

    function run($dbi, $argstr, &$request, $basepage) {

        $args = $this->getArgs($argstr, $request);
        $pagename = $args['page'];

        if (empty($args['s']))
            if ($request->isPost() and $pagename != _("AppendText"))
                return HTML($request->redirect(WikiURL($pagename, false, 'absurl'), false));
            else    
                return '';

        $page = $dbi->getPage($pagename);
        $message = HTML();

        if (!$page->exists()) { // create it?
            $message->pushContent(sprintf(_("Page could not be updated. %s doesn't exist!\n",
                                            $pagename)));
            return $message;
        }
            
        $current = $page->getCurrentRevision();
        $oldtext = $current->getPackedContent();
        $text = $args['s'];

        // If a "before" or "after" is specified but not found, we simply append text to the end.
        if (!empty($args['before'])) {
            $before = preg_quote($args['before'], "/");
            // Insert before
            $newtext =
                ( preg_match("/\n${before}/", $oldtext) ) ?
                preg_replace("/(\n${before})/",
                             "\n" .  preg_quote($text, "/") . "\\1",
                             $oldtext) :
                $this->_fallback($text, $oldtext, $args['before'], &$message);

        } elseif (!empty($args['after'])) {
            // Insert after
            $after = preg_quote($args['after'], "/");
            $newtext = 
                ( preg_match("/\n${after}/", $oldtext) ) ?
                preg_replace("/(\n${after})/",
                             "\\1\n" .  preg_quote($text, "/"),
                             $oldtext) :
                $this->_fallback($text, $oldtext, $args['after'], &$message);

        } else {
            // Append at the end
            $newtext = $oldtext .
                "\n" . $text;
        }

        require_once("lib/loadsave.php");
        $meta = $current->_data;
        $meta['summary'] = sprintf(_("AppendText to %s"), $pagename);
        if ($page->save($newtext, $current->getVersion() + 1, $meta)) {
            $message->pushContent(_("Page successfully updated."), HTML::br());
            $message->pushContent(_("Go to "));
            $message->pushContent(HTML::em(WikiLink($pagename)));
        }

        return $message;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.2  2004/11/25 08:29:43  rurban
// update from Pascal
//
// Revision 1.2  2004/11/24 11:22:30  Pascal Giard <evilynux@gmail.com>
// * Integrated rurban's modifications.
//
// Revision 1.1  2004/11/24 09:25:35  rurban
// simple plugin by Pascal Giard (QC/EMC)
//
// Revision 1.0  2004/11/23 09:43:35  epasgia
// * Initial version.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>