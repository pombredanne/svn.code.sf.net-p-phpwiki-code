<?php rcs_id('$Id: PageList.php,v 1.6 2002-01-21 16:53:35 carstenklapp Exp $');

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
            $this->setCaption(sprintf($this->_caption, $this->getTotal()));
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

        $summary = "FIXME: add brief summary and column names";

        $table = HTML::table(array('cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border' => 0,
                                   'summary' => $summary,
                                   'width' => '100%')
                            );

        $pad = new RawXml('&nbsp;&nbsp;');

        $caption = HTML::div(array('align' => 'left'), $this->getCaption());

        $thead = HTML::thead();
        foreach ($this->_columns as $column_name) {
            if ($this->_column_align($column_name) == 'right') {
                $thead->pushContent(HTML::td(array('align' => 'right'), $pad, HTML::u($column_name)));
            } else {
                $thead->pushContent(HTML::td($pad, HTML::u($column_name)));
            }
        }

        $tbody = HTML::tbody();
        foreach ($this->_pages as $page_handle) {
            $row = HTML::tr();
            foreach ($this->_columns as $column_name) {
                $col = HTML::td();
                $field = $this->_colname_to_dbfield($column_name);
                if ($this->_does_require_rev($column_name)) {
                    $current = $page_handle->getCurrentRevision();
                    $value = $current->get($field);
                    if ($field=='mtime') {
                        global $Theme;
                        $td = ($Theme->formatDateTime($value));
                    } else {
                        $td = ($value);
                    }
                } else {
                    //TODO: make method to determine formatting
                    if ($field=='pagename') {
                        $td = (new RawXml (LinkExistingWikiWord($page_handle->getName())));
                    } else {
                        $td = ($page_handle->get($field));
                    }
                }

                if ($this->_column_align($column_name) == 'right') {
                    $row->pushContent (HTML::td(array('align' => 'right'), $pad, $td));
                } else {
                    $row->pushContent (HTML::td($pad, $td));
                }
            }
            $tbody->pushContent($row);
        }

        // Final table assembly
        $table->pushContent(HTML::caption(array('align'=>'top'), $caption));
        $table->pushContent($thead);
        $table->pushContent($tbody);
        return $table;
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
