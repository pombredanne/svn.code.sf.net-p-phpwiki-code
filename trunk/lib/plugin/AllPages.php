<?php // -*-php-*-
rcs_id('$Id: AllPages.php,v 1.14 2002-09-09 08:38:19 rurban Exp $');

require_once('lib/PageList.php');

/**
 */
class WikiPlugin_AllPages
extends WikiPlugin
{
    function getName () {
        return _("AllPages");
    }

    function getDescription () {
        return _("All Pages");
    }

    function getDefaultArguments() {
        return array('noheader'	     => false,
                     'include_empty' => false,
                     'exclude'       => '',
                     'info'          => '',
                     'sortby'        => '',   // +mtime,-pagename
                     'debug'         => false
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor,markup or all
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges
    // sortby: [+|-] pagename|mtime|hits

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        // Todo: extend given _GET args
        if ($sortby) $request->setArg('sortby',$sortby);

        $pagelist = new PageList($info, $exclude);
        if (!$noheader)
            $pagelist->setCaption(_("Pages in this wiki (%d total):"));

        // deleted pages show up as version 0.
        if ($include_empty)
            $pagelist->_addColumn('version');

        if (defined('DEBUG'))
            $debug = true;

        if ($debug) $time_start = $this->getmicrotime();

        $pagelist->addPages( $dbi->getAllPages($include_empty) );

        if ($debug) $time_end = $this->getmicrotime();

        if ($debug) {
            $time = round($time_end - $time_start, 3);
            return HTML($pagelist,HTML::p(fmt("Elapsed time: %s s", $time)));
        } else {
            return $pagelist;
        }
    }

    function getmicrotime(){
        list($usec, $sec) = explode(" ",microtime());
        return (float)$usec + (float)$sec;
    }
};

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
