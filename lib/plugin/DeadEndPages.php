<?php
rcs_id('$Id$');

/**
 * Alias for OrphanedPages. Idea and name from mediawiki.
 *
	"SELECT cur_title " . 
	  "FROM cur LEFT JOIN links ON cur_title = l_from " .
	  "WHERE l_from IS NULL " .
	  "AND cur_namespace = 0 " .
	  "ORDER BY cur_title " . 
	  "LIMIT {$offset}, {$limit}";
 *
 **/
require_once('lib/PageList.php');
require_once('lib/plugin/OrphanedPages.php');

/**
 */
class WikiPlugin_DeadEndPages
extends WikiPlugin_OrphanedPages
{
    function getName () {
        return _("DeadEndPages");
    }
};

// $Log: not supported by cvs2svn $
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
