<?php // -*-php-*-
rcs_id('$Id');

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
		     'info'          => false
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        
        $pages = $dbi->getAllPages($include_empty);

        $pagelist = new PageList();
        if ($info)
            foreach (explode(",", $info) as $col)
                $pagelist->insertColumn($col);

        if (!$noheader)
            $pagelist->setCaption(_("Pages in this wiki (%d total):"));

        while ($page = $pages->next())
            $pagelist->addPage($page);

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
