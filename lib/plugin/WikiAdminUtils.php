<?php // -*-php-*-
rcs_id('$Id: WikiAdminUtils.php,v 1.4 2003-02-26 00:25:28 dairiki Exp $');
/**
 Copyright 2003 $ThePhpWikiProgrammingTeam

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
 */
class WikiPlugin_WikiAdminUtils
extends WikiPlugin
{
    function getName () {
        return _("WikiAdminUtils");
    }

    function getDescription () {
        return _("Miscellaneous utility functions of use to the administrator.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.4 $");
    }

    function getDefaultArguments() {
        return array('action'           => '',
                     'label'		=> '',
                     );
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        $args['action'] = strtolower($args['action']);
        extract($args);
        
        if (!$action)
            $this->error("No action specified");
        if (!($default_label = $this->_getLabel($action)))
            $this->error("Bad action");
        if ($request->getArg('action') != 'browse')
            return $this->disabled("(action != 'browse')");
        
        $posted = $request->getArg('wikiadminutils');
        $request->setArg('wikiadminutils', false);

        if ($request->isPost()) {
            $user = $request->getUser();
            if (!$user->isAdmin())
                return $this->error(_("You must be an administrator to use this plugin."));
            return $this->do_action($request, $posted);
        }

        if (empty($label))
            $label = $default_label;
        
        return $this->_makeButton($request, $args, $label);
    }

    function _makeButton(&$request, $args, $label) {
        $args['return_url'] = $request->getURLtoSelf();
        return HTML::form(array('action' => $request->getPostURL(),
                                'method' => 'post'),
                          HTML::p(Button('submit:', $label, 'wikiadmin')),
                          HiddenInputs($args, 'wikiadminutils'),
                          HiddenInputs(array('require_authority_for_post' =>
                                             WIKIAUTH_ADMIN)),
                          HiddenInputs($request->getArgs()));
    }
    
    function do_action(&$request, $args) {
        $method = '_do_' . str_replace('-', '_', $args['action']);
        if (!method_exists($this, $method))
            return $this->error("Bad action");

        $message = call_user_func(array(&$this, $method), $request, $args);

        $alert = new Alert(_("WikiAdminUtils says:"),
                           $message,
                           array(_("Okay") => $args['return_url']));

        $alert->show();         // noreturn
    }

    function _getLabel($action) {
        $labels = array('purge-cache' => _("Purge Markup Cache"),
                        'purge-bad-pagenames' => _("Delete Pages With Invalid Names"));
        return @$labels[$action];
    }

    function _do_purge_cache(&$request, $args) {
        $dbi = $request->getDbh();
        $pages = $dbi->getAllPages('include_empty'); // Do we really want the empty ones too?
        while (($page = $pages->next())) {
            $page->set('_cached_html', false);
        }
        return _("Markup cache purged!");
    }

    function _do_purge_bad_pagenames(&$request, $args) {
        // FIXME: this should be moved into WikiDB::normalize() or something...
        $dbi = $request->getDbh();
        $pages = $dbi->getAllPages('include_empty'); // Do we really want the empty ones too?
        $badpages = array();
        while (($page = $pages->next())) {
            $pagename = $page->getName();
            $wpn = new WikiPageName($pagename);
            if (! $wpn->isValid())
                $badpages[] = $pagename;
        }

        if (!$badpages)
            return _("No pages with bad names were found.");
        
        $list = HTML::ul();
        foreach ($badpages as $pagename) {
            $dbi->deletePage($pagename);
            $list->pushContent(HTML::li($pagename));
        }
        
        return HTML(fmt("Deleted %s pages with invalid names:",
                        count($badpages)),
                    $list);
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
