<?php rcs_id('$Id: PageList.php,v 1.13 2002-01-22 03:17:47 dairiki Exp $');

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
        $this->_messageIfEmpty = _("<no matches>");
    }

    function setCaption ($caption_string) {
        $this->_caption = $caption_string;
    }

    function getCaption () {
        // put the total into the caption if needed
        if (is_string($this->_caption) && strstr($this->_caption, '%d'))
            return sprintf($this->_caption, $this->getTotal());
        return $this->_caption;
    }

    function setMessageIfEmpty ($msg) {
        $this->_messageIfEmpty = $msg;
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
    }

    function getTotal () {
        return count($this->_pages);
    }

    function isEmpty () {
        return empty($this->_pages);
    }
    

    function getContent() {
        // Note that the <caption> element wants inline content.
        $caption = $this->getCaption();

        if ($this->isEmpty())
            return $this->_emptyList($caption);
        elseif (count($this->_columns) == 1)
            return $this->_generateList($caption);
        else
            return $this->_generateTable($caption);
    }

    function printXML() {
        PrintXML($this->getContent());
    }

    function asXML() {
        return AsXML($this->getContent());
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
                     _("First Summary")   => 'left',
                     _("Last Summary")    => 'left',
                     _("Summary")         => 'left',
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
                     _("First Summary")   => '_csummary',
                     _("Last Summary")    => 'summary',
                     _("Summary")         => 'summary',
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
                     _("First Summary")   => false,
                     _("Last Summary")    => true,
                     _("Summary")         => true,
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
        $pad = NBSP. NBSP;
        $emdash = new RawXml('&#8212;');

        $table = HTML::table(array('cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'width'       => '100%'));
        $table->setAttr('summary', "FIXME: add brief summary and column names");


        $table->pushContent(HTML::caption(array('align'=>'top'), $caption));

        $row = HTML::tr();
        foreach ($this->_columns as $column_name) {
            $row->pushContent(HTML::td(array('align'
                                             => $this->_column_align($column_name)),
                                       $pad, HTML::u($column_name)));
        }
        $table->pushContent(HTML::thead($row));
        

        $tbody = HTML::tbody();
        foreach ($this->_pages as $page_handle) {
            $row = HTML::tr();
            global $Theme;

            foreach ($this->_columns as $column_name) {
                $field = $this->_colname_to_dbfield($column_name);

                if ($this->_does_require_rev($column_name)) {
                    $current = $page_handle->getCurrentRevision();
                    $value = $current->get($field);
                }
                else
                    $value = $page_handle->get($field);

                //TODO: make method to determine formatting
                switch ($field) {
                    // needs current rev
                case 'mtime' :
                    $value = $Theme->formatDateTime($value);
                    break;
                case 'is_minor_edit' :
                    $value = $value ? _("minor") : $emdash;
                    break;

                    // does not need current rev
                case 'pagename' :
                    $value = LinkExistingWikiWord($page_handle->getName());
                    break;
                case 'locked' :
                    $value = $value ? _("locked") : $emdash;
                    break;
                case '_ctime' :
                    // FIXME: There might not be revision 1 (it may have been cleaned
                    // out long ago).
                    $revision = $page_handle->getRevision(1);
                    $value = $Theme->formatDateTime($revision->get('mtime'));
                    break;
                case '_cauthor' :
                    // FIXME: as above
                    $revision = $page_handle->getRevision(1);
                    $value = LinkExistingWikiWord($revision->get('author'));
                    break;
                case '_csummary' :
                    // FIXME: as above
                    $revision = $page_handle->getRevision(1);
                    //TODO: link WikiWords
                    //$td = new RawXml (LinkExistingWikiWord($revision->get('summary')));
                    $value = $revision->get('summary');
                    break;

                default :
                    break;
                }

                $row->pushContent(HTML::td(array('align' => $this->_column_align($column_name)),
                                           $pad, $value));
            }
            $tbody->pushContent($row);
        }

        $table->pushContent($tbody);
        return $table;
    }

    function _generateList($caption) {
        $list = HTML::ul();
        foreach ($this->_pages as $page_handle) {
            $list->pushContent(HTML::li(LinkWikiWord($page_handle->getName())));
        }
        if ($caption)
            $html[] = HTML::p($caption);
        $html[] = $list;
        return $html;
    }

    function _emptyList($caption) {
        $html = array();
        if ($caption)
            $html[] = HTML::p($caption);
        if ($this->_messageIfEmpty)
            $html[] = HTML::blockquote(HTML::p($this->_messageIfEmpty));
        return $html;
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
