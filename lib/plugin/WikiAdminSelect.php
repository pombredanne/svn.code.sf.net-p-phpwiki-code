<?php // -*-php-*-
rcs_id('$Id: WikiAdminSelect.php,v 1.6 2004-01-26 19:15:29 rurban Exp $');
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
 * Allows selection of multiple pages which get passed to other
 * WikiAdmin plugins then. Then do Rename, Remove, Chmod, Chown, ...
 *
 * Usage:   <?plugin WikiAdminSelect?>
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * KNOWN ISSUES:
 * Just a framework, nothing more.
 * Future versions will support PagePermissions.
 */
// maybe display more attributes with this class...
require_once('lib/PageList.php');

class WikiPlugin_WikiAdminSelect
extends WikiPlugin
{
    function getName() {
        return _("WikiAdminSelect");
    }

    function getDescription() {
        return _("Allows selection of multiple pages which get passed to other WikiAdmin plugins.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.6 $");
    }

    function getDefaultArguments() {
        return array('s'   => '*',
                     'only'    => '',
                     'exclude' => '',
                     'info'    => 'all',
                     'debug'   => false);
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
        else
            $only = false;
        if (!empty($args['exclude']))
            $exclude = explodePageList($args['exclude']);
        else
            $exclude = false;
        $info = $args['info'];
        $this->debug = $args['debug'];
        if (!empty($request->getArg['s']))
            $args['s'] = $request->getArg['s'];
        if (!empty($args['s'])) {
            $s = $args['s'];
            $sl = explodePageList($args['s']);
            $this->_list = array();
            if ($sl) {
                $request->setArg('verify',1);
                foreach ($sl as $name) {
                    $this->_list[$name] = 1;
                }
            }
        } else {
            $s = '*';
            $this->_list = array();
        }
        $this->debug = $args['debug'];
        // array_multisort($this->_list, SORT_NUMERIC, SORT_DESC);
        $pagename = $request->getArg('pagename');
        // GetUrlToSelf() with all given params
        //$uri = $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']; // without s would be better.
        $uri = $request->getURLtoSelf(false, array('verify'));
        $form = HTML::form(array('action' => $uri, 'method' => 'POST'));
        if ($request->getArg('submit') == 'WikiAdminSelect')
            $p = false;
        else
            $p = $request->getArg('p');
        //$p = @$GLOBALS['HTTP_POST_VARS']['p'];
        $form->pushContent(HTML::p(array('class' => 'wikitext'), _("Select: "),
                                   HTML::input(array('type' => 'text',
                                                     'name' => 's',
                                                     'value' => $s)),
                                   HTML::input(array('type' => 'submit',
                                                     'name' => 'WikiAdminSelect',
                                                     'value' => _("Go")))));
        if ($request->isPost() && $request->getArg('verify') && !empty($p)) {
            // List all selected pages again.
            foreach ($p as $page => $name) {
                $this->_list[$name] = 1;
            }
        } elseif ($request->isPost() && $request->_user->isAdmin()
                  && !empty($p)
                  && $request->getArg('action') == 'WikiAdminSelect') {
            // handle external plugin
            $l = new WikiPluginLoader();
            $plugin_action = $request->getArg('submit');
            $plugin = $l->getPlugin($plugin_action);

            $ul = HTML::ul();
            foreach ($p as $page => $name) {
                $plugin_args = "run_page=$name";
                $request->setArg($plugin_action, 1);
                $request->setArg('p', array($page => $name));
                $action_result = $plugin->run($dbi, $plugin_args, $request);
                $ul->pushContent(HTML::li(fmt("Selected page '%s' passed to '%s'.",
                                              $name, $select)));
                $ul->pushContent(HTML::ul(HTML::li($action_result)));
            }
        } elseif (empty($args['s'])) {
            // List all pages to select from.
            $this->collectPages($this->_list, $dbi);
        }
        $pagelist = new PageList_Selectable($info
                                            ? 'checkbox,' . $info
                                            : 'checkbox', $exclude);
        $pagelist->addPageList($this->_list);
        $form->pushContent($pagelist->getContent());
        foreach ($_GET as $k => $v) {
            if (!in_array($k,array('s','WikiAdminSelect','action')))
                $form->pushContent(HiddenInputs(array($k => $v))); // debugging params, ...
        }
        if (! $request->getArg('verify')) {
            $form->pushContent(HTML::input(array('type' => 'hidden',
                                                 'name' => 'action',
                                                 'value' => 'verify')));
            $form->pushContent(Button('submit:verify', _("Select pages"),
                                      'wikiadmin'),
                               Button('submit:cancel', _("Cancel"), 'button'));
        } else {
            global $Theme;
            $form->pushContent(HTML::input(array('type' => 'hidden',
                                                 'name' => 'action',
                                                 'value' => 'WikiAdminSelect'))
                               );
            // Add the Buttons for all registered WikiAdmin plugins
            $plugin_dir = 'lib/plugin';
            if (defined('PHPWIKI_DIR'))
                $plugin_dir = PHPWIKI_DIR . "/$plugin_dir";
            $fs = new fileSet($plugin_dir, 'WikiAdmin*.php');
            $actions = $fs->getFiles();
            foreach ($actions as $f) {
                $f = preg_replace('/.php$/','', $f);
                $s = preg_replace('/^WikiAdmin/','', $f);
                $form->pushContent(Button("submit:$f", _($s), "wikiadmin"));
                $form->pushContent($Theme->getButtonSeparator());
            }
            $form->pushContent(Button('submit:cancel', _("Cancel"), 'button'));
        }
        if (! $request->getArg('select')) {
            return $form;
        } else {
            ; //return $action_result;
        }
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.5  2003/02/24 19:38:04  dairiki
// Get rid of unused method Request::debugVars().
//
// Revision 1.4  2003/02/24 01:36:27  dairiki
// Don't use PHPWIKI_DIR unless it's defined.
// (Also typo/bugfix in SystemInfo plugin.)
//
// Revision 1.3  2003/02/22 20:49:56  dairiki
// Fixes for "Call-time pass by reference has been deprecated" errors.
//
// Revision 1.2  2003/01/18 22:14:29  carstenklapp
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
