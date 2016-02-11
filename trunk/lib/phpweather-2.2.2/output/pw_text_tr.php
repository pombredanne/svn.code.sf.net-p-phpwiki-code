<?php // -*- coding: latin-9 -*-

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce Turkish
 * output.
 *
 * @author   Ferhat Bingol <s021183@student.dtu.dk>
 * @version  pw_text_en.php,v 1.9 2002/10/20 15:57:15 gimpster Exp
 */
class pw_text_tr extends pw_text {

  /**
   * This constructor provides all the strings used.
   *
   * @param  array  This is just passed on to pw_text().
   */
  function pw_text_tr($weather, $input = array()) {
    $this->strings['charset']                  = 'UTF-8';
    $this->strings['no_data']                  = 'Üzgünüz! %s%s%s için veri bulunmuyor.';
    $this->strings['list_sentences_and']       = ' ve ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', ve ';
    $this->strings['location']                 = '%s%s%s için hazırlanan rapor.';
    $this->strings['minutes']                  = ' dakika';
    $this->strings['time_format']              = 'Rapor %s önce saat %s%s%s UTC de hazırlanmış.';
    $this->strings['time_minutes']             = 've %s%s%s dakika';
    $this->strings['time_one_hour']            = '%sone%s saat %s';
    $this->strings['time_several_hours']       = '%s%s%s saat %s';
    $this->strings['time_a_moment']            = 'a moment';
    $this->strings['meters_per_second']        = ' metre / saniye';
    $this->strings['miles_per_hour']           = ' mil / saat';
    $this->strings['meter']                    = ' metre';
    $this->strings['meters']                   = ' metre';
    $this->strings['feet']                     = ' feet';
    $this->strings['kilometers']               = ' kilometre';
    $this->strings['miles']                    = ' mil';
    $this->strings['and']                      = ' ve ';
    $this->strings['plus']                     = ' artı ';
    $this->strings['with']                     = ' ile ';
    $this->strings['wind_blowing']             = 'Rüzgarın esme hızı  ';
    $this->strings['wind_with_gusts']          = ' değerine kadar ulaşan gust ';
    $this->strings['wind_from']                = ' , yönü ';
    $this->strings['wind_variable']            = ' %svariable% yönünde.';
    $this->strings['wind_varying']             = ', %s%s%s (%s%s&deg;%s) ve %s%s%s (%s%s&deg;%s) arasında değişken';
    $this->strings['wind_calm']                = 'Rüzgar %scalm%';
    $this->strings['wind_dir'] = array(
      'kuzey',
      'kuzey/kuzeydoğu',
      'kuzeydoğu',
      'batı/kuzeydoğu',
      'doğu',
      'doğu/güneydoğu',
      'güneydoğu',
      'güney/güneydoğu',
      'güney',
      'güney/güneybatı',
      'güneybatı',
      'batı/güneybatı',
      'batı',
      'batı/kuzeybatı',
      'kuzeybatı',
      'kuzey/kuzeybatı',
      'kuzey');
    $this->strings['wind_dir_short'] = array(
      'K',
      'KKD',
      'KD',
      'DKD',
      'D',
      'DGD',
      'GD',
      'GGD',
      'G',
      'GGB',
      'GB',
      'BGB',
      'B',
      'BKB',
      'KB',
      'KKB',
      'K');
    $this->strings['wind_dir_short_long'] = array(
      'K'  => 'kuzey',
      'KD' => 'kuzeydoğu',
      'D'  => 'doğu',
      'GDE' => 'güneydoğu',
      'G'  => 'güney',
      'GB' => 'güneybatı',
      'B'  => 'batı',
      'KB' => 'kuzeybatı'
      );
    $this->strings['temperature']     = 'Sıcaklık ';
    $this->strings['dew_point']       = ', mevcut dew-point ';
    $this->strings['altimeter']       = 'Atmosferık basınç ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'Relativ humidity ';
    $this->strings['feelslike']       = 'Hissedilen sıcaklık ';
    $this->strings['cloud_group_beg'] = 'Bulunan ';
    $this->strings['cloud_group_end'] = '.';
    $this->strings['cloud_clear']     = 'Gökyüzü %sclear%s.';
    $this->strings['cloud_height']    = ' bulutların yüksekliği ';
    $this->strings['cloud_overcast']  = 'Gökyüzü %sovercast% olduğu yükselik ';
    $this->strings['cloud_vertical_visibility'] = 'görüş mesafesi %svertical visibility% ';
    $this->strings['cloud_condition'] =
      array(
	    'SKC' => 'açık',
	    'CLR' => 'açık',
	    'FEW' => 'az',
	    'SCT' => 'scattered',
	    'BKN' => 'yer yer bulutlu',
	    'OVC' => 'overcast');
    $this->strings['cumulonimbus']     = ' kumulonimbus';
    $this->strings['towering_cumulus'] = ' towering kumulus';
    $this->strings['cavok']            = ' %s altında bulut bulunmuyor ve kumulonimbus bulutları yok';
    $this->strings['currently']        = 'Şu anda ';
    $this->strings['weather']          =
      array(
	    '-' => ' light',
	    ' ' => ' moderate ',
	    '+' => ' heavy ',
	    'VC' => ' bölgede',
	    'PR' => ' kısmi',
	    'BC' => ' patches of',
	    'MI' => ' sığ',
	    'DR' => ' düşük yoğunlukta',
	    'BL' => ' esen',
	    'SH' => ' sağnak',
	    'TS' => ' fırtına',
	    'FZ' => ' dondurucu',
	    'DZ' => ' çiseleyen',
	    'RA' => ' yağmur',
	    'SN' => ' kar',
	    'SG' => ' parça karlı',
	    'IC' => ' buz kristalleri',
	    'PL' => ' buz parçalı',
	    'GR' => ' dolu',
	    'GS' => ' az dolulu',
	    'UP' => ' bilinmeyen',
	    'BR' => ' sis',
	    'FG' => ' sisli',
	    'FU' => ' parçalı sisli',
	    'VA' => ' volkanik dumanlı',
	    'DU' => ' widespread dust',
	    'SA' => ' kum',
	    'HZ' => ' puslu',
	    'PY' => ' sprey',
	    'PO' => ' well-developed dust/sand whirls',
	    'SQ' => ' bora',
	    'FC' => ' (funnel cloud tornado waterspout)',
	    'SS' => ' kur/kil fırtınası');
    $this->strings['visibility'] = 'Görüş mesafesi ';
    $this->strings['visibility_greater_than']  = 'değerinden büyük  ';
    $this->strings['visibility_less_than']     = 'değerinden düşük ';
    $this->strings['visibility_to']            = '  ';
    $this->strings['runway_upward_tendency']   = ' with an %supward%s tendency';
    $this->strings['runway_downward_tendency'] = ' with a %sdownward%s tendency';
    $this->strings['runway_no_tendency']       = ' with %sno distinct%s tendency';
    $this->strings['runway_between']           = 'arasında ';
    $this->strings['runway_left']              = ' sol';
    $this->strings['runway_central']           = ' merkez';
    $this->strings['runway_right']             = ' sağ';
    $this->strings['runway_visibility']        = 'Görüş ';
    $this->strings['runway_for_runway']        = ' uçak pisti için ';

    /* We run the parent constructor */
    $this->pw_text($weather, $input);
  }
}

?>
