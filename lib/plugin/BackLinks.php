<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.6 2002-01-15 02:42:32 carstenklapp Exp $');
/**
 */
class WikiPlugin_BackLinks
extends WikiPlugin
{
    function getName () {
        return _("BackLinks");
    }

    function getDescription () {
        return sprintf(_("Get BackLinks for %s"),'[pagename]');
    }
  
    function getDefaultArguments() {
        // FIXME: how to exclude multiple pages?
        return array('exclude'		=> '',
                     'include_self'	=> 0,
                     'noheader'		=> 0,
                     'page'		=> false,
                     'info'		=> false);
    }

    // Currently only info=false or info=hits works (I don't think
    // anything else would be useful anyway).

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (!$page)
            return '';
              
        $p = $dbi->getPage($page);
        $backlinks = $p->getLinks();
        $lines = array();
        if ($info) {
            $lines[] = $this->_tr(QElement('u', _(ucfirst($info))),
                                  QElement('u', _("Page Name")));
        }
        while ($backlink = $backlinks->next()) {
            $name = $backlink->getName();
            if ($exclude && $name == $exclude)
                continue;
            if (!$include_self && $name == $page)
                continue;
            if ($info) {
                $lines[] = $this->_tr($backlink->get($info),
                                      LinkWikiWord($name));
            } else {
                $lines[] = Element('li', LinkWikiWord($name));
            }
        }

        $html = '';
        if (!$noheader) {
            $fs = $lines ? _("These pages link to %s:") : _("No pages link to %s.");
            $header = sprintf(htmlspecialchars($fs),
                              LinkExistingWikiWord($page));
            $html = Element('p', $header) . "\n";
        }
        
        if ($info) {
            $html .= Element('blockquote',
                             Element('table', array('cellpadding' => 0,
                                                    'cellspacing' => 1,
                                                    'border' => 0),
                                     join("\n", $lines)));
            return $html;
        } else {
            return $html . Element('ul', join("\n", $lines));
        }
    }

    function _tr ($col1, $col2) {
        return "<tr><td align='right'>$col1&nbsp;&nbsp;</td>"
            . "<td>&nbsp;&nbsp;$col2</td></tr>\n";
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
