<?php // -*-php-*-
rcs_id('$Id: AllPages.php,v 1.8 2002-01-31 01:14:14 dairiki Exp $');

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
		     'info'          => ''
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pagelist = new PageList($info, $exclude);
        if (!$noheader)
            $pagelist->setCaption(_("Pages in this wiki (%d total):"));

        $pagelist->addPages( $dbi->getAllPages($include_empty) );

        return $pagelist;
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
