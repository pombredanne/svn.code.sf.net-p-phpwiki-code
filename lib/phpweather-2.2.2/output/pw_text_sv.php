<?php

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce
 * Swedish output.
 *
 * @author   Kari Salovaara och Tage Malmen  <kari.salovaara@pp1.inet.fi>
 * @link     http://www.ecosyd.net/  My homepage.
 * @version  pw_text_sv.php,v 1.2 2002/09/10 10:06:24 gimpster Exp
 */
class pw_text_sv extends pw_text {

  /**
   * This constructor provides all the strings used.
   *
   * @param  array  This is just passed on to pw_text().
   */
  function pw_text_sv($weather, $input = array()) {
    $this->strings['charset']                  = 'ISO-8859-1';
    $this->strings['no_data']                  = 'Beklagar! Det finns ingen data tillg�nglig f�r %s%s%s.';
    $this->strings['list_sentences_and']       = ' och ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', och ';
    $this->strings['location']                 = 'Detta �r en rapport f�r %s%s%s.';
    $this->strings['minutes']                  = ' minuter';
    $this->strings['time_format']              = 'Denna rapport gjordes f�r %s sedan, klockan %s%s%s UTC.';
    $this->strings['time_minutes']             = 'och %s%s%s minuter';
    $this->strings['time_one_hour']            = '%sen%s timme %s';
    $this->strings['time_several_hours']       = '%s%s%s timmar %s';
    $this->strings['time_a_moment']            = 'ett �gonblick';
    $this->strings['meters_per_second']        = ' meter per sekund';
    $this->strings['miles_per_hour']           = ' miles per timme';
    $this->strings['meter']                    = ' meter';
    $this->strings['meters']                   = ' meter';
    $this->strings['feet']                     = ' fot';
    $this->strings['kilometers']               = ' kilometer';
    $this->strings['miles']                    = ' miles';
    $this->strings['and']                      = ' och ';
    $this->strings['plus']                     = ' plus ';
    $this->strings['with']                     = ' med ';
    $this->strings['wind_blowing']             = 'Vindens hastighet var ';
    $this->strings['wind_with_gusts']          = ' i byarna �nda upp till ';
    $this->strings['wind_from']                = ' fr�n ';
    $this->strings['wind_variable']            = ' fr�n %svariable%s riktningar.';
    $this->strings['wind_varying']             = ', varierande emellan %s%s%s (%s%s&deg;%s) och %s%s%s (%s%s&deg;%s)';
    $this->strings['wind_calm']                = 'Vinden var %sstille%s';
    $this->strings['wind_dir'] = array(
      'nord',
      'nord/nordost',
      'nordost',
      'ost/nordost',
      'ost',
      'ost/sydost',
      'sydost',
      'syd/sydost',
      'syd',
      'syd/sydv�st',
      'sydv�st',
      'v�st/sydv�st',
      'v�st',
      'v�st/nordv�st',
      'nordv�st',
      'nord/nordv�st',
      'nord');
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
      'N'  => 'nord',
      'NE' => 'nordost',
      'E'  => 'ost',
      'SE' => 'sydost',
      'S'  => 'syd',
      'SW' => 'sydv�st',
      'W'  => 'v�st',
      'NW' => 'nordv�st'
      );
    $this->strings['temperature']     = 'Temperaturen var ';
    $this->strings['dew_point']       = ', med en daggpunkt p� ';
    $this->strings['altimeter']       = 'Lufttrycket var ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'Den relativa fuktigheten var ';
    $this->strings['feelslike']       = 'Temperaturen k�nns som ';
    $this->strings['cloud_group_beg'] = 'Det var ';
    $this->strings['cloud_group_end'] = '.';
    $this->strings['cloud_clear']     = 'Himmelen var %sclear%s.';
    $this->strings['cloud_height']    = ' moln p� en h�jd av ';
    $this->strings['cloud_overcast']  = 'himmelen var %sovercast%s fr�n en h�jd av ';
    $this->strings['cloud_vertical_visibility'] = 'den %svertical visibility%s var ';
    $this->strings['cloud_condition'] = array(
	    'SKC' => 'molnfri',
	    'CLR' => 'molnfri',
	    'FEW' => 'n�gra',
	    'SCT' => 'utspridda',
	    'BKN' => 'brutna',
	    'OVC' => 'mulen');
    $this->strings['cumulonimbus']     = ' cumulusmoln';
    $this->strings['towering_cumulus'] = ' tornande cumulusmoln';
    $this->strings['cavok']            = ' inga moln under %s och inga cumulusmoln';
    $this->strings['currently']        = 'F�r tillf�llet ';
    $this->strings['weather']          = 
      array(
	    '-' => 'l�tt ',
	    ' ' => 'moderat ',
	    '+' => 'kraftig ',
        'VC' => 'i n�rheten av',
	    'PR' => 'delvis ',
	    'BC' => 'bankvis ',
	    'MI' => 'l�tt ',
	    'DR' => 'l�gt drivande ',
	    'BL' => 'bl�ser ',
	    'SH' => 'skur ',
	    'TS' => '�skv�derr ',
	    'FZ' => 'frysande ',
	    'DZ' => 'duggregn  ',
	    'RA' => 'regn ',
	    'SN' => 'sn�; ',
	    'SG' => 'sn�korn ',
	    'IC' => 'iskristaller ',
	    'PL' => 'iskorn ',
	    'GR' => 'hagel ',
	    'GS' => 'sm�hagel ',
	    'UP' => 'ok�nt ',
	    'BR' => 'dis ',
	    'FG' => 'dimma ',
	    'FU' => 'r�k ',
	    'VA' => 'vulkanisk aska ',
	    'DU' => 'mycket damm ',
	    'SA' => 'sand ',
	    'HZ' => 'dis ',
	    'PY' => 'regnskur ',
	    'PO' => 'v�lutvecklade damm/sand virvlar ',
	    'SQ' => 'stormbyar ',
	    'FC' => 'tromb/tornado ',
	    'SS' => 'sandstorm/dammstorm ');
    $this->strings['visibility']               = 'Den generella sikten var ';
    $this->strings['visibility_greater_than']  = '�ver ';
    $this->strings['visibility_less_than']     = 'under ';
    $this->strings['runway_upward_tendency']   = ' med en %supward%s tendens';
    $this->strings['runway_downward_tendency'] = ' med en %sdownward%s tendens';
    $this->strings['runway_no_tendency']       = ' med %sno distinct%s tendens';
    $this->strings['runway_between']           = 'mellan ';
    $this->strings['runway_left']              = ' v�nster';
    $this->strings['runway_central']           = ' mitt';
    $this->strings['runway_right']             = ' h�ger';
    $this->strings['runway_visibility']        = 'Sikten var ';
    $this->strings['runway_for_runway']        = ' f�r landningsbanan ';

    /* We run the parent constructor */
    $this->pw_text($weather, $input);
    
  }
}

?>
