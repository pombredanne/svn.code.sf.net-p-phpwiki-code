<?php // -*-php-*-
rcs_id('$Id: WikiForm.php,v 1.8 2003-01-18 22:14:30 carstenklapp Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

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
 * This is a replacement for MagicPhpWikiURL forms.
 *
 *
 */
class WikiPlugin_WikiForm
extends WikiPlugin
{
    function getName () {
        return _("WikiForm");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.8 $");
    }

    function getDefaultArguments() {
        return array('action' => 'upload', // 'upload', 'loadfile', or
                                           // 'dumpserial'
                     'default' => false,
                     'buttontext' => false,
                     'size' => 50);
    }


    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        $form = HTML::form(array('action' => USE_PATH_INFO
                                             ? WikiURL($request->getPage())
                                             : SCRIPT_NAME,
                                 'method' => 'post',
                                 'class'  => 'wikiadmin',
                                 'accept-charset' => CHARSET),
                           HTML::input(array('type' => 'hidden',
                                             'name' => 'action',
                                             'value' => $action)),
                           USE_PATH_INFO
                           ? false
                           : HTML::input(array('type' => 'hidden',
                                               'name' => 'pagename',
                                               'value' => $targetpage)));
        $input = array('type' => 'text',
                       'value' => $default,
                       'size' => $size);

        switch ($action) {
        case 'loadfile':
            $input['name'] = 'source';
            if (!$default)
                $input['value'] = '/tmp/wikidump';
            if (!$buttontext)
                $buttontext = _("Load File");
            $class = false;
            break;
        case 'login':
            $input['name'] = 'source';
            if (!$buttontext)
                $buttontext = _("Login");
            $class = 'wikiadmin';
            break;
        case 'dumpserial':
            $input['name'] = 'directory';
            if (!$default)
                $input['value'] = '/tmp/wikidump';
            if (!$buttontext)
                $buttontext = _("Dump Pages");
            $class = 'wikiadmin';
            break;
        case 'dumphtml':
            $input['name'] = 'directory';
            if (!$default)
                $input['value'] = '/tmp/wikidumphtml';
            if (!$buttontext)
                $buttontext = _("Dump Pages as XHTML");
            $class = 'wikiadmin';
            break;
        case 'upload':
            $form->setAttr('enctype', 'multipart/form-data');
            $form->pushContent(HTML::input(array('name' => 'MAX_FILE_SIZE',
                                                 'value' =>  MAX_UPLOAD_SIZE,
                                                 'type'  => 'hidden')));
            $input['name'] = 'file';
            $input['type'] = 'file';
            if (!$buttontext)
                $buttontext = _("Upload");
            $class = false; // local OS function, so use native OS button
            break;
        default:
            return HTML::p(fmt("WikiForm: %s: unknown action", $action));
        }


        $input = HTML::input($input);
        $input->addTooltip($buttontext);
        $button = Button('submit:', $buttontext, $class);

        $form->pushContent(HTML::span(array('class' => $class),
                                      $input, $button));

        return $form;
    }
};

// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
