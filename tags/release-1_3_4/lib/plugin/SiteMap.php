<?php // -*-php-*-
rcs_id('$Id: SiteMap.php,v 1.5 2002-11-04 19:17:16 carstenklapp Exp $');
/**
http://sourceforge.net/tracker/?func=detail&aid=537380&group_id=6121&atid=306121

Submitted By: Cuthbert Cat (cuthbertcat)

This is a quick mod of BackLinks to do the job recursively. If your
site is categorized correctly, and all the categories are listed in
CategoryCategory, then a RecBackLinks there will produce a contents
page for the entire site.

The list is as deep as the recursion level.

direction: Get BackLinks or forward links (links listed on the page)

firstreversed: If true, get BackLinks for the first page and forward
links for the rest. Only applicable when direction = 'forward'.

excludeunknown: If true (default) then exclude any mentioned pages
which don't exist yet.  Only applicable when direction = 'forward'.
*/

require_once('lib/PageList.php');

class WikiPlugin_SiteMap
extends WikiPlugin
{
    function getName () {
        return _("SiteMap");
    }

    function getDescription () {
        return sprintf(_("SiteMap: Recursively get BackLinks or links for %s"),
                       '[pagename]');
    }

    function getDefaultArguments() {
        return array('exclude'		=> '',
                     'include_self'	=> 0,
                     'noheader'         => 0,
                     'page'		=> '[pagename]',
                     'description'	=> $this->getDescription(),
                     'reclimit'         => 4,
                     'info'		=> false,
                     'direction'	=> 'back',
                     'firstreversed'	=> false,
                     'excludeunknown'	=> true
                     );
    }
    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor
    //
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
                && !in_array($linkpagename, $this->ExcludedPages)) {
                $pagearr[$level . " [$linkpagename]"] = $link;
                $pagearr = $this->recursivelyGetBackLinks($link, $pagearr, 
                                                      $level . '*', $reclimit);
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
        $reversed = (($this->firstreversed) && ($startpagename == $this->initialpage));
        //trigger_error("DEBUG: \$reversed = $reversed");
        $pagelinks = $startpage->getLinks($reversed);
        while ($link = $pagelinks->next()) {
            $linkpagename = $link->getName();
            if (($linkpagename != $startpagename)
                && !in_array($linkpagename, $this->ExcludedPages)) {
                if (!$this->excludeunknown || $this->dbi->isWikiPage($linkpagename)) {
                    $pagearr[$level . " [$linkpagename]"] = $link;
                    $pagearr = $this->recursivelyGetLinks($link, $pagearr, 
                                                      $level . '*', $reclimit);
                }
            }
        }
        return $pagearr;
    }


    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request, false);
        extract($args);
        if (!$page)
            return '';
        $out = '';
        $exclude = $exclude ? explode(",", $exclude) : array();
        if (!$include_self)
            $exclude[] = $page;
        $this->ExcludedPages = $exclude;
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
        if ($direction == 'back')
            $pagearr = $this->recursivelyGetBackLinks($p, $pagearr, "*", $limit);
        else {
            $this->dbi = $dbi;
            $this->initialpage = $page;
            $this->firstreversed = $firstreversed;
            $this->excludeunknown = $excludeunknown;
            $pagearr = $this->recursivelyGetLinks($p, $pagearr, "*", $limit);
        }
        
        reset($pagearr);
        while (list($key, $link) = each($pagearr)) {
            $out .= $key . "\n";
        }
        return TransformText($out);
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
