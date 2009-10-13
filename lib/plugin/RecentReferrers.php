<?php // -*-php-*-
rcs_id('$Id$');

/**
 * Analyze our ACCESS_LOG
 * Check HTTP_REFERER
 *
 */
include_once("lib/PageList.php");

class WikiPlugin_RecentReferrers extends WikiPlugin
{
    function getName () {
        return _("RecentReferrers");
    }

    function getDescription () {
        return _("Analyse access log.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision$");
    }

    function getDefaultArguments() {
        return array_merge
            (
             PageList::supportedArgs(),
             array(
                   'limit' 	   => 15,
                   'noheader'      => false,
                   ));
    }

    function run($dbi, $argstr, &$request, $basepage) { 
        if (!ACCESS_LOG) {
            return HTML::div(array('class' => "error"), "Error: no ACCESS_LOG");
        }
        $args = $this->getArgs($argstr, $request); 
        $table = HTML::table(array('cellpadding' => 1,
                                   'cellspacing' => 2,
                                   'border'      => 0,
                                   'class'       => 'pagelist'));
        if (!$args['noheader'] and !empty($args['caption']))
            $table->pushContent(HTML::caption(array('align'=>'top'), $args['caption']));
        $logs = array();
        $limit = $args['limit'];
        $accesslog =& $request->_accesslog;
        if ($logiter = $accesslog->get_referer($limit, "external_only")
            and $logiter->count()) {
            $table->pushContent(HTML::tr(HTML::th("Target"),HTML::th("Referrer"),
                                         HTML::th("Host"),HTML::th("Date")));
            while($logentry = $logiter->next()) {
                $table->pushContent(HTML::tr(HTML::td($logentry['request']),
                                             HTML::td($logentry['referer']),
                                             HTML::td($logentry['host']),
                                             HTML::td($logentry['time'])
                                             ));
            }
            return $table;
        }
    }
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
