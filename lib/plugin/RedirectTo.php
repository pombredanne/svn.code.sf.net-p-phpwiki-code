<?php // -*-php-*-
rcs_id('$Id: RedirectTo.php,v 1.1 2002-08-27 21:51:31 rurban Exp $');
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
 * RedirectTo:
 * Usage:   <?plugin-head RedirectTo href=http://www.internet-technology.de/fourwins_de.htm ?>
 *      or  <?plugin-head RedirectTo page=AnotherPage ?>
 *          at the VERY FIRST LINE in the content! Otherwise it will be ignored.
 * Author:  Reini Urban <rurban@x-ray.at>
 *
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

    function getDefaultArguments() {
        return array( 'href' => '',
                      // 'type' => 'Temp' // or 'Permanent' // s far ignored
                      'page' => false,
                      'args' => false,  // pass more args to the page. TestMe!
                      );
    }

    function run($dbi, $argstr, $request) {
        $args = ($this->getArgs($argstr, $request));
        $href = $args['href'];
        $page = $args['page'];
        if (!$href and !$page)
            return $this->error(sprintf(_("href=%s parameter missing"), 'href'));
        // FIXME: unmunged url hack
        if ($href)
            $url = preg_replace('/href=(.*)\Z/','$1',$argstr);
        else {
            $url = $request->getURLtoSelf(array_merge(array('pagename' => $page), $args['args']));
        }
        if ($page == $request->getArg($pagename)) {
            return $this->error(sprintf(_("Recursive redirect to self %s"), $url));
        }
        return $request->redirect($url);
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
