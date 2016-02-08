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
    $this->strings['no_data']                  = 'Przepraszamy! Nie ma danych dost�pnych dla %s%s%s.';
    $this->strings['list_sentences_and']       = ' i ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', i ';
    $this->strings['location']                 = 'To jest raport dla %s%s%s.';
    $this->strings['minutes']                  = ' minut';
    $this->strings['time_format']              = 'Raport zosta� utworzony %s temu, o godzinie %s%s%s UTC.';
    $this->strings['time_minutes']             = 'i %s%s%s minut';
    $this->strings['time_one_hour']            = '%sjedn�%s godzin� %s';
    $this->strings['time_several_hours']       = '%s%s%s godzin %s';
    $this->strings['time_a_moment']            = 'chwil�';
    $this->strings['meters_per_second']        = ' metr�w na sekund�';
    $this->strings['miles_per_hour']           = ' mil na godzin�';
    $this->strings['meter']                    = ' metr�w';
    $this->strings['meters']                   = ' metr�w';
    $this->strings['feet']                     = ' st�p';
    $this->strings['kilometers']               = ' kilometr�w';
    $this->strings['miles']                    = ' mil';
    $this->strings['and']                      = ' i ';
    $this->strings['plus']                     = ' plus ';
    $this->strings['with']                     = ' z ';
    $this->strings['wind_blowing']             = 'Wiatr wia� z pr�dko�ci� ';
    $this->strings['wind_with_gusts']          = ' w porywach do ';
    $this->strings['wind_from']                = ' z kierunku ';
    $this->strings['wind_variable']            = ' ze %szmiennego%s kierunku';
    $this->strings['wind_varying']             = ', wahaj�ce si� pomi�dzy %s%s%s (%s%s&deg;%s) a %s%s%s (%s%s&deg;%s)';
    $this->strings['wind_calm']                = 'Wiatr by� %sspokojny%s';
    $this->strings['wind_dir'] = array(
      'p�nocnego',
      'p�nocnego/p�nocno-wschodniego',
      'p�nocno-wschodniego',
      'wschodniego/po�nocno-wschodniego',
      'wschodniego',
      'wschodniego/po�udniowo-wschodniego',
      'po�udnioweo-wschodniego',
      'po�udniowego/po�udniowo-wschodniego',
      'po�udniowego',
      'po�udniowego/po�udniowo-zachodniego',
      'po�udniowo-zachodniego',
      'zachodniego/po�udniowo-zachodniego',
      'zachodniego',
      'zachodniego/p�nocno-zachodniego',
      'p�nocno-zachodniego',
      'p�nocnego/p�nocno-zachodniego',
      'p�nocnego');
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
    $this->strings['temperature']     = 'Temperatura wynosi�a ';
    $this->strings['dew_point']       = ', punkt rosy ';
    $this->strings['altimeter']       = 'Ci�nienie QHN wynosi�o ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'Wilgotno�� wzgl�dna wynosi�a ';
    $this->strings['feelslike']       = 'Temperatura by�a odczuwalna jako ';
    $this->strings['cloud_group_beg'] = 'By�o ';
    $this->strings['cloud_group_end'] = '.';
    $this->strings['cloud_clear']     = 'Niebo by�o %sczyste%s.';
    $this->strings['cloud_height']    = ' na wysoko�ci ';
    $this->strings['cloud_overcast']  = 'Ca�kowite %szachmurzenie%s o podstawie ';
    $this->strings['cloud_vertical_visibility'] = '%spionowa widzialno��%s ';
    $this->strings['cloud_condition'] =
      array(
	    'SKC' => 'niebo bezchmurne',
	    'CLR' => 'niebo bezchmurne (0/8)',
	    'FEW' => 'zachmurzenie niewielkie (1/8 - 2/8)',
	    'SCT' => 'zachmurzenie rozrzucone (3/8 - 4/8)',
	    'BKN' => 'zachmurzenie poprzerywane (5/8 - 7/8) ',
	    'OVC' => 'zachmurzenie ca�kowite (8/8)');
    $this->strings['cumulonimbus']     = ' cumulonimbus';
    $this->strings['towering_cumulus'] = ' cumulus wypi�trzony';
    $this->strings['cavok']            = ' brak chmur poni�ej %s, brak cumulonimbus�w oraz brak zjawisk atmosferycznych';
    $this->strings['currently']        = 'Aktualnie ';
    $this->strings['weather']          = 
      array(
	    '-' => ' lekkie',
	    ' ' => ' �rednie ',
	    '+' => ' mocne ',
	    'VC' => ' w pobli�u',
	    'PR' => ' cz�ciowe',
	    'BC' => ' p�aty',
	    'MI' => ' p�ytkie',
	    'DR' => ' nisko unosz�ce',
	    'BL' => ' podmuchy',
	    'SH' => ' przelotne opady',
	    'TS' => ' burza z piorunami',
	    'FZ' => ' przymrozek',
	    'DZ' => ' m�awka',
	    'RA' => ' deszcz',
	    'SN' => ' �nieg',
	    'SG' => ' gruby �nieg',
	    'IC' => ' kryszta�ki lodu',
	    'PL' => ' ice pellets',
	    'GR' => ' grad',
	    'GS' => ' ma�y grad',
	    'UP' => ' nieznany',
	    'BR' => ' zamglenie',
	    'FG' => ' mg�y',
	    'FU' => ' dym',
	    'VA' => ' popi� wulkaniczny',
	    'DU' => ' widespread dust',
	    'SA' => ' piasek',
	    'HZ' => ' zm�tnienie',
	    'PY' => ' py� wodny',
	    'PO' => ' mocno rozwijaj�ce si� wiry piaskowe/py�owe',
	    'SQ' => ' nawa�nica',
	    'FC' => ' tr�ba powietrzna, wodna, tornado',
	    'SS' => ' burza piaskowa/py�owa');
    $this->strings['visibility'] = 'Widzialno�� pozioma wynosi�a ';
    $this->strings['visibility_greater_than']  = 'wi�cej ni� ';
    $this->strings['visibility_less_than']     = 'mniej ni� ';
    $this->strings['visibility_to']            = ' do ';
    $this->strings['runway_upward_tendency']   = ' z tendencj� %wzrostow�%s';
    $this->strings['runway_downward_tendency'] = ' z tendencj� %smalej�c�%s';
    $this->strings['runway_no_tendency']       = ' bez %srozr�nialnej%s tendencji';
    $this->strings['runway_between']           = 'pomi�dzy ';
    $this->strings['runway_left']              = ' lewego';
    $this->strings['runway_central']           = ' �rodkowego';
    $this->strings['runway_right']             = ' prawego';
    $this->strings['runway_visibility']        = 'Widoczno�� by�a ';
    $this->strings['runway_for_runway']        = ' dla pasa ';

    /* We run the parent constructor */
    $this->pw_text($weather, $input);
  }
}

?>
