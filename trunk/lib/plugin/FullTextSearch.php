<?php // -*-php-*-
rcs_id('$Id: FullTextSearch.php,v 1.6 2002-01-21 06:55:47 dairiki Exp $');

require_once('lib/TextSearchQuery.php');

/**
 */
class WikiPlugin_FullTextSearch
extends WikiPlugin
{
    function getName () {
        return _("FullTextSearch");
    }

    function getDescription () {
        return _("Full Text Search");
    }

    function getDefaultArguments() {
        return array('s'		=> false,
                     'noheader'		=> false);
    }

        
    function run($dbi, $argstr, $request) {

        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);
        
        $query = new TextSearchQuery($s);
        $pages = $dbi->fullSearch($query);
        $lines = array();
        $hilight_re = $query->getHighlightRegexp();
        $count = 0;
        $found = 0;

        $list = HTML::dl();
        global $Theme;
        
        while ($page = $pages->next()) {
            $count++;
            $name = $page->getName();
            $list->pushContent(HTML::dt($Theme->linkExistingWikiWord($name)));
            if ($hilight_re)
                $list->pushContent($this->showhits($page, $hilight_re));
        }
        if (!$list->getContent())
            $list->pushContent(HTML::dd(_("<no matches>")));

        if ($noheader)
            return $list;
        
        return array(HTML::p(fmt("Full text search results for '%s'", $s)),
                     $list);
    }

    function showhits($page, $hilight_re) {
        $FS = &$GLOBALS['FieldSeparator'];
        $current = $page->getCurrentRevision();
        $matches = preg_grep("/$hilight_re/i", $current->getContent());
        $html = array();
        foreach ($matches as $line) {
            $line = str_replace($FS, '', $line);
            $line = preg_replace("/$hilight_re/i", "${FS}OT\\0${FS}CT", $line);
            $line = htmlspecialchars($line);
            $line = str_replace("${FS}OT", '<strong>', $line);
            $line = str_replace("${FS}CT", '</strong>', $line);
            $html[] = HTML::dd(HTML::small(new RawXml($line)));
        }
        return $html;
    }
};
        
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
