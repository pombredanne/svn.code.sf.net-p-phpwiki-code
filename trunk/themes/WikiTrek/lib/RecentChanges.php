<?php rcs_id('$Id: RecentChanges.php,v 1.1 2002-01-28 19:23:10 carstenklapp Exp $');
/*
 * Extensions/modifications to the stock RecentChanges (and PageHistory) format.
 */


require_once('lib/plugin/RecentChanges.php');
require_once('lib/plugin/PageHistory.php');

function WikiTrek_RC_revision_formatter (&$fmt, &$rev) {
    $class = 'rc-' . $fmt->importance($rev);
        
    return HTML::li(array('class' => $class),
                    $fmt->diffLink($rev), " ",
                    $fmt->pageLink($rev), " ",
                    $fmt->time($rev),
                    ($fmt->importance($rev)=='minor') ? _("(minor edit)") ." " : '',
                    " . . . ",
                    $fmt->summaryAsHTML($rev),
                    " . . . [ ",
                    $fmt->authorLink($rev), " ]");
}

class _WikiTrek_RecentChanges_Formatter
extends _RecentChanges_HtmlFormatter
{
    function format_revision (&$rev) {
        return WikiTrek_RC_revision_formatter($this, $rev);
    }
    function summaryAsHTML ($rev) {
        if ( !($summary = $this->summary($rev)) )
            return '';
        return  HTML::strong( array('class' => 'wiki-summary'),
                              " ",
                              do_transform($summary, 'LinkTransform'),
                              " ");
    }

    function diffLink ($rev) {
        global $Theme;
        return $Theme->makeButton(_("[diff]"), $this->diffURL($rev), 'wiki-rc-action');
    }

}

class _WikiTrek_PageHistory_Formatter
extends _PageHistory_HtmlFormatter
{
    function format_revision (&$rev) {
        return WikiTrek_RC_revision_formatter($this, $rev);
    }
    function summaryAsHTML ($rev) {
        if ( !($summary = $this->summary($rev)) )
            return '';
        return  HTML::strong( array('class' => 'wiki-summary'),
                              " ",
                              do_transform($summary, 'LinkTransform'),
                              " ");
    }
}


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
