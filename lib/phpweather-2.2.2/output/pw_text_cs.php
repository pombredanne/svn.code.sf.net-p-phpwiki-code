<?php // -*- coding: latin-2 -*-

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce Czech
 * output.
 *
 * @author   Václav Říkal <vaclavr@physics.muni.cz>
 * @author   Ondrej Jombík <nepto@platon.sk>
 * @author   Radoslava Fedáková <mortischka@pobox.sk>
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
      $this->strings['no_data']                  = 'Lituji, nejsou dostupné žádné informace pro %s%s%s.';
      $this->strings['list_sentences_and']       = ' a ';
      $this->strings['list_sentences_comma']     = ', ';
      $this->strings['list_sentences_final_and'] = ' a ';
      $this->strings['location']                 = 'Toto je meterologická zpráva letiště %s%s%s.';
      $this->strings['minutes']                  = ' minutami';
      $this->strings['time_format']              = 'Zpráva byla sestavena před %s, v %s%s%s UTC.';
      $this->strings['time_minutes']             = 'a %s%s%s minutami';
      $this->strings['time_one_hour']            = '%sjednou%s hodinou %s';
      $this->strings['time_several_hours']       = '%s%s%s hodinami %s';
      $this->strings['time_a_moment']            = 'právě teď';
      $this->strings['meters_per_second']        = ' metrů za sekundu';
      $this->strings['miles_per_hour']           = ' mil za hodinu';
      $this->strings['meter']                    = ' metrů';
      $this->strings['meters']                   = ' metry';
      $this->strings['feet']                     = ' stop';
      $this->strings['kilometers']               = ' kilometrů';
      $this->strings['miles']                    = ' mil';
      $this->strings['and']                      = ' a ';
      $this->strings['plus']                     = ' plus ';
      $this->strings['with']                     = ' s ';
      $this->strings['wind_blowing']             = 'Rychlost větru byla ';
      $this->strings['wind_with_gusts']          = ' se silnými nárazy od ';
      $this->strings['wind_from']                = ' z ';
      $this->strings['wind_variable']            = ' z %srůzných%s směrů';
      $this->strings['wind_varying']             = ', proměnlivý vítr od %s%s%s (%s%s&deg;%s) a %s%s%s (%s%s&deg;%s)';
      $this->strings['wind_calm']                = 'Bylo %sbezvětří%s';
      $this->strings['wind_dir'] =
        array('severu',
              'severu/severovýchodu',
              'severovýchodu',
              'východu/severovýchodu',
              'východu',
              'východu/jihovýchodu',
              'jihovýchodu',
              'jihu/jihovýchodu',
              'jihu',
              'jihu/jihozápadu',
              'jihozápadu',
              'západu/jihozápadu',
              'západu',
              'západu/severozápadu',
              'severozápadu',
              'severu/severozápadu',
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
              'SV' => 'severovýchod',
              'V'  => 'východ',
              'JV' => 'jihovýchod',
              'J'  => 'jih',
              'JZ' => 'jihozápad',
              'Z'  => 'západ',
              'SZ' => 'severozápad');
      $this->strings['temperature']     = 'Teplota byla ';
      $this->strings['dew_point']       = ' a rosný bod byl ';
      $this->strings['altimeter']       = 'Atmosférický tlak byl ';
      $this->strings['hPa']             = ' hPa';
      $this->strings['inHg']            = ' inHg';
      $this->strings['rel_humidity']    = 'Relativní vlhkost vzduchu byla ';
      $this->strings['feelslike']       = 'Teplota sa zdála být ';
      $this->strings['cloud_group_beg'] = 'Bylo ';
      $this->strings['cloud_group_end'] = '.';
      $this->strings['cloud_clear']     = 'Obloha byla %sjasná%s.';
      $this->strings['cloud_height']    = ' se základnou mraků ve výšce ';
      $this->strings['cloud_overcast']  = ' obloha byla %szatažená%s od výšky ';
      $this->strings['cloud_vertical_visibility'] = '%svertikální viditelnost%s byla ';
      $this->strings['cloud_condition'] =
        array('SKC' => 'jasno',
              'CLR' => 'jasno',
              'FEW' => 'skorojasno', /*'niekoľko',*/
              'SCT' => 'polojasno',
              'BKN' => 'oblačno',
              'OVC' => 'zataženo');
      $this->strings['cumulonimbus']     = ' cumulonimbus';
      $this->strings['towering_cumulus'] = ' kupovitá oblačnost'; /*tyčíci se nahromaděné - to je přece blbost*/
      $this->strings['cavok']            = ' žádná oblačnost pod %s ani žádná kupovitá oblačnost';
      $this->strings['currently']        = 'Aktuální počasí: ';
      $this->strings['weather']          =
        array(/* Intensity */
              '-' => ' slabý ',
              ' ' => ' střední ',
              '+' => ' silný ',
              /* Proximity */
              'VC' => ' v blízkosti',
              /* Descriptor */
              'PR' => ' převážně pokrývající letiště',
              'BC' => ' pásy',
              'MI' => ' přízemní',
              'DR' => ' nízko zvířený',
              'BL' => ' zvířený',
              'SH' => ' přehánky',
              'TS' => ' bouřka',
              'FZ' => ' námrzající',
              /* Precipitation */
              'DZ' => ' mrholení',
              'RA' => ' déšť', /* ' daždivo', */
              'SN' => ' sníh',
              'SG' => ' zrnitý sníh',
              'IC' => ' ledové krystalky',
              'PL' => ' zmrzlý déšť',
              'GR' => ' kroupy',
              'GS' => ' slabé krupobití',
              'UP' => ' neznámé',
              /* Obscuration */
              'BR' => ' kouřmo',
              'FG' => ' mlha',
              'FU' => ' kouř',
              'VA' => ' vulkanický popel',
              'DU' => ' prašno',
              'SA' => ' písek', /* písečné */
              'HZ' => ' zákal',
              'PY' => ' mrholení s malými kapkami',
              /* Other */
              'PO' => ' písečné víry',
              'SQ' => ' húlava',
              'FC' => ' průtrž mračen',
              'SS' => ' prašná/písečná bouře');
      $this->strings['visibility'] = 'Celková viditenost byla ';
      $this->strings['visibility_greater_than']  = 'větší než ';
      $this->strings['visibility_less_than']     = 'menší než ';
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
      $this->strings['runway_visibility']        = 'Viditeľnosť bola ';
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
          $this->strings['meters_per_second'] = ' metrů za sekundu';
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
          $this->strings['miles_per_hour'] = ' míle za hodinu';
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
       * ze severu, z jihu, ze západu, z východu
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
          /* we must remove word 'před', because we wanted string:
           * 'Report bol zostavený prave teraz, ...' */
          $this->strings['time_format'] =
            str_replace(' před ', ' ', $this->strings['time_format']);
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
          $this->strings['weather']['-']  = ' slabého ';
          $this->strings['weather'][' ']  = ' středního ';
          $this->strings['weather']['+']  = ' hustého ';
          $this->strings['weather']['RA'] = ' deště';
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
