<?php // -*-php-*-
rcs_id('$Id: TitleSearch.php,v 1.3 2001-12-15 10:55:16 carstenklapp Exp $');

require_once('lib/TextSearchQuery.php');

/**
 */
class WikiPlugin_TitleSearch
extends WikiPlugin
{
    var $name = 'TitleSearch';
    var $description = 'TitleSearch';
    
    function getDefaultArguments() {
        return array('s'		=> false,
                     'auto_redirect'	=> false,
                     'noheader'		=> false);
    }

    function getDefaultFormArguments() {
        $defaults = parent::getDefaultFormArguments();
        $defaults['description'] = 'Title Search';
        return $defaults;
    }
    
    function run($dbi, $argstr, $request) {

        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);
        
        $query = new TextSearchQuery($s);
        $pages = $dbi->titleSearch($query);
        $lines = array();
        while ($page = $pages->next()) {
            $name = $page->getName();
            $lines[] = Element('li', LinkExistingWikiWord($name));
            $last_name = $name;
        }

        if ($auto_redirect && count($lines) == 1)
            $request->redirect(WikiURL($last_name));

        $html = '';
        if (!$noheader)
            $html .= QElement('p',
                              sprintf(gettext("Title search results for '%s'"), $s));
        if ($lines)
            $html .= Element('ul', join("\n", $lines));
        else
            $html .= Element('dl', QElement('dd', gettext("<no matches>")));
        
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
