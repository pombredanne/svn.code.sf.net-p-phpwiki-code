<?php // -*-php-*-
rcs_id('$Id: RawHtml.php,v 1.5 2003-01-18 22:01:43 carstenklapp Exp $');
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

// Define ENABLE_RAW_HTML to true to enable the RawHtml plugin.
//
// IMPORTANT!!!: This plugin is currently insecure, as it's method of
// determining whether it was invoked from a locked page is flawed.
// (See the FIXME: comment below.)
//
// ENABLE AT YOUR OWN RISK!!!
//
if (!defined('ENABLE_RAW_HTML'))
    define('ENABLE_RAW_HTML', false);

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
                            "\$Revision: 1.5 $");
    }

    function run($dbi, $argstr, $request) {
        if (!defined('ENABLE_RAW_HTML') || ! ENABLE_RAW_HTML) {
            return $this->error(_("Raw HTML is disabled in this wiki."));
        }

        // FIXME: this test for lockedness is badly flawed.  It checks
        // the requested pages locked state, not the page the plugin
        // invocation came from.  (These could be different in the
        // case of ActionPages, or where the IncludePage plugin is
        // used.)
        $page = $request->getPage();
        if (! $page->get('locked')) {
            return $this->error(fmt(_("%s is only allowed in locked pages."),
                                    _("Raw HTML")));
        }

        return HTML::raw($argstr);
    }
}

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
