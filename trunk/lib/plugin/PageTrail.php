<?php // -*-php-*-
rcs_id('$Id: PageTrail.php,v 1.1 2002-09-27 13:27:17 rurban Exp $');
/**
 * A simple PageTrail WikiPlugin.
 * Put this at the end of each page to store the trail.
 *
 * Usage:
 * <?plugin PageTrail?>
 * <?plugin PageTrail numberlinks=5?>
 * <?plugin PageTrail invisible=1?>  
 */

// Constants are defined before the class.
if (!defined('THE_END'))
    define('THE_END', "!");

class WikiPlugin_PageTrail
extends WikiPlugin
{
    // Four required functions in a WikiPlugin.
    var $def_numberlinks = 5;

    function getName () {
        return _("PageTrail");
    }

    function getDescription () {
        return _("PageTrail Plugin");

    }
    // default values
    function getDefaultArguments() {
        return array('numberlinks' => $this->def_numberlinks, 
                     'invisible' => false,
                     );
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        if ($numberlinks > 10 || $numberlinks < 0) { $numberlinks = $this->def_numberlinks; }

        // Get name of the current page we are on
        $thispage = $request->getArg('pagename');
        $thiscookie = $request->cookies->get("Wiki_PageTrail");
        $Pages = explode(':',$thiscookie);
        array_unshift($Pages, $thispage);
        $request->cookies->set("Wiki_PageTrail",implode(':',$Pages));

        if (! $invisible) {
            $numberlinks = min(count($Pages)-1, $numberlinks);
            $html = HTML::tt(fmt('%s', WikiLink($Pages[$numberlinks-1]), 'auto'));
            for ($i = $numberlinks-2; $i >= 0; $i--) {
                if (!empty($Pages[$i]))
                    $html->pushContent(fmt(' ==> %s', WikiLink($Pages[$i], 'auto')));
            }
            $html->pushContent(THE_END);
            return $html;
        } else 
            return HTML();
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>