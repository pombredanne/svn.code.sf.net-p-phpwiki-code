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
    $this->strings['charset']                  = 'UTF-8';
    $this->strings['no_data']                  = 'Valitan! Ei tietoja saatavilla %s%s%s sääasemalle.';
    $this->strings['list_sentences_and']       = ' ja ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', ja ';
    $this->strings['location']                 = 'Tämä on  raportti %s%s%s sääasemalta.';
    $this->strings['minutes']                  = ' minuttteja';
    $this->strings['time_format']              = 'Tämä raportti tehtiin %s sitten, kello %s%s%s UTC.';
    $this->strings['time_minutes']             = 'ja %s%s%s minuuttia';
    $this->strings['time_one_hour']            = '%s1%s tunti %s';
    $this->strings['time_several_hours']       = '%s%s%s tuntia %s';
    $this->strings['time_a_moment']            = 'hetki';
    $this->strings['meters_per_second']        = ' metriä/sekunnissa';
    $this->strings['miles_per_hour']           = ' mailia/tunnissa';
    $this->strings['meter']                    = ' metriä';
    $this->strings['meters']                   = ' metriä';
    $this->strings['feet']                     = ' jalkaa';
    $this->strings['kilometers']               = ' kilometriä';
    $this->strings['miles']                    = ' mailia';
    $this->strings['and']                      = ' ja ';
    $this->strings['plus']                     = ' enemmän ';
    $this->strings['with']                     = ' with ';
    $this->strings['wind_blowing']             = 'Tuulen voimakkuus ';
    $this->strings['wind_with_gusts']          = ' puskittain aina ';
    $this->strings['wind_from']                = ' alkaen ';
    $this->strings['wind_variable']            = ' muuttuen %ssuuntien%s välillä.';
    $this->strings['wind_varying']             = ', vaihdellen %s%s%s (%s%s&deg;%s) ja %s%s%s (%s%s&deg;%s) välillä';
    $this->strings['wind_calm']                = 'Tuuli oli %styyni%s';
    $this->strings['wind_dir'] = array(
      'pohjoinen',
      'pohjoinen/koillinen',
      'koillinen',
      'itä/koillinen',
      'itä',
      'itä/kaakko',
      'kaakko',
      'etelä/kaakko',
      'etelä',
      'etelä/lounas',
      'lounas',
      'länsi/lounas',
      'länsi',
      'länsi/luode',
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
      'E'  => 'itä',
      'SE' => 'kaakko',
      'S'  => 'etelä',
      'SW' => 'lounas',
      'W'  => 'länsi',
      'NW' => 'luode'
      );
    $this->strings['temperature']     = 'Lämpötila oli ';
    $this->strings['dew_point']       = ', kastepisteen ollessa ';
    $this->strings['altimeter']       = 'Ilmanpaine oli ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'Suhteellinen kosteus oli ';
    $this->strings['feelslike']       = 'Jolloin lämpötila tuntuu kuin ';
    $this->strings['cloud_group_beg'] = 'Havainnointihetkellä ';
    $this->strings['cloud_group_end'] = '.';
    $this->strings['cloud_clear']     = 'Taivas oli %sselkeä%s.';
    $this->strings['cloud_height']    = ' pilvikorkeuden ollessa ';
    $this->strings['cloud_overcast']  = 'Taivas oli pilviverhossa %skokonaan%s alkaen korkeudesta ';
    $this->strings['cloud_vertical_visibility'] = ' %s pystysuuntainen näkyvyys oli %s ';
    $this->strings['cloud_condition'] =
      array(
	    'SKC' => 'selkeä',
	    'CLR' => 'selkeä',
	    'FEW' => 'muutamia pilviä, ',
	    'SCT' => 'hajanaisia pilviä, ',
	    'BKN' => 'rikkonainen pilvikerros, ',
	    'OVC' => 'täysin pilvinen, ');
    $this->strings['cumulonimbus']     = ' cumulonimbus';
    $this->strings['towering_cumulus'] = ' korkeaksi pullistunut cumulus';
    $this->strings['cavok']            = ' ei ollut pilviä alle %s eikä cumulonimbus pilviä';
    $this->strings['currently']        = 'Parhaillaan ';
    $this->strings['weather']          =
      array(
	    '-' => ' kevyttä',
	    ' ' => ' kohtalaista ',
	    '+' => ' rankkaa ',
	    'VC' => ' läheisyydessä',
	    'PR' => ' osittain',
	    'BC' => ' paikoittain',
	    'MI' => ' matalalla',
	    'DR' => ' matalalla ajelehtivia',
	    'BL' => ' tuulee',
	    'SH' => ' kuurottaista',
	    'TS' => ' ukkosmyrsky',
	    'FZ' => ' jäätävää',
	    'DZ' => ' tihkusade',
	    'RA' => ' sadetta',
	    'SN' => ' lunta',
	    'SG' => ' snow grains',
	    'IC' => ' jääkiteitä',
	    'PL' => ' jää pellettejä',
	    'GR' => ' jäärakeita',
	    'GS' => ' heikkoa raetta',
	    'UP' => ' tuntematon',
	    'BR' => ' utua',
	    'FG' => ' sumua',
	    'FU' => ' savua',
	    'VA' => ' vulkaanista tuhkaa',
	    'DU' => ' runsaasti pölyä',
	    'SA' => ' hiekkaa',
	    'HZ' => ' auerta',
	    'PY' => ' tihkusade',
	    'PO' => ' kehittyneitä pöly/hiekka pyörteitä',
	    'SQ' => ' ukkospuuskia',
	    'FC' => ' trombeja/tornado/vesipyörre',
	    'SS' => ' hiekkamyrsky/pölymyrsky');
    $this->strings['visibility'] = 'Näkyvyys oli ';
    $this->strings['visibility_greater_than']  = 'suurempi kuin ';
    $this->strings['visibility_less_than']     = 'vähemmän kuin ';
    $this->strings['visibility_to']            = ' yltäen ';
    $this->strings['runway_upward_tendency']   = ' jossa %sylöspäin%s suuntaus';
    $this->strings['runway_downward_tendency'] = ' jossa a %salaspäin%s suuntaus';
    $this->strings['runway_no_tendency']       = ' jossa %sei määriteltyä%s suuntausta';
    $this->strings['runway_between']           = 'välillä ';
    $this->strings['runway_left']              = ' vasen';
    $this->strings['runway_central']           = ' keskellä';
    $this->strings['runway_right']             = ' oikea';
    $this->strings['runway_visibility']        = 'Näkyvyys oli ';
    $this->strings['runway_for_runway']        = ' kiitotiellä ';

    /* We run the parent constructor */
    $this->pw_text($weather, $input);
  }
}

?>
