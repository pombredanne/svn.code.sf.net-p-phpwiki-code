<?php // -*-php-*-
rcs_id('$Id: MostPopular.php,v 1.9 2002-01-21 06:55:47 dairiki Exp $');
/**
 */
class WikiPlugin_MostPopular
extends WikiPlugin
{
    function getName () {
        return _("MostPopular");
    }

    function getDescription () {
        return _("List the most popular pages");
    }

    function getDefaultArguments() {
        return array('limit'	=> 20,
                     'noheader'	=> 0);
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pages = $dbi->mostPopular($limit);
        
        $table = HTML::table(array('cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border' => 0),
                             $this->_tr(HTML::u(_("Hits")),
                                        HTML::u(_("Page Name"))));

        while ($page = $pages->next()) {
            $hits = $page->get('hits');
            if ($hits == 0)
                break;
            $table->pushContent($this->_tr($hits,
                                           _LinkWikiWord($page->getName())));
        }
        $pages->free();
        $table = HTML::blockquote($table);

        if ($noheader)
            return $table;
        
        if ($limit > 0)
            $head = fmt("The %s most popular pages of this wiki:", $limit);
        else
            $head = _("Visited pages on this wiki, ordered by popularity:");
        return array(HTML::p($head), $table);
    }

    function _tr ($col1, $col2) {
        return HTML::tr(HTML::td(array('align' => 'right'),
                                 $col1, new RawXml('&nbsp;&nbsp;')),
                        HTML::td(new RawXml('&nbsp;&nbsp;'), $col2));
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
