<?php // -*-php-*-
rcs_id('$Id: TextSearchIter.php,v 1.5 2005-09-11 13:20:52 rurban Exp $');

class WikiDB_backend_dumb_TextSearchIter
extends WikiDB_backend_iterator
{
    function WikiDB_backend_dumb_TextSearchIter(&$backend, &$pages, $search, $fulltext=false, 
                                                $options=array()) 
    {
        $this->_backend = &$backend;
        $this->_pages = $pages;
        $this->_fulltext = $fulltext;
        $this->_search  = $search;
        $this->_index   = 0;

        if (isset($options['limit'])) $this->_limit = $options['limit'];
        else $this->_limit = 0;
        if (isset($options['exclude'])) $this->_exclude = $options['exclude'];
        else $this->_exclude = false;
    }

    function _get_content(&$page) {
        $backend = &$this->_backend;
        $pagename = $page['pagename'];
        
        if (!isset($page['versiondata'])) {
            $version = $backend->get_latest_version($pagename);
            $page['versiondata'] = $backend->get_versiondata($pagename, $version, true);
        }
        return $page['versiondata']['%content'];
    }
        
    function _match(&$page) {
        $text = $page['pagename'];
        if ($result = $this->_search->match($text)) // first match the pagename only
            return $result;

        if ($this->_fulltext) {
            $text .= "\n" . $this->_get_content($page);
            return $this->_search->match($text);
        } else
            return $result;
    }

    function next() {
        $pages = &$this->_pages;
        while ($page = $pages->next()) {
            if ($this->_match($page)) {
                if ($this->_limit and ($this->_index++ >= $this->_limit))
                    return false;
                return $page;
            }
        }
        return false;
    }

    function free() {
        $this->_pages->free();
    }
};

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>