<?php // -*-php-*-
rcs_id('$Id: CreateToc.php,v 1.2 2004-03-02 16:43:04 rurban Exp $');
/*
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
 * CreateToc:  Automatically link headers at the top
 *
 * Usage:   <?plugin CreateToc headers=!!!,!! jshide||=1 align=left noheaders=0 ?>
 * @author:  Reini Urban
 */

class WikiPlugin_CreateToc
extends WikiPlugin
{
    function getName() {
        return _("CreateToc");
    }

    function getDescription() {
        return _("Automatically link headers at the top");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.2 $");
    }

    function getDefaultArguments() {
        return array( 'pagename'  => '[pagename]', // not sure yet. TOC of another page here?
                      // or headers=1,2,3 is also possible.
                      'headers'   => "!!!,!!,!",   // "!!!" => h1, "!!" => h2, "!" => h3
                      'noheader'  => 0,            // omit <h1>Table of Contents</h1>
                      'align'     => 'left',
                      // not yet
                      'jshide'    => 0,            // collapsed TOC as DHTML button 
                      );
    }

    // Feature request: proper nesting
    function extractHeaders (&$content, $level=2) {
        $headers = array();
        if ($level < 1 or $level > 6) $level = 1;
        for ($i=0; $i<count($content); $i++) {
            if (preg_match('/^\s*(!{'.$level.',3})([^!].+)$/',$content[$i],$match)) {
            	if (!strstr($content[$i],'#[')) {
            	    $s = trim($match[2]);
                    $headers[] = $s;
                    $content[$i] = $match[1]." #[|$s][$s|#$s]";
            	}
            }
        }
        return $headers;
    }
                
    function run($dbi, $argstr, $request, $basepage) {
        extract($this->getArgs($argstr, $request));
        if ($pagename) {
            // Expand relative page names.
            $page = new WikiPageName($pagename, $basepage);
            $pagename = $page->name;
        }
        if (!$pagename) {
            return $this->error(_("no page specified"));
        }
        $page = $dbi->getPage($pagename);
        $current = $page->getCurrentRevision();
        $content = $current->getContent();
        $html = HTML::div(array('class' => 'toc','align' => $align));
        if (!$noheader)
            $html->pushContent(HTML::h1(_("Table Of Contents")));
        $list = HTML::ul(array('class' => 'toc'));
        //Todo: replace !!! with level 1, ...
        //Todo: proper indent of heading
        if ($headers = $this->extractHeaders(&$content, 1)) {
            foreach ($headers as $h) {
                $link = new WikiPageName($pagename,$page,$h);
                $list->pushContent(HTML::li(WikiLink($link,'known',$h)));
            }
        }
        //Fixme: Put new contents back to pagecache. 
        // Will require yet another & arg to $plugin->run()
        $html->pushContent($list);
        return $html;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.1  2004/03/01 18:10:28  rurban
// first version, without links, anchors and jscript folding
//
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
