<?php // -*- coding: latin-2 -*-

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce 
 * Hungarian output.
 * A magyar sz�veg� id�j�r�sjelent�shez a pw_text innen
 * veszi a sztringeket.
 *
 * @author   Mih�ly Gyulai 
 * @link     http://gyulai.freeyellow.com/  The homepage of the author.
 * @version  pw_text_hu.php,v 1.14 2003/09/16 22:57:11 gimpster Exp
 */
class pw_text_hu extends pw_text {

  /**
   * This constructor provides all the strings used.
   *
   * @param  array   This is just passed on to pw_text().
   *		     Ezt a param�tert �tadjuk pw_text() -nek.
   */
  function pw_text_hu($weather, $input = array()) {
    $this->strings['charset']                  = 'ISO-8859-2';
    $this->strings['no_data']                  = 'Sajnos nincs adat %s%s%s sz�m�ra.';
    $this->strings['list_sentences_and']       = ' �s ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', �s ';
    $this->strings['location']                 = 'Id�j�r�sjelent�s %s%s%s sz�m�ra.';
    $this->strings['minutes']                  = ' ';
    $this->strings['time_format']              = 'A jelent�s %s perccel ezel�tt k�sz�lt, %s%s%s UTC-kor.';
    $this->strings['time_minutes']             = '�s %s%s%s ';
    $this->strings['time_one_hour']            = '%segy%s �r�val %s';
    $this->strings['time_several_hours']       = '%s%s%s �r�val %s';
    $this->strings['time_a_moment']            = 'jelenleg';
    $this->strings['meters_per_second']        = ' m/s';
    $this->strings['miles_per_hour']           = ' m�rf�ld/h';
    $this->strings['meter']                    = ' m';
    $this->strings['meters']                   = ' m';
    $this->strings['feet']                     = ' l�b';
    $this->strings['kilometers']               = ' km';
    $this->strings['miles']                    = ' m�rf�ld';
    $this->strings['and']                      = ' �s ';
    $this->strings['plus']                     = ' �s ';
    $this->strings['with']                     = '';
    $this->strings['wind_blowing']             = 'Sz�lsebess�g: ';
    $this->strings['wind_with_gusts']          = ' sz�ll�k�sek: ';
    $this->strings['wind_from']                = ' ir�nya: ';
    $this->strings['wind_variable']            = ' %sk�l�nb�z�%s ir�nyokb�l.';
    $this->strings['wind_varying']             = ', v�ltozik %s%s%s (%s%s&deg;%s) �s %s%s%s (%s%s&deg;%s) k�z�tt';
    $this->strings['wind_calm']                = 'Sz�l %snem f�jt%s';

  $this->strings['wind_dir'] = array(
  '�szak',
  '�szak/�szakkelet',
  '�szakkelet',
  'Kelet/�szakkelet',
  'Kelet',
  'Kelet/D�lkelet',
  'D�lkelet',
  'D�l/D�lkelet',
  'D�l',
  'D�l/D�lnyugat',
  'D�lnyugat',
  'Nyugat/D�lnyugat',
  'Nyugat',
  'Nyugat/�szaknyugat',
  '�szaknyugat',
  '�szak/�szaknyugat',
  '�szak');

  $this->strings['wind_dir_short'] = array(
  '�',
  '�/�K',
  '�K',
  'K/�K',
  'K',
  'K/DK',
  'DK',
  'D/DK',
  'D',
  'D/DNY',
  'DNY',
  'NY/DNY',
  'NY',
  'NY/�NY',
  '�NY',
  '�/�NY',
  '�'
);

  $this->strings['wind_dir_short_long'] = array(
      '�'  => '�szaki',
      '�K' => '�szakkeleti',
      'K'  => 'keleti',
      'DK' => 'd�lkeleti',
      'D'  => 'd�li',
      'DNY' => 'd�lnyugati',
      'NY'  => 'nyugati',
      '�NY' => '�szaknyugati'
      );

    $this->strings['temperature']     = 'A h�m�rs�klet ';
    $this->strings['dew_point']       = ', a harmatpont ';
    $this->strings['altimeter']       = 'A l�gk�ri nyom�s ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'A relat�v p�ratartalom ';
    $this->strings['feelslike']       = 'A h��rzet ';
    $this->strings['cloud_group_beg'] = 'Az �gbolton';
    $this->strings['cloud_group_end'] = ' magass�gban.';
    $this->strings['cloud_clear']     = 'Az �gbolt %sfelh�tlen%s volt.';
    $this->strings['cloud_height']    = 'felh� ';
    $this->strings['cloud_overcast']  = 'az �gbolt %sborult%s ';
    $this->strings['cloud_vertical_visibility'] = 'a %sf�gg�leges l�that�s�g%s ';
  
  $this->strings['cloud_condition'] = array(
	    'SKC' => ' der�lt',
	    'CLR' => ' tiszta',
	    'FEW' => ' n�h�ny ',
	    'SCT' => ' sz�rv�nyos ',
	    'BKN' => ' szakadozott ',
	    'OVC' => ' borult');
  
    $this->strings['cumulonimbus']     = ' gomoly';
    $this->strings['towering_cumulus'] = ' vihar';
    $this->strings['cavok']            = ' nincsenek felh�k %s magass�gban, �s nincs gomolyfelh�';
    $this->strings['currently']        = 'Jellemz�: ';
  
  $this->strings['weather'] = array(
  '-' => ' k�nny� ',
  ' ' => ' enyhe ',
  '+' => ' er�s ',
  'VC' => ' a k�zelben',
  'PR' => ' r�szleges',
  'BC' => ' szakadozott',
  'MI' => ' felsz�nes',
  'DR' => 'enyhe l�gmozg�s',
  'BL' => 'sz�ll�k�s',
  'SH' => 'z�por',
  'TS' => 'zivatar',
  'FZ' => 'fagy',
  'DZ' => 'szit�l� es�',
  'RA' => 'es�',
  'SN' => 'h�',
  'SG' => 'szemcs�s h�',
  'IC' => 'j�gkrist�ly',
  'PE' => 'j�gdara',
  'GR' => 'j�ges�',
  'GS' => 'apr� j�ges� �s/vagy h�dara',
  'UP' => 'ismeretlen',
  'BR' => 'k�d',
  'FG' => 's�r� k�d',
  'FU' => 'f�st',
  'VA' => 'vulk�ni hamu',
  'DU' => 'kiterjedt por',
  'SA' => 'homok',
  'HZ' => 'p�ra',
  'PY' => 'permet',
  'PO' => 'por/homok �rv�ny',
  'SQ' => 'sz�lroham',
  'FC' => 'felh�t�lcs�r/torn�d�/v�zoszlop',
  'SS' => 'homokvihar/porvihar'
);

    $this->strings['visibility'] = 'A l�that�s�g �ltal�ban ';
    $this->strings['visibility_greater_than']  = 'nagyobb, mint ';
    $this->strings['visibility_less_than']     = 'kisebb, mint ';
    $this->strings['runway_upward_tendency']   = ' %sn�vekv�%s tendenci�val';
    $this->strings['runway_downward_tendency'] = ' %scs�kken�%s tendenci�val';
    $this->strings['runway_no_tendency']       = ' hat�rozott %stendencia n�lk�l%s';
    $this->strings['runway_between']           = 'k�z�tti? ';
    $this->strings['runway_left']              = ' bal';
    $this->strings['runway_central']           = ' k�z�ps�';
    $this->strings['runway_right']             = ' jobb';
    $this->strings['runway_visibility']        = 'A l�that�s�g ';
    $this->strings['runway_for_runway']        = ' a kifut�p�ly�n ';

  /* We run the parent constructor */
  
  $this->pw_text($weather, $input);
    
  }
}

?>
