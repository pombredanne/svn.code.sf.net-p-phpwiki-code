<?php // -*-php-*-
rcs_id('$Id: UserPage.php,v 1.5 2002-01-22 03:17:47 dairiki Exp $');
/**
 * UserPage:  a clone of the clublet sign-in facility
 * usage:   <?plugin UserPage?>
 * author:  Joe Edelman <joe@orbis-tertius.net>
 */

require_once('lib/WikiUser.php');

// to add:  emails, prefs

class WikiPlugin_UserPage
extends WikiPlugin
{
    function getDefaultArguments() {
        return array( 'edit'   => false,    // a page you want to edit
                      'browse' => false,    // a page you were browsing when you clicked
                      'uname'  => false     // the username you'd like to log in as
                      );
    }

    function login($uname) {
        global $user;
        if (!$uname)
            return false;
        return $user->attemptLogin($uname);
    }
    
    function run($dbi, $argstr, &$request) {
        global $WikiNameRegexp;
        extract($this->getArgs($argstr, $request));
        
        if (! ALLOW_BOGO_LOGIN)
            return HTML::p(_("Your sysadmin has disallowed use of the UserPage plugin!"));
            
        if ($uname && $this->login($uname)) {
            if ($edit)
                $request->redirect(WikiURL($edit, array('action' => 'edit')));
            if ($browse)
                $request->redirect(WikiURL($browse));
            return HTML::p(_("You should be logged in now."));
        } else {  // not logged in yet
            $text = '';
            if ($edit) {
                $html[] = HTML::p(fmt("Before you can edit %s, you need to sign in.",
                                      LinkWikiWord($edit)));
            }
            if ($uname) {
                $html[] = HTML::p(_("The name you use to sign in must be in WikiWord format."));

                // this 'list explosion' simply provides an
                // uncluttered _() string for the language translator
                // it could use some refactoring
                list($wne,$example_names) = explode(':', _("examples include: TomJefferson, AlexHamilton"));
                list($ea,$eb) = explode(",",$example_names);
                $html[] = HTML::p("$wne: ");
                $html[] = HTML::ul(HTML::li($ea),
                                   HTML::li($eb),
                                   // Note: Don't _() the "YoYoMa", see:
                                   // <http://bsuvc.bsu.edu/~jdbrocklesby/yoyoma.htm>
                                   HTML::li('YoYoMa'));

                $html[] = HTML::p(_("Please re-enter your name in this form."));
            } else {

                // temporary comment: in this case it may assist the
                // translator for grammar sake to see the complete
                // sentence without variable substitution ($wst,$wwf)
                // $wst = LinkWikiWord("WordsStrungTogether");

                $html[] = HTML::p(_("Please enter your name as WordsStrungTogether (e.g. John Smith as JohnSmith)."));
            }

            $form = HTML::form(array('action' => $request->getURLtoSelf(),
                                     'method' => 'post',
                                     'accept-charset' => CHARSET));
            foreach (array('edit', 'browse') as $k) 
                if ($$k)
                    $form->pushContent(HTML::input(array('type' => 'hidden',
                                                         'name' => $k,
                                                         'value' => $$k)));
            $form->pushContent(HTML::p(_("Sign in:") . ' ',
                                       HTML::input(array('type' => 'text',
                                                         'name' => "uname")),
                                       HTML::input(array('type' => 'submit',
                                                         'value' => _("Sign In")))));
            $html[] = $form;
            return $html;
        }
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
