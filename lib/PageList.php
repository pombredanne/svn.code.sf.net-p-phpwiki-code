<?php rcs_id('$Id: PageList.php,v 1.93 2004-06-25 14:29:17 rurban Exp $');

/**
 * List a number of pagenames, optionally as table with various columns.
 * This library relieves some work for these plugins:
 *
 * AllPages, BackLinks, LikePages, MostPopular, TitleSearch, WikiAdmin* and more
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
 * 'creator'  _("Creator")
 * 'owner'    _("Owner")
 * 'checkbox'  selectable checkbox at the left.
 * 'content'  
 *
 * Special, custom columns: Either theme or plugin (WikiAdmin*) specific.
 * 'remove'   _("Remove")     
 * 'perm'     _("Permission Mask")
 * 'acl'      _("ACL")
 * 'renamed_pagename'   _("Rename to")
 * 'ratingwidget', ... wikilens theme specific.
 * 'custom'   See plugin/WikiTranslation
 *
 * Symbolic 'info=' arguments:
 * 'all'       All columns except the special columns
 * 'most'      pagename, mtime, author, size, hits, ...
 * 'some'      pagename, mtime, author
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
 *   all columns are sortable (Thanks to the wikilens team).
 *
 * TODO: 
 *   rows arguments for multiple pages/multiple rows.
 *
 *   ->supportedArgs() which arguments are supported, so that the plugin 
 *                     doesn't explictly need to declare it 
 *   Status: already merged in some plugins calls
 *
 *   new method:
 *     list not as <ul> or table, but as simple comma-seperated list
 *
 *   fix memory exhaustion on large pagelists. 
 *   Status: fixed 2004-06-25 16:19:36 rurban but needs further testing.
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

    function getHeading () {
        return $this->_heading;
    }

    function setHeading ($heading) {
        $this->_heading = $heading;
    }

    // old-style heading
    function heading () {
        // allow sorting?
        if (1 or in_array($this->_field, PageList::sortable_columns())) {
            // multiple comma-delimited sortby args: "+hits,+pagename"
            // asc or desc: +pagename, -pagename
            $sortby = PageList::sortby($this->_field, 'flip_order');
            //Fixme: pass all also other GET args along. (limit, p[])
            //TODO: support GET and POST
            $s = HTML::a(array('href' => 
                               $GLOBALS['request']->GetURLtoSelf(array('sortby' => $sortby,
                                                                       'nocache' => '1')),
                               'class' => 'pagetitle',
                               'title' => sprintf(_("Sort by %s"), $this->_field)), 
                         HTML::raw('&nbsp;'), HTML::u($this->_heading), HTML::raw('&nbsp;'));
        } else {
            $s = HTML(HTML::raw('&nbsp;'), HTML::u($this->_heading), HTML::raw('&nbsp;'));
        }
        return HTML::th(array('align' => 'center'),$s);
    }

    // new grid-style
    // see activeui.js 
    function button_heading ($pagelist, $colNum) {
        global $WikiTheme, $request;
        // allow sorting?
        if (1 or in_array($this->_field, PageList::sortable_columns())) {
            // multiple comma-delimited sortby args: "+hits,+pagename"
            $src = false; 
            $noimg_src = $WikiTheme->getButtonURL('no_order');
            if ($noimg_src)
                $noimg = HTML::img(array('src' => $noimg_src,
                                         'width' => '7', 
                                         'height' => '7',
                                         'border' => 0,
                                         'alt'    => '.'));
            else 
                $noimg = HTML::raw('&nbsp;');
            if ($request->getArg('sortby')) {
                if ($pagelist->sortby($colNum, 'check')) { // show icon?
                    $sortby = $pagelist->sortby($request->getArg('sortby'), 'flip_order');
                    $request->setArg('sortby', $sortby);
                    $desc = (substr($sortby,0,1) == '-'); // asc or desc? (+pagename, -pagename)
                    $src = $WikiTheme->getButtonURL($desc ? 'asc_order' : 'desc_order');
                } else {
                    $sortby = $pagelist->sortby($colNum, 'init');
                }
            } else {
                $sortby = $pagelist->sortby($colNum, 'init');
            }
            if (!$src) {
                $img = $noimg;
                //$img->setAttr('alt', _("Click to sort"));
            } else {
                $img = HTML::img(array('src' => $src, 
                                       'width' => '7', 
                                       'height' => '7', 
                                       'border' => 0,
                                       'alt' => _("Click to reverse sort order")));
            }
            $s = HTML::a(array('href' => 
                               //Fixme: pass all also other GET args along. (limit, p[])
                               //Fixme: convert to POST submit[sortby]
                               $request->GetURLtoSelf(array('sortby' => $sortby,
                                                            'nocache' => '1')),
                               'class' => 'gridbutton', 
                               'title' => sprintf(_("Click to sort by %s"), $this->_field)),
                         HTML::raw('&nbsp;'),
                         $noimg,
                         HTML::raw('&nbsp;'),
                         $this->_heading,
                         HTML::raw('&nbsp;'),
                         $img,
                         HTML::raw('&nbsp;'));
        } else {
            $s = HTML(HTML::raw('&nbsp;'), $this->_heading, HTML::raw('&nbsp;'));
        }
        return HTML::th(array('align' => 'center', 'valign' => 'middle', 
                              'class' => 'gridbutton'), $s);
    }

    /**
     * Take two columns of this type and compare them.
     * An undefined value is defined to be < than the smallest defined value.
     * This base class _compare only works if the value is simple (e.g., a number).
     *
     * @param  $colvala  $this->_getValue() of column a
     * @param  $colvalb  $this->_getValue() of column b
     *
     * @return -1 if $a < $b, 1 if $a > $b, 0 otherwise.
     */
    function _compare($colvala, $colvalb) {
        if (is_string($colvala))
            return strcmp($colvala,$colvalb);
        $ret = 0;
        if (($colvala === $colvalb) || (!isset($colvala) && !isset($colvalb))) {
            ;
        } else {
            $ret = (!isset($colvala) || ($colvala < $colvalb)) ? -1 : 1;
        }
        return $ret; 
    }
};

class _PageList_Column extends _PageList_Column_base {
    function _PageList_Column ($field, $default_heading, $align = false) {
        $this->_PageList_Column_base($default_heading, $align);

        $this->_need_rev = substr($field, 0, 4) == 'rev:';
        $this->_iscustom = substr($field, 0, 7) == 'custom:';
        if ($this->_iscustom) {
            $this->_field = substr($field, 7);
        }
        elseif ($this->_need_rev)
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
    
    function _getSortableValue ($page_handle, &$revision_handle) {
        return _PageList_Column::_getValue($page_handle, $revision_handle);
    }
};

/* overcome a call_user_func limitation by not being able to do:
 * call_user_func_array(array(&$class, $class_name), $params);
 * So we need $class = new $classname($params);
 * And we add a 4th param for the parent $pagelist object
 */
class _PageList_Column_custom extends _PageList_Column {
    function _PageList_Column_custom($params) {
    	$this->_pagelist =& $params[3];
        $this->_PageList_Column($params[0], $params[1], $params[2]);
    }
}

class _PageList_Column_size extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        if (!$revision_handle)
            $revision_handle = $page_handle->getCurrentRevision();
        return $this->_getSize($revision_handle);
    }
    
    function _getSortableValue ($page_handle, &$revision_handle) {
        if (!$revision_handle)
            $revision_handle = $page_handle->getCurrentRevision();
    	return (empty($revision_handle->_data['%content'])) 
    	       ? 0 : strlen($revision_handle->_data['%content']);
    }

    function _getSize($revision_handle) {
        $bytes = @strlen($revision_handle->_data['%content']);
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
    	//FIXME: check if $this is available in the parent (->need_rev)
        $val = _PageList_Column::_getValue($page_handle, $revision_handle);
        return $val ? $this->_textIfTrue : $this->_textIfFalse;
    }
};

class _PageList_Column_checkbox extends _PageList_Column {
    function _PageList_Column_checkbox ($field, $default_heading, $name='p') {
        $this->_name = $name;
        $heading = HTML::input(array('type'  => 'button',
                                     'title' => _("Click to de-/select all pages"),
                                     //'width' => '100%',
                                     'name'  => $default_heading,
                                     'value' => $default_heading,
                                     'onclick' => "flipAll(this.form)"
                                     ));
        $this->_PageList_Column($field, $heading, 'center');
    }
    function _getValue ($pagelist, $page_handle, &$revision_handle) {
        $pagename = $page_handle->getName();
        $selected = !empty($pagelist->_selected[$pagename]);
        if (strstr($pagename,'[') or strstr($pagename,']')) {
            $pagename = str_replace(array('[',']'),array('%5B','%5D'),$pagename);
        }
        if ($selected) {
            return HTML::input(array('type' => 'checkbox',
                                     'name' => $this->_name . "[$pagename]",
                                     'value' => 1,
                                     'checked' => 'CHECKED'));
        } else {
            return HTML::input(array('type' => 'checkbox',
                                     'name' => $this->_name . "[$pagename]",
                                     'value' => 1));
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
        global $WikiTheme;
        $this->Theme = &$WikiTheme;
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

// Output is hardcoded to limit of first 50 bytes. Otherwise
// on very large Wikis this will fail if used with AllPages
// (PHP memory limit exceeded)
// FIXME: old PHP without superglobals
class _PageList_Column_content extends _PageList_Column {
    function _PageList_Column_content ($field, $default_heading, $align = false) {
        $this->_PageList_Column($field, $default_heading, $align);
        $this->bytes = 50;
        if ($field == 'content') {
            $this->_heading .= sprintf(_(" ... first %d bytes"),
                                       $this->bytes);
        } elseif ($field == 'hi_content') {
            if (!empty($_POST['admin_replace'])) {
                $search = $_POST['admin_replace']['from'];
                $this->_heading .= sprintf(_(" ... around %s"),
                                           '»'.$search.'«');
            }
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
    
    function _getSortableValue ($page_handle, &$revision_handle) {
        return substr(_PageList_Column::_getValue($page_handle, $revision_handle),0,50);
    }
};

class _PageList_Column_author extends _PageList_Column {
    function _PageList_Column_author ($field, $default_heading, $align = false) {
        _PageList_Column::_PageList_Column($field, $default_heading, $align);
        $this->dbi =& $GLOBALS['request']->getDbh();
    }

    function _getValue ($page_handle, &$revision_handle) {
        $author = _PageList_Column::_getValue($page_handle, $revision_handle);
        if (isWikiWord($author) && $this->dbi->isWikiPage($author))
            return WikiLink($author);
        else
            return $author;
    }
};

class _PageList_Column_owner extends _PageList_Column_author {
    function _getValue ($page_handle, &$revision_handle) {
        $author = $page_handle->getOwner();
        if (isWikiWord($author) && $this->dbi->isWikiPage($author))
            return WikiLink($author);
        else
            return $author;
    }
};

class _PageList_Column_creator extends _PageList_Column_author {
    function _getValue ($page_handle, &$revision_handle) {
        $author = $page_handle->getCreator();
        if (isWikiWord($author) && $this->dbi->isWikiPage($author))
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
        if ($this->dbi->isWikiPage($page_handle->getName()))
            return WikiLink($page_handle);
        else
            return WikiLink($page_handle, 'unknown');
    }
    
    function _getSortableValue ($page_handle, &$revision_handle) {
    	return $page_handle->getName();
    }

    /**
     * Compare two pagenames for sorting.  See _PageList_Column::_compare.
     **/
    function _compare($colvala, $colvalb) {
        return strcmp($colvala, $colvalb);
    }
};

/**
 * A class to bundle up a page with a reference to PageList, so that
 * the compare function for usort() has access to it all.
 * This is a hack necessitated by the interface to usort()-- comparators
 * can't get information upon construction; you get a comparator by class
 * name, not by instance.
 * @author: Dan Frankowski
 */
class _PageList_Page {
    var $_pagelist;
    var $_page;

    function _PageList_Page($pagelist, $page_handle) {
        $this->_pagelist = $pagelist;
        $this->_page = $page_handle;
    }

    function getPageList() {
        return $this->_pagelist;
    }

    function getPage() {
        return $this->_page;
    }
}

class PageList {
    var $_group_rows = 3;
    var $_columns = array();
    var $_columnsMap = array();      // Maps column name to column number.
    var $_excluded_pages = array();
    var $_pages = array();
    var $_caption = "";
    var $_pagename_seen = false;
    var $_types = array();
    var $_options = array();
    var $_selected = array();
    var $_sortby = array();
    var $_maxlen = 0;

    function PageList ($columns = false, $exclude = false, $options = false) {
        if ($options)
            $this->_options = $options;

        // let plugins predefine only certain objects, such its own custom pagelist columns
        if (!empty($this->_options['types'])) {
            $this->_types = $this->_options['types'];
            unset($this->_options['types']);
        }
        $this->_initAvailableColumns();
        $symbolic_columns = 
            array(
                  'all' =>  array_diff(array_keys($this->_types), // all but...
                                       array('checkbox','remove','renamed_pagename',
                                             'content','hi_content','perm','acl')),
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
            if (!in_array('pagename',$columns))
                $this->_addColumn('pagename');
            foreach ($columns as $col) {
                $this->_addColumn($col);
            }
        }
        // If 'pagename' is already present, _addColumn() will not add it again
        $this->_addColumn('pagename');

        foreach (array('sortby','limit','paging','count') as $key) {
          if (!empty($options) and !empty($options[$key])) {
            $this->_options[$key] = $options[$key];
          } else {
            $this->_options[$key] = $GLOBALS['request']->getArg($key);
          }
        }
        $this->_options['sortby'] = $this->sortby($this->_options['sortby'], 'init');
        if ($exclude) {
            if (!is_array($exclude))
                $exclude = $this->explodePageList($exclude,false,
                                                  $this->_options['sortby'],
                                                  $this->_options['limit']);
            $this->_excluded_pages = $exclude;
        }
        $this->_messageIfEmpty = _("<no matches>");
    }

    // Currently PageList takes these arguments:
    // 1: info, 2: exclude, 3: hash of options
    // Here we declare which options are supported, so that 
    // the calling plugin may simply merge this with its own default arguments 
    function supportedArgs () {
        return array(//Currently supported options:
                     'info'              => 'pagename',
                     'exclude'           => '',          // also wildcards and comma-seperated lists

                     /* select pages by meta-data: */
                     'author'   => false, // current user by []
                     'owner'    => false, // current user by []
                     'creator'  => false, // current user by []

                     // for the sort buttons in <th>
                     'sortby'            => 'pagename', // same as for WikiDB::getAllPages

                     //PageList pager options:
                     // These options may also be given to _generate(List|Table) later
                     // But limit and offset might help the query WikiDB::getAllPages()
                     'cols'     => 1,       // side-by-side display of list (1-3)
                     'limit'    => 0,       // number of rows (pagesize)
                     'paging'   => 'auto',  // 'auto'  normal paging mode
                     //			    // 'smart' drop 'info' columns and enhance rows 
                     //                     //         when the list becomes large
                     //                     // 'none'  don't page at all
                     //'azhead' => 0        // provide shortcut links to pages starting with different letters
                     );
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
    	return !empty($this->_options['count'])
    	       ? (integer) $this->_options['count'] : count($this->_pages);
    }

    function isEmpty () {
        return empty($this->_pages);
    }

    function addPage($page_handle) {
        $this->_pages[] = new _PageList_Page($this, $page_handle);
    }

    function _getPageFromHandle($ph) {
        $page_handle = $ph;
        if (is_string($page_handle)) {
            if (empty($page_handle)) return $page_handle;
            $dbi = $GLOBALS['request']->getDbh();
            $page_handle = $dbi->getPage($page_handle);
        }
        return $page_handle;
    }

    /**
     * Take a PageList_Page object, and return an HTML object to display
     * it in a table or list row.
     */
    function _renderPageRow ($pagelist_page) {
        $page_handle = $pagelist_page->getPage();

        $page_handle = $this->_getPageFromHandle($page_handle);
        if (!isset($page_handle) || empty($page_handle)
            || in_array($page_handle->getName(), $this->_excluded_pages))
            return; // exclude page.
            
        //FIXME. only on sf.net
        if (!is_object($page_handle)) {
            trigger_error("PageList: Invalid page_handle $page_handle", E_USER_WARNING);
            return;
        }
        // enforce view permission
        if (!mayAccessPage('view',$page_handle->getName()))
            return;

        $group = (int)($this->getTotal() / $this->_group_rows);
        $class = ($group % 2) ? 'oddrow' : 'evenrow';
        $revision_handle = false;
        $this->_maxlen = max($this->_maxlen, strlen($page_handle->getName()));

        if (count($this->_columns) > 1) {
            $row = HTML::tr(array('class' => $class));
            foreach ($this->_columns as $col)
                $row->pushContent($col->format($this, $page_handle, $revision_handle));
        }
        else {
            $col = $this->_columns[0];
            $row = $col->_getValue($page_handle, $revision_handle);
        }

        return $row;
    }

    function addPages ($page_iter) {
        //Todo: if limit check max(strlen(pagename))
        while ($page = $page_iter->next())
            $this->addPage($page);
    }

    function addPageList (&$list) {
        reset ($list);
        while ($page = next($list))
            $this->addPage((string)$page);
    }

    function maxLen() {
        global $DBParams, $request;
        if ($DBParams['dbtype'] == 'SQL' or $DBParams['dbtype'] == 'ADODB') {
            $dbi =& $request->getDbh();
            extract($dbi->_backend->_table_names);
            if ($DBParams['dbtype'] == 'SQL') {
                $res = $dbi->_backend->_dbh->getOne("SELECT max(length(pagename)) FROM $page_tbl LIMIT 1");
                if (DB::isError($res) || empty($res)) return false;
                else return $res;
            } elseif ($DBParams['dbtype'] == 'ADODB') {
                $row = $dbi->_backend->_dbh->getRow("SELECT max(length(pagename)) FROM $page_tbl LIMIT 1");
                return $row ? $row[0] : false;
            }
        } else 
            return false;
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
    
    /** Now all columns are sortable. 
     *  These are the colums which have native WikiDB backend methods. 
     */
    function sortable_columns() {
        return array('pagename','mtime','hits');
    }

    /** 
     * Handle sortby requests for the DB iterator and table header links.
     * Prefix the column with + or - like "+pagename","-mtime", ...
     * supported actions: 'flip_order' "mtime" => "+mtime" => "-mtime" ...
     *                    'db'         "-pagename" => "pagename DESC"
     * Now all columns are sortable. (patch by DanFr)
     */
    function sortby ($column, $action) {
        if (empty($column)) return;
        //support multiple comma-delimited sortby args: "+hits,+pagename"
        if (strstr($column,',')) {
            $result = array();
            foreach (explode(',',$column) as $col) {
                $result[] = $this->sortby($col,$action);
            }
            return join(",",$result);
        }
        if (substr($column,0,1) == '+') {
            $order = '+'; $column = substr($column,1);
        } elseif (substr($column,0,1) == '-') {
            $order = '-'; $column = substr($column,1);
        }
        // default order: +pagename, -mtime, -hits
        if (empty($order))
            if (in_array($column,array('mtime','hits')))
                $order = '-';
            else
                $order = '+';
        if ($action == 'flip_order') {
            return ($order == '+' ? '-' : '+') . $column;
        } elseif ($action == 'init') {
            $this->_sortby[$column] = $order;
            return $order . $column;
        } elseif ($action == 'check') {
            return (!empty($this->_sortby[$column]) or 
                    ($GLOBALS['request']->getArg('sortby') and 
                     strstr($GLOBALS['request']->getArg('sortby'),$column)));
        } elseif ($action == 'db') {
            // asc or desc: +pagename, -pagename
            return $column . ($order == '+' ? ' ASC' : ' DESC');
        }
        return '';
    }

    // echo implode(":",explodeList("Test*",array("xx","Test1","Test2")));
    function explodePageList($input, $perm = false, $sortby=false, $limit=false) {
        // expand wildcards from list of all pages
        if (preg_match('/[\?\*]/',$input)) {
            $dbi = $GLOBALS['request']->getDbh();
            $allPagehandles = $dbi->getAllPages($perm,$sortby,$limit);
            while ($pagehandle = $allPagehandles->next()) {
                $allPages[] = $pagehandle->getName();
            }
            return explodeList($input, $allPages);
        } else {
            //TODO: do the sorting, normally not needed if used for exclude only
            return explode(',',$input);
        }
    }

    function allPagesByAuthor($wildcard, $perm=false, $sortby=false, $limit=false) {
        $dbi = $GLOBALS['request']->getDbh();
        $allPagehandles = $dbi->getAllPages($perm, $sortby, $limit);
        $allPages = array();
        if ($wildcard === '[]') {
            $wildcard = $GLOBALS['request']->_user->getAuthenticatedId();
            if (!$wildcard) return $allPages;
        }
        while ($pagehandle = $allPagehandles->next()) {
            $name = $pagehandle->getName();
            $author = $pagehandle->getAuthor();
            if ($author) {
                if (preg_match('/[\?\*]/', $wildcard)) {
                    if (glob_match($wildcard, $author))
                        $allPages[] = $name;
                } elseif ($wildcard == $author) {
                      $allPages[] = $name;
                }
            }
        }
        return $allPages;
    }

    function allPagesByOwner($wildcard, $perm=false, $sortby=false, $limit=false) {
        $dbi = $GLOBALS['request']->getDbh();
        $allPagehandles = $dbi->getAllPages($perm, $sortby, $limit);
        $allPages = array();
        if ($wildcard === '[]') {
            $wildcard = $GLOBALS['request']->_user->getAuthenticatedId();
            if (!$wildcard) return $allPages;
        }
        while ($pagehandle = $allPagehandles->next()) {
            $name = $pagehandle->getName();
            $owner = $pagehandle->getOwner();
            if ($owner) {
                if (preg_match('/[\?\*]/', $wildcard)) {
                    if (glob_match($wildcard, $owner))
                        $allPages[] = $name;
                } elseif ($wildcard == $owner) {
                      $allPages[] = $name;
                }
            }
        }
        return $allPages;
    }

    function allPagesByCreator($wildcard, $perm=false, $sortby=false, $limit=false) {
        $dbi = $GLOBALS['request']->getDbh();
        $allPagehandles = $dbi->getAllPages($perm, $sortby, $limit);
        $allPages = array();
        if ($wildcard === '[]') {
            $wildcard = $GLOBALS['request']->_user->getAuthenticatedId();
            if (!$wildcard) return $allPages;
        }
        while ($pagehandle = $allPagehandles->next()) {
            $name = $pagehandle->getName();
            $creator = $pagehandle->getCreator();
            if ($creator) {
                if (preg_match('/[\?\*]/', $wildcard)) {
                    if (glob_match($wildcard, $creator))
                        $allPages[] = $name;
                } elseif ($wildcard == $creator) {
                      $allPages[] = $name;
                }
            }
        }
        return $allPages;
    }

    ////////////////////
    // private
    ////////////////////
    /** Plugin and theme hooks: 
     *  If the pageList is initialized with $options['types'] these types are also initialized, 
     *  overriding the standard types.
     */
    function _initAvailableColumns() {
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
                  'perm'
                  => new _PageList_Column_perm('perm', _("Permission")),
                  'acl'
                  => new _PageList_Column_acl('acl', _("ACL")),
                  */
                  'checkbox'
                  => new _PageList_Column_checkbox('p', _("Select")),
                  'pagename'
                  => new _PageList_Column_pagename,
                  'mtime'
                  => new _PageList_Column_time('rev:mtime', _("Last Modified")),
                  'hits'
                  => new _PageList_Column('hits', _("Hits"), 'right'),
                  'size'
                  => new _PageList_Column_size('rev:size', _("Size"), 'right'),
                                              /*array('align' => 'char', 'char' => ' ')*/
                  'summary'
                  => new _PageList_Column('rev:summary', _("Last Summary")),
                  'version'
                  => new _PageList_Column_version('rev:version', _("Version"),
                                                 'right'),
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
                  => new _PageList_Column_bool('locked', _("Locked"),
                                               _("locked")),
                  'minor'
                  => new _PageList_Column_bool('rev:is_minor_edit',
                                               _("Minor Edit"), _("minor")),
                  'markup'
                  => new _PageList_Column('rev:markup', _("Markup")),
                  // 'rating' initialised by the wikilens theme hook: addPageListColumn
                  /*
                  'rating'
                  => new _PageList_Column_rating('rating', _("Rate")),
                  */
                  );
        if (empty($this->_types))
            $this->_types = array();
        // add plugin specific pageList columns, initialized by $options['types']
        $this->_types = array_merge($standard_types, $this->_types);
        // add theme custom specific pageList columns: 
        //   set the 4th param as the current pagelist object.
        if (!empty($customPageListColumns)) {
            foreach ($customPageListColumns as $column => $params) {
                $class_name = array_shift($params);
                $params[3] =& $this;
                $class = new $class_name($params);
            }
            $this->_types = array_merge($this->_types, $customPageListColumns);
        }
    }

    function getOption($option) {
        if (array_key_exists($option, $this->_options)) {
            return $this->_options[$option];
        }
        else {
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
     * @param column name
     * @return  true if column is added, false otherwise
     */
    function _addColumn ($column) {
    	
        if (isset($this->_columns_seen[$column]))
            return false;       // Already have this one.
	if (!isset($this->_types[$column]))
            $this->_initAvailableColumns();
        $this->_columns_seen[$column] = true;

        if (strstr($column, ':'))
            list ($column, $heading) = explode(':', $column, 2);

        if (!isset($this->_types[$column])) {
            $silently_ignore = array('numbacklinks','rating','coagreement','minmisery',
                                     'averagerating','top3recs');
            if (!in_array($column,$silently_ignore))
                trigger_error(sprintf("%s: Bad column", $column), E_USER_NOTICE);
            return false;
        }
        if ($column == 'ratingwidget' and !$GLOBALS['request']->_user->isSignedIn())
            return false;

        $this->addColumnObject($this->_types[$column]);

        return true;
    }

    /**
     * Add a column to this PageList, given a column object.
     *
     * @param $col object   An object derived from _PageList_Column.
     **/
    function addColumnObject($col) {
    	if (is_array($col)) {// custom column object
    	    $params =& $col;
            $class_name = array_shift($params);
            $params[3] =& $this;
            $col = new $class_name($params);
        }
        $heading = $col->getHeading();
        if (!empty($heading))
            $col->setHeading($heading);

        $this->_columns[] = $col;
        $this->_columnsMap[$col->_field] = count($this->_columns)-1;
    }

    /**
     * Compare _PageList_Page objects.
     **/
    function _pageCompare($a, $b) {
        $pagelist = $a->getPageList();
        $pagea = $a->getPage();
        $pageb = $b->getPage();
        if (count($pagelist->_sortby) == 0) {
            // No columns to sort by
            return 0;
        }
        else {
            foreach ($pagelist->_sortby as $colNum => $direction) {
            	if (!isset($pagelist->_columns_seen[$colNum])) return 0;
                $colkey = $colNum;
                if (!is_int($colkey)) { // or column fieldname
                    $colkey = $pagelist->_columnsMap[$colNum];
                }
                $col = $pagelist->_columns[$colkey];

                $revision_handle = false;
                $pagea = PageList::_getPageFromHandle($pagea);  // If a string, convert to page
                assert(isa($pagea, 'WikiDB_Page'));
                assert(isset($col));
                $aval = $col->_getSortableValue($pagea, $revision_handle);
                $pageb = PageList::_getPageFromHandle($pageb);  // If a string, convert to page
                
                $revision_handle = false;
                assert(isa($pageb, 'WikiDB_Page'));
                $bval = $col->_getSortableValue($pageb, $revision_handle);

                $cmp = $col->_compare($aval, $bval);
                if ($direction === "-") {
                    // Reverse the sense of the comparison
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
     */
    function _sortPages() {
        if (count($this->_sortby) > 0) {
            // There are columns to sort by
            usort($this->_pages, array('PageList', '_pageCompare'));
        }        
    }

    function limit($limit) {
        if (strstr($limit,','))
            return split(',',$limit);
        else
            return array(0,$limit);
    }
    
    // make a table given the caption
    function _generateTable($caption) {
        $this->_sortPages();

        $rows = array();
        foreach ($this->_pages as $pagenum => $page) {
            $rows[] = $this->_renderPageRow($page);
        }

        $table = HTML::table(array('cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'class'       => 'pagelist'));
        if ($caption)
            $table->pushContent(HTML::caption(array('align'=>'top'), $caption));

        //Warning: This is quite fragile. It depends solely on a private variable
        //         in ->_addColumn()
        if (!empty($this->_columns_seen['checkbox'])) {
            $table->pushContent($this->_jsFlipAll());
        }
        $do_paging = ( isset($this->_options['paging']) and 
                       !empty($this->_options['limit']) and $this->getTotal() and
                       $this->_options['paging'] != 'none' );
        $row = HTML::tr();
        $table_summary = array();
        $i = 0;
        foreach ($this->_columns as $col) {
            $heading = $col->button_heading($this, $i);
            if ($do_paging and 
                isset($col->_field) and $col->_field == 'pagename' and 
                ($maxlen = $this->maxLen()))
                $heading->setAttr('width',$maxlen * 7);
            $row->pushContent($heading);
            if (is_string($col->getHeading()))
                $table_summary[] = $col->getHeading();
            $i++;
        }
        // Table summary for non-visual browsers.
        $table->setAttr('summary', sprintf(_("Columns: %s."), 
                                           implode(", ", $table_summary)));

        if ( $do_paging ) {
            // if there are more pages than the limit, show a table-header, -footer
            list($offset,$pagesize) = $this->limit($this->_options['limit']);
            $numrows = $this->getTotal();
            if (!$pagesize or
                (!$offset and $numrows <= $pagesize) or
                ($offset + $pagesize < 0)) 
            {
                $table->pushContent(HTML::thead($row),
                                    HTML::tbody(false, $rows));
                return $table;
            }
            global $request;
            include_once('lib/Template.php');

            $tokens = array();
            $pagename = $request->getArg('pagename');
            $defargs = $request->args;
            unset($defargs['pagename']); unset($defargs['action']);
            //$defargs['nocache'] = 1;
            $prev = $defargs;
            $tokens['PREV'] = false; $tokens['PREV_LINK'] = "";
            $tokens['COLS'] = count($this->_columns);
            $tokens['COUNT'] = $numrows; 
            $tokens['OFFSET'] = $offset; 
            $tokens['SIZE'] = $pagesize;
            $tokens['NUMPAGES'] = (int)($numrows / $pagesize)+1;
            $tokens['ACTPAGE'] = (int) (($offset+1) / $pagesize)+1;
            if ($offset > 0) {
            	$prev['limit'] = min(0,$offset - $pagesize) . ",$pagesize";
            	$prev['count'] = $numrows;
            	$tokens['LIMIT'] = $prev['limit'];
                $tokens['PREV'] = true;
                $tokens['PREV_LINK'] = WikiURL($pagename,$prev);
                $prev['limit'] = "0,$pagesize";
                $tokens['FIRST_LINK'] = WikiURL($pagename,$prev);
            }
            $next = $defargs;
            $tokens['NEXT'] = false; $tokens['NEXT_LINK'] = "";
            if ($offset + $pagesize < $numrows) {
                $next['limit'] = min($offset + $pagesize,$numrows - $pagesize) . ",$pagesize";
            	$next['count'] = $numrows;
            	$tokens['LIMIT'] = $next['limit'];
                $tokens['NEXT'] = true;
                $tokens['NEXT_LINK'] = WikiURL($pagename,$next);
                $next['limit'] = $numrows - $pagesize . ",$pagesize";
                $tokens['LAST_LINK'] = WikiURL($pagename,$next);
            }
            $paging = new Template("pagelink", $request, $tokens);
            $table->pushContent(HTML::thead($paging),
                                HTML::tbody(false, HTML($row, $rows)),
                                HTML::tfoot($paging));
            return $table;
        }
        $table->pushContent(HTML::thead($row),
                            HTML::tbody(false, $rows));
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
        $list = HTML::ul(array('class' => 'pagelist'));
        $i = 0;
        foreach ($this->_pages as $pagenum => $page) {
            $pagehtml = $this->_renderPageRow($page);
            $group = ($i++ / $this->_group_rows);
            $class = ($group % 2) ? 'oddrow' : 'evenrow';
            $list->pushContent(HTML::li(array('class' => $class),$pagehtml));
        }
        $out = HTML();
        //Warning: This is quite fragile. It depends solely on a private variable
        //         in ->_addColumn()
        // Questionable if its of use here anyway. This is a one-col pagename list only.
        //if (!empty($this->_columns_seen['checkbox'])) $out->pushContent($this->_jsFlipAll());
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

    // Condense list: "Page1, Page2, ..." 
    // Alternative $seperator = HTML::Raw(' &middot; ')
    function _generateCommaList($seperator = ', ') {
        return HTML(join($seperator, $list));
    }

};

/* List pages with checkboxes to select from.
 * The [Select] button toggles via _jsFlipAll
 */

class PageList_Selectable
extends PageList {

    function PageList_Selectable ($columns=false, $exclude=false, $options = false) {
        if ($columns) {
            if (!is_array($columns))
                $columns = explode(',', $columns);
            if (!in_array('checkbox',$columns))
                array_unshift($columns,'checkbox');
        } else {
            $columns = array('checkbox','pagename');
        }
        PageList::PageList($columns, $exclude, $options);
    }

    function addPageList ($array) {
        while (list($pagename,$selected) = each($array)) {
            if ($selected) $this->addPageSelected((string)$pagename);
            $this->addPage((string)$pagename);
        }
    }

    function addPageSelected ($pagename) {
        $this->_selected[$pagename] = 1;
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.92  2004/06/21 17:01:39  rurban
// fix typo and rating method call
//
// Revision 1.91  2004/06/21 16:22:29  rurban
// add DEFAULT_DUMP_DIR and HTML_DUMP_DIR constants, for easier cmdline dumps,
// fixed dumping buttons locally (images/buttons/),
// support pages arg for dumphtml,
// optional directory arg for dumpserial + dumphtml,
// fix a AllPages warning,
// show dump warnings/errors on DEBUG,
// don't warn just ignore on wikilens pagelist columns, if not loaded.
// RateIt pagelist column is called "rating", not "ratingwidget" (Dan?)
//
// Revision 1.90  2004/06/18 14:38:21  rurban
// adopt new PageList style
//
// Revision 1.89  2004/06/17 13:16:08  rurban
// apply wikilens work to PageList: all columns are sortable (slightly fixed)
//
// Revision 1.88  2004/06/14 11:31:35  rurban
// renamed global $Theme to $WikiTheme (gforge nameclash)
// inherit PageList default options from PageList
//   default sortby=pagename
// use options in PageList_Selectable (limit, sortby, ...)
// added action revert, with button at action=diff
// added option regex to WikiAdminSearchReplace
//
// Revision 1.87  2004/06/13 16:02:12  rurban
// empty list of pages if user=[] and not authenticated.
//
// Revision 1.86  2004/06/13 15:51:37  rurban
// Support pagelist filter for current author,owner,creator by []
//
// Revision 1.85  2004/06/13 15:33:19  rurban
// new support for arguments owner, author, creator in most relevant
// PageList plugins. in WikiAdmin* via preSelectS()
//
// Revision 1.84  2004/06/08 13:51:56  rurban
// some comments only
//
// Revision 1.83  2004/05/18 13:35:39  rurban
//  improve Pagelist layout by equal pagename width for limited lists
//
// Revision 1.82  2004/05/16 22:07:35  rurban
// check more config-default and predefined constants
// various PagePerm fixes:
//   fix default PagePerms, esp. edit and view for Bogo and Password users
//   implemented Creator and Owner
//   BOGOUSERS renamed to BOGOUSER
// fixed syntax errors in signin.tmpl
//
// Revision 1.81  2004/05/13 12:30:35  rurban
// fix for MacOSX border CSS attr, and if sort buttons are not found
//
// Revision 1.80  2004/04/20 00:56:00  rurban
// more paging support and paging fix for shorter lists
//
// Revision 1.79  2004/04/20 00:34:16  rurban
// more paging support
//
// Revision 1.78  2004/04/20 00:06:03  rurban
// themable paging support
//
// Revision 1.77  2004/04/18 01:11:51  rurban
// more numeric pagename fixes.
// fixed action=upload with merge conflict warnings.
// charset changed from constant to global (dynamic utf-8 switching)
//

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
