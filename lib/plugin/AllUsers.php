<?php // -*-php-*-
rcs_id('$Id: AllUsers.php,v 1.5 2003-02-21 04:08:26 dairiki Exp $');
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

require_once('lib/PageList.php');

/**
 * Based on AllPages.
 *
 * We currently don't get externally authenticated users which didn't
 * store their Preferences.
 */
class WikiPlugin_AllUsers
extends WikiPlugin
{
    function getName () {
        return _("AllUsers");
    }

    function getDescription() {
        return _("With external authentication all users which stored their Preferences. Without external authentication all once signed-in users (from version 1.3.4 on).");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.5 $");
    }

    function getDefaultArguments() {
        return array('noheader'      => false,
                     'include_empty' => true,
                     'exclude'       => '',
                     'info'          => '',
                     'sortby'        => '',   // +mtime,-pagename
                     'debug'         => false
                     );
    }
    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor,markup or all
    // exclude arg allows multiple pagenames exclude=WikiAdmin,.SecretUser
    //
    // include_empty shows also users which stored their preferences,
    // but never saved their homepage
    //
    // sortby: [+|-] pagename|mtime|hits

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        // Todo: extend given _GET args
        if ($sortby)
            $request->setArg('sortby',$sortby);

        $pagelist = new PageList($info, $exclude);
        if (!$noheader)
            $pagelist->setCaption(_("Authenticated users on this wiki (%d total):"));

        // deleted pages show up as version 0.
        if ($include_empty)
            $pagelist->_addColumn('version');

        if (defined('DEBUG'))
            $debug = true;

        $timer = new DebugTimer;

        $page_iter = $dbi->getAllPages($include_empty);
        while ($page = $page_iter->next()) {
            if ($page->isUserPage($include_empty))
                $pagelist->addPage($page);
        }

        if ($debug) {
            return HTML($pagelist,
                        HTML::p(fmt("Elapsed time: %s s", $timer->getStats())));
        } else {
            return $pagelist;
        }
    }

    function getmicrotime(){
        list($usec, $sec) = explode(" ", microtime());
        return (float)$usec + (float)$sec;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.4  2003/01/18 21:19:25  carstenklapp
// Code cleanup:
// Reformatting; added copyleft, getVersion, getDescription
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
