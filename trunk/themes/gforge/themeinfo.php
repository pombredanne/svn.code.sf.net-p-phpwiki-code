<?php
rcs_id('$Id$');

require_once('lib/WikiTheme.php');

class WikiTheme_gforge extends WikiTheme {

    function header() {
        global $HTML, $group_id, $group_public_name, $request, $project, $Language;

        $pagename = $request->getArg('pagename');

        $submenu = Template('navbar');
        
        //group is private
        if (!$project->isPublic()) {
            //if it's a private group, you must be a member of that group
            session_require(array('group'=>$group_id));
        }

        //for dead projects must be member of admin project
        if (!$project->isActive()) {
            //only SF group can view non-active, non-holding groups
            session_require(array('group'=>'1'));
        }

        $HTML->header(array('title'=> $group_public_name.': '.htmlspecialchars($pagename) ,
            'pagename'=> $pagename, 'group' => $group_id, 'toptab' => 'wiki',
            'css' => 'gforge.css" />'."\n".'    <base href="'.PHPWIKI_BASE_URL,
            'submenu' => $submenu->asXML()));

        // Display a warning banner for internal users when the wiki is opened
        // to external users.
        if ($project->getIsExternal()) {
        	$external_user = false;
        	if (session_loggedin()) {
        		$user = session_get_user();
        		$external_user = $user->getIsExternal();
        	}
        	if (!$external_user) {
	        	$page = $request->getPage();
	        	if ($page->get('external')) {
	    			$external_msg = 'This page is external.';
	    		}
	    		echo $HTML->warning_msg($Language->getText('project_admin','external_project') .
	    								(isset($external_msg) ? ' ' . $external_msg : ''));
			}
        }
    }
    
    function footer() {
        global $HTML;
        
        $HTML->footer(array());
        
    }
}

$WikiTheme = new WikiTheme_gforge('gforge');
// CSS file defines fonts, colors and background images for this style.

/**
 * The logo image appears on every page and links to the HomePage.
 */
$WikiTheme->addImageAlias('logo', WIKI_NAME . 'Logo.png');

/**
 * The Signature image is shown after saving an edited page. If this
 * is set to false then the "Thank you for editing..." screen will
 * be omitted.
 */

$WikiTheme->addImageAlias('signature', WIKI_NAME . "Signature.png");
// Uncomment this next line to disable the signature.
$WikiTheme->addImageAlias('signature', false);

/*
 * Link icons.
 */
// $WikiTheme->setLinkIconAttr('after');
$WikiTheme->setLinkIcon('http');
$WikiTheme->setLinkIcon('https');
$WikiTheme->setLinkIcon('ftp');
$WikiTheme->setLinkIcon('mailto');
//$WikiTheme->setLinkIcon('interwiki');
//$WikiTheme->setLinkIcon('wikiuser');
//$WikiTheme->setLinkIcon('*', 'url');

$WikiTheme->setButtonSeparator("\n | ");

/**
 * WikiWords can automatically be split by inserting spaces between
 * the words. The default is to leave WordsSmashedTogetherLikeSo.
 */
$WikiTheme->setAutosplitWikiWords(false);

/**
 * Layout improvement with dangling links for mostly closed wiki's:
 * If false, only users with edit permissions will be presented the 
 * special wikiunknown class with "?" and Tooltip.
 * If true (default), any user will see the ?, but will be presented 
 * the PrintLoginForm on a click.
 */
$WikiTheme->setAnonEditUnknownLinks(false);

/*
 * You may adjust the formats used for formatting dates and times
 * below.  (These examples give the default formats.)
 * Formats are given as format strings to PHP strftime() function See
 * http://www.php.net/manual/en/function.strftime.php for details.
 * Do not include the server's zone (%Z), times are converted to the
 * user's time zone.
 */
$WikiTheme->setDateFormat("%d %B %Y");
$WikiTheme->setTimeFormat("%H:%M");

/*
 * To suppress times in the "Last edited on" messages, give a
 * give a second argument of false:
 */
//$WikiTheme->setDateFormat("%B %d, %Y", false); 


// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
