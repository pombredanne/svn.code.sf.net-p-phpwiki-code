<?php // -*-php-*-
rcs_id('$Id: MostPopularIter.php,v 1.1 2001-09-18 19:16:23 dairiki Exp $');

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
    @$ahits = (int)$a['pagedata']['hits'];
    @$bhits = (int)$b['pagedata']['hits'];
    return $bhits - $ahits;
}

?>
