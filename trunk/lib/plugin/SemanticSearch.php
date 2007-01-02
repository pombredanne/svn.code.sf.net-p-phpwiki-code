<?php // -*-php-*-
rcs_id('$Id: SemanticSearch.php,v 1.2 2007-01-02 13:23:06 rurban Exp $');
/*
 Copyright 2007 Reini Urban

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once('lib/TextSearchQuery.php');
require_once('lib/PageList.php');

/**
 * Search for relations/attributes and its values.
 * page - relation::object. e.g list all cities: is_a::city => relation=is_a&s=city
 *
 * An attribute has just a value, which is a number, and which is for sure no pagename, 
 * and its value goes through some units unification. (not yet)
 * We can also do numerical comparison (e.g. range searching) with attributes. 
 *   population>1000000 (not yet)
 *
 * A more generic <ask> feature will use multiple comparison and nesting. 
 *   <ask is_a::city and population &gt; 1.000.000 and population &lt; 10.000.000>
 *   <ask (is_a::city or is_a::country) and population &lt; 10.000.000>
 * 
 * @author: Reini Urban
 */
class WikiPlugin_SemanticSearch
extends WikiPlugin
{
    function getName() {
        return _("SemanticSearch");
    }
    function getDescription() {
        return _("Search relations and attributes");
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.2 $");
    }
    function getDefaultArguments() { 
        return array_merge
            (
             PageList::supportedArgs(),  // paging and more.
	     array(
		   's'          => "*",  // linkvalue query string
		   'page'       => "*",  // which pages (glob allowed), default: all
		   'relation'   => '',   // linkname. which relations. default all
		   'attribute'  => '',   // linkname. which attributes. default all
		   'attr_op'    => ':=', // a funny written way for equality for pure aesthetic pleasure 
		   			 // "All attributes which have this value set"
		   'units'      => '',   // ?
		   'case_exact' => false,
		   'regex'      => 'auto',
		   'noform'     => false, // don't show form with results.
		   'noheader'   => false  // no caption
		   ));
    }

    function showForm (&$dbi, &$request, $args) {
    	global $WikiTheme;
	$action = $request->getPostURL();
	$hiddenfield = HiddenInputs($request->getArgs(),'',
				    array('action','page','s','relation','attribute'));
	$pagefilter = HTML::input(array('name' => 'page',
					'value' => $args['page'],
					'title' => _("Search only in these pages. With autocompletion."),
					'class' => 'dropdown', 
					'acdropdown' => 'true', 
					'autocomplete_complete' => 'true',
					'autocomplete_matchsubstring' => 'false', 
					'autocomplete_list' => 'xmlrpc:wiki.titleSearch ^[S] 4'
					), '');
	$allrelations = $dbi->listRelations(false,false,true);
	$svalues = empty($allrelations) ? "" : join("','", $allrelations);
	$reldef = JavaScript("var semsearch_relations = new Array('".$svalues."')");
	$relation = HTML::input(array('name' => 'relation', 
				      'value' => $args['relation'],
				      'title' => _("Filter by this relation. With autocompletion."),
				      'class' => 'dropdown', 
				      'style' => 'width:10em',
				      'acdropdown' => 'true', 
				      'autocomplete_assoc' => 'false', 
				      'autocomplete_complete' => 'true', 
				      'autocomplete_matchsubstring' => 'true', 
				      'autocomplete_list' => 'array:semsearch_relations'
				      ), '');
	$queryrel = HTML::input(array('name' => 's', 
				      'value' => $args['s'],
				      'title' => _("Filter by this link. These are pagenames. With autocompletion."),
				      'class' => 'dropdown', 
				      'acdropdown' => 'true', 
				      'autocomplete_complete' => 'true', 
				      'autocomplete_matchsubstring' => 'true', 
				      'autocomplete_list' => 'xmlrpc:wiki.titleSearch ^[S] 4'
				      ), '');
	$relsubmit = Button('submit:semsearch[relations]',  _("Relations"), false);
	// just testing some dhtml... not yet done
	$enhancements = HTML();
	$nbsp = HTML::raw('&nbsp;');
	$this_uri = $_SERVER['REQUEST_URI'].'#';
	$andbutton = new Button(_("AND"),$this_uri,'wikiaction',
				array(
				      'onclick' => "addquery('rel', 'and')",
				      'title' => _("Add an AND query")));
	$orbutton = new Button(_("OR"),$this_uri,'wikiaction',
				array(
				      'onclick' => "addquery('rel', 'or')",
				      'title' => _("Add an OR query")));
	if (DEBUG)
	    $enhancements = HTML::span($andbutton, $nbsp, $orbutton);  
	$instructions = _("Search in pages for a relation with that value, which is a pagename.");
	$form1 = HTML::form(array('action' => $action,
				  'method' => 'post',
				  'accept-charset' => $GLOBALS['charset']),
			    $reldef,
			    $hiddenfield, HiddenInputs(array('attribute'=>'')),
			    $instructions, HTML::br(),
			    HTML::table
			    (array('border' => 0,'cellspacing' => 2),
			     HTML::colgroup(array('span' => 6)),
			     HTML::thead
			     (HTML::th(''),HTML::th('Pagefilter'),HTML::th('Relation'),
			      HTML::th(''),HTML::th(array('span' => 2),'Links')),
			     HTML::tbody
			     (HTML::tr(
				       HTML::td($nbsp,$nbsp,$nbsp),
				       HTML::td($pagefilter, ": "),
				       HTML::td($relation),
				       HTML::td(HTML::strong(HTML::tt('  ::  '))), 
				       HTML::td($queryrel),
				       HTML::td($nbsp, $relsubmit, 
						$nbsp, $enhancements)))));

	$allattrs = $dbi->listRelations(false,true,true);
	if (empty($allrelations) and empty($allattrs)) // be nice to the dummy.
	    $this->_norelations_warning = _("No relations nor attributes in the whole wikidb defined!");
	$svalues = empty($allattrs) ? "" : join("','", $allattrs);
	$attdef = JavaScript("var semsearch_attributes = new Array('".$svalues."')\n"
	                    ."var semsearch_op = new Array('"
	                          .join("','", $this->_supported_operators)
	                          ."')");
	$attribute = HTML::input(array('name' => 'attribute', 
				       'value' => $args['attribute'],
				       'title' => _("Filter by this attribute name. With autocompletion."),
				       'class' => 'dropdown', 
				       'style' => 'width:10em',
				       'acdropdown' => 'true', 
				       'autocomplete_complete' => 'true', 
				       'autocomplete_matchsubstring' => 'true', 
				       'autocomplete_assoc' => 'false', 
				       'autocomplete_list' => 'array:semsearch_attributes'
				      ), '');
	$attr_op = HTML::input(array('name' => 'attr_op', 
				        'value' => $args['attr_op'],
				        'title' => _("Logical operator. With autocompletion."),
				        'class' => 'dropdown', 
				        'style' => 'width:2em',
				        'acdropdown' => 'true', 
				        'autocomplete_complete' => 'true', 
				        'autocomplete_matchsubstring' => 'true', 
				        'autocomplete_assoc' => 'false', 
				        'autocomplete_list' => 'array:semsearch_op'
				      ), '');
	$queryatt = HTML::input(array('name' => 's', 
				      'value' => $args['s'],
				      'title' => _("Filter by this numeric attribute value. With autocompletion."), //?
				      'class' => 'dropdown', 
				      'acdropdown' => 'false',
				      'autocomplete_complete' => 'true', 
				      'autocomplete_matchsubstring' => 'false', 
				      'autocomplete_assoc' => 'false',
				      'autocomplete_list' => 'plugin:SemanticSearch page='.$args['page'].' attribute=^[S]'
				      ), '');
	$andbutton = new Button(_("AND"),$this_uri,'wikiaction',
				array(
				      'onclick' => "addquery('attr', 'and')",
				      'title' => _("Add an AND query")));
	$orbutton = new Button(_("OR"),$this_uri,'wikiaction',
				array(
				      'onclick' => "addquery('attr', 'or')",
				      'title' => _("Add an OR query")));
	if (DEBUG)
	    $enhancements = HTML::span($andbutton, $nbsp, $orbutton);
	$attsubmit = Button('submit:semsearch[attributes]', _("Attributes"), false);
	$instructions = HTML::span(_("Search in pages for an attribute with that numeric value."),"\n");
	if (DEBUG)
	    $instructions->pushContent
	      (HTML(" ", new Button(_("Advanced..."),$this_uri)));
	$form2 = HTML::form(array('action' => $action,
				  'method' => 'post',
				  'accept-charset' => $GLOBALS['charset']),
			    $attdef, 
			    $hiddenfield, HiddenInputs(array('relation'=>'')),
			    $instructions, HTML::br(),
			    HTML::table
			    (array('border' => 0,'cellspacing' => 2),
			     HTML::colgroup(array('span' => 6)),
			     HTML::thead
			     (HTML::th(''),HTML::th('Pagefilter'),HTML::th('Attribute'),
			      HTML::th('Op'),HTML::th(array('span' => 2),'Value')),
			     HTML::tbody
			     (HTML::tr(
				       HTML::td($nbsp,$nbsp,$nbsp),
				       HTML::td($pagefilter, ": "),
				       HTML::td($attribute), 
				       HTML::td($attr_op),
				       HTML::td($queryatt),
				       HTML::td($nbsp, $attsubmit,
						$nbsp, $enhancements)))));
	
	return HTML($form1, $form2);
    }
 
    function run ($dbi, $argstr, &$request, $basepage) { 
        global $WikiTheme;
	                          
	$this->_supported_operators = array(':=','<','<=','>','>=','!=','==','=~'); 
        $args = $this->getArgs($argstr, $request);
        if (empty($args['page']))
            $args['page'] = "*";
        if (!isset($args['s'])) // it might be (integer) 0
            $args['s'] = "*";
	$form = $this->showForm($dbi, $request, $args);
	if (isset($this->_norelations_warning))
	    $form->pushContent(HTML::div(array('class' => 'warning'),
	                                 _("Warning:").$this->_norelations_warning));
        extract($args);
	// for convenience and harmony we allow GET requests also.
	if (!$request->isPost()) {
	    if ($relation or $attribute) // check for good GET request
	        ;
	    else     
	        return $form; // nobody called us, so just display our supadupa form
	}
        $pagequery = new TextSearchQuery($page, $args['case_exact'], $args['regex']);
        // we might want to check for semsearch['relations'] and semsearch['attributes'] also
	if (empty($relation) and empty($attribute)) {
	    // so we just clicked without selecting any relation. 
	    // hmm. check which button we clicked, before we do the massive alltogether search.
	    $posted = $request->getArg("semsearch");
	    if (isset($semsearch['relations']))
		$relation = '*';
	    elseif (isset($posted['attributes']))
		$attribute = '*';
	}
	$searchtype = "Text";
	if (!empty($relation)) {
	    $querydesc = $relation."::".$s;
	    $linkquery = new TextSearchQuery($s, $args['case_exact'], $args['regex']);
	    $relquery = new TextSearchQuery($relation, $args['case_exact'], $args['regex']);
	    $links = $dbi->linkSearch($pagequery, $linkquery, 'relation', $relquery);
	    $pagelist = new PageList($args['info'], $args['exclude'], $args);
	    $pagelist->_links = array();
	    while ($link = $links->next()) {
	        $pagelist->addPage($link['pagename']);
	        $pagelist->_links[] = $link;
	    }
	    $pagelist->addColumnObject
		(new _PageList_Column_SemanticSearch_relation('relation', _("Relation"), $pagelist));
	    $pagelist->addColumnObject
		(new _PageList_Column_SemanticSearch_link('link', _("Link"), $pagelist));
	}
	// can we merge two different pagelist?
	if (!empty($attribute)) {
	    $relquery = new TextSearchQuery($attribute, $args['case_exact'], $args['regex']);
	    if (!in_array($attr_op, $this->_supported_operators)) {
		return HTML($form,$this->error(fmt("Illegal operator: %s",
					           HTML::tt($attr_op))));
	    }
	    // TODO: support unit suffixes
	    if (preg_match('/^\d+$/', $s)) { // do comparison only with numbers 
		//include_once("lib/SemanticWeb.php");
		// TODO: Unify units to some format before comparison.
		/* Sooner or later we want logical expressions also:
		 *  population < 1million AND area > 50km2
		 * Here we check only for one attribute per page.
		 */
		// it might not be the best idea to use '*' as variable to expand
		if ($attribute == '*') $attribute = '_x'; 
		$querydesc = $attribute." ".$attr_op." ".$s;
		$searchtype = "Numeric";
		$linkquery = new NumericSearchQuery($querydesc, $attribute);
		if ($attribute == '_x') $attribute = '*'; 
		$querydesc = $attribute." ".$attr_op." ".$s;
	    // text matcher or '*' MATCH_ALL
	    } elseif (in_array($attr_op, array(':=','==','=~'))) { 
		if ($attr_op == '=~') {
		    if ($s == '*') $s = '.*'; // help the poor user. we need pcre syntax.	
		    $linkquery = new TextSearchQuery("$s", $args['case_exact'], 'pcre');
		}
		else		                                 
		    $linkquery = new TextSearchQuery("$s", $args['case_exact'], $args['regex']);
		$querydesc = "$attribute $attr_op $s";
	    } else {
		$querydesc = $attribute." ".$attr_op." ".$s;
		return HTML($form, $this->error(fmt("Only text operators can used with strings: %s",
		                                    HTML::tt($querydesc))));
	    }
	    $links = $dbi->linkSearch($pagequery, $linkquery, 'attribute', $relquery);
	    if (empty($relation)) {
	        $pagelist = new PageList($args['info'], $args['exclude'], $args);
	        $pagelist->_links = array();
	    }
	    while ($link = $links->next()) {
	        $pagelist->addPage($link['pagename']);
	        $pagelist->_links[] = $link;
	    }
	    $pagelist->addColumnObject
		(new _PageList_Column_SemanticSearch_relation('attribute', _("Attribute"), $pagelist));
	    $pagelist->addColumnObject
		(new _PageList_Column_SemanticSearch_link('value', _("Value"), $pagelist));
	}
	if (!isset($pagelist)) {
	    $querydesc = _("<empty>");
	    $pagelist = new PageList();
	}
	if (!$noheader) {
	// We put the form into the caption just to be able to return one pagelist object, 
	// and to still have the convenience form at the top. we could workaround this by 
	// putting the form as WikiFormRich into the actionpage. but thid doesnt look as 
	// nice as this here.
	    $pagelist->setCaption
	    (   // on mozilla the form doesn't fit into the caption very well.
		HTML($noform ? '' : HTML($form,HTML::hr()),
	             fmt("Semantic %s Search Result for \"%s\" in pages \"%s\"",$searchtype,$querydesc,$page)));
	}
	return $pagelist;
    }
};

class _PageList_Column_SemanticSearch_relation 
extends _PageList_Column 
{
    function _PageList_Column_SemanticSearch_relation ($field, $heading, &$pagelist) {
	$this->_field = $field;
        $this->_heading = $heading;
	$this->_need_rev = false;
	$this->_iscustom = true;
	$this->_pagelist =& $pagelist;
    }
    function _getValue(&$page, $revision_handle) {
	if (is_object($page)) $text = $page->getName();
        else $text = $page;
        $link = $this->_pagelist->_links[$this->current_row];
        return WikiLink($link['linkname'],'if_known');
    }
}
class _PageList_Column_SemanticSearch_link 
extends _PageList_Column_SemanticSearch_relation 
{
    function _getValue(&$page, $revision_handle) {
	if (is_object($page)) $text = $page->getName();
        else $text = $page;
        $link = $this->_pagelist->_links[$this->current_row];
        if ($this->_field != 'value')
            return WikiLink($link['linkvalue'],'if_known');
        else    
	    return $link['linkvalue'];
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.1  2006/03/07 20:52:01  rurban
// not yet working good enough
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
