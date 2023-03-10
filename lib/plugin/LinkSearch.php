<?php
/**
 * Copyright © 2007 Reini Urban
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

require_once 'lib/TextSearchQuery.php';
require_once 'lib/PageList.php';

/**
 * Similar to SemanticSearch, just for ordinary in- or outlinks.
 *
 * @author: Reini Urban
 */

class WikiPlugin_LinkSearch extends WikiPlugin
{
    public $current_row;

    public function getDescription()
    {
        return _("Search page and link names.");
    }

    public function getDefaultArguments()
    {
        return array_merge(
            PageList::supportedArgs(), // paging and more.
            array(
                's' => "", // linkvalue query string
                'page' => "*", // which pages (glob allowed), default: all
                'direction' => "out", // or in
                'case_exact' => false,
                'regex' => 'auto',
                'noform' => false, // don't show form with results.
                'noheader' => false // no caption
            )
        );
    }

    public function showForm(&$dbi, &$request, $args)
    {
        $action = $request->getPostURL();
        $hiddenfield = HiddenInputs(
            $request->getArgs(),
            '',
            array('action', 'page', 's', 'direction')
        );
        $pagefilter = HTML::input(array('name' => 'page',
            'value' => $args['page'],
            'title' => _("Search only in these pages. With autocompletion."),
            'class' => 'dropdown',
            'autocomplete_complete' => 'true',
            'autocomplete_matchsubstring' => 'false',
            'autocomplete_list' => 'xmlrpc:wiki.titleSearch ^[S] 4'
        ), '');
        $query = HTML::input(array('name' => 's',
            'value' => $args['s'],
            'title' => _("Filter by this link. These are pagenames. With autocompletion."),
            'class' => 'dropdown',
            'autocomplete_complete' => 'true',
            'autocomplete_matchsubstring' => 'true',
            'autocomplete_list' => 'xmlrpc:wiki.titleSearch ^[S] 4'
        ), '');
        $dirsign_switch = JavaScript("
function dirsign_switch() {
  var d = document.getElementById('dirsign')
  d.innerHTML = (d.innerHTML == ' =&gt; ') ? ' &lt;= ' : ' =&gt; '
}
");
        $dirsign = " => ";
        $in = $out = array('name' => 'direction', 'type' => 'radio', 'onChange' => 'dirsign_switch()');
        $out['value'] = 'out';
        $out['id'] = 'dir_out';
        if ($args['direction'] == 'out') {
            $out['checked'] = 'checked';
        }
        $in['value'] = 'in';
        $in['id'] = 'dir_in';
        if ($args['direction'] == 'in') {
            $in['checked'] = 'checked';
            $dirsign = " <= ";
        }
        $direction = HTML(
            HTML::input($out),
            HTML::label(array('for' => 'dir_out'), _("outgoing")),
            HTML::input($in),
            HTML::label(array('for' => 'dir_in'), _("incoming"))
        );
        /*
        $direction = HTML::select(array('name'=>'direction',
                                        'onChange' => 'dirsign_switch()'));
        $out = array('value' => 'out');
        if ($args['direction']=='out') $out['selected'] = 'selected';
        $in = array('value' => 'in');
        if ($args['direction']=='in') {
            $in['selected'] = 'selected';
            $dirsign = " <= ";
        }
        $direction->pushContent(HTML::option($out, _("outgoing")));
        $direction->pushContent(HTML::option($in, _("incoming")));
        */
        $submit = Button('submit:search', _("LinkSearch"));
        $instructions = _("Search in pages for links with the matching name.");
        return HTML::form(
            array('action' => $action,
                'method' => 'get',
                'accept-charset' => 'UTF-8'),
            $dirsign_switch,
            $hiddenfield,
            $instructions,
            HTML::br(),
            $pagefilter,
            HTML::strong(HTML::samp(array('id' => 'dirsign'), $dirsign)),
            $query,
            HTML::raw('&nbsp;'),
            $direction,
            HTML::raw('&nbsp;'),
            $submit
        );
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        $args = $this->getArgs($argstr, $request);
        extract($args);

        if (!is_bool($noform)) {
            if (($noform == '0') || ($noform == 'false')) {
                $noform = false;
            } elseif (($noform == '1') || ($noform == 'true')) {
                $noform = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "noform"));
            }
        }

        if (!is_bool($noheader)) {
            if (($noheader == '0') || ($noheader == 'false')) {
                $noheader = false;
            } elseif (($noheader == '1') || ($noheader == 'true')) {
                $noheader = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "noheader"));
            }
        }

        if (empty($args['page'])) {
            $args['page'] = "*";
        }
        $form = $this->showForm($dbi, $request, $args);
        if (empty($s)) {
            return $form;
        }
        $pagequery = new TextSearchQuery($page, $args['case_exact'], $args['regex']);
        $linkquery = new TextSearchQuery($s, $args['case_exact'], $args['regex']);
        $links = $dbi->linkSearch($pagequery, $linkquery, $direction == 'in' ? 'linkfrom' : 'linkto');
        $pagelist = new PageList($args['info'], $args['exclude'], $args);
        $pagelist->_links = array();
        while ($link = $links->next()) {
            $pagelist->addPage($link['pagename']);
            $pagelist->_links[] = $link;
        }
        $pagelist->addColumnObject(new _PageList_Column_LinkSearch_link('link', _("Link"), $pagelist));

        if (!$noheader) {
            // We put the form into the caption just to be able to return one pagelist object,
            // and to still have the convenience form at the top. we could workaround this by
            // putting the form as WikiFormRich into the actionpage. But this does not look as
            // nice as this here.
            $pagelist->setCaption( // on mozilla the form doesn't fit into the caption very well.
                HTML(
                    $noform ? '' : HTML($form, HTML::hr()),
                    fmt("LinkSearch result for “%s” in pages “%s”, direction %s", $s, $page, $direction)
                ));
        }
        return $pagelist;
    }
}

// FIXME: sortby errors with this column
class _PageList_Column_LinkSearch_link extends _PageList_Column
{
    public function __construct($field, $heading, &$pagelist)
    {
        $this->_field = $field;
        $this->_heading = $heading;
        $this->_need_rev = false;
        $this->_iscustom = true;
        $this->_pagelist =& $pagelist;
    }

    public function _getValue($page_handle, $revision_handle)
    {
        if (is_object($page_handle)) {
            $text = $page_handle->getName();
        } else {
            $text = $page_handle;
        }
        $link = $this->_pagelist->_links[$this->current_row];
        return WikiLink($link['linkvalue'], 'if_known');
    }
}
