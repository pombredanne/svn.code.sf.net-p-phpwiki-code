<?php // -*-php-*-
rcs_id('$Id: RedirectTo.php,v 1.8 2003-02-16 19:49:18 dairiki Exp $');
/*
 Copyright 2002 $ThePhpWikiProgrammingTeam

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
 * Redirect to another page or external uri. Kind of PageAlias.
 * Usage:
 * <?plugin-head RedirectTo href="http://www.internet-technology.de/fourwins_de.htm" ?>
 *      or  <?plugin-head RedirectTo page=AnotherPage ?>
 * at the VERY FIRST LINE in the content! Otherwise it will be ignored.
 *
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * BUGS/COMMENTS:
 *
 * This plugin could probably result in a lot of confusion, especially when
 * redirecting to external sites.  (Perhaps it can even be used for dastardly
 * purposes?)  Maybe it should be disabled by default.
 *
 * It would be nice, when redirecting to another wiki page, to (as
 * UseModWiki does) add a note to the top of the target page saying
 * something like "(Redirected from SomeRedirectingPage)".
 */
class WikiPlugin_RedirectTo
extends WikiPlugin
{
    function getName() {
        return _("RedirectTo");
    }

    function getDescription() {
        return _("Redirects to another url or page.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.8 $");
    }

    function getDefaultArguments() {
        return array( 'href' => '',
                      // 'type' => 'Temp' // or 'Permanent' // so far ignored
                      'page' => false,
                      );
    }

    function run($dbi, $argstr, $request) {
        $args = ($this->getArgs($argstr, $request));

        $href = $args['href'];
        $page = $args['page'];
        if ($href) {
            /*
             * Use quotes on the href argument value, like:
             *   <?plugin RedirectTo href="http://funky.com/a b \" c.htm" ?>
             *
             * Do we want some checking on href to avoid malicious
             * uses of the plugin? Like stripping tags or hexcode.
             */
            $url = preg_replace('/%\d\d/','',strip_tags($href));
            $thispage = $request->getPage();
            if (! $thispage->get('locked')) {
                return $this->disabled(fmt(_("%s is only allowed in locked pages."),
                                           _("Redirect to an external url")));
            }
        }
        else if ($page) {
            $url = WikiURL($page,
                           array('redirectfrom' => $request->getArg('pagename')),
                           'abs_path');
        }
        else {
            return $this->error(fmt("%s or %s parameter missing",
                                    "'href'", "'page'"));
        }

        if ($page == $request->getArg('pagename')) {
            return $this->error(fmt("Recursive redirect to self: '%s'", $url));
        }

        if ($request->getArg('action') != 'browse')
            return $this->disabled("(action != 'browse')");

        $redirectfrom = $request->getArg('redirectfrom');
        if ($redirectfrom !== false) {
            if ($redirectfrom)
                return $this->disabled(_("Double redirect not allowed."));
            else {
                // Got here by following the "Redirected from ..." link
                // on a browse page.
                return $this->disabled(_("Viewing redirecting page."));
            }
        }
        
        return $request->redirect($url);
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.7  2003/02/15 23:32:56  dairiki
// Usability improvements for the RedirectTo plugin.
//
// (Mostly this applies when using RedirectTo with a page=OtherPage
// argument to redirect to another page in the same wiki.)
//
// (Most of these ideas are stolen verbatim from UseModWiki.)
//
//  o Multiple redirects (PageOne -> PageTwo -> PageThree) not allowed.
//
//  o Redirects are not activated except when action == 'browse'.
//
//  o When redirections are disabled, (hopefully understandable)
//    diagnostics are displayed.
//
//  o A link to the redirecting page is displayed after the title
//    of the target page.  If the user follows this link, redirects
//    are disabled.  This allows for easy editing of the redirecting
//    page.
//
// FIXME: Stylesheets, and perhaps templates other than the defaults
// will probably have to be updated before this works well in other
// styles and/or themes.
//
// Revision 1.6  2003/01/18 22:01:44  carstenklapp
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
