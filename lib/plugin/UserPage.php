<?php // -*-php-*-
rcs_id('$Id: UserPage.php,v 1.3 2001-12-28 11:12:21 carstenklapp Exp $');
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
        $user->state = 'authorized';
        $user->userid = $uname;
        $user->_save();
    }
    
    function run($dbi, $argstr, &$request) {
        global $WikiNameRegexp;
        extract($this->getArgs($argstr, $request));
        
        if (! ALLOW_BOGO_LOGIN)
            return Element('p',sprintf(_("Your sysadmin has disallowed use of the %s plugin!"),"UserPage"));
            
        if ($uname && preg_match('/\A' . $WikiNameRegexp . '\z/', $uname)) {
            $this->login($uname);
            if ($edit) $request->redirect(WikiURL($edit, array('action' => 'edit')));
            if ($browse) $request->redirect(WikiURL($browse));
            return Element('p',_("You should be logged in now."));

        } else {  // not logged in yet
            $text = '';
            if ($edit) {
                $p = LinkWikiWord($edit);
                $text .= Element('p',sprintf(_("Before you can edit %s, you need to sign in."),$p));
            }
            if ($uname && ! preg_match('/\A' . $WikiNameRegexp . '\z/', $uname)) {
                $text .= Element('p',_("The name you use to sign in must be in WikiWord format."));

                // this 'list explosion' simply provides an
                // uncluttered _() string for the language translator
                // it could use some refactoring
                list($wne,$example_names) = explode(':', _("examples include: TomJefferson, AlexHamilton"));
                list($ea,$eb) = explode(",",$example_names);
                $text .= " $wne: <ul><li>$ea<li>$eb";

                // Note: Don't _() the "YoYoMa", see:
                // <http://bsuvc.bsu.edu/~jdbrocklesby/yoyoma.htm>
                $text .= '<li>YoYoMa</ul>';

                $text .= Element('p',_("Please re-enter your name in this form."));
            } else {

                // temporary comment: in this case it may assist the
                // translator for grammar sake to see the complete
                // sentence without variable substitution ($wst,$wwf)
                // $wst = LinkWikiWord("WordsStrungTogether");

                $text .= Element('p',_("Please enter your name as WordsStrungTogether (e.g. John Smith as JohnSmith)."));
            }
            $text .= '<p><form>' ._("Sign in:"). ' <input type="text" name="uname">';
            foreach (array('edit', 'browse') as $k) 
                if ($$k) {
                    $v = $$k;
                    $text .= "<input type=\"hidden\" name=\"$k\" value=\"$v\">";
                }
            $text .= '<input type="submit" value="Sign In">';
            $text .= "</form>";
            return $text;

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
