<?php // -*-php-*-
rcs_id('$Id: PhpHighlight.php,v 1.5 2002-11-08 18:33:12 carstenklapp Exp $');
/**
 * A plugin that runs the highlight_string() function in PHP on it's
 * arguments to pretty-print PHP code.
 *
 * Usage:
 * <?plugin PhpHighlight default='#FF0000' comment='#0000CC'
 * code that should be highlighted
 * ?>
 *
 * You should not add '<?php' and '?>' to the code - the plugin does
 * this automatically.
 *
 * Author: Martin Geisler <gimpster@gimpster.com>.
 *
 * Added compatibility for PHP < 4.2.0, where the highlight_string()
 * function has no second argument.
 * Added ability to override colors defined in php.ini --Carsten Klapp
 */

class WikiPlugin_PhpHighlight
extends WikiPlugin
{
    // Four required functions in a WikiPlugin.

    function getName () {
        return _("PhpHighlight");
    }

    function getDescription () {
        return _("PHP syntax highlighting");

    }
    // Establish default values for each of this plugin's arguments.
    function getDefaultArguments() {
        // TODO: results of ini_get() should be static for multiple
        // invocations of plugin on one WikiPage
        return array('source'  => false,
                     'string'  => ini_get("highlight.string"),  //'#00CC00',
                     'comment' => ini_get("highlight.comment"), //'#FF9900',
                     'keyword' => ini_get("highlight.keyword"), //'#006600',
                     'bg'      => ini_get("highlight.bg"),      //'#FFFFFF',
                     'default' => ini_get("highlight.default"), //'#0000CC',
                     'html'    => ini_get("highlight.html")     //'#000000'
                     );
    }

    function run($dbi, $argstr, $request) {

        extract($this->getArgs($argstr, $request));

        if (!function_exists('version_compare')
            || version_compare(phpversion(), '4.2.0', 'lt')) {
            // trigger_error(sprintf(_("%s requires PHP version %s or newer."),
            //                      $this->getName(), "4.2.0"), E_USER_NOTICE);
            /* return unhighlighted text as if <verbatim> were used */
            // return HTML::pre($argstr); // early return
            $has_old_php = true;
        }

        $this->sanify_colors($string, $comment, $keyword, $bg, $default, $html);
        $this->set_colors($string, $comment, $keyword, $bg, $default, $html);

        /* Automatically wrap with "<?php\n" and "\n?>" required by
           highlight_string(): */
        $source = "<?php\n" . $source . "\n?>";

        if (!empty($has_old_php)) {
            ob_start();
            highlight_string($source);
            $str = ob_get_contents();
            ob_end_clean();
        } else {
            $str = highlight_string($source, true);
        }
        /* Remove "<?php\n" and "\n?>": */
        $str = str_replace(array('&lt;?php<br />', '?&gt;'), '', $str);

        /**
         * We might have made some empty font tags. (The following
         * str_replace string does not produce results on my system,
         * maybe a php bug? '<font color="$color"></font>')
         */
        foreach (array($string, $comment, $keyword, $bg, $default, $html) as $color) {
            $search = "<font color=\"$color\"></font>";
            $str = str_replace($search, '', $str);
        }

        /* restore default colors in case of multiple invocations of
           this plugin on one page */
        $this->restore_colors();
        return new RawXml($str);
    }

    function handle_plugin_args_cruft(&$argstr, &$args) {
        $args['source'] = $argstr;
    }

    /**
     * Make sure color argument is valid
     * See http://www.w3.org/TR/REC-html40/types.html#h-6.5
     */
    function sanify_colors($string, $comment, $keyword, $bg, $default, $html) {
        static $html4colors = array("black", "silver", "gray", "white", "maroon", "red",
                                    "purple", "fuchsia", "green", "lime", "olive", "yellow",
                                    "navy", "blue", "teal", "aqua");
        static $MAXLEN = 7; /* strlen("fuchsia"), strlen("#00FF00"), strlen("#fff") */
        foreach (array($string, $comment, $keyword, $bg, $default, $html) as $color) {
            $length = strlen($color);
            //trigger_error(sprintf(_("DEBUG: color '%s' is length %d."), $color, $length), E_USER_NOTICE);
            if (($length == 7 || $length == 4) && substr($color, 0, 1) == "#"
            && "#" == preg_replace("/[a-fA-F0-9]/", "", $color)
             ) {
                //trigger_error(sprintf(_("DEBUG: color '%s' appears to be hex."), $color), E_USER_NOTICE);
                // stop checking, ok to go
            } elseif (($length < $MAXLEN + 1) && in_array($color, $html4colors)) {
                //trigger_error(sprintf(_("DEBUG color '%s' appears to be an HTML 4 color."), $color), E_USER_NOTICE);
                // stop checking, ok to go
            } else {
                trigger_error(sprintf(_("Invalid color: %s"),
                                      $color), E_USER_NOTICE);
                // FIXME: also change color to something valid like "black" or ini_get("highlight.xxx")
            }
        }
    }

    function set_colors($string, $comment, $keyword, $bg, $default, $html) {
        // set highlight colors
        $this->oldstring = ini_set('highlight.string', $string);
        $this->oldcomment = ini_set('highlight.comment', $comment);
        $this->oldkeyword = ini_set('highlight.keyword', $keyword);
        $this->oldbg = ini_set('highlight.bg', $bg);
        $this->olddefault = ini_set('highlight.default', $default);
        $this->oldhtml = ini_set('highlight.html', $html);
    }

    function restore_colors() {
        // restore previous default highlight colors
        ini_set('highlight.string', $this->oldstring);
        ini_set('highlight.comment', $this->oldcomment);
        ini_set('highlight.keyword', $this->oldkeyword);
        ini_set('highlight.bg', $this->oldbg);
        ini_set('highlight.default', $this->olddefault);
        ini_set('highlight.html', $this->oldhtml);
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
