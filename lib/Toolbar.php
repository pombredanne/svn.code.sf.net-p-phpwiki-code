<?php rcs_id('$Id: Toolbar.php,v 1.2 2002-01-03 02:17:11 carstenklapp Exp $');

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


// There is still some work to do before these functions
// can be used as ${tokens} in the xhtml templates:
//
// Plugins and [pagename] need to be dealt with.
//
// Which variables are to be passed to the functions is not finalised yet.
//
// The raw html should also be replaced with calls to
// Element() and/or QElement()


//BrowsePage stuff
function toolbar_Warning_IsCurrent($IS_CURRENT, $BROWSE, $PAGEURL) {
    $html = "";
    if (! $IS_CURRENT) {
        $html .= "<p><strong>" ._("Note:") ."</strong> " ._("You are viewing an old revision of this page.");
        $html .= "<a href=\"" .${BROWSE} .${PAGEURL} ."\">" ._("View the current version") ."</a>.</p>";
	$html .= "<hr class=\"ignore\" noshade=\"noshade\" />";
    }
    return $html;
}

function toolbar_Info_LastModified($IS_CURRENT, $LASTMODIFIED, $VERSION) {
    $html = "";
    if ($IS_CURRENT) {
       $html .= sprintf(_("Last edited on %s."),$LASTMODIFIED);
    } else {
       $html .= sprintf(_("Version %s, saved on %s."),$VERSION,$LASTMODIFIED);
    }
    return $html;
}

function toolabr_action_PageActions($page, $user, $IS_CURRENT, $ACTION, $VERSION) {
    $html = "";
    if ($page->get('locked') && !$user->is_admin()) {
        $html .= _("Page locked");
    } else {
	if ($IS_CURRENT) {
            $html .= "<a class=\"wikiaction\" href=\"" .${ACTION} ."edit\">" ._("Edit") ."</a>";
        } else {
            $html .= "<a class=\"wikiaction\" href=\"" .${ACTION} ."edit&amp;version=" .${VERSION};
            $html .= ">" ._("Edit old revision") ."</a>";
        }
    }
    if ($user->is_admin()) {
	if ($page->get('locked')) {
            $html .= separator() ."<a class=\"wikiadmin\" href=\"" .${ACTION} ."unlock\">" ._("Unlock page") ."</a>";
        } else {
            $html .= separator() ."<a class=\"wikiadmin\" href=\"" .{$ACTION} ."lock\">" ._("Lock page") ."</a>";
        }
        $html .= separator() ."<a class=\"wikiadmin\" href=\"" .{$ACTION} ."remove\">" ._("Remove page") ."</a>";
    }
    $html .= separator() ."<?plugin-link PageHistory page=\"[pagename]\"";
    if ($IS_CURRENT) {
        $html .= separator() ."<a class=\"wikiaction\" href=\"" .${ACTION} ."diff&amp;previous=major\">" ._("Diff") ."</a>";
    } else {
        $html .= separator() ."<a class=\"wikiaction\""; 
        $html .= "href=\"" .${ACTION} ."diff&amp;version=" .${VERSION} ."&amp;previous=major\">" ._("Diff") ."</a>";
    }
        $html .= 
    $html .= separator() .LinkExistingWikiWord(_("BackLinks"));

    return $html;
}

function toolbar_action_SearchActions($page, $CHARSET) {
    $html = "";
    $html .= "<form action=\"" .WikiURL(_("TitleSearch")) ."\" method=\"get\" accept-charset=\"" .${CHARSET} ."\">";
    $html .= LinkExistingWikiWord(_("RecentChanges"));
    $html .= separator() .LinkExistingWikiWord(_("FindPage"));
    $html .= separator() ."<span><input type=\"hidden\" name=\"auto_redirect\" value=\"1\" />";
    $html .= "<input type=\"text\"  name=\"s\" size=\"12\"";
    $html .= " title=" ._("Quick Search");
    $html .= " onmouseover=\"window.status='" ._("Quick Search") ."'; return true;";
    $html .= " onmouseout=\"window.status=''; return true;\" /></span>";
    $html .= separator() ."<?plugin-link LikePages page=\"[pagename]\" ?>";
    $html .= "</form>";
    return $html;
}

function toolbar_User_UserSignInOut($user, $USERID, $ACTION) {
    $html = "";
    if ($user->is_authenticated()) {
        $html .= sprintf(_("You are signed in as %s"), LinkWikiWord($USERID));
        $html .= separator() ."<a class=\"wikiaction\" href=\"" .${ACTION} ."logout\">" ._("SignOut") ."</a>";
    } else {
        $html .= "<a class=\"wikiaction\" href=\"" .${ACTION} ."login\">" ._("SignIn") ."</a>";
    }
    return $html;
}

function toolbar_action_Logo($BROWSE, $WIKI_NAME, $LOGO) {
    $html = "";
    $html .= "<div>";
    $html .= "<a class=\"wikilink\" href=\"" .${BROWSE} ._("HomePage") ."\">";
    $html .= "<img alt=\"" .${WIKI_NAME} .":" ._("HomePage");
    $html .= " src=\"" .${LOGO} ."\"";
    $html .= " border=\"0\" ."\" align=\"right\" />";
    $html .= "</a></div>"
    return $html;
}

function toolbar_action_Navigation() {
    $html = "";
    $html .= _("RecentChanges");
//    $html .= separator() ._("RandomPage");
//    $html .= separator() ._("WantedPages");
//    $html .= separator() ._("SandBox");
    return $html;
}


//EditPage stuff

function toolbar_Warning_Preview($PREVIEW_CONTENT) {
    $html = "";
    if (!empty($PREVIEW_CONTENT)) {
        $html .= "<p><strong>" ._("Preview only!  Changes not saved.") ."</strong></p>";
        $html .= "<div class="wikitext">" .${PREVIEW_CONTENT} ."</div>";
        $html .= "<hr class=\"ignore\" noshade />";
    }
    return $html;
}

function toolbar_Warning_OldRevision() {
    $html = "";
    if (!$IS_CURRENT) {
        $html .= "<p><strong>" ._("Warning: You are editing an old revision.");
        $html .= " " ._("Saving this page will overwrite the current version.") ."</strong></p>";
        $html .= "<hr class=\"ignore\" noshade />";
    }
    return $html;
}

function toolbar_User_AuthorSignInOut() {
    $html = "";
    if ($user->is_authenticated()) {
        $html .= sprintf(_("You are signed in as %s."), LinkWikiWord($USERID));
    } else {
        $html .= sprintf(_("Author will be logged as %s."),"<em>${USERID}</em>");
        $html .= separator() ."<a class=\"wikiaction\" href=\"" .${ACTION} ."login\">";
        $html .= _("SignIn") ."<small>*</small></a>";
        $html .= "<br><small>*backup and reload after signing in</small>";
    }
    return $html;
}

function toolbar_Info_EditTips() {
    $html = "";
    $html .= sprintf(_("You can change the size of the editing area in %s."), LinkExistingWikiWord(_("UserPreferences")));
    $html .= sprintf(_("See %s tips for editing."),LinkExistingWikiWord(_("GoodStyle")));
    return $html;
}

function toolbar_Info_EditHelp() {
    $html = "";
    $html .= "<div class=\"wiki-edithelp\">";
    $html .= "<?plugin IncludePage page=" ._("TextFormattingRules") ."section=" ._("Synopsis") ."quiet=1?>";
    $html .= "</div>";
    return $html;
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
