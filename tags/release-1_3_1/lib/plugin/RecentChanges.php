<?php // -*-php-*-
rcs_id('$Id: RecentChanges.php,v 1.1 2001-09-18 19:19:05 dairiki Exp $');
/**
 */
class WikiPlugin_RecentChanges
extends WikiPlugin
{
    var $name = 'RecentChanges';
    
    function getDefaultArguments() {
        return array('days'		=> 2,
                     'show_minor'	=> false,
                     'show_major'	=> true,
                     'show_all'		=> false);
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        $params = array('include_minor_revisions' => $show_minor,
                        'exclude_major_revisions' => !$show_major,
                        'include_all_revisions' => $show_all);
        if ($days > 0.0) {
            $params['since'] = time() - 24 * 3600 * $days;
            $html = "<h3>RecentChanges in the last $days days</h3>\n";
        }
        else {
            $html = sprintf("<h3>RecentChanges</h3>\n", $days);
        }
             
        $changes = $dbi->mostRecent($params);

        global $dateformat;
        global $WikiNameRegexp;
        
        $last_date = '';
        $lines = array();

        $diffargs = array('action' => 'diff');

        while ($rev = $changes->next()) {
            $created = $rev->get('mtime');
            $date = strftime($dateformat, $created);
            $time = strftime("%l:%M %P", $created); // Make configurable.
            if ($date != $last_date) {
                if ($lines) {
                    $html .= Element('ul', join("\n", $lines));
                    $lines = array();
                }
                $html .= Element('p',QElement('b', $date));
                $last_date = $date;
            }
            
            $page = $rev->getPage();
            $pagename = $page->getName();

            if ($show_all) {
                // FIXME: should set previous, too, if showing only minor or major revs.
                //  or maybe difftype.
                $diffargs['version'] = $rev->getVersion();
            }
            
            $diff = QElement('a',
                             array('href' => WikiURL($pagename, $diffargs)),
                             "(diff)");
            
            $wikipage = LinkWikiWord($page->getName());

            $author = $rev->get('author');
            if (preg_match("/^$WikiNameRegexp\$/", $author))
                $author = LinkWikiWord($author);
            else
                $author = htmlspecialchars($author);

            $summary = $rev->get('summary');
            if ($summary)
                $summary = QElement('b', "[$summary]");
            
            $lines[] = Element('li',
                               "$diff $wikipage $time $summary ... $author");
        }
        if ($lines)
            $html .= Element('ul', join("\n", $lines));
        
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
