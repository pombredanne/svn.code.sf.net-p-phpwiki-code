<?php // -*-php-*-
rcs_id('$Id: RecentReferrers.php,v 1.1 2004-11-06 04:52:29 rurban Exp $');

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

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array_merge
            (
             PageList::supportedArgs(),
             array(
                   'limit' 	   => 15,
                   'noheader'      => false,
                   'debug'         => false
                   ));
    }

    function run($dbi, $argstr, &$request, $basepage) { 
        if (!ACCESS_LOG) return;
        $args = $this->getArgs($argstr, $request); 
        $table = HTML::table(array('cellpadding' => 1,
                                   'cellspacing' => 2,
                                   'border'      => 0,
                                   'class'       => 'pagelist'));
        if (!$args['noheader'] and !empty($args['caption']))
            $table->pushContent(HTML::caption(array('align'=>'top'), $args['caption']));
        $logs = array();
        $limit = $args['limit'];
        $logentry = new Request_AccessLogEntry(ACCESS_LOG);
        while ($logentry->read()) {
            if (!empty($logentry->referer)) {
            	$logs[] = $logentry;
                if ($limit and count($logs) > $limit)
                    array_shift($logs);
                $logentry = new Request_AccessLogEntry(ACCESS_LOG);
            }
        }
        if ($logs) {
            $logs = array_reverse($logs);
            $table->pushContent(HTML::tr(HTML::th("Target"),HTML::th("Referrer"),
                                         HTML::th("Host"),HTML::th("Date")));
            $logs = array_slice($logs,0,min($limit,count($logs)));
            foreach ($logs as $logentry) {
                $table->pushContent(HTML::tr(HTML::td($logentry->request),
                                             HTML::td($logentry->referer),
                                             HTML::td($logentry->host),
                                             HTML::td($logentry->time)
                                             ));
            }
            return $table;
        }
    }
}

// $Log: not supported by cvs2svn $

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>