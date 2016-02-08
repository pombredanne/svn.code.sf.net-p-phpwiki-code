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
    $this->strings['charset']                  = 'ISO-8859-9';
    $this->strings['no_data']                  = '�zg�n�z! %s%s%s i�in veri bulunmuyor.';
    $this->strings['list_sentences_and']       = ' ve ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', ve ';
    $this->strings['location']                 = '%s%s%s i�in haz�rlanan rapor.';
    $this->strings['minutes']                  = ' dakika';
    $this->strings['time_format']              = 'Rapor %s �nce saat %s%s%s UTC de haz�rlanm��.';
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
    $this->strings['plus']                     = ' art� ';
    $this->strings['with']                     = ' ile ';
    $this->strings['wind_blowing']             = 'R�zgar�n esme h�z�  ';
    $this->strings['wind_with_gusts']          = ' de�erine kadar ula�an gust ';
    $this->strings['wind_from']                = ' , y�n� ';
    $this->strings['wind_variable']            = ' %svariable% y�n�nde.';
    $this->strings['wind_varying']             = ', %s%s%s (%s%s&deg;%s) ve %s%s%s (%s%s&deg;%s) aras�nda de�i�ken';
    $this->strings['wind_calm']                = 'R�zgar %scalm%';
    $this->strings['wind_dir'] = array(
      'kuzey',
      'kuzey/kuzeydo�u',
      'kuzeydo�u',
      'bat�/kuzeydo�u',
      'do�u',
      'do�u/g�neydo�u',
      'g�neydo�u',
      'g�ney/g�neydo�u',
      'g�ney',
      'g�ney/g�neybat�',
      'g�neybat�',
      'bat�/g�neybat�',
      'bat�',
      'bat�/kuzeybat�',
      'kuzeybat�',
      'kuzey/kuzeybat�',
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
      'KD' => 'kuzeydo�u',
      'D'  => 'do�u',
      'GDE' => 'g�neydo�u',
      'G'  => 'g�ney',
      'GB' => 'g�neybat�',
      'B'  => 'bat�',
      'KB' => 'kuzeybat�'
      );
    $this->strings['temperature']     = 'S�cakl�k ';
    $this->strings['dew_point']       = ', mevcut dew-point ';
    $this->strings['altimeter']       = 'Atmosfer�k bas�n� ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'Relativ humidity ';
    $this->strings['feelslike']       = 'Hissedilen s�cakl�k ';
    $this->strings['cloud_group_beg'] = 'Bulunan ';
    $this->strings['cloud_group_end'] = '.';
    $this->strings['cloud_clear']     = 'G�ky�z� %sclear%s.';
    $this->strings['cloud_height']    = ' bulutlar�n y�ksekli�i ';
    $this->strings['cloud_overcast']  = 'G�ky�z� %sovercast% oldu�u y�kselik ';
    $this->strings['cloud_vertical_visibility'] = 'g�r�� mesafesi %svertical visibility% ';
    $this->strings['cloud_condition'] =
      array(
	    'SKC' => 'a��k',
	    'CLR' => 'a��k',
	    'FEW' => 'az',
	    'SCT' => 'scattered',
	    'BKN' => 'yer yer bulutlu',
	    'OVC' => 'overcast');
    $this->strings['cumulonimbus']     = ' kumulonimbus';
    $this->strings['towering_cumulus'] = ' towering kumulus';
    $this->strings['cavok']            = ' %s alt�nda bulut bulunmuyor ve kumulonimbus bulutlar� yok';
    $this->strings['currently']        = '�u anda ';
    $this->strings['weather']          = 
      array(
	    '-' => ' light',
	    ' ' => ' moderate ',
	    '+' => ' heavy ',
	    'VC' => ' b�lgede',
	    'PR' => ' k�smi',
	    'BC' => ' patches of',
	    'MI' => ' s��',
	    'DR' => ' d���k yo�unlukta',
	    'BL' => ' esen',
	    'SH' => ' sa�nak',
	    'TS' => ' f�rt�na',
	    'FZ' => ' dondurucu',
	    'DZ' => ' �iseleyen',
	    'RA' => ' ya�mur',
	    'SN' => ' kar',
	    'SG' => ' par�a karl�',
	    'IC' => ' buz kristalleri',
	    'PL' => ' buz par�al�',
	    'GR' => ' dolu',
	    'GS' => ' az dolulu',
	    'UP' => ' bilinmeyen',
	    'BR' => ' sis',
	    'FG' => ' sisli',
	    'FU' => ' par�al� sisli',
	    'VA' => ' volkanik dumanl�',
	    'DU' => ' widespread dust',
	    'SA' => ' kum',
	    'HZ' => ' puslu',
	    'PY' => ' sprey',
	    'PO' => ' well-developed dust/sand whirls',
	    'SQ' => ' bora',
	    'FC' => ' (funnel cloud tornado waterspout)',
	    'SS' => ' kur/kil f�rt�nas�');
    $this->strings['visibility'] = 'G�r�� mesafesi ';
    $this->strings['visibility_greater_than']  = 'de�erinden b�y�k  ';
    $this->strings['visibility_less_than']     = 'de�erinden d���k ';
    $this->strings['visibility_to']            = '  ';
    $this->strings['runway_upward_tendency']   = ' with an %supward%s tendency';
    $this->strings['runway_downward_tendency'] = ' with a %sdownward%s tendency';
    $this->strings['runway_no_tendency']       = ' with %sno distinct%s tendency';
    $this->strings['runway_between']           = 'aras�nda ';
    $this->strings['runway_left']              = ' sol';
    $this->strings['runway_central']           = ' merkez';
    $this->strings['runway_right']             = ' sa�';
    $this->strings['runway_visibility']        = 'G�r�� ';
    $this->strings['runway_for_runway']        = ' u�ak pisti i�in ';

    /* We run the parent constructor */
    $this->pw_text($weather, $input);
  }
}

?>