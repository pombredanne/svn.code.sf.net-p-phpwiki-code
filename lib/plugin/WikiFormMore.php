<?php // -*-php-*-
rcs_id('$Id: WikiFormMore.php,v 1.1 2004-07-01 13:11:53 rurban Exp $');
/**
 Copyright 1999, 2000, 2001, 2002, 2004 $ThePhpWikiProgrammingTeam

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
 * This is another replacement for MagicPhpWikiURL forms.
 * Previously encoded with the "phpwiki:" syntax.
 *
 * Enhanced WikiForm to be more generic:
 * - required and optional editboxes 
 * - check boxes (flags)
 * - radio buttons (selections)
 * - pulldowns (selections)
 * - hidden args
 * - action, submit buttontext, optional cancel button
 * - GET or POST.
 * Samples:
   <?plugin WikiFormMore action=dumpserial method=GET 
            checkboxes[]=array('name'=>"include",'value'=>"all") 
            editboxes[]=array('name'=>"directory",'value'=>DEFAULT_DUMP_DIR) 
            editboxes[]=array('name'=>"pages",'value'=>"*") 
            editboxes[]=array('name'=>"exclude",'value'=>"") ?>
   <?plugin WikiFormMore action=dumphtml method=GET 
            editboxes[]=array('name'=>"directory",'value'=>HTML_DUMP_DIR) 
            editboxes[]=array('name'=>"pages",'value'=>"*") 
            editboxes[]=array('name'=>"exclude",'value'=>"") ?>
 */
class WikiPlugin_WikiFormMore
extends WikiPlugin
{
    function getName () {
        return _("WikiForm");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array('action' => false,
                     'method' => 'POST',
                     'class' => false,
                     'buttontext' => false,
                     'cancel'  => false,
                     );
    }

    function handle_plugin_args_cruft($argstr, $args) {
    	global $editboxes, $hidden, $checkboxes, $radiobuttons, $pulldown;
    	$allowed = array("editboxes", "hidden", "checkboxes", "radiobuttons", "pulldown");
    	$arg_array = preg_split("/[\n\s]+/", $argstr);
    	// for security we should check this better
    	for ($i = 0; $i < count($arg_array); $i++) {
    	    if (!preg_match("/^(".join("|",$allowed).")(\[\d*\])\s*=/", $arg_array[$i])) {
    	    	trigger_error(sprintf("Invalid argument %s ignored",htmlentities($arg_array[$i])), 
    	    	              E_USER_WARNING);
    	    	unset($arg_array[$i]);
    	    }
    	}
    	$eval = str_replace("$ ;","","$".join("; $", $arg_array).";");
    	eval($eval);
        return;
    }

    function run($dbi, $argstr, &$request, $basepage) {
    	global $editboxes, $hidden, $checkboxes, $radiobuttons, $pulldown;
        $editboxes=array(); $hidden=array(); $checkboxes=array(); $radiobuttons=array(); $pulldown=array();
        extract($this->getArgs($argstr, $request));
        if (empty($action)) {
            //trigger_error(sprintf(_("argument '%s' not declared by plugin"),
            //                      'action'), E_USER_NOTICE);
            return $this->error(fmt("A required argument '%s' is missing.","action"));
        }
        $form = HTML::form(array('action' => $request->getPostURL(),
                                 'method' => $method,
                                 'class'  => 'wikiadmin',
                                 'accept-charset' => $GLOBALS['charset']),
                           HiddenInputs(array('action' => $action,
                                              'pagename' => $basepage)));
        if ($checkboxes) {
            foreach ($checkboxes as $input) {
                $input['type'] = 'checkbox';
                if (empty($input['name']))
                    return $this->error("A required argument '%s' is missing.","checkboxes[][name]");
                if (empty($input['value'])) $input['value'] = 1;
                if (empty($input['text'])) 
                    $input['text'] = gettext($input['name'])."=".$input['value'];
                $text = $input['text'];
                unset($input['text']);
                if (!empty($input['checked'])) $input['checked'] = 'checked';
                $form->pushContent(HTML::div(array('class' => $class), HTML::input($input), $text));
            }
        }
        if ($radiobuttons) {
            foreach ($radiobuttons as $input) {
                $input['type'] = 'radio';
                if (empty($input['name']))
                    return $this->error("A required argument '%s' is missing.","radiobuttons[][name]");
                if (empty($input['text'])) $input['text'] = gettext($input['name']);
                $text = $input['text'];
                unset($input['text']);
                if (empty($input['value'])) $input['value'] = 1;
                if ($input['checked']) $input['checked'] = 'checked';
                $form->pushContent(HTML::div(array('class' => $class), HTML::input($input), $text));
            }
        }
        if ($editboxes) {
            foreach ($editboxes as $input) {
                $input['type'] = 'text';
                if (empty($input['name']))
                    return $this->error("A required argument '%s' is missing.","editboxes[][name]");
                if (empty($input['text'])) $input['text'] = gettext($input['name']);
                $text = $input['text'];
                unset($input['text']);
                $form->pushContent(HTML::div(array('class' => $class), HTML::input($input), $text));
            }
        }
        if ($hidden) {
            foreach ($hidden as $input) {
                $input['type'] = 'hidden';
                if (empty($input['name']))
                    return $this->error("A required argument '%s' is missing.","hidden[][name]");
                unset($input['text']);
                $form->pushContent(HTML::input($input));
            }
        }
        if ($request->getArg('start_debug'))
            $form->pushContent(HTML::input(array('name' => 'start_debug',
                                                 'value' =>  $request->getArg('start_debug'),
                                                 'type'  => 'hidden')));
        if (empty($buttontext)) $buttontext = $action;
        $submit = Button('submit:', $buttontext, $class);
        if ($cancel) {
            $form->pushContent(HTML::span(array('class' => $class),
                                          $submit, Button('submit:cancel', _("Cancel"), $class)));
        } else {
            $form->pushContent(HTML::span(array('class' => $class),
                                          $submit));
        }
        return $form;
    }
};

// $Log: not supported by cvs2svn $
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
