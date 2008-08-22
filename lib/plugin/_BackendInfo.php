<?php // -*-php-*-
rcs_id('$Id$');
/**
 Copyright 1999,2000,2001,2002,2006,2007 $ThePhpWikiProgrammingTeam

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

require_once('lib/Template.php');
/**
 */
class WikiPlugin__BackendInfo
extends WikiPlugin
{
    function getName () {
        return _("DebugInfo");
    }

    function getDescription () {
        return sprintf(_("Get debugging information for %s."), '[pagename]');
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision$");
    }

    function getDefaultArguments() {
        return array('page' => '[pagename]',
		     'notallversions' => 0);
    }

    function run($dbi, $argstr, &$request, $basepage) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page))
            return '';

        $backend = &$dbi->_backend;
        $this->chunk_split = true;
        $this->readonly_pagemeta = array();
        $this->hidden_pagemeta = array ('_cached_html');

        $html = HTML(HTML::h3(fmt("Querying backend directly for '%s'",
                                  $page)));

        $table = HTML::table(array('border' => 1,
                                   'cellpadding' => 2,
                                   'cellspacing' => 0));
        $pagedata = $backend->get_pagedata($page);
        if (!$pagedata) {
            // FIXME: invalid HTML
            $html->pushContent(HTML::p(fmt("No pagedata for %s", $page)));
        }
        else {
            $this->_fixupData($pagedata);
            $table->pushContent($this->_showhash("get_pagedata('$page')", $pagedata));
        }
	if (!$notallversions) {
	    $version = $backend->get_latest_version($page);
	    $vdata = $backend->get_versiondata($page, $version, true);
	    $this->_fixupData($vdata);
	    $table->pushContent(HTML::tr(HTML::td(array('colspan' => 2))));
	    $table->pushContent($this->_showhash("get_versiondata('$page',$version)",
						 $vdata));
	} else {
	    for ($version = $backend->get_latest_version($page);
		 $version;
		 $version = $backend->get_previous_version($page, $version))
		{
		    $vdata = $backend->get_versiondata($page, $version, true);
		    $this->_fixupData($vdata);
		    $table->pushContent(HTML::tr(HTML::td(array('colspan' => 2))));
		    $table->pushContent($this->_showhash("get_versiondata('$page',$version)",
							 $vdata));
		}
	}

        $linkdata = $backend->get_links($page, false);
        if ($linkdata->count())
            $table->pushContent($this->_showhash("get_links('$page')", $linkdata->asArray()));
        $relations = $backend->get_links($page, false, false, false, false, false, true);
        if ($relations->count()) {
            $table->pushContent($this->_showhash("get_relations('$page')", array()));
            while ($rel = $relations->next())
                $table->pushContent($this->_showhash(false, $rel));
        }
        $linkdata = $backend->get_links($page, true);
        if ($linkdata->count())
            $table->pushContent($this->_showhash("get_backlinks('$page')", $linkdata->asArray()));

        $html->pushContent($table);
        return $html;
    }

    /**
     * Really should have a _fixupPagedata and _fixupVersiondata, but this works.
     * also used in plugin/EditMetaData
     */
    function _fixupData(&$data, $prefix='') {
	if (!is_array($data)) return;
	
        global $request;
        $user = $request->getUser();
        foreach ($data as $key => $val) {
            $fullkey = $prefix . '[' . $key . ']';
            if (is_integer($key)) {
            	;
            } elseif ($key == 'passwd' and !$user->isAdmin()) {
                $data[$key] = $val ? _("<not displayed>") : _("<empty>");
            } elseif ($key and $key == '_cached_html') {
                $val = TransformedText::unpack($val);
                ob_start();
                print_r($val);
                $data[$key] = HTML::pre(ob_get_contents());
                ob_end_clean();
            }
            elseif (is_bool($val)) {
            	$data[$key] = $this->_showvalue($key, $val ? "true" : "false", $prefix);
            }
            elseif (is_string($val) && ((substr($val, 0, 2) == 'a:' 
					 or (substr($val, 0, 2) == 'O:')))) 
	    {
                // how to indent this table?
                $val = unserialize($val);
                $this->_fixupData($val, $fullkey);
                $data[$key] = HTML::table(array('border' => 1,
                                                'cellpadding' => 2,
                                                'cellspacing' => 0),
                                          $this->_showhash(false, $val, $fullkey));
            }
            elseif (is_array($val)) {
                // how to indent this table?
                $this->_fixupData($val, $fullkey);
                $data[$key] = HTML::table(array('border' => 1,
                                                'cellpadding' => 2,
                                                'cellspacing' => 0),
                                          $this->_showhash(false, $val, $fullkey));
            } elseif (is_object($val)) {
                // how to indent this table?
                ob_start();
                print_r($val);
                $val = HTML::pre(ob_get_contents());
                ob_end_clean();
                $data[$key] = HTML::table(array('border' => 1,
                                                'cellpadding' => 2,
                                                'cellspacing' => 0),
                                          $this->_showhash(false, $val, $fullkey));
            }
            elseif ($key and $key == '%content') {
                if ($val === true)
                    $val = '<true>';
                elseif (strlen($val) > 40)
                    $val = substr($val,0,40) . " ...";
                $data[$key] = $val;
            }
        }
        unset($data['%pagedata']); // problem in backend
    }

    /* also used in plugin/EditMetaData */
    function _showhash ($heading, $hash, $prefix='') {
        $rows = array();
        if ($heading)
            $rows[] = HTML::tr(array('bgcolor' => '#ffcccc',
                                     'style' => 'color:#000000'),
                               HTML::td(array('colspan' => 2,
                                              'style' => 'color:#000000'),
                                        $heading));
	if (!is_array($hash)) return array();
        ksort($hash);
        foreach ($hash as $key => $val) {
            if ($this->chunk_split and is_string($val)) $val = chunk_split($val);	
            $rows[] = HTML::tr(HTML::td(array('align' => 'right',
                                              'bgcolor' => '#cccccc',
                                              'style' => 'color:#000000'),
                                        HTML(HTML::raw('&nbsp;'), $key,
                                             HTML::raw('&nbsp;'))),
                               HTML::td(array('bgcolor' => '#ffffff',
                                              'style' => 'color:#000000'),
                                        $this->_showvalue($key, $val, $prefix))
                               );
        }
        return $rows;
    }

    /* also used in plugin/EditMetaData */
    function _showvalue ($key, $val, $prefix='') {
	return $val ? $val : HTML::raw('&nbsp;');
    }

};

// $Log: not supported by cvs2svn $
// Revision 1.27  2007/05/13 18:13:48  rurban
// base class for EditmetaData
//
// Revision 1.26  2007/01/07 18:44:47  rurban
// Add notallversion argument. Support more types: objects, serialized objects. split overlong strings
//
// Revision 1.25  2006/09/06 06:02:36  rurban
// print linkinfo also
//
// Revision 1.24  2005/01/29 19:47:43  rurban
// support bool
//
// Revision 1.23  2005/01/21 14:13:23  rurban
// stabilize on numeric keys (strange php problem)
//
// Revision 1.22  2004/02/17 12:11:36  rurban
// added missing 4th basepage arg at plugin->run() to almost all plugins. This caused no harm so far, because it was silently dropped on normal usage. However on plugin internal ->run invocations it failed. (InterWikiSearch, IncludeSiteMap, ...)
//
// Revision 1.21  2003/02/21 04:22:28  dairiki
// Make this work for array-valued data.  Make display of cached markup
// readable.  Some code cleanups.  (This still needs more work.)
//
// Revision 1.20  2003/01/18 21:19:24  carstenklapp
// Code cleanup:
// Reformatting; added copyleft, getVersion, getDescription
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
