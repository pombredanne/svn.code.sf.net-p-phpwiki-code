<?php rcs_id('$Id: Template.php,v 1.16 2002-01-08 00:31:24 dairiki Exp $');

require_once("lib/ErrorManager.php");
require_once("lib/WikiPlugin.php");
require_once("lib/Toolbar.php");

//FIXME: This is a mess and needs to be refactored.
//  (In other words: this is all in a state of flux, so don't count on any
//   of this being the same tomorrow...)

class Template
{
    function Template($tmpl) {
        //$this->_tmpl = $this->_munge_input($tmpl);
	$this->_tmpl = $tmpl;
        $this->_vars = array();
    }

    function _munge_input($template) {
	// Expand "< ?plugin-* ...? >"
	preg_match_all('/<\?plugin.*?\?>/s', $template, $m);
	global $dbi, $request;	// FIXME: no globals?
	$pluginLoader = new WikiPluginLoader;
	foreach (array_unique($m[0]) as $plugin_pi) {
	    $orig[] = '/' . preg_quote($plugin_pi, '/') . '/s';
            // Plugin args like 'description=_("Get backlinks")' get
            // gettexted.
            // FIXME: move this to WikiPlugin.php.
            $translated_pi = preg_replace('/(\s\w+=)_\("((?:[^"\\\\]|\\.)*)"\)/xse',
                                          '"\1\"" . gettext("\2") . "\""',
                                          $plugin_pi);
	    $repl[] = $pluginLoader->expandPI($translated_pi, $dbi, $request);
	}

         // Convert ${VAR} to < ?php echo "$VAR"; ? >
        //$orig[] = '/\${(\w[\w\d]*)}/';
        //$repl[] = '<?php echo "$\1"; ? >';
        $orig[] = '/\${(\w[\w\d]*)}/e';
        $repl[] = '$this->_getReplacement("\1")';

	// Convert $VAR[ind] to < ?php echo "$VAR[ind]"; ? >
        $orig[] = '/\$(\w[\w\d]*)\[([\w\d]+)\]/e';
        $repl[] = '$this->_getReplacement("\1", "\2")';

        // Convert $_("String") to < ?php echo htmlspecialchars(gettext("String")); ? >
        $orig[] = '/\$_\(("(?:[^"\\\\]|\\.)*")\)/xs';
        $repl[] = "<?php echo htmlspecialchars(gettext(\\1)); ?>";
        
        // Convert tag attributes like foo=_("String") to foo="String" (with gettext mapping).
        $orig[] = '/( < \w [^>]* \w=)_\("((?:[^"\\\\]|\\.)*)"\)/xse';
        $repl[] = '"\1\"" . htmlspecialchars(gettext("\2")) . "\""';
        
        return preg_replace($orig, $repl, $template);

        $ret = preg_replace($orig, $repl, $template);
        echo QElement('pre', $ret);
        return $ret;
        

    }

    function _getReplacement($varname, $index = false) {
	// FIXME: report missing vars.
	$vars = &$this->_vars;
	if (isset($vars[$varname])) {
	    $value = $vars[$varname];
	    if ($index !== false)
		@$value = (string) $value[$index];
	    return str_replace('?', '&#63;', $value);
	}
	return false;
    }
    
    /**
     * Substitute HTML replacement text for tokens in template. 
     *
     * Constructs a new WikiTemplate based upon the named template.
     *
     * @access public
     *
     * @param $token string Name of token to substitute for.
     *
     * @param $replacement string Replacement HTML text.
     */
    function replace($varname, $value) {
        $this->_vars[$varname] = $value;
    }

    /**
     * Substitute text for tokens in template. 
     *
     * @access public
     *
     * @param $token string Name of token to substitute for.
     *
     * @param $replacement string Replacement text.
     * The replacement text is run through htmlspecialchars()
     * to escape any special characters.
     */
    function qreplace($varname, $value) {
        $this->_vars[$varname] = htmlspecialchars($value);
    }
    

    /**
     * Include/remove conditional text in template.
     *
     * @access public
     *
     * @param $token string Conditional token name.
     * The text within any matching if blocks (or single line ifs) will
     * be included in the template expansion, while the text in matching
     * negated if blocks will be excluded. 
     */
    /*
    function setConditional($token, $value = true) {
        $this->_iftoken[$token] = $value;
    }
    */
    
    function getExpansion($varhash = false) {
	$savevars = $this->_vars;
        if (is_array($varhash)) {
	    foreach ($varhash as $key => $val)
		$this->_vars[$key] = $val;
	}
        extract($this->_vars);
        if (isset($this->_iftoken))
            $_iftoken = $this->_iftoken;
        
        ob_start();

        //$this->_dump_template();

        global $ErrorManager;
        $ErrorManager->pushErrorHandler(new WikiMethodCb($this, '_errorHandler'));
        eval('?>' . $this->_munge_input($this->_tmpl));
        $ErrorManager->popErrorHandler();

        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    function printExpansion($args = false) {
        echo $this->getExpansion($args);
    }

    // Debugging:
    function _dump_template () {
        $lines = explode("\n", $this->_munge_input($this->_tmpl));
        echo "<pre>\n";
        $n = 1;
        foreach ($lines as $line)
            printf("%4d  %s\n", $n++, htmlspecialchars($line));
        echo "</pre>\n";
    }

    function _errorHandler($error) {
        if (!preg_match('/: eval\(\)\'d code$/', $error->errfile))
	    return false;
	$error->errfile = "In template";
	$lines = explode("\n", $this->_tmpl);
	if (isset($lines[$error->errline - 1]))
	    $error->errstr .= ":\n\t" . $lines[$error->errline - 1];
	return $error;
    }
};

class TemplateFile
extends Template
{
    function TemplateFile($filename) {
	$this->_template_file = $filename;
        $fp = fopen($filename, "rb");
        $data = fread($fp, filesize($filename));
        fclose($fp);
        $this->Template($data);
    }
}

class WikiTemplate
extends TemplateFile
{
    /**
     * Constructor.
     *
     * Constructs a new WikiTemplate based upon the named template.
     *
     * @access public
     *
     * @param $template string Which template.
     */
    function WikiTemplate($template, $page_revision = false) {
        global $templates;

        $this->TemplateFile(FindFile($templates[$template]));

        $this->_template_name = $template;

        $this->setGlobalTokens();
        if ($page_revision)
            $this->setPageRevisionTokens($page_revision);
    }
    

    function setPageTokens(&$page) {
	/*
        if ($page->get('locked'))
            $this->setConditional('LOCK');
        // HACK: note that EDITABLE may also be set in setWikiUserTokens.
        if (!$page->get('locked'))
            $this->setConditional('EDITABLE');
	*/
	
        $pagename = $page->getName();
        $this->replace('NAVIGATION', toolbar_action_Navigation($pagename));
        $this->replace('SEARCH', toolbar_action_SearchActions($pagename,CHARSET));

	$this->replace('page', $page);
        $this->qreplace('CHARSET', CHARSET);
        $this->qreplace('PAGE', $pagename);
        $this->qreplace('PAGEURL', rawurlencode($pagename));
        $this->qreplace('SPLIT_PAGE', split_pagename($pagename));
        $this->qreplace('BROWSE_PAGE', WikiURL($pagename));

        $this->replace('EDIT_TIPS', toolbar_Info_EditTips());

        // FIXME: this is a bit of dangerous hackage.
        $this->qreplace('ACTION', WikiURL($pagename, array('action' => '')));

        // FIXME:?
        //$this->replace_callback('HITS', array($page, 'getHitCount'));
        //$this->replace_callback('RELATEDPAGES', array($page, 'getHitCount'));
        //_dotoken('RELATEDPAGES', LinkRelatedPages($dbi, $name), $page);
    }

    function setPageRevisionTokens(&$revision) {
        $page = & $revision->getPage();
        
        $current = & $page->getCurrentRevision();
        $previous = & $page->getRevisionBefore($revision->getVersion());

        $this->replace('VIEW_WARNINGS',
                       toolbar_Warnings_View($current->getVersion() == $revision->getVersion(),$page->getName()));
        $this->replace('IS_CURRENT',
		       $current->getVersion() == $revision->getVersion());
//        $this->replace('EDIT_WARNINGS',
//                       toolbar_Warnings_Edit(!empty($PREVIEW_CONTENT),
//                                             $current->getVersion() == $revision->getVersion()));
	/*
        if ($previous && $previous->getVersion() != 0)
            $this->setConditional('COPY'); // FIXME: should rename HAVE_COPY?
	*/
	
        global $datetimeformat;
        
        $this->replace('LASTMODIFIED',
                         toolbar_Info_LastModified(
                            ($current->getVersion() == $revision->getVersion()),
                            strftime($datetimeformat, $revision->get('mtime')),
                            $revision->getVersion() ));
//        $this->qreplace('LASTMODIFIED',
//                        strftime($datetimeformat, $revision->get('mtime')));

        $this->qreplace('LASTAUTHOR', $revision->get('author'));
        $this->qreplace('VERSION', $revision->getVersion());
        $this->qreplace('CURRENT_VERSION', $current->getVersion());

        $this->setPageTokens($page);
    }

    function setWikiUserTokens(&$user) {
	/*
        if ( $user->is_admin() ) {
            $this->setConditional('ADMIN');
            $this->setConditional('EDITABLE');
        }
        if ( ! $user->is_authenticated() )
            $this->setConditional('ANONYMOUS');
	*/
	$this->replace('user', $user);
        $this->qreplace('USERID', $user->id());

        //WARNING: hackage! $pagename is not available here
        $pagename="";
        $this->replace('SIGNIN', toolbar_User_UserSignInOut($user->is_authenticated(), $user->id(), $pagename));

        $prefs = $user->getPreferences();
        $this->qreplace('EDIT_AREA_WIDTH', $prefs['edit_area.width']);
        $this->qreplace('EDIT_AREA_HEIGHT', $prefs['edit_area.height']);
    }

    function setGlobalTokens () {
        global $user, $logo, $CSS_URLS, $CSS_DEFAULT, $RCS_IDS;
        
        // FIXME: This a a bit of dangerous hackage.
        $this->qreplace('BROWSE', WikiURL(''));
        $this->replace('CSS', CSS_URL($CSS_URLS, $CSS_DEFAULT));
        $this->qreplace('WIKI_NAME', WIKI_NAME);

        if (isset($user))
            $this->setWikiUserTokens($user);
        if (isset($logo))
            $this->replace('LOGO', toolbar_action_Logo(WIKI_NAME, $logo));
//            $this->qreplace('LOGO', DataURL($logo));
        if (isset($RCS_IDS))
            $this->qreplace('RCS_IDS', $RCS_IDS);

        $this->qreplace('BASE_URL',
			// FIXME:
                        //WikiURL($GLOBALS['pagename'], false, 'absolute_url')
                        BaseURL()
                        );
    }
};


/**
 * Generate page contents using a template.
 *
 * This is a convenience function for backwards compatibility with the old
 * GeneratePage().
 *
 * @param $template string name of the template (see config.php for list of names)
 *
 * @param $content string html content to put into the page
 *
 * @param $title string page title
 *
 * @param $page_revision object Current WikiDB_PageRevision, if available.
 *
 * @return string HTML expansion of template.
 */
function GeneratePage($template, $content, $title, $page_revision = false) {
    // require_once("lib/template.php");
    $t = new WikiTemplate($template);
    if ($page_revision)
        $t->setPageRevisionTokens($page_revision);
    $t->replace('CONTENT', $content);
    $t->replace('TITLE', $title);
    return $t->getExpansion();
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
