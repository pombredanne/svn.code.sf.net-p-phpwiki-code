<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.4 2001-12-16 18:33:25 dairiki Exp $');
/**
 */
class WikiPlugin_BackLinks
extends WikiPlugin
{
    function getName () {
        return _("BackLinks");
    }

    function getDescription () {
        return _("Get BackLinks for [pagename]");
    }
  
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
            $fs = $lines ? _("These pages link to %s:") : _("No pages link to %s.");
            $header = sprintf(htmlspecialchars($fs),
                              LinkExistingWikiWord($page));
            $html = Element('p', $header) . "\n";
        }
        
        return $html . Element('ul', join("\n", $lines));
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
