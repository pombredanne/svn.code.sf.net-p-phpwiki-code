<?php // -*-php-*-
rcs_id('$Id$');
/**
 Copyright 1999,2000,2001,2002,2007 $ThePhpWikiProgrammingTeam

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
 * Plugin EditMetaData
 *
 * This plugin shows the current page-level metadata and gives an
 * entry box for adding a new field or changing an existing one. (A
 * field can be deleted by specifying a blank value.) Certain fields,
 * such as 'hits' cannot be changed.
 *
 * If there is a reason to do so, I will add support for revision-
 * level metadata as well.
 *
 * Access by restricted to ADMIN_USER
 *
 * Written by MichaelVanDam, to test out some ideas about
 * PagePermissions and PageTypes.
 *
 * Rewritten for recursive array support by ReiniUrban.
 */

require_once('lib/plugin/_BackendInfo.php');

class WikiPlugin_EditMetaData 
extends WikiPlugin__BackendInfo
{
    function getName () {
        return _("EditMetaData");
    }

    function getDescription () {
        return sprintf(_("Edit metadata for %s"), '[pagename]');
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision$");
    }

    function getDefaultArguments() {
        return array('page'       => '[pagename]'
                    );
    }

    function run($dbi, $argstr, &$request, $basepage) {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);
        if (!$page)
            return '';

        $this->hidden_pagemeta = array ('_cached_html');
        $this->readonly_pagemeta = array ('hits', 'passwd');
        $dbi = $request->getDbh();
        $p = $dbi->getPage($page);
        $pagemeta = $p->getMetaData();
        $this->chunk_split = false;
        
        // Look at arguments to see if submit was entered. If so,
        // process this request before displaying.
        //
        if ($request->isPost() 
	    and $request->_user->isAdmin() 
	    and $request->getArg('metaedit')) 
	{
            $metafield = trim($request->getArg('metafield'));
            $metavalue = trim($request->getArg('metavalue'));
            $meta = $request->getArg('meta');
            $changed = 0;
            // meta[__global[_upgrade][name]] => 1030.13
            foreach ($meta as $key => $val) {
            	if ($val != $pagemeta[$key] 
		    and !in_array($key, $this->readonly_pagemeta)) 
		{
            	    $changed++;
                    $p->set($key, $val);
                }
            }
            if ($metafield and !in_array($metafield, $this->readonly_pagemeta)) {
            	// __global[_upgrade][name] => 1030.13
                if (preg_match('/^(.*?)\[(.*?)\]$/', $metafield, $matches)) {
                    list(, $array_field, $array_key) = $matches;
                    $array_value = $pagemeta[$array_field];
                    $array_value[$array_key] = $metavalue;
                    if ($pagemeta[$array_field] != $array_value) {
	            	$changed++;
                    	$p->set($array_field, $array_value);
                    }
                } elseif ($pagemeta[$metafield] != $metavalue) {
	            $changed++;
                    $p->set($metafield, $metavalue);
                }
            }
            if ($changed) {
                $dbi->touch();
		$url = $request->getURLtoSelf(false, 
                                          array('meta','metaedit','metafield','metavalue'));
		$request->redirect($url);
		// The rest of the output will not be seen due to the
		// redirect.
		return '';
	    }
        }

        // Now we show the meta data and provide entry box for new data.
        $html = HTML();
        //$html->pushContent(HTML::h3(fmt("Existing page-level metadata for %s:",
	//				$page)));
	//$dl = $this->_display_values('', $pagemeta);
        //$html->pushContent($dl);
        if (!$pagemeta) {
            // FIXME: invalid HTML
            $html->pushContent(HTML::p(fmt("No metadata for %s", $page)));
	    $table = HTML();
        }
        else {
	    $table = HTML::table(array('border' => 1,
				       'cellpadding' => 2,
				       'cellspacing' => 0));
            $this->_fixupData($pagemeta);
            $table->pushContent($this->_showhash("MetaData('$page')", $pagemeta));
        }

        if ($request->_user->isAdmin()) {
            $action = $request->getPostURL();
            $hiddenfield = HiddenInputs($request->getArgs());
            $instructions = _("Add or change a page-level metadata 'key=>value' pair. Note that you can remove a key by leaving the value-box empty.");
            $keyfield = HTML::input(array('name' => 'metafield'), '');
            $valfield = HTML::input(array('name' => 'metavalue'), '');
            $button = Button('submit:metaedit', _("Submit"), false);
            $form = HTML::form(array('action' => $action,
                                     'method' => 'post',
                                     'accept-charset' => $GLOBALS['charset']),
                               $hiddenfield,
			       // edit existing fields
			       $table,
			       // add new ones
                               $instructions, HTML::br(),
                               $keyfield, ' => ', $valfield,
                               HTML::raw('&nbsp;'), $button
                               );

            $html->pushContent($form);
        } else {
            $html->pushContent(HTML::em(_("Requires WikiAdmin privileges to edit.")));
        }
        return $html;
    }

    function _showvalue ($key, $val, $prefix='') {
    	if (is_array($val) or is_object($val)) return $val;
	if (in_array($key, $this->hidden_pagemeta)) return '';
	if ($prefix) {
	    $fullkey = $prefix . '[' . $key . ']';
	    if (substr($fullkey,0,1) == '[') {
	    	$meta = "meta".$fullkey;
	    	$fullkey = preg_replace("/\]\[/", "[", substr($fullkey, 1), 1);
	    } else {
		$meta = preg_replace("/^([^\[]+)\[/", "meta[$1][", $fullkey, 1);
	    }
	} else {
	    $fullkey = $key;
	    $meta = "meta[".$key."]";
	}
	//$meta = "meta[".$fullkey."]";
        $arr = array('name' => $meta, 'value' => $val);
	if (strlen($val) > 20)
	    $arr['size'] = strlen($val);
	if (in_array($key, $this->readonly_pagemeta)) {
	    $arr['readonly'] = 'readonly';
	    return HTML::input($arr);
	} else {
	    return HTML(HTML::em($fullkey), HTML::br(),
	    		HTML::input($arr));
	}
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.13  2007/05/15 16:32:25  rurban
// Recursive array editing: support global_data
//
// Revision 1.12  2007/01/04 16:46:31  rurban
// Make the header a h3
//
// Revision 1.11  2004/06/01 16:48:11  rurban
// dbi->touch
// security fix to allow post admin only.
//
// Revision 1.10  2004/04/18 01:11:52  rurban
// more numeric pagename fixes.
// fixed action=upload with merge conflict warnings.
// charset changed from constant to global (dynamic utf-8 switching)
//
// Revision 1.9  2004/02/17 12:11:36  rurban
// added missing 4th basepage arg at plugin->run() to almost all plugins. This caused no harm so far, because it was silently dropped on normal usage. However on plugin internal ->run invocations it failed. (InterWikiSearch, IncludeSiteMap, ...)
//
// Revision 1.8  2003/11/27 17:05:41  carstenklapp
// Update: Omit page cache object ('_cached_html') from metadata display.
//
// Revision 1.7  2003/11/22 17:41:42  carstenklapp
// Minor internal change: Removed redundant call to gettext within
// fmt(). (locale make: EditMetaData.php:113: warning: keyword nested in
// keyword arg)
//
// Revision 1.6  2003/02/26 01:56:52  dairiki
// Tuning/fixing of POST action URLs and hidden inputs.
//
// Revision 1.5  2003/02/21 04:17:13  dairiki
// Delete now irrelevant comment.
//
// Revision 1.4  2003/01/18 21:41:01  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
//

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
