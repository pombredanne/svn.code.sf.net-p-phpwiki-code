<?php rcs_id('$Id: PageList.php,v 1.54 2004-02-15 21:41:29 rurban Exp $');

/**
 * List a number of pagenames, optionally as table with various columns.
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
 * 'remove'   _("Remove") //todo: move this admin action away, not really an info column
 *
 * 'checkbox'  A selectable checkbox appears at the left.
 * 'all'       All columns except remove, content and renamed_pagename
 * 'most'      pagename, mtime, author, size, hits, ...
 * 'some'      pagename, mtime, author
 *
 * FIXME: In this refactoring I have un-implemented _ctime, _cauthor, and
 * number-of-revision.  Note the _ctime and _cauthor as they were implemented
 * were somewhat flawed: revision 1 of a page doesn't have to exist in the
 * database.  If lots of revisions have been made to a page, it's more than likely
 * that some older revisions (include revision 1) have been cleaned (deleted).
 *
 * TODO: limit, offset, rows arguments for multiple pages/multiple rows.
 *       check PagePerm "list" access-type
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
            // multiple comma-delimited sortby args: "+hits,+pagename"
            // asc or desc: +pagename, -pagename
            $sortby = PageList::sortby($this->_field,'flip_order');
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
        $heading = HTML::input(array('type'  => 'button',
                                     'title' => _("Click to de-/select all pages"),
                                     'name'  => $default_heading,
                                     'value' => $default_heading,
                                     'onclick' => "flipAll(this.form)"
                                     ));
        $this->_PageList_Column($field, $heading, 'center');
    }
    function _getValue ($pagelist, $page_handle, &$revision_handle) {
        $pagename = $page_handle->getName();
        if (!empty($pagelist->_selected[$pagename])) {
            return HTML::input(array('type' => 'checkbox',
                                     'name' => $this->_name . "[$pagename]",
                                     'value' => $pagename,
                                     'checked' => 'CHECKED'));
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
// only for WikiAdminRemove or WikiAdminSelect
class _PageList_Column_remove extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        return Button(array('action' => 'remove'), _("Remove"),
                      $page_handle->getName());
    }
};

// only for WikiAdminRename
class _PageList_Column_renamed_pagename extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        $post_args = $GLOBALS['request']->getArg('admin_rename');
        $value = str_replace($post_args['from'], $post_args['to'],$page_handle->getName());
        return HTML::div(" => ",HTML::input(array('type' => 'text',
                                                  'name' => 'rename[]',
                                                  'value' => $value)));
    }
};

// Output is hardcoded to limit of first 50 bytes. Otherwise
// on very large Wikis this will fail if used with AllPages
// (PHP memory limit exceeded)
class _PageList_Column_content extends _PageList_Column {
    function _PageList_Column_content ($field, $default_heading, $align = false) {
        _PageList_Column::_PageList_Column($field, $default_heading, $align);
        $this->bytes = 50;
        if ($field == 'content')
            $this->_heading .= sprintf(_(" ... first %d bytes"),
                                       $this->bytes);
        elseif ($field == 'hi_content') {
            $search = $_POST['admin_replace']['from'];
            $this->_heading .= sprintf(_(" ... around %s"),
                                       '»'.$search.'«');
        }
    }
    function _getValue ($page_handle, &$revision_handle) {
        if (!$revision_handle)
            $revision_handle = $page_handle->getCurrentRevision();
        // Not sure why implode is needed here, I thought
        // getContent() already did this, but it seems necessary.
        $c = implode("\n", $revision_handle->getContent());
        if ($this->_field == 'hi_content') {
            $search = $_POST['admin_replace']['from'];
            if ($search and ($i = strpos($c,$search))) {
                $l = strlen($search);
                $j = max(0,$i - ($this->bytes / 2));
                return HTML::div(array('style' => 'font-size:x-small'),
                                 HTML::div(array('class' => 'transclusion'),
                                           HTML::span(substr($c, $j, ($this->bytes / 2))),
                                           HTML::span(array("style"=>"background:yellow"),$search),
                                           HTML::span(substr($c, $i+$l, ($this->bytes / 2))))
                                 );
            } else {
                $c = sprintf(_("%s not found"),
                             '»'.$search.'«');
                return HTML::div(array('style' => 'font-size:x-small','align'=>'center'),
                                 $c);
            }
        } elseif (($len = strlen($c)) > $this->bytes) {
            $c = substr($c, 0, $this->bytes);
        }
        include_once('lib/BlockParser.php');
        // false --> don't bother processing hrefs for embedded WikiLinks
        $ct = TransformText($c, $revision_handle->get('markup'), false);
        return HTML::div(array('style' => 'font-size:x-small'),
                         HTML::div(array('class' => 'transclusion'), $ct),
                         // Don't show bytes here if size column present too
                         ($this->parent->_columns_seen['size'] or !$len) ? "" :
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
        $this->_initAvailableColumns();
        $symbolic_columns = 
            array(
                  'all' =>  array_diff(array_keys($this->_types),
                                       array('checkbox','remove','renamed_pagename','content')),
                  'most' => array('pagename','mtime','author','size','hits'),
                  'some' => array('pagename','mtime','author')
                  );
        if ($columns) {
            if (!is_array($columns))
                $columns = explode(',', $columns);
            // expand symbolic columns:
            foreach ($symbolic_columns as $symbol => $cols) {
                if (in_array($symbol,$columns)) { // e.g. 'checkbox,all'
                    $columns = array_diff(array_merge($columns,$cols),array($symbol));
                }
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

    function addPage ($page_handle) {
        if (is_string($page_handle)) {
	    if (in_array($page_handle, $this->_excluded_pages))
        	return;             // exclude page.
            $dbi = $GLOBALS['request']->getDbh();
            $page_handle = $dbi->getPage($page_handle);
        } elseif (is_object($page_handle)) {
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

    /** 
     * handles sortby requests for the DB iterator and table header links
     * prefix the column with + or - like "+pagename","-mtime", ...
     * supported column: 'pagename','mtime','hits'
     * supported action: 'flip_order', 'db'
     */
    function sortby ($column, $action) {
        if (substr($column,0,1) == '+') {
            $order = '+'; $column = substr($column,1);
        } elseif (substr($column,0,1) == '-') {
            $order = '-'; $column = substr($column,1);
        }
        if (in_array($column,array('pagename','mtime','hits'))) {
            // default order: +pagename, -mtime, -hits
            if (empty($order))
                if (in_array($column,array('mtime','hits')))
                    $order = '-';
                else
                    $order = '+';
            //TODO: multiple comma-delimited sortby args: "+hits,+pagename"
            if ($action == 'flip_order') {
                return ($order == '+' ? '-' : '+') . $column;
            } elseif ($action == 'db') {
                // asc or desc: +pagename, -pagename
                return $column . ($order == '+' ? ' ASC' : ' DESC');
            }
        }
        return '';
    }

    // echo implode(":",explodeList("Test*",array("xx","Test1","Test2")));
    function explodePageList($input, $perm = false, $sortby = '') {
        // expand wildcards from list of all pages
        if (preg_match('/[\?\*]/',$input)) {
            $dbi = $GLOBALS['request']->getDbh();
            $allPagehandles = $dbi->getAllPages($perm,$sortby);
            while ($pagehandle = $allPagehandles->next()) {
                $allPages[] = $pagehandle->getName();
            }
            return explodeList($input, $allPages);
        } else {
            //TODO: do the sorting
            return explode(',',$input);
        }
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

                  'hi_content'
                  => new _PageList_Column_content('hi_content', _("Content")),
                  
                  'remove'
                  => new _PageList_Column_remove('remove', _("Remove")),

                  'renamed_pagename'
                  => new _PageList_Column_renamed_pagename('rename', _("Rename to")),

                  'checkbox'
                  => new _PageList_Column_checkbox('p', _("Select")),

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

        //Warning: This is quite fragile. It depends solely on a private variable
        //         in ->_addColumn()
        if (in_array('checkbox',$this->_columns_seen)) {
            $table->pushContent($this->_jsFlipAll());
        }
        $row = HTML::tr();
        $table_summary = array();
        foreach ($this->_columns as $col) {
            $row->pushContent($col->heading());
            if (is_string($col->_heading))
                $table_summary[] = $col->_heading;
        }
        // Table summary for non-visual browsers.
        $table->setAttr('summary', sprintf(_("Columns: %s."), 
                                           implode(", ", $table_summary)));

        $table->pushContent(HTML::thead($row),
                            HTML::tbody(false, $this->_rows));
        return $table;
    }

    function _jsFlipAll() {
      return JavaScript("
function flipAll(formObj) {
  var isFirstSet = -1;
  for (var i=0;i < formObj.length;i++) {
      fldObj = formObj.elements[i];
      if (fldObj.type == 'checkbox') { 
         if (isFirstSet == -1)
           isFirstSet = (fldObj.checked) ? true : false;
         fldObj.checked = (isFirstSet) ? false : true;
       }
   }
}");
    }

    function _generateList($caption) {
        $list = HTML::ul(array('class' => 'pagelist'), $this->_rows);
        $out = HTML();
        //Warning: This is quite fragile. It depends solely on a private variable
        //         in ->_addColumn()
        if (in_array('checkbox',$this->_columns_seen)) {
            $out->pushContent($this->_jsFlipAll());
        }
        if ($caption)
            $out->pushContent(HTML::p($caption));
        $out->pushContent($list);
        return $out;
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
        if ($columns) {
            if (!is_array($columns))
                $columns = explode(',', $columns);
            if (!in_array('checkbox',$columns))
                array_unshift($columns,'checkbox');
        } else {
            $columns = array('checkbox','pagename');
        }
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
    //Todo:
    //insert javascript when clicked on Selected Select/Deselect all
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
