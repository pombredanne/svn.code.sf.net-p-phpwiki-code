<?php // -*-php-*-
// +---------------------------------------------------------------------+
// | WikiPluginCached.php                                                |
// +---------------------------------------------------------------------+
// | Copyright (C) 2002 Johannes Große (Johannes Gro&szlig;e)            |
// | You may copy this code freely under the conditions of the GPL       |
// +---------------------------------------------------------------------+

/* There is a bug in it:
   When the cache is empty and you safe the wikipages,
   an immediately created cached output of 
   RecentChanges will at the rss-image-link include 
   an action=edit
*/


require_once "lib/WikiPluginCached.php";
require_once "lib/plugin/RecentChanges.php";

class WikiPlugin_RecentChangesCached extends WikiPluginCached
{   
    /* --------- overwrite virtual or abstract methods ---------------- */
    function getPluginType() {
        return PLUGIN_CACHED_HTML;
    }
 
    function getName() {
        return "RecentChangesCached";
    }

    function getDescription() {
        return 'Caches output of RecentChanges called with default arguments.';
    }

    function getDefaultArguments() {
        return WikiPlugin_RecentChanges::getDefaultArguments();
    }

    function getExpire($dbi, $argarray, $request) {
        return '+900'; // 15 minutes
    }

    function getHtml($dbi, $argarray, $request) {
        $loader = new WikiPluginLoader;
        return $loader->expandPI('<?plugin RecentChanges '
            . WikiPluginCached::glueArgs($argarray) 
            . ' ?>',$request);            
    }

} // WikiPlugin_TexToPng



// For emacs users
// Local Variables:
// mode: php
// tab-width: 4
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
