<?php // -*-php-*-
rcs_id('$Id PhpWeather.php 2002-08-26 15:30:13 rurban$');
/**
 * This plugin requires a separate program called PHPWeather.
 * For more information and to download PHPWeather,
 * See: http://sourceforge.net/projects/phpweather/
 *
 * There are still some problems with this plugin.
 *
 * Make sure you download the latest CVS version of PHPWeather from
 * http://phpweather.sourceforge.net/downloads/
 *
 * Coming soon: When no ICAO code is provided, a popup of cities will be presented.
 *
 * Usage:
 * <?plugin PhpWeather?>
 * <?plugin PhpWeather icao=KJFK ?>
 * <?plugin PhpWeather icao=LOWG language=en version=1.61 location=Graz-Thalerhof-Flughafen ?>
 */

// Name the PHPWeather folder 'phpweather' and put it anywhere inside
// phpwiki, such as the plugin folder
if (!defined('PHPWEATHER_FOLDER')) {
    if (preg_match('/sourceforge\.net/', SERVER_NAME)) {
        define('PHPWEATHER_FOLDER', '/home/groups/p/ph/phpwiki/htdocs/demo/lib/plugin/phpweather');
        define('PHPWEATHER_VERSION', 1.92);
    } elseif (isWindows()) {
        //define('PHPWEATHER_FOLDER', 'V:/home/rurban/phpweather-1.61');
        //define('PHPWEATHER_VERSION', 1.61);
        define('PHPWEATHER_FOLDER', 'V:/home/rurban/phpweather');
        define('PHPWEATHER_VERSION', 1.92);
    } else { // defaults to a parallel dir to phpwiki
        define('PHPWEATHER_VERSION', 1.92);
        define('PHPWEATHER_FOLDER', PHPWIKI_DIR . '/../phpweather');
    }
}

class WikiPlugin_PhpWeather
extends WikiPlugin
{
    function getName () {
        return _("PhpWeather");
    }
    function getDescription () {
        return _("The PhpWeather plugin provides weather reports from the Internet.");
    }
    function getDefaultArguments() {
        global $LANG;
        return array('icao' => 'KJFK',
                     'language' => $LANG == 'C' ? 'en' : $LANG,
                     'version'  => PHPWEATHER_VERSION,
                     'location' => '', // needed for version < 1.9
                     'popups' => 0
                     );
    }
    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));
        $html = HTML::form(array('action' => $request->getURLtoSelf(),
                                 'method' => "POST"));
        if ($version > 1.7) { // The newer version as class
            require_once(PHPWEATHER_FOLDER . '/phpweather.php');
            $w = new phpweather(array() /*$properties*/);
        } else { // The old and latest stable release (currently 1.61)
            @include(PHPWEATHER_FOLDER . "/locale_$language.inc");
            @include(PHPWEATHER_FOLDER . '/config-dist.inc');
            @include(PHPWEATHER_FOLDER . '/config.inc');
            include(PHPWEATHER_FOLDER . '/phpweather.inc');
            $cities = array(
                            'BGTL' => 'Thule A. B., Greenland',
                            'EGKK' => 'London / Gatwick Airport, United Kingdom',
                            'EKCH' => 'Copenhagen / Kastrup, Denmark',
                            'ENGM' => 'Oslo / Gardermoen, Norway',
                            'ESSA' => 'Stockholm / Arlanda, Sweden',
                            'FCBB' => 'Brazzaville / Maya-Maya, Congo',
                            'LEMD' => 'Madrid / Barajas, Spain',
                            'LFPB' => 'Paris / Le Bourget, France',
                            'LHBP' => 'Budapest / Ferihegy, Hungary',
                            'LIRA' => 'Roma / Ciampino, Italy',
                            'LMML' => 'Luqa International Airport, Malta',
                            'KNYC' => 'New York City, Central Park, NY, United States',
                            'NZCM' => 'Williams Field, Antarctic',
                            'UUEE' => 'Moscow / Sheremet\'Ye , Russian Federation',
                            'RKSS' => 'Seoul / Kimp\'O International Airport, Korea',
                            'YSSY' => 'Sydney Airport, Australia',
                            'ZBAA' => 'Beijing, China'
                            );
        }
        if (!$icao) $icao = $request->getArg('icao');
        if ($icao) {
            if ($version > 1.7) { // The newer version as class
                $w->set_icao($icao);
                if (!in_array($language,explode(',','en,da,de,hu,no'))) $language = 'en';
                $w->set_language($language);
                $m = $w->print_pretty();
            } else {
                $metar = get_metar($icao);
                $data = process_metar($metar);
                // catch output into buffer and return it as string
                ob_start();
                pretty_print_metar($metar, $location);
                $m = ob_get_contents();
                ob_end_clean();
            }
            $html->pushContent(new RawXml($m));
        }
        $popups = $popups || !$icao;
        if ($popups) {
            // display the popups: cc and stations
            $options = HTML();
            if ($version > 1.7) {
                $countries = $GLOBALS['obj']->db->get_countries();
                $selected_cc = $request->getArg('cc');
                while (list($cc, $country) = each($countries)) {
                    if ($cc == $selected_cc) {
                        $options->pushContent(HTML::option(array('value' => $cc, 'selected' => 'selected'), 
                                                           ($country ? $country : $cc) . "\n"));
                    } else {
                        $options->pushContent(HTML::option(array('value' => $cc),
                                                           ($country ? $country : $cc) . "\n"));
                    }
                }
                if ($selected_cc) $html->pushContent(HTML::input(array('type' => "hidden", 'name' => "old_cc", 'value' => $selected_cc))); 
                $html->pushContent(HTML::select(array('name' => "cc", 'id' => 'cc'),
                                                $options));
            }
            if ($selected_cc or $version < 1.7) {
                $options = HTML();
                $country = '';
                if ($version > 1.7)
                    $cities = $GLOBALS['obj']->db->get_icaos($selected_cc, $country); 
                $selected_icao = $request->getArg('icao');
                while (list($icao, $name) = each($cities)) { 
                    if ($icao == $selected_icao) {
                        $options->pushContent(HTML::option(array('value' => $icao, 'selected' => 'selected'), 
                                                           ($name ? $name : $icao) . "\n"));
                    } else {
                        $options->pushContent(HTML::option(array('value' => $icao), 
                                                           ($name ? $name : $icao) . "\n"));
                    }
                }
                if ($selected_icao) $html->pushContent(HTML::input(array('type' => "hidden",'name' => "old_icao",'value' => $selected_icao))); 
                $html->pushContent(HTML::select(array('name' => "icao", 'id' => 'icao'),
                                                $options));
            }
            $html->pushContent(HTML::input(array('type' => "submit")));
        }
        // trigger_error("required argument 'icao' missing");
        return $html;
    }
}

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>