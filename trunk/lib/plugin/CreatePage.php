<?php // -*-php-*-
rcs_id('$Id: CreatePage.php,v 1.1 2004-03-08 18:57:59 rurban Exp $');
/**
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
 * CreatePage plugin.
 *
 * This allows you to create a page using a forms-based interface.
 * Put it <?plugin-form CreatePage ?> at some page, browse this page, 
 * enter the name of the page to create, then click the button.
 *
 * Usage:
 * <?plugin-form CreatePage ?>
 */
class WikiPlugin_CreatePage
extends WikiPlugin
{
    function getName () {
        return _("CreatePage");
    }

    function getDescription () {
        return _("Create a Wiki page.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array('s'          => false,
                     'targetpage' => '[pagename]',
                     //'method'     => 'POST'
                     );
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);

        if (empty($args['s'])) // or $request->getArg('action') != 'CreatePage')
            return HTML($request->redirect(WikiURL($args['targetpage'],'', 'absurl'), true));

        return HTML($request->redirect(WikiURL($args['s'], 'action=edit', 'absurl'), true));
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.1.2.2  2004/02/23 21:22:29  dfrankow
// Add a little doc
//
// Revision 1.1.2.1  2004/02/21 15:29:19  dfrankow
// Allow a CreatePage edit box, as GUI syntactic sugar
//
// Revision 1.1.1.1  2004/01/29 14:30:28  dfrankow
// Right out of the 1.3.7 package
//
// Revision 1.20  2003/11/02 20:42:35  carstenklapp
// Allow for easy page creation when search returns no matches.
// Based on cuthbertcat's patch, SF#655090 2002-12-17.
//
// Revision 1.19  2003/03/07 02:50:16  dairiki
// Fixes for new javascript redirect.
//
// Revision 1.18  2003/02/21 04:16:51  dairiki
// Don't NORETURN from redirect.
//
// Revision 1.17  2003/01/18 22:08:01  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
