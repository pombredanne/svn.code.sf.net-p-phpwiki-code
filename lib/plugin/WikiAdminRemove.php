<?php // -*-php-*-
rcs_id('$Id: WikiAdminRemove.php,v 1.6 2003-02-16 19:47:17 dairiki Exp $');
/*
 Copyright 2002 $ThePhpWikiProgrammingTeam

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
 * Displays a url in a seperate frame inside our body.
 * Usage:   <?plugin WikiAdminRemove?>
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * KNOWN ISSUES:
 * Currently we must be Admin.
 * Future versions will support PagePermissions.
 * requires PHP 4.2 so far.
 */
// maybe display more attributes with this class...
require_once('lib/PageList.php');

class WikiPlugin_WikiAdminRemove
extends WikiPlugin
{
    function getName() {
        return _("WikiAdminRemove");
    }

    function getDescription() {
        return _("Permanently remove all selected pages.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.6 $");
    }

    function getDefaultArguments() {
        return array('only'     => '',
                     'exclude'  => '',
                     'run_page' => false, // why this? forgot it.
                     'info'     => '',
                     'sortby'   => '',
                     'debug'    => false);
    }

    function collectPages(&$list, &$dbi) {
        $allPages = $dbi->getAllPages();
        while ($pagehandle = $allPages->next()) {
            $pagename = $pagehandle->getName();
            if (empty($list[$pagename]))
                $list[$pagename] = 0;
        }
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        if (!empty($args['only']))
            $only = explodePageList($args['only']);
        else $only = false;
        if (!empty($args['exclude']))
            $exclude = explodePageList($args['exclude']);
        else
            $exclude = false;
        $info = $args['info'];
        $this->debug = $args['debug'];

        $this->_list = array();
        // array_multisort($this->_list, SORT_NUMERIC, SORT_DESC);
        $pagename = $request->getArg('pagename');
        // GetUrlToSelf() with all given params
        //$uri = $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'];
        $uri = $request->getURLtoSelf($request->debugVars(), array('verify'));
        $form = HTML::form(array('action' => $uri, 'method' => 'POST'));
        $p = $request->getArg('p');
        // Handle WikiAdminSelect
        if ($request->isPost() && $request->_user->isAdmin()
            && $p && $request->getArg('action') == 'select') {
            $request->setArg('verify',1);
            foreach ($p as $page => $name) {
                $this->_list[$name] = 1;
            }
        } elseif ($request->isPost() && $request->_user->isAdmin()
                  && $request->getArg('verify')) {
            // List all to be deleted pages again.
            foreach ($p as $page => $name) {
                $this->_list[$name] = 1;
            }
        } elseif ($request->isPost() && $request->_user->isAdmin()
                  && $request->getArg('remove')) {
            // Real delete.
            $ul = HTML::ul();
            foreach ($p as $page => $name) {
                $dbi = $request->getDbh();
                $dbi->deletePage($name);
                $ul->pushContent(HTML::li(fmt("Removed page '%s' succesfully.", $name)));
            }
            $dbi->touch();
        } else {
            // List all pages to select from.
            $this->collectPages($this->_list, &$dbi);
        }
        $pagelist = new PageList_Selectable($info
                                            ? 'checkbox,' . $info
                                            : 'checkbox', $exclude);
        $pagelist->addPageList($this->_list);
        $form->pushContent($pagelist->getContent());
        $form->pushContent(HiddenGets(array('s', 'sortby', 'verify',
                                            'WikiAdminRemove', 'remove')));
        // if (! USE_PATH_INFO ) $form->pushContent(HTML::input(array('type' => 'hidden', 'name' => 'pagename', 'value' => $pagename)));
        if (! $request->getArg('verify')) {
            $form->pushContent(HTML::input(array('type' => 'hidden',
                                                 'name' => 'action',
                                                 'value' => 'verify')));
            $form->pushContent(Button('submit:verify',
                                      _("Remove selected pages"),
                                      'wikiadmin'),
                               Button('submit:cancel', _("Cancel"), 'button'));
        } else {
            $form->pushContent(HTML::input(array('type' => 'hidden',
                                                 'name' => 'action',
                                                 'value' => 'WikiAdminRemove'))
                               );
            $form->pushContent(Button('submit:remove',
                                      _("Remove selected pages"), 'wikiadmin'),
                               Button('submit:cancel', _("Cancel"), 'button'));
        }
        if (! $request->getArg('remove')) {
            return $form;
        } else {
            return HTML::div($ul, HTML::p(_('All selected pages have been permanently removed.')));
        }
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.5  2003/01/18 22:14:28  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
