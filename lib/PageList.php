<?php rcs_id('$Id: PageList.php,v 1.47 2004-01-25 07:58:29 rurban Exp $');

/**
 * This library relieves some work for these plugins:
 *
 * AllPages, BackLinks, LikePages, Mostpopular, TitleSearch and more
 *
 * It also allows dynamic expansion of those plugins to include more
 * columns in their output.
 *
 * Column 'info=' arguments:
 *
 * 'pagename' _("Page Name")
 * 'mtime'    _("Last Modified")
 * 'hits'     _("Hits")
 * 'summary'  _("Last Summary")
 * 'version'  _("Version")),
 * 'author'   _("Last Author")),
 * 'locked'   _("Locked"), _("locked")
 * 'minor'    _("Minor Edit"), _("minor")
 * 'markup'   _("Markup")
 * 'size'     _("Size")
 * 'remove'   _("Remove") //admin action, not really an info column
 *
 * 'all'       All columns will be displayed. This argument must appear alone.
 * 'checkbox'  A selectable checkbox appears at the left.
 *
 * FIXME: In this refactoring I have un-implemented _ctime, _cauthor, and
 * number-of-revision.  Note the _ctime and _cauthor as they were implemented
 * were somewhat flawed: revision 1 of a page doesn't have to exist in the
 * database.  If lots of revisions have been made to a page, it's more than likely
 * that some older revisions (include revision 1) have been cleaned (deleted).
 *
 * FIXME:
 * The 'sortby' option is handled here correctly, but at the backends at 
 * the page iterator not yet.
 *
 * TODO: order, sortby, limit, offset, rows arguments for multiple pages/multiple rows.
 */
class _PageList_Column_base {
    var $_tdattr = array();

    function _PageList_Column_base ($default_heading, $align = false) {
        $this->_heading = $default_heading;

        if ($align) {
            // align="char" isn't supported by any browsers yet :(
            //if (is_array($align))
            //    $this->_tdattr = $align;
            //else
            $this->_tdattr['align'] = $align;
        }
    }

    function format ($pagelist, $page_handle, &$revision_handle) {
        return HTML::td($this->_tdattr,
                        HTML::raw('&nbsp;'),
                        $this->_getValue($page_handle, $revision_handle),
                        HTML::raw('&nbsp;'));
    }

    function setHeading ($heading) {
        $this->_heading = $heading;
    }

    function heading () {
        if (in_array($this->_field,array('pagename','mtime','hits'))) {
            // Todo: multiple comma-delimited sortby args: "+hits,+pagename"
            // asc or desc: +pagename, -pagename
            $sortby = '+' . $this->_field;
            if ($sorted = $GLOBALS['request']->getArg('sortby')) {
                // flip order
                if ($sorted == '+' . $this->_field)
                    $sortby = '-' . $this->_field;
                elseif ($sorted == '-' . $this->_field)
                    $sortby = '+' . $this->_field;
            }
            $s = HTML::a(array('href' => $GLOBALS['request']->GetURLtoSelf(array('sortby' => $sortby)),'class' => 'pagetitle', 'title' => sprintf(_("Sort by %s"),$this->_field)), HTML::raw('&nbsp;'), HTML::u($this->_heading), HTML::raw('&nbsp;'));
        } else {
            $s = HTML(HTML::raw('&nbsp;'), HTML::u($this->_heading), HTML::raw('&nbsp;'));
        }
        return HTML::td(array('align' => 'center'),$s);
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

class _PageList_Column_size extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        if (!$revision_handle)
            $revision_handle = $page_handle->getCurrentRevision();
        return $this->_getSize($revision_handle);
    }

    function _getSize($revision_handle) {
        $bytes = strlen($revision_handle->_data['%content']);
        return ByteFormatter($bytes);
    }
}


class _PageList_Column_bool extends _PageList_Column {
    function _PageList_Column_bool ($field, $default_heading, $text = 'yes') {
        $this->_PageList_Column($field, $default_heading, 'center');
        $this->_textIfTrue = $text;
        $this->_textIfFalse = new RawXml('&#8212;'); //mdash
    }

    function _getValue ($page_handle, &$revision_handle) {
        $val = _PageList_Column::_getValue($page_handle, $revision_handle);
        return $val ? $this->_textIfTrue : $this->_textIfFalse;
    }
};

class _PageList_Column_checkbox extends _PageList_Column {
    function _PageList_Column_checkbox ($field, $default_heading, $name='p') {
        $this->_name = $name;
        $this->_PageList_Column($field, $default_heading, 'center');
    }
    function _getValue ($pagelist, $page_handle, &$revision_handle) {
        $pagename = $page_handle->getName();
        if (!empty($pagelist->_selected[$pagename])) {
            return HTML::input(array('type' => 'checkbox',
                                     'name' => $this->_name . "[$pagename]",
                                     'value' => $pagename,
                                     'checked' => '1'));
        } else {
            return HTML::input(array('type' => 'checkbox',
                                     'name' => $this->_name . "[$pagename]",
                                     'value' => $pagename));
        }
    }
    function format ($pagelist, $page_handle, &$revision_handle) {
        return HTML::td($this->_tdattr,
                        HTML::raw('&nbsp;'),
                        $this->_getValue($pagelist, $page_handle, $revision_handle),
                        HTML::raw('&nbsp;'));
    }
};

class _PageList_Column_time extends _PageList_Column {
    function _PageList_Column_time ($field, $default_heading) {
        $this->_PageList_Column($field, $default_heading, 'right');
        global $Theme;
        $this->Theme = &$Theme;
    }

    function _getValue ($page_handle, &$revision_handle) {
        $time = _PageList_Column::_getValue($page_handle, $revision_handle);
        return $this->Theme->formatDateTime($time);
    }
};

class _PageList_Column_version extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        if (!$revision_handle)
            $revision_handle = $page_handle->getCurrentRevision();
        return $revision_handle->getVersion();
    }
};

// If needed this could eventually become a subclass
// of a new _PageList_Column_action class for other actions.
class _PageList_Column_remove extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        return Button(array('action' => 'remove'), _("Remove"),
                      $page_handle->getName());
    }
};

// Output is hardcoded to limit of first 50 bytes. Otherwise
// on very large Wikis this will fail if used with AllPages
// (PHP memory limit exceeded)
class _PageList_Column_content extends _PageList_Column {
    function _PageList_Column_content ($field, $default_heading, $align = false) {
        _PageList_Column::_PageList_Column($field, $default_heading, $align);
        $this->bytes = 50;
        $this->_heading .= sprintf(_(" ... first %d bytes"),
                                   $this->bytes);
    }
    function _getValue ($page_handle, &$revision_handle) {
        if (!$revision_handle)
            $revision_handle = $page_handle->getCurrentRevision();
        // Not sure why implode is needed here, I thought
        // getContent() already did this, but it seems necessary.
        $c = implode("\n", $revision_handle->getContent());
        if (($len = strlen($c)) > $this->bytes) {
            $c = substr($c, 0, $this->bytes);
        }
        include_once('lib/BlockParser.php');
        // false --> don't bother processing hrefs for embedded WikiLinks
        $ct = TransformText($c, $revision_handle->get('markup'), false);
        return HTML::div(array('style' => 'font-size:xx-small'),
                         HTML::div(array('class' => 'transclusion'), $ct),
                         // TODO: Don't show bytes here if size column present too
                         /* Howto??? $this->parent->_columns['size'] ? "" :*/
                         ByteFormatter($len, /*$longformat = */true));
    }
};

class _PageList_Column_author extends _PageList_Column {
    function _PageList_Column_author ($field, $default_heading, $align = false) {
        _PageList_Column::_PageList_Column($field, $default_heading, $align);
        global $WikiNameRegexp, $request;
        $this->WikiNameRegexp = $WikiNameRegexp;
        $this->dbi = &$request->getDbh();
    }

    function _getValue ($page_handle, &$revision_handle) {
        $author = _PageList_Column::_getValue($page_handle, $revision_handle);
        if (preg_match("/^$this->WikiNameRegexp\$/", $author) && $this->dbi->isWikiPage($author))
            return WikiLink($author);
        else
            return $author;
    }
};

class _PageList_Column_pagename extends _PageList_Column_base {
    var $_field = 'pagename';

    function _PageList_Column_pagename () {
        $this->_PageList_Column_base(_("Page Name"));
        global $request;
        $this->dbi = &$request->getDbh();
    }

    function _getValue ($page_handle, &$revision_handle) {
        if ($this->dbi->isWikiPage($pagename = $page_handle->getName()))
            return WikiLink($page_handle);
        else
            return WikiLink($page_handle, 'unknown');
    }
};



class PageList {
    var $_group_rows = 3;
    var $_columns = array();
    var $_excluded_pages = array();
    var $_rows = array();
    var $_caption = "";
    var $_pagename_seen = false;
    var $_types = array();
    var $_options = array();
    var $_selected = array();

    function PageList ($columns = false, $exclude = false, $options = false) {
        if ($columns == 'all') {
            $this->_initAvailableColumns();
            $columns = array_keys($this->_types);
            // FIXME: Probably a good idea to NOT include the
            // columns 'content' and 'remove' when 'all' is
            // specified.
        }

        if ($columns) {
            if (!is_array($columns))
                $columns = explode(',', $columns);
            if (in_array('all',$columns)) { // e.g. 'checkbox,all'
                $this->_initAvailableColumns();
                $columns = array_merge($columns,array_keys($this->_types));
                $columns = array_diff($columns,array('all'));
            }
            foreach ($columns as $col) {
                $this->_addColumn($col);
            }
        }
        $this->_addColumn('pagename');

        if ($exclude) {
            if (!is_array($exclude))
                $exclude = explode(',', $exclude);
            $this->_excluded_pages = $exclude;
        }

        $this->_options = $options;
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


    function getTotal () {
        return count($this->_rows);
    }

    function isEmpty () {
        return empty($this->_rows);
    }

    // $action = flip_order, db
    function sortby ($string, $action) {
        $order = '+';
        if (substr($string,0,1) == '+') {
            $order = '+'; $string = substr($string,1);
        } elseif (substr($string,0,1) == '-') {
            $order = '-'; $string = substr($string,1);
        }
        if (in_array($string,array('pagename','mtime','hits'))) {
            // Todo: multiple comma-delimited sortby args: "+hits,+pagename"
            // asc or desc: +pagename, -pagename
            if ($action == 'flip_order') {
                return ($order == '+' ? '-' : '+') . $string;
            } elseif ($action == 'db') {
                return $string . ($order == '+' ? ' ASC' : ' DESC');
            }
        }
        return '';
    }

    function addPage ($page_handle) {
        if (is_string($page_handle)) {
	    if (in_array($page_handle, $this->_excluded_pages))
        	return;             // exclude page.
            $dbi = $GLOBALS['request']->getDbh();
            $page_handle = $dbi->getPage($page_handle);
        } else {
          if (in_array($page_handle->getName(), $this->_excluded_pages))
            return;             // exclude page.
        }

        $group = (int)(count($this->_rows) / $this->_group_rows);
        $class = ($group % 2) ? 'oddrow' : 'evenrow';
        $revision_handle = false;

        if (count($this->_columns) > 1) {
            $row = HTML::tr(array('class' => $class));
            foreach ($this->_columns as $col)
                $row->pushContent($col->format($this, $page_handle, $revision_handle));
        }
        else {
            $col = $this->_columns[0];
            $row = HTML::li(array('class' => $class),
                            $col->_getValue($page_handle, $revision_handle));
        }

        $this->_rows[] = $row;
    }

    function addPages ($page_iter) {
        while ($page = $page_iter->next())
            $this->addPage($page);
    }

    function addPageList (&$list) {
        reset ($list);
        while ($page = next($list))
            $this->addPage($page);
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
    function _initAvailableColumns() {
        if (!empty($this->_types))
            return;

        $this->_types =
            array(
                  'content'
                  => new _PageList_Column_content('content', _("Content")),

                  'remove'
                  => new _PageList_Column_remove('remove', _("Remove")),

                  'checkbox'
                  => new _PageList_Column_checkbox('p', _("Selected")),

                  'pagename'
                  => new _PageList_Column_pagename,

                  'mtime'
                  => new _PageList_Column_time('rev:mtime',
                                               _("Last Modified")),
                  'hits'
                  => new _PageList_Column('hits', _("Hits"), 'right'),

                  'size'
                  => new _PageList_Column_size('size', _("Size"), 'right'),
                                               /*array('align' => 'char', 'char' => ' ')*/

                  'summary'
                  => new _PageList_Column('rev:summary', _("Last Summary")),

                  'version'
                  => new _PageList_Column_version('rev:version', _("Version"),
                                                  'right'),
                  'author'
                  => new _PageList_Column_author('rev:author',
                                                 _("Last Author")),
                  'locked'
                  => new _PageList_Column_bool('locked', _("Locked"),
                                               _("locked")),
                  'minor'
                  => new _PageList_Column_bool('rev:is_minor_edit',
                                               _("Minor Edit"), _("minor")),
                  'markup'
                  => new _PageList_Column('rev:markup', _("Markup"))
                  );
    }

    function _addColumn ($column) {

        $this->_initAvailableColumns();

        if (isset($this->_columns_seen[$column]))
            return false;       // Already have this one.
        $this->_columns_seen[$column] = true;

        if (strstr($column, ':'))
            list ($column, $heading) = explode(':', $column, 2);

        if (!isset($this->_types[$column])) {
            trigger_error(sprintf("%s: Bad column", $column), E_USER_NOTICE);
            return false;
        }

        $col = $this->_types[$column];
        if (!empty($heading))
            $col->setHeading($heading);

        $this->_columns[] = $col;

        return true;
    }

    // make a table given the caption
    function _generateTable($caption) {
        $table = HTML::table(array('cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'class'       => 'pagelist'));
        if ($caption)
            $table->pushContent(HTML::caption(array('align'=>'top'), $caption));

        $row = HTML::tr();
        foreach ($this->_columns as $col) {
            // Todo: add links to resort the table
            $row->pushContent($col->heading());
            $table_summary[] = $col->_heading;
        }
        // Table summary for non-visual browsers.
        $table->setAttr('summary', sprintf(_("Columns: %s."), implode(", ", $table_summary)));

        $table->pushContent(HTML::thead($row),
                            HTML::tbody(false, $this->_rows));
        return $table;
    }

    function _generateList($caption) {
        $list = HTML::ul(array('class' => 'pagelist'), $this->_rows);
        return $caption ? HTML(HTML::p($caption), $list) : $list;
    }

    function _emptyList($caption) {
        $html = HTML();
        if ($caption)
            $html->pushContent(HTML::p($caption));
        if ($this->_messageIfEmpty)
            $html->pushContent(HTML::blockquote(HTML::p($this->_messageIfEmpty)));
        return $html;
    }
};

/* List pages with checkboxes to select from.
 * Todo: All, None jscript buttons.
 */

class PageList_Selectable
extends PageList {

    function PageList_Selectable ($columns=false, $exclude=false) {
        PageList::PageList($columns,$exclude);
    }

    function addPageList ($array) {
        while (list($pagename,$selected) = each($array)) {
            if ($selected) $this->addPageSelected($pagename);
            $this->addPage($pagename);
        }
    }

    function addPageSelected ($pagename) {
        $this->_selected[$pagename] = 1;
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
