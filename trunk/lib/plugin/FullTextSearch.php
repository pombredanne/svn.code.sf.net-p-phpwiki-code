<?php // -*-php-*-
rcs_id('$Id: FullTextSearch.php,v 1.4 2001-12-16 18:33:25 dairiki Exp $');

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
        // FIXME: how to exclude multiple pages?
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
        
        while ($page = $pages->next()) {
            $count++;
            $name = $page->getName();
            $lines[] = Element('dt', LinkExistingWikiWord($name));
            if ($hilight_re)
                $lines[] = $this->showhits($page, $hilight_re);
        }

        $html = '';
        if (!$noheader)
            $html .= QElement('p',
                              sprintf(_("Full text search results for '%s'"), $s));
        if (!$lines)
            $lines[] = QElement('dd', _("<no matches>"));

        $html .= Element('dl', join("\n", $lines));
        return $html;
    }

    function showhits($page, $hilight_re) {
        $FS = &$GLOBALS['FieldSeparator'];
        $current = $page->getCurrentRevision();
        $matches = preg_grep("/$hilight_re/i", $current->getContent());
        $html = '';
        foreach ($matches as $line) {
            $line = str_replace($FS, '', $line);
            $line = preg_replace("/$hilight_re/i", "${FS}OT\\0${FS}CT", $line);
            $line = htmlspecialchars($line);
            $line = str_replace("${FS}OT", '<b>', $line);
            $line = str_replace("${FS}CT", '</b>', $line);
            $html .= Element('dd', Element('small', $line)) . "\n";
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
