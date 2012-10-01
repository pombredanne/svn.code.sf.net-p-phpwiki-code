<?php // -*-php-*-
rcs_id('$Id: SemanticRelations.php,v 1.4 2007-01-25 07:42:22 rurban Exp $');
/*
 Copyright 2005 Reini Urban

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

/**
 * SemanticRelations - Display the list of relations and attributes of given page(s).
 * Relations are stored in the link table.
 * Attributes as simple page meta-data.
 *
 * @author: Reini Urban
 * @see WikiPlugin_SemanticSearch
 */
class WikiPlugin_SemanticRelations
extends WikiPlugin
{
    function getName() {
        return _("SemanticRelations");
    }
    function getDescription() {
        return _("Display the list of relations and attributes on this page.");
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.4 $");
    }
    function getDefaultArguments() { 
        return array(
                     'page'       => "[pagename]", // which pages (glob allowed), default: current
                     'relations'  => '', // which relations. default all
                     'attributes' => '', // which attributes. default all
                     'units'      => '', // ?
                     'noheader'   => false,
                     'nohelp'     => false
                     );
    }
    function run ($dbi, $argstr, &$request, $basepage) { 
        global $WikiTheme;
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page))
            $page = $request->getArg('pagename');
        $relhtml = HTML();
        foreach (explodePageList($page) as $pagename) {
            $p = $dbi->getPage($pagename);
            $links = $p->getRelations(); // iter of pagelinks
            // TODO: merge same relations together located_in::here, located_in::there
            while ($object = $links->next()) {
                if ($related = $object->get('linkrelation')) { // a page name
                    $rellink = WikiLink($related, false, $related);
                    $rellink->setAttr('class', $rellink->getAttr('class').' relation');
                    $relhtml->pushContent
                        ($pagename . " ",
                         // Link to a special "Relation:" InterWiki link?
                         $rellink, 
                         HTML::span(array('class'=>'relation-symbol'), "::"), // use spaces?
                         WikiLink($object->_pagename), 
                         " ",
                         // Link to SemanticSearch
                         $WikiTheme->makeActionButton(array('relation' => $related,
                                                            's'   => $object->_pagename),
                                                      '+',
                                                      _("SemanticSearch")),
                         HTML::br());
                }
            }
            if (!empty($relhtml->_content) and !$noheader)
                $relhtml = HTML(HTML::hr(),
                                HTML::h3(fmt("Semantic relations for %s", $pagename)),
                                $relhtml);
            $atthtml = HTML();
            if ($attributes = $p->get('attributes')) { // a hash of unique pairs
                foreach ($attributes as $att => $val) {
                    $rellink = WikiLink($att, false, $att);
                    $rellink->setAttr('class', $rellink->getAttr('class').' relation');
                    $searchlink = $WikiTheme->makeActionButton
			(array('attribute' => $att,
			       's'         => $val),
			 '+',
			 _("SemanticSearch"));
                    if (!$noheader)
                        $atthtml->pushContent("$pagename  ");
		    $atthtml->pushContent(HTML::span($rellink, 
						     HTML::span(array('class'=>'relation-symbol'), 
								":="), 
						     HTML($val)),
					  " ", $searchlink,
					  HTML::br());
                }
                if (!$noheader)
                    $relhtml = HTML($relhtml,
                                    HTML::hr(),
                                    HTML::h3(fmt("Attributes of %s", $pagename)), 
                                    $atthtml);
                else
                    $relhtml = HTML($relhtml, $atthtml);
            }
        }
        if ($nohelp) return $relhtml;
        return HTML($relhtml, 
                    HTML::hr(), 
                    WikiLink(_("Help/SemanticRelations"), false,
                             HTML::em(_("Help/SemanticRelations"))),
                    " - ",
                    HTML::em(_("Find out how to add relations and attributes to pages.")));
    }

};

// $Log: not supported by cvs2svn $
// Revision 1.3  2007/01/03 21:23:06  rurban
// Clarify description: "on this page".
//
// Revision 1.2  2007/01/02 13:22:41  rurban
// default pagename: current. improve output: class, linked attributes. switch to SemanticSearch argument s
//
// Revision 1.1  2005/11/21 20:14:20  rurban
// Plugin to display the list of SemanticRelations - list of relations and
// attributes of given page(s).
// Relations are stored in the link table.
// Attributes as simple page meta-data.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>