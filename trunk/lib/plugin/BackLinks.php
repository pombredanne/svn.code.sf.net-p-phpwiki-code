<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.1 2001-09-18 19:19:05 dairiki Exp $');
/**
 */
class WikiPlugin_BackLinks
extends WikiPlugin
{
    var $name = 'BackLinks';
  
    function getDefaultArguments() {
        // FIXME: how to exclude multiple pages?
        return array('exclude'		=> '',
                     'include_self'	=> 0,
                     'noheader'		=> 0,
                     'page'		=> false);
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (!$page)
            return '';
              
        $p = $dbi->getPage($page);
        $backlinks = $p->getLinks();
        $lines = array();
        while ($backlink = $backlinks->next()) {
            $name = $backlink->getName();
            if ($exclude && $name == $exclude)
                continue;
            if (!$include_self && $name == $page)
                continue;
            $lines[] = Element('li', LinkWikiWord($name));
        }

        $html = '';
        if (!$noheader) {
            $fs = $lines
                 ? gettext("These pages link to %s:")
                 : gettext("No pages link to %s.");
            $header = sprintf(htmlspecialchars($fs),
                              LinkExistingWikiWord($page));
            $html = Element('p', $header) . "\n";
        }
        
        return $html . Element('ul', join("\n", $lines));
    }
};
        
?>
