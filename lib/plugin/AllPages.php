<?php // -*-php-*-
rcs_id('$Id: AllPages.php,v 1.17 2003-02-27 20:10:30 dairiki Exp $');
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

require_once('lib/PageList.php');

/**
 */
class WikiPlugin_AllPages
extends WikiPlugin
{
    function getName () {
        return _("AllPages");
    }

    function getDescription () {
        return _("List all pages in this wiki.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.17 $");
    }

    function getDefaultArguments() {
        return array('noheader'      => false,
                     'include_empty' => false,
                     'exclude'       => '',
                     'info'          => '',
                     'sortby'        => '',   // +mtime,-pagename
                     'debug'         => false
                     );
    }
    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor,markup or all
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges
    // sortby: [+|-] pagename|mtime|hits

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        // Todo: extend given _GET args
        if ($sortby)
            $request->setArg('sortby',$sortby);

        $pagelist = new PageList($info, $exclude);
        if (!$noheader)
            $pagelist->setCaption(_("Pages in this wiki (%d total):"));

        // deleted pages show up as version 0.
        if ($include_empty)
            $pagelist->_addColumn('version');

        $timer = new DebugTimer;

        $pagelist->addPages( $dbi->getAllPages($include_empty) );

        if (defined('DEBUG') and DEBUG) {
            return HTML($pagelist,
                        HTML::p(fmt("Elapsed time: %s s", $timer->getStats())));
        } else {
            return $pagelist;
        }
    }

    function getmicrotime(){
        list($usec, $sec) = explode(" ",microtime());
        return (float)$usec + (float)$sec;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.16  2003/02/21 04:08:26  dairiki
// New class DebugTimer in prepend.php to help report timing.
//
// Revision 1.15  2003/01/18 21:19:25  carstenklapp
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
