<?php rcs_id('$Id: PrevNext.php,v 1.1 2002-08-24 13:18:56 rurban Exp $');
/**
 * Usage: <?plugin PrevNext prev=PrevLink next=NextLink ?>
 * See also PageGroup which automatically tries to extract the various links
 *
 */
class WikiPlugin_PrevNext
extends WikiPlugin
{
    function getName() {
        return _("PrevNext");
    }

    function getDescription() {
        return sprintf(_("Easy navigation buttons for %s"),'[pagename]');
    }

    function getDefaultArguments() {
        return array(
                     'prev'    => '',
                     'next'    => '',
                     'up'      => '',
                     'contents' => '',
                     'index'   => '',
                     'up'      => '',
                     'first'   => '',
                     'last'    => '',
                     'order'   => '',
                     'style'   => 'button', // or 'text'
                     'class'   => 'wikiaction'
                     );
    }

    function run($dbi, $argstr, $request) {

        $args = $this->getArgs($argstr, $request);
        extract($args);
        $directions = array ('first'    => _("First"),
                             'prev'     => _("Previous"),
                             'next'     => _("Next"),
                             'last'     => _("Last"),
                             'up'       => _("Up"),
                             'contents'  => _("Contents"),
                             'index'    => _("Index")
                             );
        if ($order) { // reorder the buttons: comma-delimited
            $new_directions = array();	
            foreach (explode(',',$order) as $o) {
        	$new_directions[$o] = $directions[$o];
            }
            $directions = $new_directions;
            unset ($new_directions); // free memory
        }

        global $Theme;
        $sep = $Theme->getButtonSeparator();
        $links = HTML();
        if ($style == 'text') {
            if (!$sep) $sep = " | "; // force some kind of separator
            $links->pushcontent(" [ ");
        }
	$last_is_text = false; $this_is_first = true;
        foreach ($directions as $dir => $label) {
            // if ($last_is_text) $links->pushContent($sep);
            if (!empty($args[$dir])) {
                $url = $args[$dir];
                if ($style == 'button') {
                    // localized version: _("Previous").gif
                    if ($imgurl = $Theme->getButtonURL($label)) {
                    	if ($last_is_text) $links->pushContent($sep);
                        $links->pushcontent(new ImageButton($label, $url, false, $imgurl));
            		$last_is_text = false;
                    // generic version: prev.gif
                    } elseif ($imgurl = $Theme->getButtonURL($dir)) {
                    	if ($last_is_text) $links->pushContent($sep);
                        $links->pushContent(new ImageButton($label, $url, false, $imgurl));
	                $last_is_text = false;
                    } else { // text only
                    	if (! $this_is_first) $links->pushContent($sep);
                        $links->pushContent(new Button($label, $url, $class));
	                $last_is_text = true;
                    }
                } else {
                    if (! $this_is_first) $links->pushContent($sep);
                    $links->pushContent(new Button($label, $url, $class));
                    $last_is_text = true;
                }
                $this_is_first = false;
            }
        }
        if ($style == 'text') $links->pushcontent(" ] ");
        return $links;
    }
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
