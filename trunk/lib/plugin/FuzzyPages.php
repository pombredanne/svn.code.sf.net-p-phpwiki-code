<?php // -*-php-*-
rcs_id('$Id: FuzzyPages.php,v 1.1 2002-02-01 22:13:02 carstenklapp Exp $');

//require_once('lib/TextSearchQuery.php');
//require_once('lib/PageList.php');

/**
 * FuzzyChanges is an experimental plugin which looks for similar page titles.
 * 
 * Pages are considered similar if they sound similar - metaphone() (english only)
 * or if the name is written similar - levenshtein()
 *
 */
class WikiPlugin_FuzzyPages
extends WikiPlugin
{
    function getName() {
        return _("FuzzyPages");
    }
    
    function getDescription() {
        return sprintf(_("List FuzzyPages for %s"), '[pagename]');
    }
    
    function getDefaultArguments() {
        return array('page'	=> '[pagename]',
                     );
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page))
            return '';

            $descrip = fmt("These page titles match fuzzy with '%s'",
                           WikiLink($page, 'auto'));

        $thispage = $page;

        $list = array();

        $pages = $dbi->getAllPages();
        while ($page = $pages->next()) {
            $name = $page->getName();
            $metaphone_similar = similar_text(metaphone($thispage), metaphone($name));
            $levenshtein = levenshtein(metaphone($thispage), metaphone($name));
            if ($metaphone_similar < 3 || $levenshtein > 0 + abs(strlen($name) - strlen($thispage)))
                continue;
            $similar = strlen($name) - strlen($thispage) - similar_text($thispage, $name);
            $list = array_merge($list, array($name => $similar - $levenshtein));
        }

        array_multisort($list, SORT_NUMERIC, SORT_ASC);
//        array_multisort($list, SORT_NUMERIC, SORT_DESC);

        $table = HTML::table(array('cellpadding' => 2,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'class'	 => 'pagelist'));
        $table->setAttr('summary', "FIXME: add brief summary and column names");
        $table->pushContent(HTML::caption(array('align'=>'top'), $descrip));

        foreach ($list as $key => $val) {
            //$val = (strlen($val) == 0) ? "0" : $val;
            $row = HTML::tr(HTML::td(WikiLink($key)),
                            HTML::td(array('align' => 'right'), $val));
            $table->pushContent($row);
        }

        return $table;

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
