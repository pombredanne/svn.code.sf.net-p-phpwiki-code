<?php // -*-php-*-
rcs_id('$Id: TitleSearch.php,v 1.5 2002-01-21 06:55:47 dairiki Exp $');

require_once('lib/TextSearchQuery.php');

/**
 */
class WikiPlugin_TitleSearch
extends WikiPlugin
{
    function getName () {
        return _("TitleSearch");
    }

    function getDescription () {
        return _("Title Search");
    }
    
    function getDefaultArguments() {
        return array('s'		=> false,
                     'auto_redirect'	=> false,
                     'noheader'		=> false);
    }

    function run($dbi, $argstr, $request) {
        global $Theme;
        
        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);
        
        $query = new TextSearchQuery($s);
        $pages = $dbi->titleSearch($query);
        $list = HTML::ul();
        while ($page = $pages->next()) {
            $name = $page->getName();
            $list->pushContent(HTML::li($Theme->linkExistingWikiWord($name)));
            $last_name = $name;
        }

        if ($auto_redirect && count($list->getContent()) == 1)
            $request->redirect(WikiURL($last_name));
        if (!$list->getContent())
            $list = HTML::blockquote(_("<no matches>"));
        if ($noheader)
            return $list;
        

        return array(HTML::p(fmt("Title search results for '%s'", $s)),
                     $list);
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
