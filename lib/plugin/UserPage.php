<?php // -*-php-*-
rcs_id('$Id: UserPage.php,v 1.1 2001-12-02 02:31:17 joe_edelman Exp $');
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
            return '<p> Your sysadmin has disallowed use of this plugin!';
            
        if ($uname && preg_match('/\A' . $WikiNameRegexp . '\z/', $uname)) {
            $this->login($uname);
            if ($edit) $request->redirect(WikiURL($edit, array('action' => 'edit')));
            if ($browse) $request->redirect(WikiURL($browse));
            return '<p> You should be logged in now.';

        } else {  // not logged in yet
            $text = '';
            if ($edit) {
                $p = LinkWikiWord($edit);
                $text .= "<p> Before you can edit $p, you need to sign in.";
            }
            if ($uname && ! preg_match('/\A' . $WikiNameRegexp . '\z/', $uname)) {
                $wwf = LinkWikiWord('WikiWord');
                $text .= "<p> The name you use to sign in must be in $wwf format.";
                $text .= " examples include: <ul><li>TomJefferson<li>AlexHamilton";
                $text .= "<li>YoYoMa</ul>";
                $text .= "<p> Please re-enter your name in this form:";
            } else {
                $wst = LinkWikiWord('WordsStrungTogether');
                $text .= "<p> Please enter your name as $wst (e.g. John Smith as JohnSmith).";
            }
            $text .= "<p><form>Sign in: <input type=text name=uname>";
            foreach (array('edit', 'browse') as $k) 
                if ($$k) {
                    $v = $$k;
                    $text .= "<input type=hidden name=$k value=$v>";
                }
            $text .= '<input type=submit value="Sign In">';
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
