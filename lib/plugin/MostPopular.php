<?php // -*-php-*-
rcs_id('$Id: MostPopular.php,v 1.7 2002-01-20 16:54:06 dairiki Exp $');
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
        
        $lines[] = $this->_tr(QElement('u', _("Hits")),
                              QElement('u', _("Page Name")));

        while ($page = $pages->next()) {
            $hits = $page->get('hits');
            echo "HITS: $hits<br>\n";
            if ($hits == 0)
                break;
            $lines[] = $this->_tr($hits,
                                  LinkWikiWord($page->getName()));
        }
        $pages->free();

        $html = '';
        if (!$noheader) {
            if ($limit > 0)
                $msg = sprintf(_("The %s most popular pages of this wiki:"), $limit);
            else
                $msg = _("Visited pages on this wiki, ordered by popularity:");
                
            $html .= QElement('p', $msg);
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
