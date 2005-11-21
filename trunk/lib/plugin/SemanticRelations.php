<?php // -*-php-*-
rcs_id('$Id: SemanticRelations.php,v 1.1 2005-11-21 20:14:20 rurban Exp $');
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
 */
class WikiPlugin_SemanticRelations
extends WikiPlugin
{
    function getName() {
        return _("SemanticRelations");
    }
    function getDescription() {
        return _("Display the list of relations and attributes");
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }
    function getDefaultArguments() { 
        return array(
                     'page'       => "", // which pages (glob allowed), default: current
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
                    $relhtml->pushContent
                        ($pagename . " ",
                         // Link to a special "Relation:" InterWiki link?
                         WikiLink($related, false, $related), 
                         " :: ", // use spaces?
                         WikiLink($object->_pagename), 
                         " ",
                         // Link to SemanticSearch
                         $WikiTheme->makeActionButton(array('relation' => $related,
                                                            'object'   => $object->_pagename),
                                                      '+',
                                                      _("SemanticSearch")),
                         HTML::br());
                }
            }
            if (!empty($relhtml->_content) and !$noheader)
                $relhtml = HTML(HTML::hr(),
                                HTML::h3(fmt("Semantic relations for %s", $p->getName())),
                                $relhtml);
            $atthtml = HTML();
            if ($attributes = $p->get('attributes')) { // a hash of unique pairs
                foreach ($attributes as $att => $val) {
                    if ($noheader)
                        $atthtml->pushContent("$pagename  $att := $val", HTML::br());
                    else
                        $atthtml->pushContent("$att := $val", HTML::br());
                }
                if (!$noheader)
                    $relhtml = HTML($relhtml,
                                    HTML::hr(),
                                    HTML::h3(fmt("Attributes of %s", $p->getName())), 
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

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
