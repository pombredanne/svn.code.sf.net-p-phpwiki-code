<?php
/**
 * Copyright © 2004-2010 $ThePhpWikiProgrammingTeam
 * Copyright © 2008-2010 Marc-Etienne Vargenau, Alcatel-Lucent
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

/**
 * List a number of pagenames, optionally as table with various columns.
 *
 * See pgsrc/Help%2FPageList for arguments and details
 *
 * FIXME: In this refactoring I (Jeff) have un-implemented _ctime, _cauthor, and
 * number-of-revision.  Note the _ctime and _cauthor as they were implemented
 * were somewhat flawed: revision 1 of a page doesn't have to exist in the
 * database.  If lots of revisions have been made to a page, it's more than likely
 * that some older revisions (include revision 1) have been cleaned (deleted).
 *
 * DONE:
 *   paging support: limit, offset args
 *   check PagePerm "list" access-type,
 *   all columns are sortable. Thanks to the wikilens team.
 *   cols > 1, comma, azhead, ordered (OL lists)
 *   ->supportedArgs() which arguments are supported, so that the plugin
 *                     doesn't explictly need to declare it
 *   added slice option when the page_iter (e.g. ->_pages) is already sliced.
 *
 * TODO:
 *   fix sortby logic, fix multiple sortby and other paging args per page.
 *   info=relation,linkto nopage=1
 *   use custom format method (RecentChanges, rss, ...)
 *
 * FIXED:
 *   fix memory exhaustion on large pagelists with old --memory-limit php's only.
 *   Status: improved 2004-06-25 16:19:36 rurban
 */
class _PageList_Column_base
{
    public $_tdattr = array();
    public $_field;

    public function __construct($default_heading, $align = false)
    {
        $this->_heading = $default_heading;

        if ($align) {
            $this->_tdattr['class'] = "align-".$align;
        }
    }

    public function format($pagelist, $page_handle, $revision_handle)
    {
        return HTML::td(
            $this->_tdattr,
            $this->_getValue($page_handle, $revision_handle)
        );
    }

    public function getHeading()
    {
        return $this->_heading;
    }

    public function setHeading($heading)
    {
        $this->_heading = $heading;
    }

    // old-style heading
    public function heading()
    {
        global $request;
        // allow sorting?
        if (1 /* or in_array($this->_field, PageList::sortable_columns())*/) {
            // multiple comma-delimited sortby args: "+hits,+pagename"
            // asc or desc: +pagename, -pagename
            $sortby = PageList::sortby($this->_field, 'flip_order');
            //Fixme: pass all also other GET args along. (limit, p[])
            //TODO: support GET and POST
            $s = HTML::a(
                array('href' =>
                $request->getURLtoSelf(array('sortby' => $sortby)),
                    'class' => 'pagetitle',
                    'title' => sprintf(_("Sort by %s"), $this->_field)),
                HTML::u($this->_heading)
            );
        } else {
            $s = HTML::u($this->_heading);
        }
        return HTML::th($s);
    }

    // new grid-style sortable heading
    // TODO: via activeui.js ? (fast dhtml sorting)
    public function button_heading($pagelist, $colNum)
    {
        global $WikiTheme, $request;
        // allow sorting?
        if (!$WikiTheme->DUMP_MODE /* or in_array($this->_field, PageList::sortable_columns()) */) {
            // TODO: add to multiple comma-delimited sortby args: "+hits,+pagename"
            $src = false;
            $noimg_src = $WikiTheme->getButtonURL('sort_none');
            if ($noimg_src) {
                $noimg = HTML::img(array('src' => $noimg_src, 'alt' => '.'));
            } else {
                $noimg = HTML::raw('');
            }
            $reverse = false;
            if ($pagelist->sortby($colNum, 'check')) { // show icon? request or plugin arg
                $sortby = $pagelist->sortby($colNum, 'flip_order');
                $desc = (substr($sortby, 0, 1) == '-'); // +pagename or -pagename
                $src = $WikiTheme->getButtonURL($desc ? 'sort_up' : 'sort_down');
                $reverse = $desc;
            } else {
                // initially unsorted
                $sortby = $pagelist->sortby($colNum, 'get');
            }
            if (!$src) {
                $img = $noimg;
                $reverse = false;
                $img->setAttr('alt', ".");
            } else {
                $img = HTML::img(array('src' => $src,
                    'alt' => _("Click to reverse sort order")));
            }
            if ($reverse) {
                $title = _("Click to sort by reverse %s");
            } else {
                $title = _("Click to sort by %s");
            }
            $s = HTML::a(
                array('href' =>
                //Fixme: pass all also other GET args along. (limit is ok, p[])
                $request->getURLtoSelf(array('sortby' => $sortby,
                    'id' => $pagelist->id)),
                    'class' => 'gridbutton',
                    'title' => sprintf($title, $this->_heading)),
                $this->_heading,
                $img
            );
        } else {
            $s = HTML($this->_heading);
        }
        return HTML::th(array('class' => 'gridbutton'), $s);
    }

    /**
     * Take two columns of this type and compare them.
     * An undefined value is defined to be < than the smallest defined value.
     * This base class _compare only works if the value is simple (e.g., a number).
     *
     * @param  mixed $colvala  $this->_getValue() of column a
     * @param  mixed $colvalb  $this->_getValue() of column b
     *
     * @return int -1 if $a < $b, 1 if $a > $b, 0 otherwise.
     */
    public function _compare($colvala, $colvalb)
    {
        if (is_string($colvala)) {
            return strcmp($colvala, $colvalb);
        }
        $ret = 0;
        if (($colvala === $colvalb) || (!isset($colvala) && !isset($colvalb))) {
            ;
        } else {
            $ret = (!isset($colvala) || ($colvala < $colvalb)) ? -1 : 1;
        }
        return $ret;
    }
}

class _PageList_Column extends _PageList_Column_base
{
    public function __construct($field, $default_heading, $align = false)
    {
        parent::__construct($default_heading, $align);

        $this->_need_rev = substr($field, 0, 4) == 'rev:';
        $this->_iscustom = substr($field, 0, 7) == 'custom:';
        if ($this->_iscustom) {
            $this->_field = substr($field, 7);
        } elseif ($this->_need_rev) {
            $this->_field = substr($field, 4);
        } else {
            $this->_field = $field;
        }
    }

    /**
     * @param WikiDB_Page $page_handle
     * @param WikiDB_PageRevision $revision_handle
     * @return mixed
     */
    public function _getValue($page_handle, $revision_handle)
    {
        if ($this->_need_rev) {
            if (!$revision_handle) {
                // columns which need the %content should override this. (size, hi_content)
                $revision_handle = $page_handle->getCurrentRevision(false);
            }
            return $revision_handle->get($this->_field);
        } else {
            return $page_handle->get($this->_field);
        }
    }

    /**
     * @param WikiDB_Page $page_handle
     * @param WikiDB_PageRevision $revision_handle
     * @return int|string
     */
    public function _getSortableValue($page_handle, $revision_handle)
    {
        $val = $this->_getValue($page_handle, $revision_handle);
        if ($this->_field == 'hits') {
            return (int)$val;
        } elseif (is_object($val) && method_exists($val, 'asString')) {
            return $val->asString();
        } else {
            return (string)$val;
        }
    }
}

/* overcome a call_user_func limitation by not being able to do:
 * call_user_func_array(array(&$class, $class_name), $params);
 * So we need $class = new $classname($params);
 * And we add a 4th param to get at the parent $pagelist object
 */
class _PageList_Column_custom extends _PageList_Column
{
    public function __construct($params)
    {
        $this->_pagelist =& $params[3];
        parent::__construct($params[0], $params[1], $params[2]);
    }
}

class _PageList_Column_size extends _PageList_Column
{
    public function format($pagelist, $page_handle, $revision_handle)
    {
        return HTML::td(
            $this->_tdattr,
            $this->_getValuePageList($pagelist, $page_handle, $revision_handle)
        );
    }

    public function _getValuePageList($pagelist, $page_handle, $revision_handle)
    {
        if (!$revision_handle or (!$revision_handle->_data['%content']
            or $revision_handle->_data['%content'] === true)
        ) {
            $revision_handle = $page_handle->getCurrentRevision(true);
            unset($revision_handle->_data['%pagedata']['_cached_html']);
        }
        $size = $this->_getSize($revision_handle);
        // we can safely purge the content when it is not sortable
        if (empty($pagelist->_sortby[$this->_field])) {
            unset($revision_handle->_data['%content']);
        }
        return $size;
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        if (!$revision_handle) {
            $revision_handle = $page_handle->getCurrentRevision();
        }
        return (empty($revision_handle->_data['%content']))
            ? 0 : strlen($revision_handle->_data['%content']);
    }

    public function _getSize($revision_handle)
    {
        $bytes = @strlen($revision_handle->_data['%content']);
        return ByteFormatter($bytes);
    }
}

class _PageList_Column_bool extends _PageList_Column
{
    public function __construct($field, $default_heading, $text = 'yes')
    {
        parent::__construct($field, $default_heading, 'center');
        $this->_textIfTrue = $text;
        $this->_textIfFalse = new RawXml('&#8212;'); //mdash
    }

    public function _getValue($page_handle, $revision_handle)
    {
        //FIXME: check if $this is available in the parent (->need_rev)
        $val = parent::_getValue($page_handle, $revision_handle);
        return $val ? $this->_textIfTrue : $this->_textIfFalse;
    }
}

class _PageList_Column_checkbox extends _PageList_Column
{
    public function __construct($field, $default_heading, $name = 'p')
    {
        $this->_name = $name;
        $heading = HTML::input(array('type' => 'button',
            'title' => _("Click to de-/select all pages"),
            'name' => $default_heading,
            'value' => $default_heading,
            'onclick' => "flipAll(this.form)"
        ));
        parent::__construct($field, $heading, 'center');
    }

    public function _getValuePageList($pagelist, $page_handle, $revision_handle)
    {
        $pagename = $page_handle->getName();
        $selected = !empty($pagelist->_selected[$pagename]);
        if (strstr($pagename, '[') or strstr($pagename, ']')) {
            $pagename = str_replace(array('[', ']'), array('%5B', '%5D'), $pagename);
        }
        if ($selected) {
            return HTML::input(array('type' => 'checkbox',
                'name' => $this->_name . "[$pagename]",
                'value' => 1,
                'checked' => 'checked'));
        } else {
            return HTML::input(array('type' => 'checkbox',
                'name' => $this->_name . "[$pagename]",
                'value' => 1));
        }
    }

    public function format($pagelist, $page_handle, $revision_handle)
    {
        return HTML::td(
            $this->_tdattr,
            $this->_getValuePageList($pagelist, $page_handle, $revision_handle)
        );
    }

    // don't sort this javascript button
    public function button_heading($pagelist, $colNum)
    {
        $s = HTML($this->_heading);
        return HTML::th(array('class' => 'gridbutton'), $s);
    }
}

class _PageList_Column_time extends _PageList_Column
{
    public function __construct($field, $default_heading)
    {
        parent::__construct($field, $default_heading, 'right');
        global $WikiTheme;
        $this->WikiTheme = &$WikiTheme;
    }

    public function _getValue($page_handle, $revision_handle)
    {
        $time = parent::_getValue($page_handle, $revision_handle);
        return $this->WikiTheme->formatDateTime($time);
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return parent::_getValue($page_handle, $revision_handle);
    }
}

class _PageList_Column_version extends _PageList_Column
{
    public function _getValue($page_handle, $revision_handle)
    {
        if (!$revision_handle) {
            $revision_handle = $page_handle->getCurrentRevision();
        }
        return $revision_handle->getVersion();
    }
}

// Output is hardcoded to limit of first 50 bytes. Otherwise
// on very large Wikis this will fail if used with AllPages
// (PHP memory limit exceeded)
class _PageList_Column_content extends _PageList_Column
{
    public function __construct(
        $field,
        $default_heading,
        $align = false,
        $search = false,
        $hilight_re = false
    )
    {
        parent::__construct($field, $default_heading, $align);
        $this->bytes = 50;
        $this->search = $search;
        $this->hilight_re = $hilight_re;
        if ($field == 'content') {
            $this->_heading .= sprintf(
                _(" ... first %d bytes"),
                $this->bytes
            );
        } elseif ($field == 'rev:hi_content') {
            global $HTTP_POST_VARS;
            if (!$this->search and !empty($HTTP_POST_VARS['admin_replace'])) {
                $this->search = $HTTP_POST_VARS['admin_replace']['from'];
            }
            $this->_heading .= sprintf(_(" ... around “%s”"), $this->search);
        }
    }

    public function _getValue($page_handle, $revision_handle)
    {
        if (!$revision_handle or (!$revision_handle->_data['%content']
            or $revision_handle->_data['%content'] === true)
        ) {
            $revision_handle = $page_handle->getCurrentRevision();
        }
        if (!empty($revision_handle->_data['%pagedata'])) {
            $revision_handle->_data['%pagedata']['_cached_html'] = '';
        }
        $c = $revision_handle->getPackedContent();

        if ($this->_field == 'hi_content') {
            $search = $this->search;
            $score = '';
            if (is_object($page_handle) and !empty($page_handle->score)) {
                $score = $page_handle->score;
            } elseif (is_array($page_handle) and !empty($page_handle['score'])) {
                $score = $page_handle['score'];
            }

            $hilight_re = $this->hilight_re;
            // use the TextSearchQuery highlighter
            if ($search and $hilight_re) {
                $matches = preg_grep("/$hilight_re/i", $revision_handle->getContent());
                $html = array();
                foreach (array_slice($matches, 0, 5) as $line) {
                    $line = WikiPlugin_FullTextSearch::highlight_line($line, $hilight_re);
                    $html[] = HTML::p(HTML::small(array('class' => 'search-context'), $line));
                }
                if ($score) {
                    $html[] = HTML::small(sprintf("... [%0.1f]", $score));
                }
                return $html;
            }
            // Remove special characters so that highlighting works
            $search = preg_replace('/^[\^\*]/', '', $search);
            $search = preg_replace('/[\^\*]$/', '', $search);
            if ($search and ($i = strpos(strtolower($c), strtolower($search))) !== false) {
                $l = strlen($search);
                $j = max(0, $i - ($this->bytes / 2));
                return HTML::div(
                    array('style' => 'font-size:x-small'),
                    HTML::div(
                        array('class' => 'transclusion'),
                        HTML::span(($j ? '...' : '')
                            . substr($c, $j, ($j ? $this->bytes / 2 : $i))),
                        HTML::span(
                            array("style" => "background:yellow"),
                            substr($c, $i, $l)
                        ),
                        HTML::span(substr($c, $i + $l, ($this->bytes / 2))
                            . "..." . " "
                            . ($score ? sprintf("[%0.1f]", $score) : ""))
                    )
                );
            } else {
                $c = sprintf(_("“%s” not found"), $search);
                return HTML::div(
                    array('class' => 'align-center'),
                    $c . " " . ($score ? sprintf("[%0.1f]", $score) : "")
                );
            }
        } else {
            $len = strlen($c);
            if ($len > $this->bytes) {
                $c = substr($c, 0, $this->bytes);
            }
        }
        include_once 'lib/BlockParser.php';
        // false --> don't bother processing hrefs for embedded WikiLinks
        $ct = TransformText($c);
        if (empty($pagelist->_sortby[$this->_field])) {
            unset($revision_handle->_data['%pagedata']['_cached_html']);
        }
        return HTML::div(
            array('style' => 'font-size:x-small'),
            HTML::div(array('class' => 'transclusion'), $ct),
            // Don't show bytes here if size column present too
            ($this->parent->_columns_seen['size'] or !$len) ? "" :
                ByteFormatter($len, /*$longformat = */
                    true)
        );
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        if (is_object($page_handle) and !empty($page_handle->score)) {
            return $page_handle->score;
        } elseif (is_array($page_handle) and !empty($page_handle['score'])) {
            return $page_handle['score'];
        } else {
            return substr(parent::_getValue($page_handle, $revision_handle), 0, 50);
        }
    }
}

class _PageList_Column_author extends _PageList_Column
{
    public function __construct($field, $default_heading, $align = false)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        parent::__construct($field, $default_heading, $align);
        $this->dbi =& $request->getDbh();
    }

    public function _getValue($page_handle, $revision_handle)
    {
        $author = parent::_getValue($page_handle, $revision_handle);
        if ($this->dbi->isWikiPage($author)) {
            return WikiLink($author);
        } else {
            return $author;
        }
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return parent::_getValue($page_handle, $revision_handle);
    }
}

class _PageList_Column_owner extends _PageList_Column_author
{
    public function _getValue($page_handle, $revision_handle)
    {
        $author = $page_handle->getOwner();
        if ($this->dbi->isWikiPage($author)) {
            return WikiLink($author);
        } else {
            return $author;
        }
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return parent::_getValue($page_handle, $revision_handle);
    }
}

class _PageList_Column_creator extends _PageList_Column_author
{
    public function _getValue($page_handle, $revision_handle)
    {
        $author = $page_handle->getCreator();
        if ($this->dbi->isWikiPage($author)) {
            return WikiLink($author);
        } else {
            return $author;
        }
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return parent::_getValue($page_handle, $revision_handle);
    }
}

class _PageList_Column_pagename extends _PageList_Column_base
{
    public $_field = 'pagename';

    public function __construct()
    {
        parent::__construct(_("Page Name"));
        global $request;
        $this->dbi = &$request->getDbh();
    }

    public function _getValue($page_handle, $revision_handle)
    {
        if ($this->dbi->isWikiPage($page_handle->getName())) {
            return WikiLink($page_handle);
        } else {
            return WikiLink($page_handle, 'unknown');
        }
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return $page_handle->getName();
    }

    /**
     * Compare two pagenames for sorting.  See _PageList_Column::_compare.
     **/
    public function _compare($colvala, $colvalb)
    {
        return strcmp($colvala, $colvalb);
    }
}

class _PageList_Column_perm extends _PageList_Column
{
    public function _getValue($page_handle, $revision_handle)
    {
        $perm_array = pagePermissions($page_handle->_pagename);
        return pagePermissionsSimpleFormat(
            $perm_array,
            $page_handle->get('author'),
            $page_handle->get('group')
        );
    }
}

class _PageList_Column_acl extends _PageList_Column
{
    public function _getValue($page_handle, $revision_handle)
    {
        $perm_tree = pagePermissions($page_handle->_pagename);

        list($type, $perm) = pagePermissionsAcl($perm_tree[0], $perm_tree);
        if ($type == 'inherited') {
            $type = sprintf(_("page permission inherited from %s"), $perm_tree[1][0]);
        } elseif ($type == 'page') {
            $type = _("individual page permission");
        } elseif ($type == 'default') {
            $type = _("default page permission");
        }
        $result = HTML::span();
        $result->pushContent($type);
        $result->pushContent(HTML::br());
        $result->pushContent($perm->asAclLines());
        return $result;
    }
}

class PageList
{
    public $_group_rows = 3;
    public $_columns = array();
    private $_columnsMap = array(); // Maps column name to column number.
    private $_excluded_pages = array();
    public $_pages = array();
    public $_caption = "";
    public $_types = array();
    public $_options = array();
    public $_selected = array();
    public $_sortby = array();
    public $_maxlen = 0;
    private $_messageIfEmpty = '';
    public $_columns_seen = array();
    private $_stack;

    public function __construct($columns = array(), $exclude = array(), $options = array())
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        // unique id per pagelist on each page.
        if (!isset($request->_pagelist)) {
            $request->_pagelist = 0;
        } else {
            $request->_pagelist++;
        }
        $this->id = $request->_pagelist;
        if ($request->getArg('count')) {
            $options['count'] = $request->getArg('count');
        }
        if ($options) {
            $this->_options = $options;
        }

        $this->initAvailableColumns();
        // let plugins predefine only certain objects, such its own custom pagelist columns
        $symbolic_columns =
            array(
                'all' => array_diff(array_keys($this->_types), // all but...
                    array('checkbox', 'remove', 'renamed_pagename',
                        'content', 'hi_content', 'perm', 'acl')),
                'most' => array('pagename', 'mtime', 'author', 'hits'),
                'some' => array('pagename', 'mtime', 'author')
            );
        if (isset($this->_options['listtype'])
            and $this->_options['listtype'] == 'dl'
        ) {
            $this->_options['nopage'] = 1;
        }
        if ($columns) {
            if (!is_array($columns)) {
                $columns = explode(',', $columns);
            }
            // expand symbolic columns:
            foreach ($symbolic_columns as $symbol => $cols) {
                if (in_array($symbol, $columns)) { // e.g. 'checkbox,all'
                    $columns = array_diff(array_merge($columns, $cols), array($symbol));
                }
            }
            unset($cols);
            if (empty($this->_options['nopage']) and !in_array('pagename', $columns)) {
                $this->_addColumn('pagename');
            }
            foreach ($columns as $col) {
                if (!empty($col)) {
                    $this->_addColumn($col);
                }
            }
            unset($col);
        }
        // If 'pagename' is already present, _addColumn() will not add it again
        if (empty($this->_options['nopage'])) {
            $this->_addColumn('pagename');
        }

        if (!empty($this->_options['types'])) {
            foreach ($this->_options['types'] as $type) {
                $this->_types[$type->_field] = $type;
                $this->_addColumn($type->_field);
            }
            unset($this->_options['types']);
        }

        global $request;
        // explicit header options: ?id=x&sortby=... override options[]
        // support multiple sorts. check multiple, no nested elseif
        if (($this->id == $request->getArg("id"))
            and $request->getArg('sortby')
        ) {
            // add it to the front of the sortby array
            $this->sortby($request->getArg('sortby'), 'init');
            $this->_options['sortby'] = $request->getArg('sortby');
        } // plugin options
        if (!empty($options['sortby'])) {
            if (empty($this->_options['sortby'])) {
                $this->_options['sortby'] = $options['sortby'];
            }
            $this->sortby($options['sortby'], 'init');
        } // global options
        if (!isset($request->args["id"]) and $request->getArg('sortby')
            and empty($this->_options['sortby'])
        ) {
            $this->_options['sortby'] = $request->getArg('sortby');
            $this->sortby($this->_options['sortby'], 'init');
        }
        // same as above but without the special sortby push, and mutually exclusive (elseif)
        foreach ($this->pagingArgs() as $key) {
            if ($key == 'sortby') {
                continue;
            }
            if (($this->id == $request->getArg("id"))
                and $request->getArg($key)
            ) {
                $this->_options[$key] = $request->getArg($key);
            } // plugin options
            elseif (!empty($options) and !empty($options[$key])) {
                $this->_options[$key] = $options[$key];
            } // global options
            elseif (!isset($request->args["id"]) and $request->getArg($key)) {
                $this->_options[$key] = $request->getArg($key);
            } else {
                $this->_options[$key] = false;
            }
        }
        if ($exclude) {
            if (is_string($exclude) and !is_array($exclude)) {
                $exclude = $this->explodePageList(
                    $exclude,
                    false,
                    $this->_options['sortby'],
                    $this->_options['limit']
                );
            }
            $this->_excluded_pages = $exclude;
        }
        $this->_messageIfEmpty = _("No matches");
    }

    // Currently PageList takes these arguments:
    // 1: info, 2: exclude, 3: hash of options
    // Here we declare which options are supported, so that
    // the calling plugin may simply merge this with its own default arguments
    public static function supportedArgs()
    {
        // Todo: add all supported Columns, like locked, minor, ...
        return array( // Currently supported options:
            /* what columns, what pages */
            'info' => 'pagename',
            'exclude' => '', // also wildcards, comma-separated lists
            // and <!plugin-list !> arrays
            /* select pages by meta-data: */
            'author' => false, // current user by []
            'owner' => false, // current user by []
            'creator' => false, // current user by []

            /* for the sort buttons in <th> */
            'sortby' => '', // same as for WikiDB::getAllPages
            // (unsorted is faster)

            /* PageList pager options:
             * These options may also be given to _generate(List|Table) later
             * But limit and offset might help the query WikiDB::getAllPages()
             */
            'limit' => 50, // number of rows (pagesize)
            'paging' => 'auto', // 'auto'   top + bottom rows if applicable
            //                      // 'top'    top only if applicable
            //                      // 'bottom' bottom only if applicable
            //                      // 'none'   don't page at all
            // (TODO: clarify what if $paging==false ?)

            /* list-style options (with single pagename column only so far) */
            'cols' => 1, // side-by-side display of list (1-3)
            'azhead' => 0, // 1: group by initials
            // 2: provide shortcut links to initials also
            'comma' => 0, // condensed comma-separated list,
            // 1 if without links, 2 if with
            'commasep' => false, // Default: ', '
            'listtype' => '', // ul (default), ol, dl, comma
            'ordered' => false, // OL or just UL lists (ignored for comma)
            'linkmore' => '', // If count>0 and limit>0 display a link with
            // the number of all results, linked to the given pagename.

            'nopage' => false, // for info=col omit the pagename column
            // array_keys($this->_types). filter by columns: e.g. locked=1
            'pagename' => null, // string regex
            'locked' => null,
            'minor' => null,
            'mtime' => null,
            'hits' => null,
            'size' => null,
            'version' => null,
            'external' => null,
        );
    }

    private function pagingArgs()
    {
        return array('sortby', 'limit', 'paging', 'count', 'dosort');
    }

    /**
     * @param    mixed $caption    string or HTML
     */
    public function setCaption($caption)
    {
        $this->_caption = $caption;
    }

    /**
     * @param    mixed $caption    string or HTML
     */
    public function addCaption($caption)
    {
        $this->_caption = HTML($this->_caption, " ", $caption);
    }

    private function getCaption()
    {
        // put the total into the caption if needed
        if (is_string($this->_caption) && strstr($this->_caption, '%d')) {
            return sprintf($this->_caption, $this->getTotal());
        }
        return $this->_caption;
    }

    public function getTotal()
    {
        return !empty($this->_options['count'])
            ? (int)$this->_options['count'] : count($this->_pages);
    }

    public function isEmpty()
    {
        return empty($this->_pages);
    }

    public function addPage($page_handle)
    {
        $pagename = is_string($page_handle) ? $page_handle : $page_handle->getName();
        if (in_array($pagename, $this->pageNames())) {
            return;
        }
        if (!empty($this->_excluded_pages)) {
            if (!in_array($pagename, $this->_excluded_pages)) {
                $this->_pages[] = $page_handle;
            }
        } else {
            $this->_pages[] = $page_handle;
        }
    }

    public function pageNames()
    {
        $pages = array();
        $limit = @$this->_options['limit'];
        foreach ($this->_pages as $page_handle) {
            if (is_object($page_handle)) {
                $pages[] = $page_handle->getName();
            } else {
                $pages[] = $page_handle;
            }
            if ($limit and count($pages) > $limit) {
                break;
            }
        }
        return $pages;
    }

    private function getPageFromHandle($page_handle)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (is_string($page_handle)) {
            if (empty($page_handle)) {
                return $page_handle;
            }
            $page_handle = $request->_dbi->getPage($page_handle);
        }
        return $page_handle;
    }

    /**
     * Take a PageList_Page object, and return an HTML object to display
     * it in a table or list row.
     */
    private function renderPageRow($page_handle, $i = 0)
    {
        $page_handle = $this->getPageFromHandle($page_handle);
        //FIXME. only on sf.net
        if (!is_object($page_handle)) {
            trigger_error("PageList: Invalid page_handle $page_handle", E_USER_WARNING);
            return false;
        }
        if (!isset($page_handle)
            or empty($page_handle)
            or (!empty($this->_excluded_pages)
                and in_array($page_handle->getName(), $this->_excluded_pages))) {
            return false; // exclude page.
        }
        // enforce view permission
        if (!mayAccessPage('view', $page_handle->getName())) {
            return false;
        }

        $group = (int)($i / $this->_group_rows);
        $class = ($group % 2) ? 'oddrow' : 'evenrow';
        $revision_handle = false;
        $this->_maxlen = max($this->_maxlen, strlen($page_handle->getName()));

        if (count($this->_columns) > 1) {
            $row = HTML::tr(array('class' => $class));
            $j = 0;
            foreach ($this->_columns as $col) {
                $col->current_row = $i;
                $col->current_column = $j;
                $row->pushContent($col->format($this, $page_handle, $revision_handle));
                $j++;
            }
        } else {
            $col = $this->_columns[0];
            $col->current_row = $i;
            $col->current_column = 0;
            $row = $col->_getValue($page_handle, $revision_handle);
        }

        return $row;
    }

    /* ignore from, but honor limit */
    public function addPages($page_iter)
    {
        // TODO: if limit check max(strlen(pagename))
        $limit = $page_iter->limit();
        $i = 0;
        if ($limit) {
            list($from, $limit) = $this->limit($limit);
            $this->_options['slice'] = 0;
            $limit += $from;
            while ($page = $page_iter->next()) {
                $i++;
                if ($from and $i < $from) {
                    continue;
                }
                if (!$limit or ($limit and $i < $limit)) {
                    $this->addPage($page);
                }
            }
        } else {
            $this->_options['slice'] = 0;
            while ($page = $page_iter->next()) {
                $this->addPage($page);
            }
        }
        if (!is_array($page_iter->_options)) {
            $this->_options['slice'] = 1;
        }
        if ($i and empty($this->_options['count'])) {
            $this->_options['count'] = $i;
        }
    }

    public function addPageList($list)
    {
        if (empty($list)) {
            return;
        } // Protect reset from a null arg
        if (isset($this->_options['limit'])) { // extract from,count from limit
            list($from, $limit) = WikiDB_backend::limit($this->_options['limit']);
            $limit += $from;
        } else {
            $limit = 0;
        }
        $this->_options['slice'] = 0;
        $i = 0;
        foreach ($list as $page) {
            $i++;
            if ($from and $i < $from) {
                continue;
            }
            if (!$limit or ($limit and $i < $limit)) {
                if (is_object($page)) {
                    $page = $page->_pagename;
                }
                $this->addPage((string)$page);
            }
        }
    }

    private function maxLen()
    {
        global $request;
        $dbi =& $request->getDbh();
        if (is_a($dbi, 'WikiDB_SQL')) {
            extract($dbi->_backend->_table_names);
            $res = $dbi->_backend->_dbh->getOne("SELECT max(length(pagename)) FROM $page_tbl");
            if (DB::isError($res) || empty($res)) {
                return false;
            } else {
                return $res;
            }
        } else {
            return false;
        }
    }

    /**
     * @return bool|WikiDB_Page
     */
    public function first()
    {
        if (count($this->_pages) > 0) {
            return $this->_pages[0];
        }
        return false;
    }

    public function getContent()
    {
        // Note that the <caption> element wants inline content.
        $caption = $this->getCaption();

        if ($this->isEmpty()) {
            return $this->_emptyList($caption);
        } elseif (isset($this->_options['listtype'])
            and in_array($this->_options['listtype'], array('ol', 'ul', 'comma', 'dl'))) {
            return $this->generateList($caption);
        } elseif (count($this->_columns) == 1) {
            return $this->generateList($caption);
        } else {
            return $this->_generateTable($caption);
        }
    }

    public function printXML()
    {
        PrintXML($this->getContent());
    }

    public function asXML()
    {
        return AsXML($this->getContent());
    }

    /**
     * Handle sortby requests for the DB iterator and table header links.
     * Prefix the column with + or - like "+pagename","-mtime", ...
     *
     * Supported actions:
     *   'init'       :   unify with predefined order. "pagename" => "+pagename"
     *   'flip_order' :   "mtime" => "+mtime" => "-mtime" ...
     *   'db'         :   "-pagename" => "pagename DESC"
     *   'check'      :
     *
     * Now all columns are sortable. (patch by Dan Frankowski)
     * Some columns have native DB backend methods, some not.
     */
    public function sortby($column, $action, $valid_fields = false)
    {
        global $request;

        if (empty($column)) {
            return '';
        }
        if (is_int($column)) {
            $column = $this->_columns[$column - 1]->_field;
        }

        // support multiple comma-delimited sortby args: "+hits,+pagename"
        // recursive concat
        if (strstr($column, ',')) {
            $result = ($action == 'check') ? true : array();
            foreach (explode(',', $column) as $col) {
                if ($action == 'check') {
                    $result = $result && $this->sortby($col, $action, $valid_fields);
                } else {
                    $result[] = $this->sortby($col, $action, $valid_fields);
                }
            }
            // 'check' returns true/false for every col. return true if all are true.
            // i.e. the unsupported 'every' operator in functional languages.
            if ($action == 'check') {
                return $result;
            } else {
                return join(",", $result);
            }
        }
        if (substr($column, 0, 1) == '+') {
            $order = '+';
            $column = substr($column, 1);
        } elseif (substr($column, 0, 1) == '-') {
            $order = '-';
            $column = substr($column, 1);
        }
        // default initial order: +pagename, -mtime, -hits
        if (empty($order)) {
            if (!empty($this->_sortby[$column])) {
                $order = $this->_sortby[$column];
            } else {
                if (in_array($column, array('mtime', 'hits'))) {
                    $order = '-';
                } else {
                    $order = '+';
                }
            }
        }
        if ($action == 'get') {
            return $order . $column;
        } elseif ($action == 'flip_order') {
            return ($order == '+' ? '-' : '+') . $column;
        } elseif ($action == 'init') { // only allowed from PageList::PageList
            $this->_sortby[$column] = $order; // forces show icon
            return $order . $column;
        } elseif ($action == 'check') { // show icon?
            //if specified via arg or if clicked
            return (!empty($this->_sortby[$column]) or $this->sortby($column, 'clicked'));
        } elseif ($action == 'clicked') { // flip sort order?
            global $request;
            $arg = $request->getArg('sortby');
            return ($arg
                and strstr($arg, $column)
                    and (!isset($request->args['id'])
                        or $this->id == $request->getArg('id')));
        } elseif ($action == 'db') {
            // Performance enhancement: use native DB sort if possible.
            if (($valid_fields and in_array($column, $valid_fields))
                or (method_exists($request->_dbi->_backend, 'sortable_columns')
                    and (in_array($column, $request->_dbi->_backend->sortable_columns())))
            ) {
                // omit this sort method from the sortPages call at rendering
                // asc or desc: +pagename, -pagename
                return $column . ($order == '+' ? ' ASC' : ' DESC');
            } else {
                return '';
            }
        }
        return '';
    }

    /* Splits pagelist string into array.
     * Test* or Test1,Test2
     * Limitation: Doesn't split into comma-sep and then expand wildcards.
     * "Test1*,Test2*" is expanded into TextSearch "Test1* Test2*"
     */
    public static function explodePageList(
        $input,
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    )
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (empty($input)) {
            return array();
        }
        if (is_array($input)) {
            return $input;
        }
        // expand wildcards from list of all pages
        if (preg_match('/[\?\*]/', $input) or substr($input, 0, 1) == "^") {
            include_once 'lib/TextSearchQuery.php';
            $search = new TextSearchQuery(
                str_replace(",", " or ", $input),
                true,
                (substr($input, 0, 1) == "^") ? 'posix' : 'glob'
            );
            $dbi = $request->getDbh();
            $iter = $dbi->titleSearch($search, $sortby, $limit, $exclude);
            $pages = array();
            while ($pagehandle = $iter->next()) {
                $pages[] = trim($pagehandle->getName());
            }
            return $pages;
        } else {
            //TODO: do the sorting, normally not needed if used for exclude only
            return array_map("trim", explode(',', $input));
        }
    }

    // TODO: optimize getTotal => store in count
    public static function allPagesByAuthor(
        $wildcard,
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    )
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $dbi = $request->getDbh();
        $allPagehandles = $dbi->getAllPages($include_empty, $sortby, $limit, $exclude);
        $allPages = array();
        if ($wildcard === '[]') {
            $wildcard = $request->_user->getAuthenticatedId();
            if (!$wildcard) {
                return $allPages;
            }
        }
        $do_glob = preg_match('/[\?\*]/', $wildcard);
        while ($pagehandle = $allPagehandles->next()) {
            $name = $pagehandle->getName();
            $author = $pagehandle->getAuthor();
            if ($author) {
                if ($do_glob) {
                    if (glob_match($wildcard, $author)) {
                        $allPages[] = $name;
                    }
                } elseif ($wildcard == $author) {
                    $allPages[] = $name;
                }
            }
            // TODO: purge versiondata_cache
        }
        return $allPages;
    }

    public static function allPagesByOwner(
        $wildcard,
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    )
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $dbi = $request->getDbh();
        $allPagehandles = $dbi->getAllPages($include_empty, $sortby, $limit, $exclude);
        $allPages = array();
        if ($wildcard === '[]') {
            $wildcard = $request->_user->getAuthenticatedId();
            if (!$wildcard) {
                return $allPages;
            }
        }
        $do_glob = preg_match('/[\?\*]/', $wildcard);
        while ($pagehandle = $allPagehandles->next()) {
            $name = $pagehandle->getName();
            $owner = $pagehandle->getOwner();
            if ($owner) {
                if ($do_glob) {
                    if (glob_match($wildcard, $owner)) {
                        $allPages[] = $name;
                    }
                } elseif ($wildcard == $owner) {
                    $allPages[] = $name;
                }
            }
        }
        return $allPages;
    }

    public static function allPagesByCreator(
        $wildcard,
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    )
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $dbi = $request->getDbh();
        $allPagehandles = $dbi->getAllPages($include_empty, $sortby, $limit, $exclude);
        $allPages = array();
        if ($wildcard === '[]') {
            $wildcard = $request->_user->getAuthenticatedId();
            if (!$wildcard) {
                return $allPages;
            }
        }
        $do_glob = preg_match('/[\?\*]/', $wildcard);
        while ($pagehandle = $allPagehandles->next()) {
            $name = $pagehandle->getName();
            $creator = $pagehandle->getCreator();
            if ($creator) {
                if ($do_glob) {
                    if (glob_match($wildcard, $creator)) {
                        $allPages[] = $name;
                    }
                } elseif ($wildcard == $creator) {
                    $allPages[] = $name;
                }
            }
        }
        return $allPages;
    }

    // UserPages are pages NOT owned by ADMIN_USER
    public static function allUserPages(
        $include_empty = false,
        $sortby = '',
        $limit = '',
        $exclude = ''
    )
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $dbi = $request->getDbh();
        $allPagehandles = $dbi->getAllPages($include_empty, $sortby, $limit, $exclude);
        $allPages = array();
        while ($pagehandle = $allPagehandles->next()) {
            $name = $pagehandle->getName();
            $owner = $pagehandle->getOwner();
            if ($owner !== ADMIN_USER) {
                $allPages[] = $name;
            }
        }
        return $allPages;
    }

    /** Plugin and theme hooks:
     *  If the pageList is initialized with $options['types'] these types are also initialized,
     *  overriding the standard types.
     */
    private function initAvailableColumns()
    {
        global $customPageListColumns;
        $standard_types =
            array(
                'content'
                => new _PageList_Column_content('rev:content', _("Content")),
                // new: plugin specific column types initialised by the relevant plugins
                /*
                'hi_content' // with highlighted search for SearchReplace
                => new _PageList_Column_content('rev:hi_content', _("Content")),
                'remove'
                => new _PageList_Column_remove('remove', _("Remove")),
                // initialised by the plugin
                'renamed_pagename'
                => new _PageList_Column_renamed_pagename('rename', _("Rename to")),
                */
                'perm'
                => new _PageList_Column_perm('perm', _("Permission")),
                'acl'
                => new _PageList_Column_acl('acl', _("ACL")),
                'checkbox'
                => new _PageList_Column_checkbox('p', _("All")),
                'pagename'
                => new _PageList_Column_pagename(),
                'mtime'
                => new _PageList_Column_time('rev:mtime', _("Last Modified")),
                'hits'
                => new _PageList_Column('hits', _("Hits"), 'right'),
                'size'
                => new _PageList_Column_size('rev:size', _("Size"), 'right'),
                'summary'
                => new _PageList_Column('rev:summary', _("Last Summary")),
                'version'
                => new _PageList_Column_version(
                    'rev:version',
                    _("Version"),
                    'right'
                ),
                'author'
                => new _PageList_Column_author('rev:author', _("Last Author")),
                'owner'
                => new _PageList_Column_owner('author_id', _("Owner")),
                'creator'
                => new _PageList_Column_creator('author_id', _("Creator")),
                /*
                'group'
                => new _PageList_Column_author('group', _("Group")),
                */
                'locked'
                => new _PageList_Column_bool(
                    'locked',
                    _("Locked"),
                    _("locked")
                ),
                'external'
                => new _PageList_Column_bool(
                    'external',
                    _("External"),
                    _("external")
                ),
                'minor'
                => new _PageList_Column_bool(
                    'rev:is_minor_edit',
                    _("Minor Edit"),
                    _("minor")
                ),
                // 'rating' initialised by the wikilens theme hook: addPageListColumn
                /*
                'rating'
                => new _PageList_Column_rating('rating', _("Rate")),
                */
            );
        if (empty($this->_types)) {
            $this->_types = array();
        }
        // add plugin specific pageList columns, initialized by $options['types']
        $this->_types = array_merge($standard_types, $this->_types);
        // add theme custom specific pageList columns:
        //   set the 4th param as the current pagelist object.
        if (!empty($customPageListColumns)) {
            foreach ($customPageListColumns as $column => $params) {
                $class_name = array_shift($params);
                $params[3] =& $this;
                // ref to a class does not work with php-4
                $this->_types[$column] = new $class_name($params);
            }
        }
    }

    public function getOption($option)
    {
        if (array_key_exists($option, $this->_options)) {
            return $this->_options[$option];
        } else {
            return null;
        }
    }

    /**
     * Add a column to this PageList, given a column name.
     * The name is a type, and optionally has a : and a label. Examples:
     *
     *   pagename
     *   pagename:This page
     *   mtime
     *   mtime:Last modified
     *
     * If this function is called multiple times for the same type, the
     * column will only be added the first time, and ignored the succeeding times.
     * If you wish to add multiple columns of the same type, use addColumnObject().
     *
     * @param string $column column name
     * @return bool true if column is added, false otherwise
     */
    public function _addColumn($column)
    {
        global $request;

        if (isset($this->_columns_seen[$column])) {
            return false;
        } // Already have this one.
        if (!isset($this->_types[$column])) {
            $this->initAvailableColumns();
        }
        $this->_columns_seen[$column] = true;

        if (strstr($column, ':')) {
            list($column, $heading) = explode(':', $column, 2);
        }

        // FIXME: these column types have hooks (objects) elsewhere
        // Omitting this warning should be overridable by the extension
        if (!isset($this->_types[$column])) {
            $silently_ignore = array('numbacklinks',
                'rating', 'ratingvalue',
                'coagreement', 'minmisery',
                'averagerating', 'top3recs',
                'relation', 'linkto');
            if (!in_array($column, $silently_ignore)) {
                trigger_error(sprintf("%s: Bad column", $column));
            }
            return false;
        }
        if (!(defined('FUSIONFORGE') && FUSIONFORGE)) {
            // FIXME: anon users might rate and see ratings also.
            // Defer this logic to the plugin.
            if ($column == 'rating' and !$request->_user->isSignedIn()) {
                return false;
            }
        }

        $this->addColumnObject($this->_types[$column]);

        return true;
    }

    /**
     * Add a column to this PageList, given a column object.
     *
     * @param array|object $col   An object derived from _PageList_Column.
     **/
    public function addColumnObject($col)
    {
        if (is_array($col)) { // custom column object
            $params =& $col;
            $class_name = array_shift($params);
            $params[3] =& $this;
            $col = new $class_name($params);
        }
        $heading = $col->getHeading();
        if (!empty($heading)) {
            $col->setHeading($heading);
        }

        $this->_columns[] = $col;
        $this->_columnsMap[$col->_field] = count($this->_columns); // start with 1
    }

    /**
     * Compare _PageList_Page objects.
     **/
    private function pageCompare($a, $b)
    {
        if (empty($this->_sortby) or count($this->_sortby) == 0) {
            // No columns to sort by
            return 0;
        } else {
            $pagea = $this->getPageFromHandle($a); // If a string, convert to page
            assert(is_a($pagea, 'WikiDB_Page'));
            $pageb = $this->getPageFromHandle($b); // If a string, convert to page
            assert(is_a($pageb, 'WikiDB_Page'));
            foreach ($this->_sortby as $colNum => $direction) {
                // get column type object
                if (!is_int($colNum)) { // or column fieldname
                    if (isset($this->_columnsMap[$colNum])) {
                        $col = $this->_columns[$this->_columnsMap[$colNum] - 1];
                    } elseif (isset($this->_types[$colNum])) {
                        $col = $this->_types[$colNum];
                    }
                }
                if (empty($col)) {
                    return 0;
                }
                assert(isset($col));
                $revision_handle = false;
                $aval = $col->_getSortableValue($pagea, $revision_handle);
                $revision_handle = false;
                $bval = $col->_getSortableValue($pageb, $revision_handle);

                $cmp = $col->_compare($aval, $bval);
                if ($direction === "-") { // Reverse the sense of the comparison
                    $cmp *= -1;
                }

                if ($cmp !== 0) {
                    // This is the first comparison that is not equal-- go with it
                    return $cmp;
                }
            }
            return 0;
        }
    }

    /**
     * Put pages in order according to the sortby arg, if given
     * If the sortby cols are already sorted by the DB call, don't do usort.
     * TODO: optimize for multiple sortable cols
     */
    private function sortPages()
    {
        if (count($this->_sortby) > 0) {
            $need_sort = $this->_options['dosort'];
            if (!$need_sort) {
                foreach ($this->_sortby as $col => $dir) {
                    if (!$this->sortby($col, 'db')) {
                        $need_sort = true;
                    }
                }
            }
            if ($need_sort) { // There are some columns to sort by
                // TODO: consider nopage
                usort($this->_pages, array($this, 'pageCompare'));
            }
        }
    }

    public function limit($limit)
    {
        if (is_array($limit)) {
            list($from, $count) = $limit;
            if ((!empty($from) && !is_numeric($from)) or (!empty($count) && !is_numeric($count))) {
                trigger_error(_("Illegal “limit” argument: must be an integer or two integers separated by comma"));
                return array(0, 0);
            }
            return $limit;
        }
        if (strstr($limit, ',')) {
            list($from, $limit) = explode(',', $limit);
            if ((!empty($from) && !is_numeric($from)) or (!empty($limit) && !is_numeric($limit))) {
                trigger_error(_("Illegal “limit” argument: must be an integer or two integers separated by comma"));
                return array(0, 0);
            }
            return array($from, $limit);
        } else {
            if (!empty($limit) && !is_numeric($limit)) {
                trigger_error(_("Illegal “limit” argument: must be an integer or two integers separated by comma"));
                return array(0, 0);
            }
            return array(0, $limit);
        }
    }

    public function pagingTokens($numrows = false, $ncolumns = false, $limit = false)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if ($numrows === false) {
            $numrows = $this->getTotal();
        }
        if ($limit === false) {
            $limit = $this->_options['limit'];
        }
        if ($ncolumns === false) {
            $ncolumns = count($this->_columns);
        }

        list($offset, $pagesize) = $this->limit($limit);
        if (!$pagesize or
            (!$offset and $numrows < $pagesize) or
            (($offset + $pagesize) < 0)
        ) {
            return false;
        }

        $pagename = $request->getArg('pagename');
        $defargs = array_merge(array('id' => $this->id), $request->args);
        if (USE_PATH_INFO) {
            unset($defargs['pagename']);
        }
        if (isset($defargs['action']) and ($defargs['action'] == 'browse')) {
            unset($defargs['action']);
        }
        $prev = $defargs;

        $tokens = array();
        $tokens['PREV'] = false;
        $tokens['PREV_LINK'] = "";
        $tokens['COLS'] = $ncolumns;
        $tokens['COUNT'] = $numrows;
        $tokens['OFFSET'] = $offset;
        $tokens['SIZE'] = $pagesize;
        $tokens['NUMPAGES'] = (int)ceil($numrows / $pagesize);
        $tokens['ACTPAGE'] = (int)ceil(($offset / $pagesize) + 1);
        if ($offset > 0) {
            $prev['limit'] = max(0, $offset - $pagesize) . ",$pagesize";
            $prev['count'] = $numrows;
            $tokens['LIMIT'] = $prev['limit'];
            $tokens['PREV'] = true;
            $tokens['PREV_LINK'] = WikiURL($pagename, $prev);
            $prev['limit'] = "0,$pagesize"; // FIRST_LINK
            $tokens['FIRST_LINK'] = WikiURL($pagename, $prev);
        }
        $next = $defargs;
        $tokens['NEXT'] = false;
        $tokens['NEXT_LINK'] = "";
        if (($offset + $pagesize) < $numrows) {
            $next['limit'] = min($offset + $pagesize, $numrows - $pagesize)
                . ",$pagesize";
            $next['count'] = $numrows;
            $tokens['LIMIT'] = $next['limit'];
            $tokens['NEXT'] = true;
            $tokens['NEXT_LINK'] = WikiURL($pagename, $next);
            $next['limit'] = $numrows - $pagesize . ",$pagesize"; // LAST_LINK
            $tokens['LAST_LINK'] = WikiURL($pagename, $next);
        }
        return $tokens;
    }

    // make a table given the caption
    public function _generateTable($caption = '')
    {
        if (count($this->_sortby) > 0) {
            $this->sortPages();
        }

        // wikiadminutils hack. that's a way to pagelist non-pages
        $rows = isset($this->_rows) ? $this->_rows : array();
        $i = 0;
        $count = $this->getTotal();
        $do_paging = (isset($this->_options['paging'])
            and !empty($this->_options['limit'])
                and $count
                    and $this->_options['paging'] != 'none');
        if ($do_paging) {
            $tokens = $this->pagingTokens(
                $count,
                count($this->_columns),
                $this->_options['limit']
            );
            if ($tokens and !empty($this->_options['slice'])) {
                $this->_pages = array_slice($this->_pages, $tokens['OFFSET'], $tokens['SIZE']);
            }
        }
        foreach ($this->_pages as $pagenum => $page) {
            $one_row = $this->renderPageRow($page, $i++);
            $rows[] = $one_row;
        }
        $table = HTML::table(array('class' => 'fullwidth pagelist'));
        if ($caption) {
            $table->pushContent(HTML::caption(array('class' => 'top'), $caption));
        }

        $row = HTML::tr();
        $i = 1; // start with 1!
        foreach ($this->_columns as $col) {
            $heading = $col->button_heading($this, $i);
            if ($do_paging
                and isset($col->_field)
                    and $col->_field == 'pagename'
                        and ($maxlen = $this->maxLen())
            ) {
            }
            $row->pushContent($heading);
            $i++;
        }
        $table->pushContent(HTML::colgroup(array('span' => count($this->_columns))));
        if ($do_paging) {
            if ($tokens === false) {
                $table->pushContent(
                    HTML::thead($row),
                    HTML::tbody(false, $rows)
                );
                return $table;
            }

            $paging = Template("pagelink", $tokens);
            if ($this->_options['paging'] != 'bottom') {
                $table->pushContent(HTML::thead($paging));
            }
            if ($this->_options['paging'] != 'top') {
                $table->pushContent(HTML::tfoot($paging));
            }
            $table->pushContent(HTML::tbody(false, HTML($row, $rows)));
            return $table;
        } else {
            $table->pushContent(
                HTML::thead($row),
                HTML::tbody(false, $rows)
            );
            return $table;
        }
    }

    /* recursive stack for private sublist options (azhead, cols) */
    private function saveOptions($opts)
    {
        $stack = array('pages' => $this->_pages);
        foreach ($opts as $k => $v) {
            $stack[$k] = $this->_options[$k];
            $this->_options[$k] = $v;
        }
        if (empty($this->_stack)) {
            $this->_stack = new Stack();
        }
        $this->_stack->push($stack);
    }

    private function restoreOptions()
    {
        assert($this->_stack);
        $stack = $this->_stack->pop();
        $this->_pages = $stack['pages'];
        unset($stack['pages']);
        foreach ($stack as $k => $v) {
            $this->_options[$k] = $v;
        }
    }

    // 'cols'   - split into several columns
    // 'azhead' - support <h3> grouping into initials
    // 'ordered' - OL or UL list (not yet inherited to all plugins)
    // 'comma'  - condensed comma-list only, 1: no links, >1: with links
    // FIXME: only unique list entries, esp. with nopage
    private function generateList($caption = '')
    {
        if (empty($this->_pages)) {
            return false; // stop recursion
        }
        if (!isset($this->_options['listtype'])) {
            $this->_options['listtype'] = '';
        }
        foreach ($this->_pages as $pagenum => $page) {
            $one_row = $this->renderPageRow($page);
            $rows[] = array('header' => WikiLink($page), 'render' => $one_row);
        }
        $out = HTML();
        if ($caption) {
            $out->pushContent(HTML::p($caption));
        }

        // Semantic Search et al: only unique list entries, esp. with nopage
        if (!is_array($this->_pages[0]) and is_string($this->_pages[0])) {
            $this->_pages = array_unique($this->_pages);
        }
        if (count($this->_sortby) > 0) {
            $this->sortPages();
        }
        $count = $this->getTotal();
        $do_paging = (isset($this->_options['paging'])
            and !empty($this->_options['limit'])
                and $count
                    and $this->_options['paging'] != 'none');
        if ($do_paging) {
            $tokens = $this->pagingTokens(
                $count,
                count($this->_columns),
                $this->_options['limit']
            );
            if ($tokens) {
                $paging = Template("pagelink", $tokens);
                $out->pushContent(HTML::table(array('class' => 'fullwidth'), $paging));
            }
        }

        if (!empty($this->_options['limit']) and !empty($this->_options['slice'])) {
            list($offset, $count) = $this->limit($this->_options['limit']);
        } else {
            $offset = 0;
            $count = count($this->_pages);
        }
        // need a recursive switch here for the azhead and cols grouping.
        if (!empty($this->_options['cols']) and $this->_options['cols'] > 1) {
            $length = intval($count / ($this->_options['cols']));
            // If division does not give an integer, we need one more line
            // E.g. 13 pages to display in 3 columns.
            if (($length * ($this->_options['cols'])) != $count) {
                $length += 1;
            }
            $width = sprintf("%d", 100 / $this->_options['cols']) . '%';
            $cols = HTML::tr(array('class' => 'top'));
            for ($i = $offset; $i < $offset + $count; $i += $length) {
                $this->saveOptions(array('cols' => 0, 'paging' => 'none'));
                $this->_pages = array_slice($this->_pages, $i, $length);
                $cols->pushContent(HTML::td($this->generateList()));
                $this->restoreOptions();
            }
            // speed up table rendering by defining colgroups
            $out->pushContent(HTML::table(
                HTML::colgroup(array('span' => $this->_options['cols'],
                       'style' => 'width: '.$width)),
                $cols
            ));
            return $out;
        }

        // Ignore azhead if not sorted by pagename
        if (!empty($this->_options['azhead'])
            and strstr($this->sortby($this->_options['sortby'], 'init'), "pagename")
        ) {
            $cur_h = substr($this->_pages[0]->getName(), 0, 1);
            $out->pushContent(HTML::h3($cur_h));
            // group those pages together with same $h
            $j = 0;
            for ($i = 0; $i < count($this->_pages); $i++) {
                $page =& $this->_pages[$i];
                $h = substr($page->getName(), 0, 1);
                if ($h != $cur_h and $i > $j) {
                    $this->saveOptions(array('cols' => 0, 'azhead' => 0, 'ordered' => $j + 1));
                    $this->_pages = array_slice($this->_pages, $j, $i - $j);
                    $out->pushContent($this->generateList());
                    $this->restoreOptions();
                    $j = $i;
                    $out->pushContent(HTML::h3($h));
                    $cur_h = $h;
                }
            }
            if ($i > $j) { // flush the rest
                $this->saveOptions(array('cols' => 0, 'azhead' => 0, 'ordered' => $j + 1));
                $this->_pages = array_slice($this->_pages, $j, $i - $j);
                $out->pushContent($this->generateList());
                $this->restoreOptions();
            }
            return $out;
        }

        if ($this->_options['listtype'] == 'comma') {
            $this->_options['comma'] = 2;
        }
        if (!empty($this->_options['comma'])) {
            if ($this->_options['comma'] == 1) {
                $out->pushContent($this->generateCommaListAsString());
            } else {
                $out->pushContent($this->generateCommaList());
            }
            return $out;
        }

        if ($this->_options['listtype'] == 'ol') {
            if (empty($this->_options['ordered'])) {
                $this->_options['ordered'] = $offset + 1;
            }
        } elseif ($this->_options['listtype'] == 'ul') {
            $this->_options['ordered'] = 0;
        }
        if ($this->_options['listtype'] == 'ol' and !empty($this->_options['ordered'])) {
            $list = HTML::ol(array('class' => 'pagelist',
                'start' => $this->_options['ordered']));
        } elseif ($this->_options['listtype'] == 'dl') {
            $list = HTML::dl(array('class' => 'pagelist'));
        } else {
            $list = HTML::ul(array('class' => 'pagelist'));
        }
        $i = 0;
        //TODO: currently we ignore limit here and hope that the backend didn't ignore it. (BackLinks)
        if (!empty($this->_options['limit'])) {
            list($offset, $pagesize) = $this->limit($this->_options['limit']);
        } else {
            $pagesize = 0;
        }
        foreach (array_reverse($rows) as $one_row) {
            $pagehtml = $one_row['render'];
            if (!$pagehtml) {
                continue;
            }
            $group = (int)($i++ / $this->_group_rows);
            //TODO: here we switch every row, in tables every third.
            //      unification or parametrized?
            $class = ($group % 2) ? 'oddrow' : 'evenrow';
            if ($this->_options['listtype'] == 'dl') {
                $header = $one_row['header'];
                $list->pushContent(
                    HTML::dt(array('class' => $class), $header),
                    HTML::dd(array('class' => $class), $pagehtml)
                );
            } else {
                $list->pushContent(HTML::li(array('class' => $class), $pagehtml));
            }
            if ($pagesize and $i > $pagesize) {
                break;
            }
        }
        $out->pushContent($list);
        if ($do_paging and $tokens) {
            $out->pushContent(HTML::table(array('class' => 'fullwidth'), $paging));
        }
        return $out;
    }

    // comma=1
    // Condense list without a href links: "Page1, Page2, ..."
    // FIXME: only unique list entries, esp. with nopage
    private function generateCommaListAsString()
    {
        if (defined($this->_options['commasep'])) {
            $separator = $this->_options['commasep'];
        } else {
            $separator = ', ';
        }
        $pages = array();
        foreach ($this->_pages as $pagenum => $page) {
            if ($s = $this->renderPageRow($page)) { // some pages are not viewable
                $pages[] = is_string($s) ? $s : $s->asString();
            }
        }
        return HTML(join($separator, $pages));
    }

    // comma=2
    // Normal WikiLink list.
    // Future: 1 = reserved for plain string (see above)
    //         2 and more => HTML link specialization?
    // FIXME: only unique list entries, esp. with nopage
    private function generateCommaList()
    {
        if (defined($this->_options['commasep'])) {
            $separator = HTML::raw($this->_options['commasep']);
        } else {
            $separator = ', ';
        }
        $html = HTML();
        $html->pushContent($this->renderPageRow($this->_pages[0]));
        next($this->_pages);
        foreach ($this->_pages as $pagenum => $page) {
            if ($s = $this->renderPageRow($page)) { // some pages are not viewable
                $html->pushContent($separator, $s);
            }
        }
        return $html;
    }

    public function _emptyList($caption)
    {
        $html = HTML();
        if ($caption) {
            $html->pushContent(HTML::p($caption));
        }
        if (!empty($this->_messageIfEmpty)) {
            $html->pushContent(HTML::blockquote(HTML::p($this->_messageIfEmpty)));
        }
        return $html;
    }
}

/* List pages with checkboxes to select from.
 * The [Select] button toggles via Javascript flipAll
 */

class PageList_Selectable extends PageList
{
    public function __construct($columns = false, $exclude = '', $options = array())
    {
        if ($columns) {
            if (!is_array($columns)) {
                $columns = explode(',', $columns);
            }
            if (!in_array('checkbox', $columns)) {
                array_unshift($columns, 'checkbox');
            }
        } else {
            $columns = array('checkbox', 'pagename');
        }
        parent::__construct($columns, $exclude, $options);
    }

    public function addPageList($array)
    {
        foreach ($array as $pagename => $selected) {
            if ($selected) {
                $this->addPageSelected((string)$pagename);
            }
            $this->addPage((string)$pagename);
        }
    }

    public function addPageSelected($pagename)
    {
        $this->_selected[$pagename] = 1;
    }
}

class PageList_Unselectable extends PageList
{
    public function __construct($columns = false, $exclude = '', $options = array())
    {
        if ($columns) {
            if (!is_array($columns)) {
                $columns = explode(',', $columns);
            }
        } else {
            $columns = array('pagename');
        }
        parent::__construct($columns, $exclude, $options);
    }

    public function addPageList($array)
    {
        foreach ($array as $pagename => $selected) {
            if ($selected) {
                $this->addPageSelected((string)$pagename);
            }
            $this->addPage((string)$pagename);
        }
    }

    public function addPageSelected($pagename)
    {
        $this->_selected[$pagename] = 1;
    }
}
