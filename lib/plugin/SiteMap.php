<?php // -*-php-*-
rcs_id('$Id: SiteMap.php,v 1.4 2002-11-04 06:48:46 carstenklapp Exp $');
/**
http://sourceforge.net/tracker/?func=detail&aid=537380&group_id=6121&atid=306121

Submitted By: Cuthbert Cat (cuthbertcat)

This is a quick mod of BackLinks to do the job recursively. If your
site is categorized correctly, and all the categories are listed in
CategoryCategory, then a RecBackLinks there will produce a contents
page for the entire site.

The list is as deep as the recursion level.
*/

require_once('lib/PageList.php');

class WikiPlugin_SiteMap
extends WikiPlugin
{
    function getName () {
        return _("SiteMap");
    }

    function getDescription () {
        return sprintf(_("SiteMap: Recursively get BackLinks for %s"),
                       '[pagename]');
    }

    function getDefaultArguments() {
        return array('exclude'		=> '',
                     'include_self'	=> 0,
                     'noheader'         => 0,
                     'page'		=> '[pagename]',
                     'description'	=> $this->getDescription(),
                     'reclimit'         => 4,
                     'info'		=> false
                     );
    }
    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor
    //
    // exclude arg allows multiple pagenames
    // exclude=HomePage,RecentChanges

    function recursivelyGetLinks($startpage, $pagearr, $level = '*', 
                                 $reclimit = '***') {
        static $VisitedPages = array();

        $startpagename = $startpage->getName();
        // echo "<br> recursivelyGetLinks( " . $startpagename . " , "
        // . $level . " )\n";
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
                $pagearr = $this->recursivelyGetLinks($link, $pagearr, 
                                                      $level . '*', $reclimit);
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
        $pagearr = $this->recursivelyGetLinks($p, $pagearr, "*", $limit);

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
