<?php // -*-php-*-
rcs_id('$Id: PhpHighlight.php,v 1.3 2002-11-04 19:29:40 carstenklapp Exp $');
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
        // TODO: results of ini_get() should be static for multiple invocations of plugin on one WikiPage
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

        if ($has_old_php) {
            ob_start();
            highlight_string("<?php\n" . $source . "\n?>");
            $str = ob_get_contents();
            ob_end_clean();
        } else {
            $str = highlight_string("<?php\n" . $source . "\n?>", true);
        }
        /* Remove "<?php\n" and "\n?>": */
        $str = str_replace(array('&lt;?php<br />', '?&gt;'), '', $str);
        /* We might have made some empty font tags: */
        $search = '<font color="$default"></font>';
        $str = str_replace($search, '', $str);

        return new RawXml($str);
    }

    function handle_plugin_args_cruft(&$argstr, &$args) {
        $args['source'] = $argstr;
    }

    function sanify_colors(&$string, &$comment, &$keyword, &$bg, &$default, &$html) {
        /* Make sure color argument is either 6 digits or 3 digits prepended by a #,
           or maybe an html color name like "black" */
        // TODO
    }

    function set_colors($string, $comment, $keyword, $bg, $default, $html) {
        // set highlight colors
        ini_set('highlight.string', $string);
        ini_set('highlight.comment', $comment);
        ini_set('highlight.keyword', $keyword);
        ini_set('highlight.bg', $bg);
        ini_set('highlight.default', $default);
        ini_set('highlight.html', $html);
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
