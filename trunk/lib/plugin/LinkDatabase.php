<?php // -*-php-*-
rcs_id('$Id: LinkDatabase.php,v 1.1 2004-11-30 21:02:16 rurban Exp $');
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

require_once('lib/PageList.php');
require_once('lib/WikiPluginCached.php');

/**
 * To be used by WikiBrowser at http://touchgraph.sourceforge.net/
 * (only via a static text file by ?format=text) or the 
 * Hypergraph applet without intermediate text file
 * http://hypergraph.sourceforge.net/ (not yet tested)
 */
class WikiPlugin_LinkDatabase
extends WikiPluginCached
{
    function getName () {
        return _("LinkDatabase");
    }
    function getPluginType() {
        return PLUGIN_CACHED_HTML;
    }
    function getDescription () {
        return _("List all pages with all links in text-format for some Java Visualization tools");
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }
    function getExpire($dbi, $argarray, $request) {
        return '+900'; // 15 minutes
    }

    function getDefaultArguments() {
        return array_merge
            (
             PageList::supportedArgs(),
             array(
                   'format'        => 'text', // or 'html'
                   'noheader'      => false,
                   'include_empty' => false,
                   'exclude_from'  => false,
                   'info'          => '',
                   ));
    }

    function getHtml($dbi, $argarray, $request, $basepage) {
        $this->run($dbi, WikiPluginCached::glueArgs($argarray), $request, $basepage);
    }
    
    function run($dbi, $argstr, $request, $basepage) {
        $args = $this->getArgs($argstr, $request);
        $caption = _("All pages with all links in this wiki (%d total):");
        
        if ( !empty($args['owner']) ) {
            $pages = PageList::allPagesByOwner($args['owner'],$args['include_empty'],$args['sortby'],$args['limit']);
            if ($args['owner'])
                $caption = fmt("List of pages owned by [%s] (%d total):", 
                               WikiLink($args['owner'], 'if_known'),
                               count($pages));
        } elseif ( !empty($args['author']) ) {
            $pages = PageList::allPagesByAuthor($args['author'],$args['include_empty'],$args['sortby'],$args['limit']);
            if ($args['author'])
                $caption = fmt("List of pages last edited by [%s] (%d total):", 
                               WikiLink($args['author'], 'if_known'), 
                               count($pages));
        } elseif ( !empty($args['creator']) ) {
            $pages = PageList::allPagesByCreator($args['creator'],$args['include_empty'],$args['sortby'],$args['limit']);
            if ($args['creator'])
                $caption = fmt("List of pages created by [%s] (%d total):", 
                               WikiLink($args['creator'], 'if_known'), 
                               count($pages));
        } else {
            if (! $request->getArg('count'))  $args['count'] = $dbi->numPages($args['include_empty'], $args['exclude_from']);
            else $args['count'] = $request->getArg('count');
            $pages = $dbi->getAllPages($args['include_empty'], $args['sortby'], $args['limit'], $args['exclude_from']);
        }
        if ($args['format'] == 'html') {
            $args['types']['links'] = 
                new _PageList_Column_LinkDatabase_links('links', _("Links"), 'left');
            $pagelist = new PageList($args['info'], $args['exclude_from'], $args);
            if (!$args['noheader']) $pagelist->setCaption($caption);
            return $pagelist;
        } elseif ($args['format'] == 'text') {
            $request->discardOutput();
            $request->buffer_output(COMPRESS_OUTPUT);
            if (!headers_sent())
                header("Content-Type: text/plain");
            $request->checkValidators();
            while ($page = $pages->next()) {
                echo $page->getName();
                $links = $page->getPageLinks(false, $args['sortby'], $args['limit'], $args['exclude']);
                while ($link = $links->next()) {
                    echo " ", $link->getName();
                }
                echo "\n";
            }
            flush();
            $request->finish();
        } else {
            return $this->error(fmt("Unsupported format argument %s", $args['format']));
        }
    }
};

class _PageList_Column_LinkDatabase_links extends _PageList_Column {
    function _getValue($page, &$revision_handle) {
        $out = HTML();
        $links = $page->getPageLinks();
        while ($link = $links->next()) {
            $out->pushContent(" ", WikiLink($link));
        }
        return $out;
    }
}

// $Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>