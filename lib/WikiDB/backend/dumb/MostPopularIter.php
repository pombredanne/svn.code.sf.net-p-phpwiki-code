<?php // -*-php-*-
rcs_id('$Id: MostPopularIter.php,v 1.3 2002-01-20 16:51:40 dairiki Exp $');

require_once('lib/WikiDB/backend.php');


/**
 * An inefficient but general most_popular iterator. 
 *
 * This iterator will work with any backend which implements the
 * backend::get_all_pages() and backend::get_pagedata()
 * methods.
 */
class WikiDB_backend_dumb_MostPopularIter
extends WikiDB_backend_iterator
{
    function WikiDB_backend_dumb_MostPopularIter($backend, &$all_pages, $limit) {
        $this->_pages = array();
        $pages = &$this->_pages;

        while ($page = & $all_pages->next()) {
            if (!isset($page['pagedata']))
                $page['pagedata'] = $backend->get_pagedata($page['pagename']);
            $pages[] = $page;
        }

        usort($pages, 'WikiDB_backend_dumb_MostPopularIter_sortf');

        if ($limit && $limit < count($pages))
            array_splice($pages, $limit);
    }

    function next() {
        return array_shift($this->_pages);
    }
    
    function free() {
        unset($this->_pages);
    }
}

function WikiDB_backend_dumb_MostPopularIter_sortf($a,$b) {
    $ahits = $bhits = 0; 
    if (isset($a['pagedata']['hits']))
        $ahits = (int)$a['pagedata']['hits'];
    if (isset($b['pagedata']['hits']))
        $bhits = (int)$b['pagedata']['hits'];
    return $bhits - $ahits;
}

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>
