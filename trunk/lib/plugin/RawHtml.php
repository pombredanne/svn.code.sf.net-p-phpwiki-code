<?php // -*-php-*-
rcs_id('$Id: RawHtml.php,v 1.6 2003-03-17 21:24:53 dairiki Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

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

// Define ENABLE_RAW_HTML to false (in index.php) to disable the RawHtml plugin.
//
if (!defined('ENABLE_RAW_HTML'))
    define('ENABLE_RAW_HTML', true);

/**
 * A plugin to provide for raw HTML within wiki pages.
 */
class WikiPlugin_RawHtml
extends WikiPlugin
{
    function getName () {
        return "RawHtml";
    }

    function getDescription () {
        return _("A plugin to provide for raw HTML within wiki pages.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.6 $");
    }

    function run($dbi, $argstr, &$request, $basepage) {
        if (!defined('ENABLE_RAW_HTML') || ! ENABLE_RAW_HTML) {
            return $this->disabled(_("Raw HTML is disabled in this wiki."));
        }
        if (!$basepage) {
            return $this->error("$basepage unset?");
        }
        
        $page = $request->getPage($basepage);

        if (! $page->get('locked')) {
            return $this->disabled(fmt(_("%s is only allowed in locked pages."),
                                       _("Raw HTML")));
        }

        return HTML::raw($argstr);
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.5  2003/01/18 22:01:43  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
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
