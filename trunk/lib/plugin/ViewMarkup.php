<?php // -*-php-*-
rcs_id('$Id: ViewMarkup.php,v 1.3 2001-12-31 08:30:36 carstenklapp Exp $');
require_once('lib/Template.php');
/**
 * A handy plugin for viewing the WikiMarkup of locked pages.
 * based on _BackendInfo.php, v 1.4
 */
class WikiPlugin_ViewMarkup
extends WikiPlugin
{
    function getName () {
        return _("ViewMarkup");
    }

    function getDescription () {
        return sprintf(_("View WikiMarkup for page '%s'."),'[pagename]');
    }
    
        function WikiPlugin_ViewMarkup() {
    }

    function getDefaultArguments() {
        return array('page'	=> false);
        
    }
    
    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page))
            return '';
        
        //fetch the latest version of the page. Should this be made to
        //work when viewing an old revision of a page too?
        $backend = &$dbi->_backend;
        $version = $backend->get_latest_version($page);
        $vdata = $backend->get_versiondata($page, $version, true);

        $content = &$vdata['%content'];

        $html = QElement('h2',
                 sprintf(_("Revealing WikiMarkup for page '%s':"), urlencode($page)));

        /* only good for WikiMarkup with few newlines */
        //$html .= Element('pre', nl2br($content) );

        /* only good for WikiMarkup with lots of newlines */
        //$html .= Element('pre', $content );

        /* good for any WikiMarkup
           but probably will not appear monospaced */
        //$html .= nl2br($content);

        /* <tt> seems to be a good compromise in IE and OmniWeb
           it doesn't combine newlines and <br>, and renders monospaced */
        $html .= Element('tt', nl2br($content));

        return $html;

    }
};
        
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
