<?php // -*-php-*-
rcs_id('$Id: LikePages.php,v 1.3 2001-12-16 18:33:25 dairiki Exp $');

require_once('lib/TextSearchQuery.php');

/**
 */
class WikiPlugin_LikePages
extends WikiPlugin
{
    function getName() {
        return _("LikePages");
    }
    
    function getDescription() {
        return _("List LikePages for [pagename]");
    }
    
    function getDefaultArguments() {
        // FIXME: how to exclude multiple pages?
        return array('page'		=> false,
                     'prefix'		=> false,
                     'suffix'		=> false,
                     'exclude'		=> false,
                     'noheader'		=> false
                     );
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page) && empty($prefix) && empty($suffix))
            return '';

        $html = '';
        if ($prefix) {
            $suffix = false;
            if (!$noheader)
                $html .= QElement('p', sprintf(_("Page names with prefix '%s'"),
                                               $prefix));
        }
        elseif ($suffix) {
            if (!$noheader)
                $html .= QElement('p', sprintf(_("Page names with suffix '%s'"),
                                               $suffix));
        }
        elseif ($page) {
            $words = explode(" ", split_pagename($page));
            assert($words);
            $prefix = $words[0];
            list($suffix) = array_reverse($words);
            $exclude = $page;
            
            if (!$noheader) {
                $fs = _("These pages share an initial or final title word with '%s'");
                $html .= Element('p', sprintf(htmlspecialchars($fs), LinkWikiWord($page)));
            }
        }

        // Search for pages containing either the suffix or the prefix.
        $search = array();
        if (!empty($prefix)) {
            $search[] = $this->_quote($prefix);
            $match[] = '^' . preg_quote($prefix, '/');
        }
        if (!empty($suffix)) {
            $search[] = $this->_quote($suffix);
            $match[] = preg_quote($suffix, '/') . '$';
        }
        $query = new TextSearchQuery(join(' OR ', $search));
        $match_re = '/' . join('|', $match) . '/';

        $pages = $dbi->titleSearch($query);
        $lines = array();
        while ($page = $pages->next()) {
            $name = $page->getName();
            if (!preg_match($match_re, $name))
                continue;
            if (!empty($exclude) && $name == $exclude)
                continue;
            $lines[] = Element('li', LinkWikiWord($name));
        }
        if ($lines)
            $html .= Element('ul', join("\n", $lines));
        else
            $html .= QElement('blockquote', gettext("<none>"));
        
        return $html;
    }

    function _quote($str) {
        return "'" . str_replace("'", "''", $str) . "'";
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
