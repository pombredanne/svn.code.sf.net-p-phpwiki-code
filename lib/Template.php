<?php rcs_id('$Id: Template.php,v 1.23 2002-01-19 07:21:58 dairiki Exp $');

require_once("lib/ErrorManager.php");
require_once("lib/WikiPlugin.php");

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

        // Convert < ?= expr ? > to < ?php $this->_print(expr); ? >
        $orig[] = '/<\?=(.*?)\?>/s';
        $repl[] = '<?php $this->_print(\1);?>';
        
        // Convert tag attributes like foo=_("String") to foo="String" (with gettext mapping).
        $orig[] = '/( < \w [^>]* \w=)_\("((?:[^"\\\\]|\\.)*)"\)/xse';
        $repl[] = '"\1\"" . htmlspecialchars(gettext("\2")) . "\""';
        
        return preg_replace($orig, $repl, $template);

        //$ret = preg_replace($orig, $repl, $template);
        //echo QElement('pre', $ret);
        //return $ret;
    }

    function _getReplacement($varname, $index = false) {
	// FIXME: report missing vars.

        echo "GET: $varname<br>\n";
        
	$vars = &$this->_vars;
	if (!isset($vars[$varname]))
            return false;

        $value = $vars[$varname];
        if ($index !== false)
            @$value = $value[$index];

        if (!is_string($value))
            $value = $this->_toString($value);

        // Quote '?' to avoid inadvertently inserting "<? php", "? >", or similar...
        return str_replace('?', '&#63;', $value);
    }
    
    function _print ($val) {
        $string_val = '';

        if (is_array($val)) {
            $n = 0;
            foreach ($val as $item) {
                if ($n++)
                    echo "\n";
                $this->_print($item);
            }
        }
        elseif (is_object($val)) {
            if (isa($val, 'Template')) {
                // Expand sub-template with defaults from this template.
                $val->printExpansion($this->_vars);
            }
            elseif (method_exists($val, 'printhtml')) {
                $val->printHTML();
            }
            elseif (method_exists($val, 'ashtml')) {
                echo $val->asHTML();
            }
            elseif (method_exists($val, 'asstring')) {
                return htmlspecialchars($val->asString());
            }
        }
        else {
            echo (string) $val;
        }
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
    
    function printExpansion ($defaults = false) {
        $vars = &$this->_vars;
        if ($defaults !== false) {
            $save_vars = $vars;
            if (!is_array($defaults)) {
                if (!isset($vars['CONTENT']))
                    $vars['CONTENT'] = $defaults;
            }
            else {
                foreach ($defaults as $key => $val)
                    if (!isset($vars[$key]))
                        $vars[$key] = $val;
            }
        }
        extract($vars);
        
        //$this->_dump_template();

        global $ErrorManager;
        $ErrorManager->pushErrorHandler(new WikiMethodCb($this, '_errorHandler'));

        eval('?>' . $this->_munge_input($this->_tmpl));

        $ErrorManager->popErrorHandler();

        if (isset($save_vars))
            $vars = $save_vars;
    }

    function getExpansion ($defaults = false) {
        ob_start();
        $this->printExpansion($defaults);
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
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

        // Hack alert: Ignore 'undefined variable' messages for variables
        //  whose names are ALL_CAPS.
        if (preg_match('/Undefined variable:\s*[_A-Z]+\s*$/', $error->errstr))
            return true;
        
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
        global $Theme;
        $this->TemplateFile($Theme->findTemplate($template));
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

        $this->replace('page', $page);
        $this->qreplace('PAGE', $pagename);
        $this->qreplace('PAGEURL', rawurlencode($pagename));
        $this->qreplace('SPLIT_PAGE', split_pagename($pagename));
        $this->qreplace('BROWSE_PAGE', WikiURL($pagename));

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

        $this->replace('IS_CURRENT',
		       $current->getVersion() == $revision->getVersion());
	
        global $datetimeformat;
        
        //$this->qreplace('LASTMODIFIED',
        //              strftime($datetimeformat, $revision->get('mtime')));

        $this->qreplace('LASTAUTHOR', $revision->get('author'));
        $this->qreplace('VERSION', $revision->getVersion());
        $this->qreplace('CURRENT_VERSION', $current->getVersion());

        $this->replace('revision', $revision);
        
        $this->setPageTokens($page);
    }

    function setWikiUserTokens(&$user) {
	$this->replace('user', $user);
        $this->qreplace('USERID', $user->getId());

        $prefs = $user->getPreferences();
        $this->qreplace('EDIT_AREA_WIDTH', $prefs['edit_area.width']);
        $this->qreplace('EDIT_AREA_HEIGHT', $prefs['edit_area.height']);
    }

    function setGlobalTokens () {
        global $user, $RCS_IDS, $Theme, $request;
        
        // FIXME: This a a bit of dangerous hackage.
        $this->replace('Theme', $Theme);
        $this->qreplace('BROWSE', WikiURL(''));
        $this->qreplace('WIKI_NAME', WIKI_NAME);

        if (isset($user))
            $this->setWikiUserTokens($user);
        if (isset($RCS_IDS))
            $this->qreplace('RCS_IDS', $RCS_IDS);

        require_once('lib/ButtonFactory.php');
        $this->replace('ButtonFactory', new ButtonFactory);

        $query_args = $request->getArgs();
        unset($query_args['login']);
        $this->replace('query_args', $query_args);
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
    // FIXME: More hackage.  Really GeneratePage should go away, at some point.
    assert($template == 'MESSAGE');
    $t = new WikiTemplate('top');
    $t->qreplace('TITLE', $title);
    $t->qreplace('HEADER', $title);
    if ($page_revision)
        $t->setPageRevisionTokens($page_revision);
    $t->replace('CONTENT', $content);
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
