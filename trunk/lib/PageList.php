<?php rcs_id('$Id: PageList.php,v 1.24 2002-01-27 02:57:42 carstenklapp Exp $');

/**
 * This library relieves some work for these plugins:
 *
 * BackLinks, LikePages, Mostpopular, TitleSearch
 *
 * It also allows dynamic expansion of those plugins to include more
 * columns in their output.
 *
 *
 * Column arguments:
 *
 * 'mtime'   _("Last Modified")
 * 'hits'    _("Hits")
 * 'summary' _("Last Summary")
 * 'author'  _("Last Author")),
 * 'locked'  _("Locked"), _("locked")
 * 'minor'   _("Minor Edit"), _("minor")
 *
 *
 * FIXME?: Make caption work properly with new HtmlElement
 *
 * FIXME: In this refactoring I have un-implemented _ctime, _cauthor, and
 * number-of-revision.  Note the _ctime and _cauthor as they were implemented
 * were somewhat flawed: revision 1 of a page doesn't have to exist in the
 * database.  If lots of revisions have been made to a page, it's more than likely
 * that some older revisions (include revision 1) have been cleaned (deleted).
 */
class _PageList_Column_base {
    function _PageList_Column_base ($default_heading, $align = false) {
        $this->_heading = $default_heading;

        $this->_tdattr = array();
        if ($align)
            $this->_tdattr['align'] = $align;
    }
    
    function format ($page_handle, &$revision_handle) {
        return HTML::td($this->_tdattr,
                        NBSP,
                        $this->_getValue($page_handle, &$revision_handle),
                        NBSP);
    }

    function setHeading ($heading) {
        $this->_heading = $heading;
    }
    
    function heading () {
        return HTML::td(array('align' => 'center'),
                        NBSP, HTML::u($this->_heading), NBSP);
    }
};

class _PageList_Column extends _PageList_Column_base {
    function _PageList_Column ($field, $default_heading, $align = false) {
        $this->_PageList_Column_base($default_heading, $align);
        
        $this->_need_rev = substr($field, 0, 4) == 'rev:';
        if ($this->_need_rev)
            $this->_field = substr($field, 4);
        else
            $this->_field = $field;
    }
    
    function _getValue ($page_handle, &$revision_handle) {
        if ($this->_need_rev) {
            if (!$revision_handle)
                $revision_handle = $page_handle->getCurrentRevision();
            return $revision_handle->get($this->_field);
        }
        else {
            return $page_handle->get($this->_field);
        }
    }
};

class _PageList_Column_bool extends _PageList_Column {
    function _PageList_Column_bool ($field, $default_heading, $text = 'yes') {
        $this->_PageList_Column($field, $default_heading, 'center');
        $this->_textIfTrue = $text;
        $this->_textIfFalse = new RawXml('&#8212;');
    }
    
    function _getValue ($page_handle, &$revision_handle) {
        $val = _PageList_Column::_getValue($page_handle, $revision_handle);
        return $val ? $this->_textIfTrue : $this->_textIfFalse;
    }
};

class _PageList_Column_time extends _PageList_Column {
    function _PageList_Column_time ($field, $default_heading) {
        $this->_PageList_Column($field, $default_heading, 'right');
    }
    
    function _getValue ($page_handle, &$revision_handle) {
        global $Theme;
        $time = _PageList_Column::_getValue($page_handle, $revision_handle);
        return $Theme->formatDateTime($time);
    }
};

class _PageList_Column_version extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        //$version = $revision_handle->getVersion();//doesn't work(?)
        $current = $page_handle->getCurrentRevision();
        return $current->getVersion();
    }
};

class _PageList_Column_author extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        global $WikiNameRegexp, $request, $Theme;
        $dbi = $request->getDbh();

        $author = _PageList_Column::_getValue($page_handle, $revision_handle);
        if (preg_match("/^$WikiNameRegexp\$/", $author) && $dbi->isWikiPage($author))
            return $Theme->linkExistingWikiWord($author);
        else
            return $author;
    }
};

class _PageList_Column_pagename extends _PageList_Column_base {
    function _PageList_Column_pagename () {
        $this->_PageList_Column_base(_("Page Name"));
    }
    
    function _getValue ($page_handle, &$revision_handle) {
        global $Theme;
        return $Theme->LinkExistingWikiWord($page_handle->getName());
    }
};

        
class PageList {
    function PageList () {
        $this->_caption = "";
        $this->_columns = array(new _PageList_Column_pagename);
        $this->_pages = array();
        $this->_messageIfEmpty = _("<no matches>");
        $this->_group_rows = 3;
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

    /**
     * Add a column to the listing.
     *
     * @input $column string Which column to add.
     * $Column can be one of <ul>
     * <li>mtime
     * <li>hits
     * <li>summary
     * <li>author
     * <li>locked
     * <li>minor
     * </ul>
     *
     * If you would like to specify an alternate heading for the
     * column, concatenate the desired adding to $column, after adding
     * a colon.  E.g. 'hits:Page Views'.
     */ 
    function addColumn ($new_columnname) {
        if (($col = $this->_getColumn($new_columnname))) {
           if(! $this->column_exists($col->_heading)) {
                array_push($this->_columns, $col);
            }
        }
    }

    function insertColumn ($new_columnname) {
        if (($col = $this->_getColumn($new_columnname))) {
           if(! $this->column_exists($col->_heading)) {
                array_unshift($this->_columns, $col);
            }
        }
    }

    function column_exists ($heading) {
        foreach ($this->_columns as $val) {
            if ($val->_heading == $heading)
                return true;
        }
        return false;
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
    function _getColumn ($column) {
        static $types;
        if (empty($types)) {
            $types = array( 'mtime'
                            => new _PageList_Column_time('rev:mtime', _("Last Modified")),
                            'hits'
                            => new _PageList_Column('hits',  _("Hits"), 'right'),
                            'summary'
                            => new _PageList_Column('rev:summary',  _("Last Summary")),
                            'version'
                            => new _PageList_Column_version('rev:version',  _("Version"), 'right'),
                            'author'
                            => new _PageList_Column_author('rev:author',  _("Last Author")),
                            'locked'
                            => new _PageList_Column_bool('locked',  _("Locked"), _("locked")),
                            'minor'
                            => new _PageList_Column_bool('rev:is_minor_edit',
                                                         _("Minor Edit"), _("minor"))
                            );
        }

        if (strstr($column, ':'))
            list ($column, $heading) = explode(':', $column, 2);

        if (!isset($types[$column])) {
            trigger_error(sprintf("%s: Bad column", $column), E_USER_NOTICE);
            return false;
        }

        $col = $types[$column];
        if (!empty($heading))
            $col->setHeading($heading);
        return $col;
    }
        
    
    // make a table given the caption
    function _generateTable($caption) {
        $table = HTML::table(array('cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'class'	 => 'pagelist'));
        $table->setAttr('summary', "FIXME: add brief summary and column names");


        if ($caption)
            $table->pushContent(HTML::caption(array('align'=>'top'), $caption));

        $row = HTML::tr();
        foreach ($this->_columns as $col)
            $row->pushContent($col->heading());
        $table->pushContent(HTML::thead($row));
        

        $tbody = HTML::tbody();
        $n = 0;
        foreach ($this->_pages as $page_handle) {
            $row = HTML::tr();
            $revision_handle = false;
            foreach ($this->_columns as $col) {
                $row->pushContent($col->format($page_handle, $revision_handle));
            }
            $group = (int)($n++ / $this->_group_rows);
            $row->setAttr('class', ($group % 2) ? 'oddrow' : 'evenrow');
            $tbody->pushContent($row);
        }
        $table->pushContent($tbody);
        return $table;
    }

    function _generateList($caption) {
        $list = HTML::ul(array('class' => 'pagelist'));
        $n = 0;
        foreach ($this->_pages as $page_handle) {
            $group = (int)($n++ / $this->_group_rows);
            $class = ($group % 2) ? 'oddrow' : 'evenrow';

            $list->pushContent(HTML::li(array('class' => $class),
                                        LinkWikiWord($page_handle->getName())));
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
