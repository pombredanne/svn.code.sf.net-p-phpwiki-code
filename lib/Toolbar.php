<?php rcs_id('$Id: Toolbar.php,v 1.1 2001-12-27 19:25:48 carstenklapp Exp $');

require_once("lib/ErrorManager.php");
require_once("lib/WikiPlugin.php");

/*

FIXME: This is a stub for a Toolbar class to eventually replace the
PHP logic currently embedded in the html templates, used to build the
Wiki commands and navigation links at the bottom of the screen.

If you feel inspired please contribute here.

(This is all in a state of flux, so don't count on any of this being
the same tomorrow...)

*/

class Toolbar
{
    function Toolbar() {
        //$this->_tmpl = $this->_munge_input($tmpl);
	//$this->_tmpl = $tmpl;
	//$this->_tname = $tname;
        //$this->_vars = array();
    }

   function appenditem($item) {

    /*

        identify: command or info-display?
        - is WikiPlugin?
        locale

        future:
            toolbar style, text-only or graphic buttons?
            -if text-only, use " | " as item separator
    */

   }


}

class WikiToolbar
extends Toolbar
{
    /**
     * Constructor.
     *
     */
    function WikiToolbar($tname) {

    /*
        build_html_toolbar()
    	send html back to transform (or whatever will be calling this)
    */

    }

    function build_html_Toolbar() {

    /*
        toolbars could be an array of commands or labels

        which toolbar?
        - label, info display only (Modification date)
        - label, info display only ("See Goodstyle Tips for editing".)
        - Search navigation (FindPage, LikePages, search field)
        - Wiki navigation (RecentChanges, RandomPages, WantedPages, Top10 etc.)
        - Logo navigation (Homepage)
        - label and command ("You are logged in as Bogouser. | SignOut")

        which toolbar items?
        loop
            requires user authenticated?
            - check is authenticated
            - check is admin
            appenditem
        endloop
        return $html
    */

    }
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>







