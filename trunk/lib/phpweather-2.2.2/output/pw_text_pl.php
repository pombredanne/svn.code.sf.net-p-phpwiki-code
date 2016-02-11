<?php // -*- coding: latin-2 -*-

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce Polish
 * output.
 *
 * @author   Michal Margula <alchemyx@uznam.net.pl>
 * @link     http://alchemyx.uznam.net.pl/  My homepage.
 * @version  pw_text_pl.php,v 1.0 2004/09/14 15:57:15 alchemyx Exp
 */
class pw_text_pl extends pw_text {

  /**
   * This constructor provides all the strings used.
   *
   * @param  array  This is just passed on to pw_text().
   */
  function pw_text_pl($weather, $input = array()) {
    $this->strings['charset']                  = 'ISO-8859-2';
    $this->strings['no_data']                  = 'Przepraszamy! Nie ma danych dostępnych dla %s%s%s.';
    $this->strings['list_sentences_and']       = ' i ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', i ';
    $this->strings['location']                 = 'To jest raport dla %s%s%s.';
    $this->strings['minutes']                  = ' minut';
    $this->strings['time_format']              = 'Raport został utworzony %s temu, o godzinie %s%s%s UTC.';
    $this->strings['time_minutes']             = 'i %s%s%s minut';
    $this->strings['time_one_hour']            = '%sjedną%s godzinę %s';
    $this->strings['time_several_hours']       = '%s%s%s godzin %s';
    $this->strings['time_a_moment']            = 'chwilę';
    $this->strings['meters_per_second']        = ' metrów na sekundę';
    $this->strings['miles_per_hour']           = ' mil na godzinę';
    $this->strings['meter']                    = ' metrów';
    $this->strings['meters']                   = ' metrów';
    $this->strings['feet']                     = ' stóp';
    $this->strings['kilometers']               = ' kilometrów';
    $this->strings['miles']                    = ' mil';
    $this->strings['and']                      = ' i ';
    $this->strings['plus']                     = ' plus ';
    $this->strings['with']                     = ' z ';
    $this->strings['wind_blowing']             = 'Wiatr wiał z prędkością ';
    $this->strings['wind_with_gusts']          = ' w porywach do ';
    $this->strings['wind_from']                = ' z kierunku ';
    $this->strings['wind_variable']            = ' ze %szmiennego%s kierunku';
    $this->strings['wind_varying']             = ', wahające się pomiędzy %s%s%s (%s%s&deg;%s) a %s%s%s (%s%s&deg;%s)';
    $this->strings['wind_calm']                = 'Wiatr był %sspokojny%s';
    $this->strings['wind_dir'] = array(
      'północnego',
      'północnego/północno-wschodniego',
      'północno-wschodniego',
      'wschodniego/połnocno-wschodniego',
      'wschodniego',
      'wschodniego/południowo-wschodniego',
      'południoweo-wschodniego',
      'południowego/południowo-wschodniego',
      'południowego',
      'południowego/południowo-zachodniego',
      'południowo-zachodniego',
      'zachodniego/południowo-zachodniego',
      'zachodniego',
      'zachodniego/północno-zachodniego',
      'północno-zachodniego',
      'północnego/północno-zachodniego',
      'północnego');
    $this->strings['wind_dir_short'] = array(
      'N',
      'NNE',
      'NE',
      'ENE',
      'E',
      'ESE',
      'SE',
      'SSE',
      'S',
      'SSW',
      'SW',
      'WSW',
      'W',
      'WNW',
      'NW',
      'NNW',
      'N');
    $this->strings['wind_dir_short_long'] = array(
      'N'  => 'north',
      'NE' => 'northeast',
      'E'  => 'east',
      'SE' => 'southeast',
      'S'  => 'south',
      'SW' => 'southwest',
      'W'  => 'west',
      'NW' => 'northwest'
      );
    $this->strings['temperature']     = 'Temperatura wynosiła ';
    $this->strings['dew_point']       = ', punkt rosy ';
    $this->strings['altimeter']       = 'Ciśnienie QHN wynosiło ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'Wilgotność względna wynosiła ';
    $this->strings['feelslike']       = 'Temperatura była odczuwalna jako ';
    $this->strings['cloud_group_beg'] = 'Było ';
    $this->strings['cloud_group_end'] = '.';
    $this->strings['cloud_clear']     = 'Niebo było %sczyste%s.';
    $this->strings['cloud_height']    = ' na wysokości ';
    $this->strings['cloud_overcast']  = 'Całkowite %szachmurzenie%s o podstawie ';
    $this->strings['cloud_vertical_visibility'] = '%spionowa widzialność%s ';
    $this->strings['cloud_condition'] =
      array(
	    'SKC' => 'niebo bezchmurne',
	    'CLR' => 'niebo bezchmurne (0/8)',
	    'FEW' => 'zachmurzenie niewielkie (1/8 - 2/8)',
	    'SCT' => 'zachmurzenie rozrzucone (3/8 - 4/8)',
	    'BKN' => 'zachmurzenie poprzerywane (5/8 - 7/8) ',
	    'OVC' => 'zachmurzenie całkowite (8/8)');
    $this->strings['cumulonimbus']     = ' cumulonimbus';
    $this->strings['towering_cumulus'] = ' cumulus wypiętrzony';
    $this->strings['cavok']            = ' brak chmur poniżej %s, brak cumulonimbusów oraz brak zjawisk atmosferycznych';
    $this->strings['currently']        = 'Aktualnie ';
    $this->strings['weather']          = 
      array(
	    '-' => ' lekkie',
	    ' ' => ' średnie ',
	    '+' => ' mocne ',
	    'VC' => ' w pobliżu',
	    'PR' => ' częściowe',
	    'BC' => ' płaty',
	    'MI' => ' płytkie',
	    'DR' => ' nisko unoszące',
	    'BL' => ' podmuchy',
	    'SH' => ' przelotne opady',
	    'TS' => ' burza z piorunami',
	    'FZ' => ' przymrozek',
	    'DZ' => ' mżawka',
	    'RA' => ' deszcz',
	    'SN' => ' śnieg',
	    'SG' => ' gruby śnieg',
	    'IC' => ' kryształki lodu',
	    'PL' => ' ice pellets',
	    'GR' => ' grad',
	    'GS' => ' mały grad',
	    'UP' => ' nieznany',
	    'BR' => ' zamglenie',
	    'FG' => ' mgły',
	    'FU' => ' dym',
	    'VA' => ' popiół wulkaniczny',
	    'DU' => ' widespread dust',
	    'SA' => ' piasek',
	    'HZ' => ' zmętnienie',
	    'PY' => ' pył wodny',
	    'PO' => ' mocno rozwijające się wiry piaskowe/pyłowe',
	    'SQ' => ' nawałnica',
	    'FC' => ' trąba powietrzna, wodna, tornado',
	    'SS' => ' burza piaskowa/pyłowa');
    $this->strings['visibility'] = 'Widzialność pozioma wynosiła ';
    $this->strings['visibility_greater_than']  = 'więcej niż ';
    $this->strings['visibility_less_than']     = 'mniej niż ';
    $this->strings['visibility_to']            = ' do ';
    $this->strings['runway_upward_tendency']   = ' z tendencją %wzrostową%s';
    $this->strings['runway_downward_tendency'] = ' z tendencją %smalejącą%s';
    $this->strings['runway_no_tendency']       = ' bez %srozróżnialnej%s tendencji';
    $this->strings['runway_between']           = 'pomiędzy ';
    $this->strings['runway_left']              = ' lewego';
    $this->strings['runway_central']           = ' środkowego';
    $this->strings['runway_right']             = ' prawego';
    $this->strings['runway_visibility']        = 'Widoczność była ';
    $this->strings['runway_for_runway']        = ' dla pasa ';

    /* We run the parent constructor */
    $this->pw_text($weather, $input);
  }
}

?>
