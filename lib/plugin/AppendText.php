<?php // -*-php-*-
rcs_id('$Id: AppendText.php,v 1.1 2004-11-24 09:25:35 rurban Exp $');
/*
Copyright 2004 Pascal Giard (QC/EMC)

This file is not (yet) part of PhpWiki.

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
 * Append text to an existing page
 * TODO: support for lbound and hbound.
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
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array('page'     => '[pagename]',
                     'text'     => '',  // Text to append
                     'before'   => '',  // Add before (ignores after if defined)
                     'after'    => '',  // Add after line beginning with this
                     );
    }

    function run($dbi, $argstr, &$request, $basepage) {

        $args = $this->getArgs($argstr, $request);
        $pagename = $args['page'];

        if (empty($args['text']))
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
        $text = $args['text'];

        if (!empty($args['before'])) {
            $before = preg_quote($args['before']);
            if (preg_match("/\n${before}/", $oldtext)) {
                $newtext = preg_replace("/(\n${before})/",
                                        "\n${text}\\1",
                                        $oldtext);
            } else {
                $message->pushContent(sprintf(_("%s not found. Appending at the end.\n",
                                                $args['before'])));
                $newtext = $oldtext . "\n" . $text;
            }
        } elseif (!empty($args['after'])) {
            $after = preg_quote($args['after']);
            if (preg_match("/\n${after}/", $oldtext)) {
                $newtext = preg_replace("/(\n${after})/",
                                        "\\1\n${text}",
                                        $oldtext);
            } else {
                $message->pushContent(sprintf(_("%s not found. Appending at the end.\n",
                                                $args['after'])));
                $newtext = $oldtext . "\n" . $text;
            }
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