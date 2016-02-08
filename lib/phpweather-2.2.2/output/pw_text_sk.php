<?php // -*- coding: latin-2 -*-

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce Slovak
 * output.
 *
 * @author   Ondrej Jomb�k <nepto@platon.sk>
 * @author   Radoslava Fed�kov� <mortischka@pobox.sk>
 * @link     http://nepto.sk/	Ondrej's personal homepage
 * @link     http://platon.sk/	Platon Software Development Group
 * @version  pw_text_sk.php,v 1.7 2003/09/16 22:57:11 gimpster Exp
 */

/* ViM 6.0 indentation used */

class pw_text_sk extends pw_text
{
  /**
   * This constructor provides all the strings used.
   *
   * @param  array  This is just passed on to pw_text().
   */
  function pw_text_sk($weather, $input = array())
    {
      $this->strings['charset']                  = 'ISO-8859-2';
      $this->strings['no_data']                  = '�utujem, moment�lne nie s� dostupn� �iadne inform�cie pre %s%s%s.';
      $this->strings['list_sentences_and']       = ' a ';
      $this->strings['list_sentences_comma']     = ', ';
      $this->strings['list_sentences_final_and'] = ' a ';
      $this->strings['location']                 = 'Toto je meterologick� report pre %s%s%s.';
      $this->strings['minutes']                  = ' min�tami';
      $this->strings['time_format']              = 'Report bol zostaven� pred %s, o %s%s%s UTC.';
      $this->strings['time_minutes']             = 'a %s%s%s min�tami';
      $this->strings['time_one_hour']            = '%sjednou%s hodinou %s';
      $this->strings['time_several_hours']       = '%s%s%s hodinami %s';
      $this->strings['time_a_moment']            = 'pr�ve teraz';
      $this->strings['meters_per_second']        = ' metrov za sekundu';
      $this->strings['miles_per_hour']           = ' m� za hodinu';
      $this->strings['meter']                    = ' metrov';
      $this->strings['meters']                   = ' metre';
      $this->strings['feet']                     = ' st�p';
      $this->strings['kilometers']               = ' kilometrov';
      $this->strings['miles']                    = ' m�l';
      $this->strings['and']                      = ' a ';
      $this->strings['plus']                     = ' plus ';
      $this->strings['with']                     = ' s ';
      $this->strings['wind_blowing']             = 'R�chlos� vetra bola ';
      $this->strings['wind_with_gusts']          = ' so siln�m z�vanom od ';
      $this->strings['wind_from']                = ' z ';
      $this->strings['wind_variable']            = ' z %sr�znych%s smerov';
      $this->strings['wind_varying']             = ', meniaca sa medzi smerom z %s%s%s (%s%s&deg;%s) a %s%s%s (%s%s&deg;%s)';
      $this->strings['wind_calm']                = 'Vietor bol %spokojn�%s';
      $this->strings['wind_dir'] =
        array('severu',
              'severu/severov�chodu',
              'severov�chodu',
              'v�chodu/severov�chodu',
              'v�chodu',
              'v�chodu/juhov�chodu',
              'juhov�chodu',
              'juhu/juhov�chodu',
              'juhu',
              'juhu/juhoz�padu',
              'juhoz�padu',
              'z�padu/juhoz�padu',
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
              'JV' => 'juhov�chod',
              'J'  => 'juh',
              'JZ' => 'juhoz�pad',
              'Z'  => 'z�pad',
              'SZ' => 'severoz�pad');
      $this->strings['temperature']     = 'Teplota bola ';
      $this->strings['dew_point']       = ' s rosn�m bodom ';
      $this->strings['altimeter']       = 'Atmosf�rick� tlak bol ';
      $this->strings['hPa']             = ' hPa';
      $this->strings['inHg']            = ' inHg';
      $this->strings['rel_humidity']    = 'Relat�vna vlhkos� vzduchu bola ';
      $this->strings['feelslike']       = 'Teplota sa zdala by� ';
      $this->strings['cloud_group_beg'] = 'Na oblohe boli ';
      $this->strings['cloud_group_end'] = '.';
      $this->strings['cloud_clear']     = 'Obloha bola %sjasn�%s.';
      $this->strings['cloud_height']    = ' oblaky vo v��ke ';
      $this->strings['cloud_overcast']  = ' obloha bola %szamra�en�%s od v��ky ';
      $this->strings['cloud_vertical_visibility'] = '%svertik�lna vidite�nos�%s bola ';
      $this->strings['cloud_condition'] =
        array('SKC' => 'prieh�adn�',
              'CLR' => 'jasn�',
              'FEW' => 'niektor�', /*'nieko�ko',*/
              'SCT' => 'rozpt�len�',
              'BKN' => 'zatiahnut�',
              'OVC' => 'zamra�en�');
      $this->strings['cumulonimbus']     = ' nahromaden� b�rkov�';
      $this->strings['towering_cumulus'] = ' t��iace sa nahromaden�';
      $this->strings['cavok']            = ' �iadne oblaky pod %s a ani �iadne in� nahromaden� oblaky';
      $this->strings['currently']        = 'Aktu�lnym po�as�m bolo ';
      $this->strings['weather']          = 
        array(/* Intensity */
              '-' => ' riedky ',
              ' ' => ' stredn� ',
              '+' => ' hust� ',
              /* Proximity */
              'VC' => ' v pri�ahl�ch oblastiach',
              /* Descriptor */
              'PR' => ' �iasto�n�',
              'BC' => ' are�ly',
              'MI' => ' plytk�',
              'DR' => ' slab� pr�denie vzduchu',
              'BL' => ' veterno',
              'SH' => ' preh�nky',
              'TS' => ' b�rka s bleskami',
              'FZ' => ' mrznutie',
              /* Precipitation */
              'DZ' => ' mrholenie s ve�k�mi kvapkami',
              'RA' => ' d��', /* ' da�divo', */
              'SN' => ' sne�enie',
              'SG' => ' zrnit� sne�enie',
              'IC' => ' �adov� kry�t�liky',
              'PL' => ' �adovec',
              'GR' => ' krupobytie',
              'GS' => ' slab� krupobytie',
              'UP' => ' nezn�me',
              /* Obscuration */
              'BR' => ' hmlov� opar nad vodami',
              'FG' => ' hmlisto',
              'FU' => ' dymno',
              'VA' => ' sope�n� popol',
              'DU' => ' popra�ok',
              'SA' => ' pieso�no', /* pieso�n� */
              'HZ' => ' opar nad pohor�m',
              'PY' => ' mrholenie s mal�mi kvap��kami',
              /* Other */
              'PO' => ' pieso�n� v�ry',
              'SQ' => ' prudk� z�van vetra',
              'FC' => ' prietr� mra�ien',
              'SS' => ' pra�n� prieso�n� b�rka');
      $this->strings['visibility'] = 'Celkov� vidite�nos� bola ';
      $this->strings['visibility_greater_than']  = 'v��ia ako ';
      $this->strings['visibility_less_than']     = 'men�ia ako ';
      $this->strings['visibility_to']            = ' do ';
      /* this is left untranslated, because I have no metar, that use
       * this text -- Nepto [14/07/2002] */
      $this->strings['runway_upward_tendency']   = ' so %sst�paj�cou%s tendenciou';
      $this->strings['runway_downward_tendency'] = ' s %sklesaj�cou%s tendenciou';
      $this->strings['runway_no_tendency']       = ' s %snejednozna�nou%s tendenciou';
      $this->strings['runway_between']           = 'medzi ';
      $this->strings['runway_left']              = ' left';
      $this->strings['runway_central']           = ' central';
      $this->strings['runway_right']             = ' right';
      $this->strings['runway_visibility']        = 'Vidite�nos� bola ';
      $this->strings['runway_for_runway']        = ' pre prist�vaciu dr�hu ��slo ';

      /* We run the parent constructor */
      $this->pw_text($weather, $input);
    }

  function print_pretty_wind($wind)
    {
      extract($wind);
    
      if (! empty($meters_per_second)) {
        switch ($meters_per_second) {
        case 1:
          $this->strings['meters_per_second'] = ' meter za sekundu';
          break;
        case 2:
        case 3:
        case 4:
          $this->strings['meters_per_second'] = ' metre za sekundu';
          break;
        default:
          if ($meters_per_second - floor($meters_per_second) > 0)
            $this->strings['meters_per_second'] = ' metra za sekundu';
          break;
        }
      }
      if (! empty($miles_per_hour)) {
        switch ($miles_per_hour) {
        case 1:
          $this->strings['miles_per_hour'] = ' m�a za hodinu';
          break;
        case 2:
        case 3:
        case 4:
          $this->strings['miles_per_hour'] = ' m�le za hodinu';
          break;
        }
      }
    
      /*
       * Z/ZO grammar handling
       * zo severu, z juhu, zo zapadu, z vychodu
       */
      if (isset($deg)) {
        if ($deg == 'VRB') {
        } else {
          $idx = intval(round($deg / 22.5));
          if ($idx <= 2 || $idx >= 11) {
            $this->strings['wind_from'] =
              str_replace(' z ', ' zo ', $this->strings['wind_from']);
          }
        }
      }
    
      if (isset($var_beg)) {
        $idx = intval(round($var_beg / 22.5));
        if ($idx <= 2 || $idx >= 11) {
          $this->strings['wind_varying'] =
            str_replace(' z ', ' zo ', $this->strings['wind_varying']);
        }
      }
    
      return parent::print_pretty_wind($wind);
    }

  function print_pretty_clouds($clouds) {
	for ($i = 0; $i < count($clouds); $i++) {
		if ($i == 0 && $clouds[$i]['condition'] == 'OVC') {
			if (1) {
				$this->strings['cloud_group_beg'] .= ' oblaky, ';
			} else { // another solution, nicer but incomplete (see TODO below)
				$this->strings['cloud_group_beg'] = '';
				$this->strings['cloud_overcast']  =
					ucfirst(ltrim($this->strings['cloud_overcast']));
			}
		}
		if ($i < count($clouds) - 1) {
			// TODO: Obloha bola zamracena od vysky XX metrov, ... oblaky.
		}
	}
	return parent::print_pretty_clouds($clouds);
  }

  function parse_cloud_group($cloud_group)
    {
      extract($cloud_group);
    
      if (isset($condition)) {
		  if ($condition == 'CAVOK') {
			  $this->strings['cloud_group_beg'] =
				  str_replace(' boli ', ' neboli ', $this->strings['cloud_group_beg']);
		  }
      }
    
      return parent::parse_cloud_group($cloud_group);
    }
  
  function parse_runway_group($runway_group)
    {
      if (empty($runway_group) || !is_array($runway_group)) {
        return;
      }
	  // Supposing, that runway visibility will always greter than 4 metres.
	  // I cannot imagine airport runway with visibility under 5 metres. :-)
      $old_meters = $this->strings['meters'];
      $this->strings['meters'] = ' metrov';
      $ret = parent::parse_runway_group($runway_group);
      $this->strings['meters'] = $old_meters;
      return $ret;
    }
  
  function print_pretty_time($time)
    {
      $minutes_old = round((time() - $time)/60);
      if ($minutes_old > 60) {
        $minutes = $minutes_old % 60;
        if ($minutes == 1) {
          $this->strings['time_minutes']  = 'a %s%s%s min�tou';
        }
      } else {
        if ($minutes_old < 5) {
          /* we must remove word 'pred', because we wanted string:
           * 'Report bol zostaven� prave teraz, ...' */
          $this->strings['time_format'] =
            str_replace(' pred ', ' ', $this->strings['time_format']);
        }
      }
    
      return parent::print_pretty_time($time);
    }
  
  function print_pretty_weather($weather)
  {
	  $ret_str = '';
	  for ($k = 0; $k < count($weather); $k++) {

		  if ($weather[$k]['descriptor'] == 'SH') { // preh�nky ... da��a
			  $k == 0 && $this->strings['currently'] =
				  str_replace(' bolo ', ' boli ', $this->strings['currently']);
			  if ($weather[$k]['precipitation'] == 'RA') {
				  $this->strings['weather']['-']  = ' riedkeho ';
				  $this->strings['weather'][' ']  = ' stredn�ho ';
				  $this->strings['weather']['+']  = ' hust�ho ';
				  $this->strings['weather']['RA'] = ' da��a';
			  }
		  } elseif ($weather[$k]['descriptor'] == 'TS') { // b�rka
			  $k == 0 && $this->strings['currently'] =
				  str_replace(' bolo ', ' bola ', $this->strings['currently']);
			  $this->strings['weather']['-']  = ' riedkym ';
			  $this->strings['weather'][' ']  = ' stredn�m ';
			  $this->strings['weather']['+']  = ' hust�m ';
			  $this->strings['weather']['RA'] = ' da��om';
			  $this->strings['with']          = ' a ';
		  } elseif ($weather[$k]['precipitation'] == 'DZ' // mrholenie
				  || $weather[$k]['precipitation'] == 'SN') { // sne�enie
			  $this->strings['weather']['-']  = ' riedke ';
			  $this->strings['weather'][' ']  = ' stredn� ';
			  $this->strings['weather']['+']  = ' hust� ';
		  } elseif ($weather[$k]['precipitation'] == 'RA' // d��
				  || $weather[$k]['obscuration'] == 'HZ'
				  || $weather[$k]['obscuration'] == 'BR' // hmlov� opar
				  ) { 
			  $k == 0 && $this->strings['currently'] =
				  str_replace(' bolo ', ' bol ', $this->strings['currently']);
		  } elseif ($weather[$k]['obscuration'] == 'FG') { // ... hmlisto
			  $this->strings['weather']['PR'] = ' �iasto�n�';
			  $this->strings['weather']['MI'] = ' plytk�';
		  }

		  // One part of weather parsing
		  $ret_str .= $this->properties['mark_begin']
			  . $this->parse_weather_group($weather[$k])
			  . $this->properties['mark_end'];

		  // Deliminators
		  $k <= count($weather) - 3 && $ret_str .= ',';
		  $k == count($weather) - 2 && $ret_str .= ' a ';
	  }

	  return $this->strings['currently'].$ret_str.'.';
  }
}

/*

Some advanced (problematic?) metars to test:

EFKK 281950Z 18008KT 150V220 9999 -SHRA FEW012 SCT016 BKN020 BKN075 12/12 Q0998
VDPP 281030Z 23008KT 9000 FEW015 FEW025CB SCT300 33/26 Q1008 CB:S/NW/E
LZIB 150730Z 10005MPS 1200 R31/P1500N R22/P1500N -SN BR OVC006 M03/M04 Q1026 NOSIG
201530Z VABB 24012KT 5000 FU FEW018 SCT025 28/22 Q1004 NOSIG
MWCR 2820000Z 12016KT 9999 HZ FEW016 BKN200 32/25 Q1015 NOSIG
CYZT 281900Z 10019G26KT 20SM VCSH FEW025 BKN050 OVC110 13/09 A2956 RMK SC1SC6AC2 SLP009

*/

?>
