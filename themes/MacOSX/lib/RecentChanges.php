<?php rcs_id('$Id: RecentChanges.php,v 1.1 2002-01-18 00:28:44 dairiki Exp $');
/*
 * Extensions/modifications to the stock RecentChanges (and PageHistory) format.
 */


require_once('lib/plugin/RecentChanges.php');
require_once('lib/plugin/PageHistory.php');

function MacOSX_RC_revision_formatter (&$fmt, &$rev) {
    $class = 'rc-' . $fmt->importance($rev);
        
    return Element('li', array('class' => $class),
                   implode(' ', array( $fmt->diffLink($rev),
                                       $fmt->pageLink($rev),
                                       $fmt->time($rev),
                                       ' . . . ',
                                       $fmt->summaryAsHTML($rev),
                                       ' --',
                                       $fmt->authorLink($rev) )));
}

class _MacOSX_RecentChanges_Formatter
extends _RecentChanges_HtmlFormatter
{
    function format_revision (&$rev) {
        return MacOSX_RC_revision_formatter($this, $rev);
    }
}

class _MacOSX_PageHistory_Formatter
extends _PageHistory_HtmlFormatter
{
    function format_revision (&$rev) {
        return MacOSX_RC_revision_formatter($this, $rev);
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
