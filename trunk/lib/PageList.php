<?php rcs_id('$Id: PageList.php,v 1.11 2002-01-21 21:06:13 carstenklapp Exp $');

// This relieves some work for these plugins:
//
// BackLinks, LikePages, Mostpopular, TitleSearch
//
// It also allows dynamic expansion of those plugins to include more
// columns in their output.
//
// There are still a few rough edges.
//
// FIXME: Make caption work properly with new HtmlElement
//
// FIXME: Add error recovery! If the column title isn't found due to a
//        typo, the wiki craps out, you will not even be able to edit the
//        page again to fix the typo in the column name!

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

    //FIXME: yuck, get rid of ucfirst
    function addColumn ($new_columnname) {
        array_push($this->_columns, ucfirst($new_columnname));
    }

    function insertColumn ($new_columnname) {
        array_unshift($this->_columns, ucfirst($new_columnname));
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

    function getContent() {
        $caption = HTML::div(array('align' => 'left'), $this->getCaption());

        if (count($this->_columns) == 1) {
            return array($caption, $this->_generateList());
        } else {
            return $this->_generateTable($caption);
        }
    }

    ////////////////////
    // private
    ////////////////////

    // Some of these column_name aliases could probably be eliminated
    // once standard titles have been agreed upon

    // lookup alignment from column name
    function _column_align($column_name) {
        $map = array(
                     _("Page Name")       => 'left',
                     _("Page")            => 'left',
                     _("Name")            => 'left',
                     _("Last Modified")   => 'left',
                     _("Hits")            => 'right',
                     _("Visitors")        => 'right',
                     _("Date Created")    => 'left',
                     _("Creation Date")   => 'left',
                     _("# Of Revisions")  => 'right',        //FIXME: count revisions in db
                     _("Last Summary")    => 'left',
                     _("Last Author")     => 'left',
                     _("Last Edited By")  => 'left',
                     _("Author")          => 'left',
                     _("Original Author") => 'left',
                     _("Created By")      => 'left',
                     _("Locked")          => 'left',
                     _("Minor Edit")      => 'left',
                     _("Minor")           => 'left',
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
                     _("Page Name")       => 'pagename',
                     _("Page")            => 'pagename',
                     _("Name")            => 'pagename',
                     _("Last Modified")   => 'mtime',
                     _("Hits")            => 'hits',
                     _("Visitors")        => 'hits',
                     _("Date Created")    => '_ctime',
                     _("Creation Date")   => '_ctime',
                     _("# Of Revisions")  => '',        //FIXME: count revisions in db
                     _("Last Summary")    => 'summary',
                     _("Last Author")     => 'author',
                     _("Last Edited By")  => 'author',
                     _("Author")          => '_cauthor',
                     _("Original Author") => '_cauthor',
                     _("Created By")      => '_cauthor',
                     _("Locked")          => 'locked',
                     _("Minor Edit")      => 'is_minor_edit',
                     _("Minor")           => 'is_minor_edit',
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
                     _("Page Name")       => false,
                     _("Page")            => false,
                     _("Name")            => false,
                     _("Last Modified")   => true,
                     _("Hits")            => false,
                     _("Visitors")        => false,
                     _("Date Created")    => false,
                     _("Creation Date")   => false,
                     _("# Of Revisions")  => '',        //FIXME: count revisions in db
                     _("Last Summary")    => true,
                     _("Last Author")     => true,
                     _("Last Edited By")  => true,
                     _("Author")          => true,
                     _("Original Author") => false,
                     _("Created By")      => false,
                     _("Locked")          => false,
                     _("Minor Edit")      => true,
                     _("Minor")           => true,
                     );
        $key = $map[$column_name];
        return $key;
    }

    // make a table given the caption
    function _generateTable($caption) {
        $pad = new RawXml('&nbsp;&nbsp;');
        $emdash = new RawXml('&#8212;');

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
            global $Theme;

            foreach ($this->_columns as $column_name) {
                $col = HTML::td();
                $field = $this->_colname_to_dbfield($column_name);

                if ($this->_does_require_rev($column_name)) {
                    $current = $page_handle->getCurrentRevision();
                    $value = $current->get($field);
                }
                //TODO: make method to determine formatting
                switch ($field) {
                    // needs current rev
                case 'mtime' :
                    $td = ($Theme->formatDateTime($value));
                    break;
                case 'is_minor_edit' :
                    $td = ($value) ? _("minor") : $emdash;
                    break;

                    // does not need current rev
                case 'pagename' :
                    $td = new RawXml (LinkExistingWikiWord($page_handle->getName()));
                    break;
                case 'locked' :
                    $td = ($page_handle->get($field)) ? _("locked") : $emdash;
                    break;
                case '_ctime' :
                    $revision = $page_handle->getRevision(1);
                    $td = $Theme->formatDateTime($revision->get('mtime'));
                    break;
                case '_cauthor' :
                    $revision = $page_handle->getRevision(1);
                    $td = new RawXml (LinkExistingWikiWord($revision->get('author')));
                    break;

                default :
                    if ($this->_does_require_rev($column_name)) {
                        $td = ($value);
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

        $summary = "FIXME: add brief summary and column names";

        // Final table assembly
        $table = HTML::table(array('summary'     => $summary,
                                   'cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'width'       => '100%'));

        $table->pushContent(HTML::caption(array('align'=>'top'), $caption));
        $table->pushContent($thead);
        $table->pushContent($tbody);
        return $table;
    }

    function _generateList() {
        $list = HTML::ul();
        foreach ($this->_pages as $page_handle) {
            $list->pushContent(HTML::li(_LinkWikiWord($page_handle->getName())));
        }
        return $list;
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
