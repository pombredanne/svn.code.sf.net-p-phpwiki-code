<?php // -*-php-*-
rcs_id('$Id: FullTextSearch.php,v 1.2 2001-12-15 10:54:45 carstenklapp Exp $');

require_once('lib/TextSearchQuery.php');

/**
 */
class WikiPlugin_FullTextSearch
extends WikiPlugin
{
    var $name = 'FullTextSearch';
    var $description = 'FullTextSearch';

    function getDefaultArguments() {
        // FIXME: how to exclude multiple pages?
        return array('s'		=> false,
                     'noheader'		=> false);
    }

    function getDefaultFormArguments() {
        $defaults = parent::getDefaultFormArguments();
        $defaults['description'] = gettext('Full Text Search');
        return $defaults;
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
                              sprintf(gettext("Full text search results for '%s'"), $s));
        if (!$lines)
            $lines[] = QElement('dd', gettext("<no matches>"));

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

    function make_form($args) {
        // FIXME: need more thought about this whole interface.
        $args['search'] = '()';
        return MakeWikiForm($GLOBALS['pagename'], $args, 'wikiaction','Full Text Search');
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
