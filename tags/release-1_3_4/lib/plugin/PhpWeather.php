<?php // -*-php-*-
rcs_id('$Id PhpWeather.php 2002-08-26 15:30:13 rurban$');
/**
 * This plugin requires a separate program called PhpWeather. For more
 * information and to download PhpWeather, see:
 *
 *   http://sourceforge.net/projects/phpweather/
 *
 * Usage:
 *
 * <?plugin PhpWeather ?>
 * <?plugin PhpWeather menu=true ?>
 * <?plugin PhpWeather icao=KJFK ?>
 * <?plugin PhpWeather lang=en ?>
 * <?plugin PhpWeather units=only_metric ?>
 * <?plugin PhpWeather icao||=CYYZ lang||=en menu=true ?>
 *
 * If you want a menu, and you also want to change the default station
 * or language, then you have to use the ||= form, or else the user
 * wont be able to change the station or language.
 *
 * The units argument should be one of only_metric, only_imperial,
 * both_metric, or both_imperial.
 */

// We require the base class from PHP Weather, adjust this to match
// the location of PhpWeather on your server:
$WEATHER = $_SERVER['DOCUMENT_ROOT'] . '/phpweather/phpweather.php';
if(! @include_once($WEATHER)) {
    if(!in_array($WEATHER, get_included_files()) ) {
        $error = sprintf(_("Could not open file %s."), "'$WEATHER'") . " ";
        $error .= sprintf(_("Make sure %s is installed and properly configured."),
                          'PHP Weather');
        trigger_error($error);
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
        return array('icao'  => 'EKAH',
                     'cc'    => 'DK',
                     'lang'  => 'en',
                     'menu'  => false,
                     'units' => 'both_metric');
    }

    function run($dbi, $argstr, $request) {
        // When 'phpweather/phpweather.php' is not installed then
        // PHPWEATHER_BASE_DIR will be undefined
        if (!defined('PHPWEATHER_BASE_DIR'))
            return fmt("Plugin %s failed.", $this->getName()); //early return

        require_once(PHPWEATHER_BASE_DIR . '/output/pw_images.php');
        require_once(PHPWEATHER_BASE_DIR . '/pw_utilities.php');

        extract($this->getArgs($argstr, $request));
        $html = HTML();

        $w = new phpweather(); // Our weather object

        if (!empty($icao)) {
            /* We assign the ICAO to the weather object: */
            $w->set_icao($icao);
            if (!$w->get_country_code()) {
                /* The country code couldn't be resolved, so we
                 * shouldn't use the ICAO: */
                trigger_error(sprintf(_("The ICAO '%s' wasn't recognized."),
                                      $icao), E_USER_NOTICE);
                $icao = '';
            }
        }

        if (!empty($icao)) {

            /* We check and correct the language if necessary: */
            //if (!in_array($lang, array_keys($w->get_languages('text')))) {
            if (!in_array($lang, array_keys(get_languages('text')))) {
                trigger_error(sprintf(_("%s does not know about the language '%s', using 'en' instead."),
                                      $this->getName(), $lang), E_USER_NOTICE);
                $lang = 'en';
            }

            $class = "pw_text_$lang";
            require_once(PHPWEATHER_BASE_DIR . "/output/$class.php");

            $t = new $class($w);
            $t->set_pref_units($units);
            $i = new pw_images($w);

            $i_temp = HTML::img(array('src' => $i->get_temp_image()));
            $i_wind = HTML::img(array('src' => $i->get_winddir_image()));
            $i_sky  = HTML::img(array('src' => $i->get_sky_image()));

            $m = $t->print_pretty();

            $m_td = HTML::td(HTML::p(new RawXml($m)));

            $i_tr = HTML::tr();
            $i_tr->pushContent(HTML::td($i_temp));
            $i_tr->pushContent(HTML::td($i_wind));

            $i_table = HTML::table($i_tr);
            $i_table->pushContent(HTML::tr(HTML::td(array('colspan' => '2'),
                                                    $i_sky)));

            $tr = HTML::tr();
            $tr->pushContent($m_td);
            $tr->pushContent(HTML::td($i_table));

            $html->pushContent(HTML::table($tr));

        }

        /* We make a menu if asked to, or if $icao is empty: */
        if ($menu || empty($icao)) {

            $form_arg = array('action' => $request->getURLtoSelf(),
                              'method' => 'get');

            /* The country box is always part of the menu: */
            $p1 = HTML::p(new RawXml(get_countries_select($w, $cc)));

            /* We want to save the language: */
            $p1->pushContent(HTML::input(array('type'  => 'hidden',
                                               'name'  => 'lang',
                                               'value' => $lang)));
            /* And also the ICAO: */
            $p1->pushContent(HTML::input(array('type'  => 'hidden',
                                               'name'  => 'icao',
                                               'value' => $icao)));

            $caption = (empty($cc) ? _("Submit country") : _("Change country"));
            $p1->pushContent(HTML::input(array('type'  => 'submit',
                                               'value' => $caption)));

            $html->pushContent(HTML::form($form_arg, $p1));

            if (!empty($cc)) {
                /* We have selected a country, now display a list with
                 * the available stations in that country: */
                $p2 = HTML::p();

                /* We need the country code after the form is submitted: */
                $p2->pushContent(HTML::input(array('type'  => 'hidden',
                                                   'name'  => 'cc',
                                                   'value' => $cc)));

                $p2->pushContent(new RawXml(get_stations_select($w, $cc, $icao)));
                $p2->pushContent(new RawXml(get_languages_select($w, $lang)));
                $p2->pushContent(HTML::input(array('type'  => 'submit',
                                                   'value' => 'Submit location')));

                $html->pushContent(HTML::form($form_arg, $p2));

            }

        }

        return $html;
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
