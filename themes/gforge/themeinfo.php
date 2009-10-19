<?php
rcs_id('$Id$');

require_once('lib/WikiTheme.php');

class WikiTheme_gforge extends WikiTheme {

    function header() {
        global $HTML, $group_id, $group_public_name, $request, $project;

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
	    			$external_msg = _("This page is external.");
	    		}
	    		echo $HTML->warning_msg(_("This project is shared with third-party users (non Alcatel-Lucent users).") .
	    								(isset($external_msg) ? ' ' . $external_msg : ''));
			}
        }
    }

    function footer() {
        global $HTML;

        $HTML->footer(array());

    }

    function load() {

        $this->initGlobals();

        /**
         * The Signature image is shown after saving an edited page. If this
         * is set to false then the "Thank you for editing..." screen will
         * be omitted.
         */

        $this->addImageAlias('signature', WIKI_NAME . "Signature.png");
        // Uncomment this next line to disable the signature.
        $this->addImageAlias('signature', false);

        /*
         * Link icons.
         */
        $this->setLinkIcon('http');
        $this->setLinkIcon('https');
        $this->setLinkIcon('ftp');
        $this->setLinkIcon('mailto');

        $this->setButtonSeparator("");

        /**
         * WikiWords can automatically be split by inserting spaces between
         * the words. The default is to leave WordsSmashedTogetherLikeSo.
         */
        $this->setAutosplitWikiWords(false);

        /**
         * Layout improvement with dangling links for mostly closed wiki's:
         * If false, only users with edit permissions will be presented the
         * special wikiunknown class with "?" and Tooltip.
         * If true (default), any user will see the ?, but will be presented
         * the PrintLoginForm on a click.
         */
        $this->setAnonEditUnknownLinks(false);

        /*
         * You may adjust the formats used for formatting dates and times
         * below.  (These examples give the default formats.)
         * Formats are given as format strings to PHP strftime() function See
         * http://www.php.net/manual/en/function.strftime.php for details.
         * Do not include the server's zone (%Z), times are converted to the
         * user's time zone.
         */
        $this->setDateFormat("%d %B %Y");
        $this->setTimeFormat("%H:%M");
    }
}

$WikiTheme = new WikiTheme_gforge('gforge');

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
