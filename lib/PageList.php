<?php rcs_id('$Id: PageList.php,v 1.3 2002-01-21 08:01:42 carstenklapp Exp $');

// This will relieve some of the work of plugins like LikePages,
// MostPopular and allows dynamic expansion of those plugins do
// include more columns in their output.
//
// There are still a few rough edges.

class PageList {
    function PageList ($pagelist_name = '') {
        $this->name = $pagelist_name;
        $this->_caption = "";
        $this->_columns = array(_("Page Name"));
        $this->_pages = array();
        $this->_total = 0;
        $this->_html = "";
    }

    function setCaption ($caption_string) {
        $this->_caption = $caption_string;
    }

    function getCaption () {
        // put the total into the caption if needed
        if (strstr($this->_caption, '%d')) {
            $this->setCaption(sprintf($this->_caption, getTotal()));
        }
        return $this->_caption;
    }

    function addColumn ($new_columnname) {
        array_push($this->_columns, $new_columnname);
    }

    function insertColumn ($new_columnname) {
        array_unshift($this->_columns, $new_columnname);
    }

    function addPage ($page_handle) {
        array_push($this->_pages, &$page_handle);
        $this->_total = $this->_total + 1;
    }

    function getTotal () {
        if (! $this->_total) {
            //this might not be necessary
            $this->_total = count($this->_pages);
        }
        return $this->_total;
    }
    
    function getHTML() {
        // TODO: Generate a list instead of a table when only one
        // column (pagenames only).
        // TODO: use the new html functions
        $html = "";
        $pad = "&nbsp;&nbsp;";
        $html .= "<p>" . $this->getCaption() . "</p>";
        $summary = "FIXME: add brief summary and column names";
        $html .= "<table summary=\"$summary\" border=\"0\" padding=\"0\" cellspacing=\"0\">";

        //FIXME: insert column headers into first row
        foreach ($this->_pages as $page_handle) {
            $html .= "<tr>";
            foreach ($this->_columns as $column_name) {
                $html .= $this->_column_align($column_name) == 'right' ? "<td align=\"right\">" : "<dr>";
                $field = $this->_colname_to_dbfield($column_name);
                if ($this->_does_require_rev($column_name)) {
                    $current = $page_handle->getCurrentRevision();
                    $value = $current->get($field);
                    if ($field=='mtime') {
                        global $Theme;
                        $html .= $Theme->formatDateTime($value);
                    }else{
                        $html .= $value;
                    }
                } else {
                    //TODO: make method to determine formatting
                    if ($field=='pagename') {
                        $html .= LinkExistingWikiWord($page_handle->getName());
                    } else {
                        $html .= $page_handle->get($field);
                    }
                }
                //FIXME: omit padding for last column;
                $html .= $pad . "</td>";
            }
            $html .= "</tr>\n";
        }
        $html .= "</table>\n";
        return $html;
    }
    
    ////////////////////
    // private
    ////////////////////
    
    // lookup alignment from column name
    function _column_align($column_name) {
        $map = array(
                     _("Page Name")      => 'left',
                     _("Last Modified")  => 'left',
                     _("Hits")           => 'right',
                     _("Date Created")   => 'left',
                     _("# of revisions") => 'right',
                     _("Last Summary")   => 'left',
                     _("Last Edited By") => 'left'
                     );

        $key = $map[$column_name];
//            trigger_error("_column_align: key for '$column_name' is '$key'",
//                          E_USER_ERROR); //tempdebug

        if (! $key) {
            //FIXME: localise after wording has been finalized.
            trigger_error("_column_align: key for '$column_name' not found",
                          E_USER_ERROR);
        }
        return $key;
    }


    // lookup database field from column name
    function _colname_to_dbfield($column_name) {
        $map = array(
                     _("Page Name")      => 'pagename',
                     _("Last Modified")  => 'mtime',
                     _("Hits")           => 'hits',
                     _("Date Created")   => '',
                     _("# of revisions") => '',
                     _("Last Summary")   => 'summary',
                     _("Last Edited By") => ''
                    );
        $key = $map[$column_name];
        if (! $key) {
            //FIXME: localise after wording has been finalized.
            trigger_error("_colname_to_dbfield: key for '$column_name' not found",
                          E_USER_ERROR);
        }
        return $key;
    }

    
    // lookup whether fieldname requires page revision
    function _does_require_rev($column_name) {
        $map = array(
                     _("Page Name")      => false,
                     _("Last Modified")  => true,
                     _("Hits")           => false,
                     _("Date Created")   => '',
                     _("# of revisions") => '',
                     _("Last Summary")   => true,
                     _("Last Edited By") => true
                     );
        $key = $map[$column_name];
        return $key;
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
