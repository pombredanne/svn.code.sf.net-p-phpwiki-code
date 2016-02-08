<?php

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce Finnish
 * output.
 *
 * @author   Kari Salovaara <kari.salovaara@pp1.inet.fi>
 * @link     http://www.ecosyd.net/  My homepage.
 * @version  pw_text_fi.php,v 1.3 2004/01/02 02:50:45 gimpster Exp
 */
class pw_text_fi extends pw_text {

  /**
   * This constructor provides all the strings used.
   *
   * @param  array  This is just passed on to pw_text().
   */
  function pw_text_fi($weather, $input = array()) {
    $this->strings['charset']                  = 'ISO-8859-1';
    $this->strings['no_data']                  = 'Valitan! Ei tietoja saatavilla %s%s%s s��asemalle.';
    $this->strings['list_sentences_and']       = ' ja ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', ja ';
    $this->strings['location']                 = 'T�m� on  raportti %s%s%s s��asemalta.';
    $this->strings['minutes']                  = ' minuttteja';
    $this->strings['time_format']              = 'T�m� raportti tehtiin %s sitten, kello %s%s%s UTC.';
    $this->strings['time_minutes']             = 'ja %s%s%s minuuttia';
    $this->strings['time_one_hour']            = '%s1%s tunti %s';
    $this->strings['time_several_hours']       = '%s%s%s tuntia %s';
    $this->strings['time_a_moment']            = 'hetki';
    $this->strings['meters_per_second']        = ' metri�/sekunnissa';
    $this->strings['miles_per_hour']           = ' mailia/tunnissa';
    $this->strings['meter']                    = ' metri�';
    $this->strings['meters']                   = ' metri�';
    $this->strings['feet']                     = ' jalkaa';
    $this->strings['kilometers']               = ' kilometri�';
    $this->strings['miles']                    = ' mailia';
    $this->strings['and']                      = ' ja ';
    $this->strings['plus']                     = ' enemm�n ';
    $this->strings['with']                     = ' with ';
    $this->strings['wind_blowing']             = 'Tuulen voimakkuus ';
    $this->strings['wind_with_gusts']          = ' puskittain aina ';
    $this->strings['wind_from']                = ' alkaen ';
    $this->strings['wind_variable']            = ' muuttuen %ssuuntien%s v�lill�.';
    $this->strings['wind_varying']             = ', vaihdellen %s%s%s (%s%s&deg;%s) ja %s%s%s (%s%s&deg;%s) v�lill�';
    $this->strings['wind_calm']                = 'Tuuli oli %styyni%s';
    $this->strings['wind_dir'] = array(
      'pohjoinen',
      'pohjoinen/koillinen',
      'koillinen',
      'it�/koillinen',
      'it�',
      'it�/kaakko',
      'kaakko',
      'etel�/kaakko',
      'etel�',
      'etel�/lounas',
      'lounas',
      'l�nsi/lounas',
      'l�nsi',
      'l�nsi/luode',
      'luode',
      'pohjoinen/luode',
      'pohjoinen');
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
      'N'  => 'pohjoinen',
      'NE' => 'koillinen',
      'E'  => 'it�',
      'SE' => 'kaakko',
      'S'  => 'etel�',
      'SW' => 'lounas',
      'W'  => 'l�nsi',
      'NW' => 'luode'
      );
    $this->strings['temperature']     = 'L�mp�tila oli ';
    $this->strings['dew_point']       = ', kastepisteen ollessa ';
    $this->strings['altimeter']       = 'Ilmanpaine oli ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'Suhteellinen kosteus oli ';
    $this->strings['feelslike']       = 'Jolloin l�mp�tila tuntuu kuin ';
    $this->strings['cloud_group_beg'] = 'Havainnointihetkell� ';
    $this->strings['cloud_group_end'] = '.';
    $this->strings['cloud_clear']     = 'Taivas oli %sselke�%s.';
    $this->strings['cloud_height']    = ' pilvikorkeuden ollessa ';
    $this->strings['cloud_overcast']  = 'Taivas oli pilviverhossa %skokonaan%s alkaen korkeudesta ';
    $this->strings['cloud_vertical_visibility'] = ' %s pystysuuntainen n�kyvyys oli %s ';
    $this->strings['cloud_condition'] =
      array(
	    'SKC' => 'selke�',
	    'CLR' => 'selke�',
	    'FEW' => 'muutamia pilvi�, ',
	    'SCT' => 'hajanaisia pilvi�, ',
	    'BKN' => 'rikkonainen pilvikerros, ',
	    'OVC' => 't�ysin pilvinen, ');
    $this->strings['cumulonimbus']     = ' cumulonimbus';
    $this->strings['towering_cumulus'] = ' korkeaksi pullistunut cumulus';
    $this->strings['cavok']            = ' ei ollut pilvi� alle %s eik� cumulonimbus pilvi�';
    $this->strings['currently']        = 'Parhaillaan ';
    $this->strings['weather']          = 
      array(
	    '-' => ' kevytt�',
	    ' ' => ' kohtalaista ',
	    '+' => ' rankkaa ',
	    'VC' => ' l�heisyydess�',
	    'PR' => ' osittain',
	    'BC' => ' paikoittain',
	    'MI' => ' matalalla',
	    'DR' => ' matalalla ajelehtivia',
	    'BL' => ' tuulee',
	    'SH' => ' kuurottaista',
	    'TS' => ' ukkosmyrsky',
	    'FZ' => ' j��t�v��',
	    'DZ' => ' tihkusade',
	    'RA' => ' sadetta',
	    'SN' => ' lunta',
	    'SG' => ' snow grains',
	    'IC' => ' j��kiteit�',
	    'PL' => ' j�� pellettej�',
	    'GR' => ' j��rakeita',
	    'GS' => ' heikkoa raetta',
	    'UP' => ' tuntematon',
	    'BR' => ' utua',
	    'FG' => ' sumua',
	    'FU' => ' savua',
	    'VA' => ' vulkaanista tuhkaa',
	    'DU' => ' runsaasti p�ly�',
	    'SA' => ' hiekkaa',
	    'HZ' => ' auerta',
	    'PY' => ' tihkusade',
	    'PO' => ' kehittyneit� p�ly/hiekka py�rteit�',
	    'SQ' => ' ukkospuuskia',
	    'FC' => ' trombeja/tornado/vesipy�rre',
	    'SS' => ' hiekkamyrsky/p�lymyrsky');
    $this->strings['visibility'] = 'N�kyvyys oli ';
    $this->strings['visibility_greater_than']  = 'suurempi kuin ';
    $this->strings['visibility_less_than']     = 'v�hemm�n kuin ';
    $this->strings['visibility_to']            = ' ylt�en ';
    $this->strings['runway_upward_tendency']   = ' jossa %syl�sp�in%s suuntaus';
    $this->strings['runway_downward_tendency'] = ' jossa a %salasp�in%s suuntaus';
    $this->strings['runway_no_tendency']       = ' jossa %sei m��ritelty�%s suuntausta';
    $this->strings['runway_between']           = 'v�lill� ';
    $this->strings['runway_left']              = ' vasen';
    $this->strings['runway_central']           = ' keskell�';
    $this->strings['runway_right']             = ' oikea';
    $this->strings['runway_visibility']        = 'N�kyvyys oli ';
    $this->strings['runway_for_runway']        = ' kiitotiell� ';

    /* We run the parent constructor */
    $this->pw_text($weather, $input);
  }
}

?>