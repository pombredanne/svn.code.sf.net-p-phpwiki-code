<?php // -*-php-*-
rcs_id('$Id: MostPopular.php,v 1.2 2001-12-15 10:54:58 carstenklapp Exp $');
/**
 */
class WikiPlugin_MostPopular
extends WikiPlugin
{
    var $name = 'MostPopular';
    var $description = 'MostPopular';
    
    function getDefaultArguments() {
        // FIXME: how to exclude multiple pages?
        return array('limit'		=> 20,
                     'noheader'		=> 0);
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        
        $pages = $dbi->mostPopular($limit);

        $lines[] = $this->_tr(QElement('u', gettext("Hits")),
                              QElement('u', gettext("Page Name")));
        
        while ($page = $pages->next()) {
            $hits = $page->get('hits');
            if ($hits == 0)
                break;
            $lines[] = $this->_tr($hits,
                                  LinkWikiWord($page->getName()));
        }
        $pages->free();

        $html = '';
        if (!$noheader) {
            $html .= QElement('p',
                             sprintf("The %s most popular pages of this wiki:",
                                     $limit ? $limit : ''));
        }
        

        $html .= Element('blockquote',
                         Element('table', array('cellpadding' => 0,
                                                'cellspacing' => 1,
                                                'border' => 0),
                                 join("\n", $lines)));
        return $html;
    }

    function _tr ($col1, $col2) {
        return "<tr><td align='right'>$col1&nbsp;&nbsp;</td>"
            . "<td>&nbsp;&nbsp;$col2</td></tr>\n";
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
