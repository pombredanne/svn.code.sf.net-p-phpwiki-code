<?php // -*-php-*-
rcs_id('$Id: SiteMap.php,v 1.9 2004-02-12 13:05:50 rurban Exp $');
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

/**
 * http://sourceforge.net/tracker/?func=detail&aid=537380&group_id=6121&atid=306121
 *
 * Submitted By: Cuthbert Cat (cuthbertcat)
 *
 * This is a quick mod of BackLinks to do the job recursively. If your
 * site is categorized correctly, and all the categories are listed in
 * CategoryCategory, then a RecBackLinks there will produce a contents
 * page for the entire site.
 *
 * The list is as deep as the recursion level.
 *
 * direction: Get BackLinks or forward links (links listed on the page)
 *
 * firstreversed: If true, get BackLinks for the first page and forward
 * links for the rest. Only applicable when direction = 'forward'.
 *
 * excludeunknown: If true (default) then exclude any mentioned pages
 * which don't exist yet.  Only applicable when direction = 'forward'.
 */
require_once('lib/PageList.php');

class WikiPlugin_SiteMap
extends WikiPlugin
{
    function getName () {
        return _("SiteMap");
    }

    function getDescription () {
        return sprintf(_("Recursively get BackLinks or links for %s"),
                       '[pagename]');
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.9 $");
    }

    function getDefaultArguments() {
        return array('exclude'        => '',
                     'include_self'   => 0,
                     'noheader'       => 0,
                     'page'           => '[pagename]',
                     'description'    => $this->getDescription(),
                     'reclimit'       => 4,
                     'info'           => false,
                     'direction'      => 'back',
                     'firstreversed'  => false,
                     'excludeunknown' => true,
                     'includepages'   => ''    // this doesn't yet work as expected
                     );
    }
    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames
    // exclude=HomePage,RecentChanges

    function recursivelyGetBackLinks($startpage, $pagearr, $level = '*',
                                     $reclimit = '***') {
        static $VisitedPages = array();

        $startpagename = $startpage->getName();
        //trigger_error("DEBUG: recursivelyGetBackLinks( $startpagename , $level )");
        if ($level == $reclimit)
            return $pagearr;
        if (in_array($startpagename, $VisitedPages))
            return $pagearr;
        array_push($VisitedPages, $startpagename);
        $pagelinks = $startpage->getLinks();
        while ($link = $pagelinks->next()) {
            $linkpagename = $link->getName();
            if (($linkpagename != $startpagename)
                && !preg_match("/$this->ExcludedPages/", $linkpagename)) {
                $pagearr[$level . " [$linkpagename]"] = $link;
                $pagearr = $this->recursivelyGetBackLinks($link, $pagearr,
                                                          $level . '*',
                                                          $reclimit);
            }
        }
        return $pagearr;
    }

    function recursivelyGetLinks($startpage, $pagearr, $level = '*',
                                 $reclimit = '***') {
        static $VisitedPages = array();

        $startpagename = $startpage->getName();
        //trigger_error("DEBUG: recursivelyGetLinks( $startpagename , $level )");
        if ($level == $reclimit)
            return $pagearr;
        if (in_array($startpagename, $VisitedPages))
            return $pagearr;
        array_push($VisitedPages, $startpagename);
        $reversed = (($this->firstreversed)
                     && ($startpagename == $this->initialpage));
        //trigger_error("DEBUG: \$reversed = $reversed");
        $pagelinks = $startpage->getLinks($reversed);
        while ($link = $pagelinks->next()) {
            $linkpagename = $link->getName();
            if (($linkpagename != $startpagename)
                && !preg_match("/$this->ExcludedPages/", $linkpagename)) {
                if (!$this->excludeunknown
                    || $this->dbi->isWikiPage($linkpagename)) {
                    $pagearr[$level . " [$linkpagename]"] = $link;
                    $pagearr = $this->recursivelyGetLinks($link, $pagearr,
                                                          $level . '*',
                                                          $reclimit);
                }
            }
        }
        return $pagearr;
    }


    function run($dbi, $argstr, $request) {
        include_once('lib/BlockParser.php');
        
        $args = $this->getArgs($argstr, $request, false);
        extract($args);
        if (!$page)
            return '';
        $out = '';
        $exclude = $exclude ? explode(",", $exclude) : array();
        if (!$include_self)
            $exclude[] = $page;
        $this->ExcludedPages = "^(?:" . join("|", $exclude) . ")";
        $this->_default_limit = str_pad('', 3, '*');
        if (is_numeric($reclimit)) {
            if ($reclimit < 0)
                $reclimit = 0;
            if ($reclimit > 10)
                $reclimit = 10;
            $limit = str_pad('', $reclimit + 2, '*');
        } else {
            $limit = '***';
        }
        if (! $noheader)
            $out .= $description ." ". sprintf(_("(max. recursion level: %d)"),
                                               $reclimit) . ":\n\n";
        $pagelist = new PageList($info, $exclude);
        $p = $dbi->getPage($page);

        $pagearr = array();
        if ($direction == 'back') {
            $pagearr = $this->recursivelyGetBackLinks($p, $pagearr, "*",
                                                      $limit);
        }
        else {
            $this->dbi = $dbi;
            $this->initialpage = $page;
            $this->firstreversed = $firstreversed;
            $this->excludeunknown = $excludeunknown;
            $pagearr = $this->recursivelyGetLinks($p, $pagearr, "*", $limit);
        }

        reset($pagearr);
        while (list($key, $link) = each($pagearr)) {
            if (!empty($includepages)) { // this doesn't work as expected
                $a = substr_count($key, '*');
                $indenter = str_pad($nothing, $a);
                $out .= $indenter  . '<?plugin IncludePage page=' . $link->getName();
                if (is_string($includepages))
                    $out .= " " . $includepages; // args
                $out .= " ?>" . "\n";
            }
            else {
                $out .= $key . "\n";
            }
        }
        return TransformText($out, 1.0, $page /*cached or not cached?...*/); 
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.8  2004/01/24 23:24:07  rurban
// Patch by Alec Thomas, allows Perl regular expressions in SiteMap exclude lists.
//   exclude=WikiWikiWeb,(?:Category|Topic).*
// It is backwards compatible unless old exclude lists, and therefore Wiki
// page names, contain regular expression characters.
//
// Revision 1.7  2003/02/21 04:12:06  dairiki
// Minor fixes for new cached markup.
//
// Revision 1.6  2003/01/18 22:08:01  carstenklapp
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
