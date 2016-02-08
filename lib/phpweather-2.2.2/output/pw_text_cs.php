<?php // -*- coding: latin-2 -*-

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce Czech
 * output.
 *
 * @author   V�clav ��kal <vaclavr@physics.muni.cz>
 * @author   Ondrej Jomb�k <nepto@platon.sk>
 * @author   Radoslava Fed�kov� <mortischka@pobox.sk>
 * @link     http://vac.ath.cx/
 * @link     http://nepto.sk/	Ondrej's personal homepage
 * @link     http://platon.sk/	Platon Software Development Group
 *
 * @version  pw_text_cs.php,v 1.0 2002/09/22 21:13:40 gimpster Exp
 */

/* ViM 6.0 indentation used */

class pw_text_cs extends pw_text
{
  /**
   * This constructor provides all the strings used.
   *
   * @param  array  This is just passed on to pw_text().
   */
  function pw_text_cs($weather, $input = array())
    {
      $this->strings['charset']                  = 'ISO-8859-2';
      $this->strings['no_data']                  = 'Lituji, nejsou dostupn� ��dn� informace pro %s%s%s.';
      $this->strings['list_sentences_and']       = ' a ';
      $this->strings['list_sentences_comma']     = ', ';
      $this->strings['list_sentences_final_and'] = ' a ';
      $this->strings['location']                 = 'Toto je meterologick� zpr�va leti�t� %s%s%s.';
      $this->strings['minutes']                  = ' minutami';
      $this->strings['time_format']              = 'Zpr�va byla sestavena p�ed %s, v %s%s%s UTC.';
      $this->strings['time_minutes']             = 'a %s%s%s minutami';
      $this->strings['time_one_hour']            = '%sjednou%s hodinou %s';
      $this->strings['time_several_hours']       = '%s%s%s hodinami %s';
      $this->strings['time_a_moment']            = 'pr�v� te�';
      $this->strings['meters_per_second']        = ' metr� za sekundu';
      $this->strings['miles_per_hour']           = ' mil za hodinu';
      $this->strings['meter']                    = ' metr�';
      $this->strings['meters']                   = ' metry';
      $this->strings['feet']                     = ' stop';
      $this->strings['kilometers']               = ' kilometr�';
      $this->strings['miles']                    = ' mil';
      $this->strings['and']                      = ' a ';
      $this->strings['plus']                     = ' plus ';
      $this->strings['with']                     = ' s ';
      $this->strings['wind_blowing']             = 'Rychlost v�tru byla ';
      $this->strings['wind_with_gusts']          = ' se siln�mi n�razy od ';
      $this->strings['wind_from']                = ' z ';
      $this->strings['wind_variable']            = ' z %sr�zn�ch%s sm�r�';
      $this->strings['wind_varying']             = ', prom�nliv� v�tr od %s%s%s (%s%s&deg;%s) a %s%s%s (%s%s&deg;%s)';
      $this->strings['wind_calm']                = 'Bylo %sbezv�t��%s';
      $this->strings['wind_dir'] =
        array('severu',
              'severu/severov�chodu',
              'severov�chodu',
              'v�chodu/severov�chodu',
              'v�chodu',
              'v�chodu/jihov�chodu',
              'jihov�chodu',
              'jihu/jihov�chodu',
              'jihu',
              'jihu/jihoz�padu',
              'jihoz�padu',
              'z�padu/jihoz�padu',
              'z�padu',
              'z�padu/severoz�padu',
              'severoz�padu',
              'severu/severoz�padu',
              'severu');
      $this->strings['wind_dir_short'] =
        array('S',
              'SSV',
              'SV',
              'VSV',
              'V',
              'VJV',
              'JV',
              'JJV',
              'J',
              'JJZ',
              'JZ',
              'ZJZ',
              'Z',
              'ZSZ',
              'SZ',
              'SSZ',
              'S');
      $this->strings['wind_dir_short_long'] =
        array('S'  => 'sever',
              'SV' => 'severov�chod',
              'V'  => 'v�chod',
              'JV' => 'jihov�chod',
              'J'  => 'jih',
              'JZ' => 'jihoz�pad',
              'Z'  => 'z�pad',
              'SZ' => 'severoz�pad');
      $this->strings['temperature']     = 'Teplota byla ';
      $this->strings['dew_point']       = ' a rosn� bod byl ';
      $this->strings['altimeter']       = 'Atmosf�rick� tlak byl ';
      $this->strings['hPa']             = ' hPa';
      $this->strings['inHg']            = ' inHg';
      $this->strings['rel_humidity']    = 'Relativn� vlhkost vzduchu byla ';
      $this->strings['feelslike']       = 'Teplota sa zd�la b�t ';
      $this->strings['cloud_group_beg'] = 'Bylo ';
      $this->strings['cloud_group_end'] = '.';
      $this->strings['cloud_clear']     = 'Obloha byla %sjasn�%s.';
      $this->strings['cloud_height']    = ' se z�kladnou mrak� ve v��ce ';
      $this->strings['cloud_overcast']  = ' obloha byla %szata�en�%s od v��ky ';
      $this->strings['cloud_vertical_visibility'] = '%svertik�ln� viditelnost%s byla ';
      $this->strings['cloud_condition'] =
        array('SKC' => 'jasno',
              'CLR' => 'jasno',
              'FEW' => 'skorojasno', /*'nieko�ko',*/
              'SCT' => 'polojasno',
              'BKN' => 'obla�no',
              'OVC' => 'zata�eno');
      $this->strings['cumulonimbus']     = ' cumulonimbus';
      $this->strings['towering_cumulus'] = ' kupovit� obla�nost'; /*ty��ci se nahromad�n� - to je p�ece blbost*/
      $this->strings['cavok']            = ' ��dn� obla�nost pod %s ani ��dn� kupovit� obla�nost';
      $this->strings['currently']        = 'Aktu�ln� po�as�: ';
      $this->strings['weather']          = 
        array(/* Intensity */
              '-' => ' slab� ',
              ' ' => ' st�edn� ',
              '+' => ' siln� ',
              /* Proximity */
              'VC' => ' v bl�zkosti',
              /* Descriptor */
              'PR' => ' p�ev�n� pokr�vaj�c� leti�t�',
              'BC' => ' p�sy',
              'MI' => ' p��zemn�',
              'DR' => ' n�zko zv��en�',
              'BL' => ' zv��en�',
              'SH' => ' p�eh�nky',
              'TS' => ' bou�ka',
              'FZ' => ' n�mrzaj�c�',
              /* Precipitation */
              'DZ' => ' mrholen�',
              'RA' => ' d鹻', /* ' da�divo', */
              'SN' => ' sn�h',
              'SG' => ' zrnit� sn�h',
              'IC' => ' ledov� krystalky',
              'PL' => ' zmrzl� d鹻',
              'GR' => ' kroupy',
              'GS' => ' slab� krupobit�',
              'UP' => ' nezn�m�',
              /* Obscuration */
              'BR' => ' kou�mo',
              'FG' => ' mlha',
              'FU' => ' kou�',
              'VA' => ' vulkanick� popel',
              'DU' => ' pra�no',
              'SA' => ' p�sek', /* p�se�n� */
              'HZ' => ' z�kal',
              'PY' => ' mrholen� s mal�mi kapkami',
              /* Other */
              'PO' => ' p�se�n� v�ry',
              'SQ' => ' h�lava',
              'FC' => ' pr�tr� mra�en',
              'SS' => ' pra�n�/p�se�n� bou�e');
      $this->strings['visibility'] = 'Celkov� viditenost byla ';
      $this->strings['visibility_greater_than']  = 'v�t�� ne� ';
      $this->strings['visibility_less_than']     = 'men�� ne� ';
      $this->strings['visibility_to']            = ' do ';
      /* this is left untranslated, because I have no metar, that use
       * this text -- Nepto [14/07/2002] */
      $this->strings['runway_upward_tendency']   = ' with an %supward%s tendency';
      $this->strings['runway_downward_tendency'] = ' with a %sdownward%s tendency';
      $this->strings['runway_no_tendency']       = ' with %sno distinct%s tendency';
      $this->strings['runway_between']           = 'between ';
      $this->strings['runway_left']              = ' left';
      $this->strings['runway_central']           = ' central';
      $this->strings['runway_right']             = ' right';
      $this->strings['runway_visibility']        = 'Vidite�nos� bola ';
      $this->strings['runway_for_runway']        = ' for runway ';

      /* We run the parent constructor */
      $this->pw_text($weather, $input);
    }

  function print_pretty_wind($wind)
    {
      extract($wind);
    
      if (! empty($meters_per_second)) {
        switch ($meters_per_second) {
        case 1:
          $this->strings['meters_per_second'] = ' metr za sekundu';
          break;
        case 2:
        case 3:
        case 4:
          $this->strings['meters_per_second'] = ' metr� za sekundu';
          break;
        default:
          if ($meters_per_second - floor($meters_per_second) > 0)
            $this->strings['meters_per_second'] = ' metru za sekundu';
          break;
        }
      }
      if (! empty($miles_per_hour)) {
        switch ($miles_per_hour) {
        case 1:
          $this->strings['miles_per_hour'] = ' m�le za hodinu';
          break;
        case 2:
        case 3:
        case 4:
          $this->strings['miles_per_hour'] = ' mil za hodinu';
          break;
        }
      }
    
      /*
       * Z/ZO grammar handling
       * ze severu, z jihu, ze z�padu, z v�chodu
       */
      if (isset($deg)) {
        if ($deg == 'VRB') {
        } else {
          $idx = intval(round($deg / 22.5));
          if ($idx <= 2 || $idx >= 11) {
            $this->strings['wind_from'] =
              str_replace(' z ', ' ze ', $this->strings['wind_from']);
          }
        }
      }
    
      if (isset($var_beg)) {
        $idx = intval(round($var_beg / 22.5));
        if ($idx <= 2 || $idx >= 11) {
          $this->strings['wind_varying'] =
            str_replace(' z ', ' ze ', $this->strings['wind_varying']);
        }
      }
    
      return parent::print_pretty_wind($wind);
    }

  function parse_cloud_group($cloud_group)
    {
      extract($cloud_group);
    
      if (isset($condition) && $condition == 'CAVOK') {
        $this->strings['cloud_group_beg'] =
          str_replace('Bylo ', 'Nebyla ', $this->strings['cloud_group_beg']);
      }
    
      return parent::parse_cloud_group($cloud_group);
    }
  
  function print_pretty_time($time)
    {
      $minutes_old = round((time() - $time)/60);
      if ($minutes_old > 60) {
        $minutes = $minutes_old % 60;
        if ($minutes == 1) {
          $this->strings['time_minutes']  = 'a %s%s%s minutou';
        }
      } else {
        if ($minutes_old < 5) {
          /* we must remove word 'p�ed', because we wanted string:
           * 'Report bol zostaven� prave teraz, ...' */
          $this->strings['time_format'] =
            str_replace(' p�ed ', ' ', $this->strings['time_format']);
        }
      }
    
      return parent::print_pretty_time($time);
    }
  
  function print_pretty_weather($weather)
    {
      if ($weather[0]['descriptor'] == 'SH') {
        $this->strings['currently'] = str_replace(' bylo ', ' byly ',
                                                  $this->strings['currently']);
        if ($weather[0]['precipitation'] == 'RA') {
          $this->strings['weather']['-']  = ' slab�ho ';
          $this->strings['weather'][' ']  = ' st�edn�ho ';
          $this->strings['weather']['+']  = ' hust�ho ';
          $this->strings['weather']['RA'] = ' de�t�';
        }
      } elseif ($weather[0]['precipitation'] == 'RA'
                || $weather[0]['obscuration'] == 'HZ') {
        $this->strings['currently'] = str_replace(' bylo ', ' byl ',
                                                  $this->strings['currently']);
      }

      return parent::print_pretty_weather($weather);
    }
}

?>
