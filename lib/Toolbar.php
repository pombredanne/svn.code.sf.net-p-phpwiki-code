<?php rcs_id('$Id: Toolbar.php,v 1.7 2002-01-05 09:34:29 carstenklapp Exp $');

//require_once("lib/ErrorManager.php");
//require_once("lib/WikiPlugin.php");

function separator() {
/*
    $toolbar_style = "text";
    select ( $toolbar_style ) {
    case "text" :
        $separator = " | ";
        break;
    case "list" :
        $separator = ", ";
        break;
    case "image" :
        $separator = "<img alt=\" | \" src=\"" .DATA_URL( '$placeholder' ) ."\" />";
        break;
    }
    return $separator;
*/
    // just using a hardcoded separator for now
    return " | ";
}

/*

These functions will replace the PHP logic currently embedded in the
html templates, used to build the Wiki commands and navigation links
at the bottom of the screen.

If you feel inspired please contribute here!

(This is all in a state of flux, so don't count on any of this being
the same tomorrow...)


Some of these functions are already to be used as ${tokens} in the
xhtml templates.

FIXME: IncludePage plugin for EditHelp needs to be dealt with.

Plugins shuold be cleaned up, in this state they won't display any
mouseover text.

The raw html should be replaced with calls to Element() and/or
QElement()

*/


/////////////////////////////////////////////////////////////////////
// Global Page Elements

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${LOGO}
// Done.

// Requires:
// $wiki_name = WIKI_NAME;
// $logo      = $logo;

function toolbar_action_Logo($wiki_name, $logo) {
    $html = "";
    $html = Element('a', array('href' => WikiURL(_("HomePage"))),
                    QElement('img', array('alt'    => $wiki_name,
                                          'src'    => DataURL($logo),
                                          'border' => '0',
                                          'align'  => 'right')));
    return $html;
}

/////////////////////////////////////////////////////////////////////
// View Page Elemets

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${VIEW_WARNINGS}
// Done.

// Requires:
// $is_current = $current->getVersion() == $revision->getVersion();
// $pagename   = $pagename;

function toolbar_Warnings_View($is_current, $pagename) {
    $html = "";
    if (!$is_current) {
        $html .= Element('p', QElement('strong', _("Note:")) ." "
                         ._("You are viewing an old revision of this page.")
                         ." "
                         .QElement('a', array('href' => WikiURL('')
                                              .rawurlencode($pagename)),
                                   _("View the current version")) .".");
        $html .= QElement('hr', array('class'   => 'ignore',
                                      'noshade' => 'noshade'));
    }
    return $html;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${LASTMODIFIED}
// Done.

// Requires:
// $is_current   = $current->getVersion() == $revision->getVersion();
// $lastmodified = strftime($datetimeformat, $revision->get('mtime'));
// $version      = $revision->getVersion();

function toolbar_Info_LastModified($is_current, $lastmodified, $version) {
    $html = "";
    if ($is_current) {
        $html .= QElement('p', sprintf(_("Last edited on %s."),
                                       $lastmodified));
    } else {
        $html .= QElement('p', sprintf(_("Version %s, saved on %s."),
                                       $version, $lastmodified));
    }
    return $html;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${PAGE_ACTIONS} not working yet

// Requires:
// $pagelocked  = $page->get('locked');
// $userisadmin = $user->is_admin();
// $is_current  = $current->getVersion() == $revision->getVersion();
// $version     = $revision->getVersion();
// $pagename    = $page->getName();

function toolbar_action_PageActions($pagelocked, $userisadmin, $is_current, $version, $pagename) {
    $html = "";
    if ($pagelocked && !$userisadmin) {
        $html .= _("Page locked");
    } else {
	if ($is_current) {
            $html .= "<a class=\"wikiaction\" href=\"" .WikiURL($pagename, array('action' => 'edit')) ."\">" ._("Edit") ."</a>";
        } else {
            $html .= "<a class=\"wikiaction\" href=\"" .WikiURL($pagename, array('action' => 'edit&amp;version=' .$version));
            $html .= ">" ._("Edit old revision") ."</a>";
        }
    }
    if ($userisadmin) {
	if ($pagelocked) {
            $html .= separator() ."<a class=\"wikiadmin\" href=\"" .WikiURL($pagename, array('action' => 'unlock')) ."\">" ._("Unlock page") ."</a>";
        } else {
            $html .= separator() ."<a class=\"wikiadmin\" href=\"" .WikiURL($pagename, array('action' => 'lock')) ."\">" ._("Lock page") ."</a>";
        }
        $html .= separator() ."<a class=\"wikiadmin\" href=\"" .WikiURL($pagename, array('action' => 'remove')) ."\">" ._("Remove page") ."</a>";
    }
        //$html .= separator() ."<plugin-link PageHistory page=\"" .$pagename ."\"";
        $html .= separator() ."<a class=\"wikiaction\" href=\"" . WikiURL(_("PageHistory"), array('page' => $pagename)) ."\">" ._("PageHistory") ."</a>";

    if ($is_current) {
        $html .= separator() ."<a class=\"wikiaction\" href=\"" . WikiURL($pagename, array('action' => 'diff&amp;previous=major')) ."\">" ._("Diff") ."</a>";
    } else {
        $html .= separator() ."<a class=\"wikiaction\"";
        $html .= "href=\"" . WikiURL($pagename, array('action' => 'diff&amp;version=' .$version .'&amp;previous=major')) ."\">" ._("Diff") ."</a>";
    }
        //$html .= separator() ."<plugin-link BackLinks page=\"" .$pagename ."\"";
        $html .= separator() ."<a class=\"wikiaction\" href=\"" . WikiURL(_("BackLinks"), array('page' => $pagename)) ."\">" ._("BackLinks") ."</a>";

    return $html;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${SEARCH}

// FIXME: use Element()

// FIXME: Figure out a better way to prevent unwanted newlines in html?
// current hack is by moving the <form> and </form> around the
// template.

// Requires:
// $pagename = $page->getName();
// $charset  = CHARSET;

function toolbar_action_SearchActions($pagename, $charset) {
    $html = "";
    $html .= "<form action=\"" .WikiURL(_("TitleSearch")) ."\" method=\"get\" accept-charset=\"" .$charset ."\">";
    $html .= toolbar_action_Navigation($pagename) .separator() .LinkExistingWikiWord(_("FindPage"));
    $html .= separator() ."<span><input type=\"hidden\" name=\"auto_redirect\" value=\"1\" />";
    $html .= "<input type=\"text\"  name=\"s\" size=\"12\"";
    $html .= " title=" ._("Quick Search");
    $html .= " onmouseover=\"window.status='" ._("Quick Search") ."'; return true;";
    $html .= " onmouseout=\"window.status=''; return true;\" /></span>";

    //$html .= separator() ."<plugin-link LikePages page=\"" .$pagename ."\" >";
    $html .= separator() ."<a class=\"wikiaction\" href=\"" . WikiURL(_("LikePages"), array('page' => $pagename)) ."\">" ._("LikePages") ."</a>";

//    $html .= "</form>";
    return $html;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${SIGNIN}

// FIXME: use Element()

// WARNING: Hackage! $pagename is not being passed here yet.

// Requires:
// $userauth = $user->is_authenticated();
// $userid   = $user->id();
// $pagename = $pagename;

function toolbar_User_UserSignInOut($userauth, $userid, $pagename) {
    $html = "";
    if ($userauth) {
        $html .= sprintf(_("You are signed in as %s"), LinkWikiWord($userid));
        $html .= separator() ."<a class=\"wikiaction\" href=\"" . WikiURL($pagename, array('action' => 'logout')) ."\">" ._("SignOut") ."</a>";
    } else {
        $html .= "<a class=\"wikiaction\" href=\"" . WikiURL($pagename, array('action' => 'login')) ."\">" ._("SignIn") ."</a>";
    }
    return $html;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${NAVIGATION}

// FIXME: use Element()

// NOTE: This token works but is not used in the template, instead it
//       is called by ${SEARCH} to avoid extra newlines in the html.
//
// Requires:
// $pagename = ($pagename);
function toolbar_action_Navigation($pagename) {
    $html = "";
    $html .= LinkExistingWikiWord(_("RecentChanges"));
    // These lines are here for future anticipated plugins:
    //$html .= separator() ."<plugin-link RandomPage page=\"" .$pagename ."\" >";
    // FIXME: What to use instead of WikiURL()?
    //$html .= separator() ."<a class=\"wikiaction\" href=\"" .WikiURL('_("RandomPage")', array('page' => $pagename)) ."\">" ._("RandomPage") ."</a>";
    //$html .= separator() ."<plugin-link WantedPages >";
    //$html .= separator() ."<a class=\"wikiaction\" href=\"" . WikiURL(_("WantedPages")) ."\">" ._("WantedPages") ."</a>";
    //$html .= separator() .LinkExistingWikiWord(_("SandBox"));
    return $html;
}

/////////////////////////////////////////////////////////////////////
// Edit Page Elements

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${EDIT_WARNINGS}
// Done.

// Requires:
// $ispreview  = !empty($PREVIEW_CONTENT);
// $is_current = $current->getVersion() == $revision->getVersion();

function toolbar_Warnings_Edit($ispreview, $is_current) {
    $html = "";
    if ($ispreview or !$is_current) {
        if ($ispreview) {
            $html .= Element('p',
                             QElement('strong',
                                      _("Preview only!  Changes not saved.")));
        }
        if (!$is_current) {
            $html .= Element('p',
                             QElement('strong',
                                      _("Warning: You are editing an old revision.")
                                      ." "
                                      ._("Saving this page will overwrite the current version.")));
        }
        $html .= QElement('hr', array('class'   => 'ignore',
                                      'noshade' => 'noshade'));
    }
    return $html;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${} not working yet (maybe ${EDITPAGE_AUTHOR}?)

// FIXME: use Elements()

// Requires:
// $userid = ($user->id())

function toolbar_User_AuthorSignInOut($userid) {
    $html = "";
    if ($user->is_authenticated()) {
        $html .= sprintf(_("You are signed in as %s."), LinkWikiWord($userid));
    } else {
        $html .= sprintf(_("Author will be logged as %s."),"<em>" .$userid ."</em>");
        $html .= separator() ."<a class=\"wikiaction\" href=\"" .WikiURL($pagename, array('action' => 'login')) ."\">" ._("SignIn") ."</a>";
        $html .= "<small>*</small><br><small>*backup and reload after signing in</small>";
    }
    return $html;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${EDIT_TIPS}

// FIXME: use Elements()

// Requires:
// none

function toolbar_Info_EditTips() {
    $html = "";
    $html .= Element('p',
                     sprintf(_("You can change the size of the editing area in %s."),
                             LinkExistingWikiWord(_("UserPreferences")))
                     ." " .sprintf(_("See %s tips for editing."),
                                   LinkExistingWikiWord(_("GoodStyle")))
                     );
    return $html;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// Template token: ${EDIT_HELP}

// FIXME: how to call plugin IncludePage from here?

// Requires:
// none

function toolbar_Info_EditHelp() {
    $html = "";
    $html .= "<div class=\"wiki-edithelp\">";
    $html .= "<plugin IncludePage page=" ._("TextFormattingRules")
        ."section=" ._("Synopsis") ."quiet=1>";
    $html .= "</div>";
    return $html;
}


/////////////////////////////////////////////////////////////////////
// This is a stub for a Toolbar class to eventually replace the
// functions above.
//
// 
//               Nothing below this line works yet.
//
// 
/////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////




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
