<?php // -*-php-*-
rcs_id('$Id: AllPages.php,v 1.28 2004-07-08 20:30:07 rurban Exp $');
/**
 Copyright 1999, 2000, 2001, 2002, 2004 $ThePhpWikiProgrammingTeam

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
 * DONE: support author=[] (current user) and owner, creator
 * to be able to have pages: 
 * AllPagesCreatedByMe, AllPagesOwnedByMe, AllPagesLastAuthoredByMe
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
                            "\$Revision: 1.28 $");
    }

    function getDefaultArguments() {
        return array_merge
            (
             PageList::supportedArgs(),
             array(
                   'noheader'      => false,
                   'include_empty' => false,
                   'info'          => '',
                   'debug'         => false
                   ));
    }
    
    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor,markup or all
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges
    // sortby: [+|-] pagename|mtime|hits

    // 2004-07-08 22:05:35 rurban: turned off &$request to prevent from strange bug below
    function run($dbi, $argstr, &$request, $basepage) {
        $args = $this->getArgs($argstr, $request);
        // very strange php reference bug: dbi gets destroyed at array_merge with defaults
        if (!is_object($dbi)) $dbi = $request->getDbh();
        if (!is_object($request->_dbi)) {
        	trigger_error("strange php reference bug destroyed request->_dbi", E_USER_WARNING);
        	return HTML();
        }
        //extract($args);
        // Todo: extend given _GET args
        if ($sorted = $request->getArg('sortby'))
            $args['sortby'] = $sorted;
        elseif (!empty($args['sortby']))
            $request->setArg('sortby',$args['sortby']);

        if ($args['debug'])
            $timer = new DebugTimer;
        if ( !empty($args['owner']) )
            $pages = PageList::allPagesByOwner($args['owner'],$args['include_empty'],$args['sortby'],$args['limit']);
        elseif ( !empty($args['author']) )
            $pages = PageList::allPagesByAuthor($args['author'],$args['include_empty'],$args['sortby'],$args['limit']);
        elseif ( !empty($args['creator']) ) {
            $pages = PageList::allPagesByCreator($args['creator'],$args['include_empty'],$args['sortby'],$args['limit']);
        } else {
            if (! $request->getArg('count'))  $args['count'] = $dbi->numPages(false,$args['exclude']);
            else $args['count'] = $request->getArg('count');
            $args['pages'] = false;
        }
        if (empty($args['count']) and is_array($args['pages']))
            $args['count'] = count($args['pages']);
        $pagelist = new PageList($args['info'], $args['exclude'], $args);
        //if (!$sortby) $sorted='pagename';
        if (!$args['noheader']) {
            if (!is_array($args['pages']))
                $pagelist->setCaption(_("All pages in this wiki (%d total):"));
            else
                $pagelist->setCaption(_("List of pages (%d total):"));
        }

        // deleted pages show up as version 0.
        if ($args['include_empty'])
            $pagelist->_addColumn('version');

        if (is_array($args['pages']))
            $pagelist->addPageList($args['pages']);
        else
            $pagelist->addPages( $dbi->getAllPages($args['include_empty'], $args['sortby'], $args['limit']) );
        if ($args['debug']) {
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
// Revision 1.27  2004/07/08 17:31:43  rurban
// improve numPages for file (fixing AllPagesTest)
//
// Revision 1.26  2004/06/21 16:22:32  rurban
// add DEFAULT_DUMP_DIR and HTML_DUMP_DIR constants, for easier cmdline dumps,
// fixed dumping buttons locally (images/buttons/),
// support pages arg for dumphtml,
// optional directory arg for dumpserial + dumphtml,
// fix a AllPages warning,
// show dump warnings/errors on DEBUG,
// don't warn just ignore on wikilens pagelist columns, if not loaded.
// RateIt pagelist column is called "rating", not "ratingwidget" (Dan?)
//
// Revision 1.25  2004/06/14 11:31:38  rurban
// renamed global $Theme to $WikiTheme (gforge nameclash)
// inherit PageList default options from PageList
//   default sortby=pagename
// use options in PageList_Selectable (limit, sortby, ...)
// added action revert, with button at action=diff
// added option regex to WikiAdminSearchReplace
//
// Revision 1.24  2004/06/13 16:02:12  rurban
// empty list of pages if user=[] and not authenticated.
//
// Revision 1.23  2004/06/13 15:51:37  rurban
// Support pagelist filter for current author,owner,creator by []
//
// Revision 1.22  2004/06/13 15:33:20  rurban
// new support for arguments owner, author, creator in most relevant
// PageList plugins. in WikiAdmin* via preSelectS()
//
// Revision 1.21  2004/04/20 00:06:53  rurban
// paging support
//
// Revision 1.20  2004/02/22 23:20:33  rurban
// fixed DumpHtmlToDir,
// enhanced sortby handling in PageList
//   new button_heading th style (enabled),
// added sortby and limit support to the db backends and plugins
//   for paging support (<<prev, next>> links on long lists)
//
// Revision 1.19  2004/02/17 12:11:36  rurban
// added missing 4th basepage arg at plugin->run() to almost all plugins. This caused no harm so far, because it was silently dropped on normal usage. However on plugin internal ->run invocations it failed. (InterWikiSearch, IncludeSiteMap, ...)
//
// Revision 1.18  2004/01/25 07:58:30  rurban
// PageList sortby support in PearDB and ADODB backends
//
// Revision 1.17  2003/02/27 20:10:30  dairiki
// Disable profiling output when DEBUG is defined but false.
//
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
