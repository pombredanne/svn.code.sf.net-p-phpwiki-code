<?php // -*-php-*-
rcs_id('$Id: WikiFormRich.php,v 1.1 2004-07-02 11:03:53 rurban Exp $');
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
 * - editbox[] 		name=.. value=.. text=..
 * - checkbox[] 	name=.. value=0|1 checked text=..
 * - radiobutton[] 	name=.. value=.. text=..
 * - pulldown[]		name=.. value=.. text=..
 * - hidden[]		name=.. value=..
 * - action, submit buttontext, optional cancel button (bool)
 * - method=GET or POST.

 Samples:
   <?plugin WikiFormRich action=dumpserial method=GET 
            checkbox[] name=include value="all" 
            editbox[] name=directory value=DEFAULT_DUMP_DIR
            editbox[] name=pages value=*
            editbox[] name=exclude value="" ?>
   <?plugin WikiFormRich action=dumphtml method=GET 
            editbox[] name=directory value=HTML_DUMP_DIR
            editbox[] name=pages value="*"
            editbox[] name=exclude value="" ?>

   <?plugin WikiFormRich action=loadfile method=GET 
            editbox[]  name=source value=DEFAULT_WIKI_PGSRC
            checkbox[] name=overwrite value=1
            editbox[]  name=exclude value="" ?>
 */
class WikiPlugin_WikiFormRich
extends WikiPlugin
{
    function getName () {
        return _("Provide generic WikiForm input buttons");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array('action' => false,     // required argument
                     'method' => 'POST',    // or GET
                     'class'  => false,
                     'buttontext' => false, // for the submit button
                     'cancel' => false,     // boolean if the action supports cancel also
                     );
    }

    function handle_plugin_args_cruft($argstr, $args) {
    	$allowed = array("editbox", "hidden", "checkbox", "radiobutton", "pulldown");
    	// no editbox[] = array(...) allowed (space)
    	$arg_array = preg_split("/\n/", $argstr);
    	// for security we should check this better
        $arg = '';
    	for ($i = 0; $i < count($arg_array); $i++) {
    	    if (preg_match("/^\s*(".join("|",$allowed).")\[\]\s+(.+)\s*$/", $arg_array[$i], $m)) {
                $this->inputbox[][$name] = array(); $i = count($this->inputbox) - 1;
                foreach (preg_split("/[\s+]/", $m[2]) as $attr_pair) {
                    list($attr,$value) = preg_split("/\s*=\s*/", $attr_pair);
                    if (preg_match('/^".*"$/', $value, $m))
                        $value = $m[1];
                    elseif (defined($value))
                        $value = constant($value);
                    $this->inputbox[$i][$name][$attr] = $value;
                }
    	    	//trigger_error("not yet finished");
                //eval('$this->inputbox[]["'.$m[1].'"]='.$m[2].';');
            } else {
    	    	trigger_error(sprintf("Invalid argument %s ignored",htmlentities($arg_array[$i])), 
    	    	              E_USER_WARNING);
            }
    	}
        return;
    }

    function run($dbi, $argstr, &$request, $basepage) {
        extract($this->getArgs($argstr, $request));
        if (empty($action)) {
            return $this->error(fmt("A required argument '%s' is missing.","action"));
        }
        $form = HTML::form(array('action' => $request->getPostURL(),
                                 'method' => $method,
                                 'class'  => 'wikiadmin',
                                 'accept-charset' => $GLOBALS['charset']),
                           HiddenInputs(array('action' => $action,
                                              'pagename' => $basepage)));
        foreach ($this->inputbox as $inputbox) {
            foreach ($inputbox as $inputtype => $input) {
              switch($inputtype) {
              case 'checkbox':
                $input['type'] = 'checkbox';
                if (empty($input['name']))
                    return $this->error("A required argument '%s' is missing.","checkbox[][name]");
                if (empty($input['value'])) $input['value'] = 1;
                if (empty($input['text'])) 
                    $input['text'] = gettext($input['name'])."=".$input['value'];
                $text = $input['text'];
                unset($input['text']);
                if (!empty($input['checked'])) $input['checked'] = 'checked';
                $form->pushContent(HTML::div(array('class' => $class), HTML::input($input), $text));
                break;
              case 'editbox':
                $input['type'] = 'text';
                if (empty($input['name']))
                    return $this->error("A required argument '%s' is missing.","editbox[][name]");
                if (empty($input['text'])) $input['text'] = gettext($input['name']);
                $text = $input['text'];
                unset($input['text']);
                $form->pushContent(HTML::div(array('class' => $class), HTML::input($input), $text));
                break;
              case 'radiobutton':
                $input['type'] = 'radio';
                if (empty($input['name']))
                    return $this->error("A required argument '%s' is missing.","radiobutton[][name]");
                if (empty($input['text'])) $input['text'] = gettext($input['name']);
                $text = $input['text'];
                unset($input['text']);
                if ($input['checked']) $input['checked'] = 'checked';
                $form->pushContent(HTML::div(array('class' => $class), HTML::input($input), $text));
                break;
              case 'hidden':
                $input['type'] = 'hidden';
                if (empty($input['name']))
                    return $this->error("A required argument '%s' is missing.","hidden[][name]");
                unset($input['text']);
                $form->pushContent(HTML::input($input));
              case 'pulldown':
                    return $this->error("Sorry, pulldown not yet supported");
              }
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
// Revision 1.3  2004/07/01 13:59:25  rurban
// enhanced to allow arbitrary order of args and stricter eval checking
//
// Revision 1.2  2004/07/01 13:14:01  rurban
// desc only
//
// Revision 1.1  2004/07/01 13:11:53  rurban
// more generic forms
//
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
