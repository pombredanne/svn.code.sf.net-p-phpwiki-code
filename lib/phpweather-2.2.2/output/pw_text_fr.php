<?php

require_once(PHPWEATHER_BASE_DIR . '/output/pw_text.php');

/**
 * Provides all the strings needed by pw_text to produce French
 * output.
 * Contient toutes les chaines n�cessaires � pw_text
 * pour produire un texte en Fran�ais.
 *
 * @author   Guillaume Petit <gpetit@fr.st>
 * @link     http://gpetit.fr.st  My homepage.
 * @version  pw_text_fr.php,v 1.1 2002/10/23 16:53:40 gimpster Exp
 */
class pw_text_fr extends pw_text {

  /**
   * This constructor provides all the strings used.
   *
   * @param  array  This is just passed on to pw_text().
   */
  function pw_text_fr($weather, $input = array()) {
    $this->strings['charset']                  = 'ISO-8859-1';
    $this->strings['no_data']                  = 'D�sol�! Pas d\'infos disponibles pour %s%s%s.';
    $this->strings['list_sentences_and']       = ' et ';
    $this->strings['list_sentences_comma']     = ', ';
    $this->strings['list_sentences_final_and'] = ', et ';
    $this->strings['location']                 = 'Voici le bulletin pour %s%s%s.';
    $this->strings['minutes']                  = ' minutes';
    $this->strings['time_format']              = 'Le bulletin a �t� fait il y a %s , � %s%s%s UTC.';
    $this->strings['time_minutes']             = 'et %s%s%s minutes';
    $this->strings['time_one_hour']            = '%sune%s heure %s';
    $this->strings['time_several_hours']       = '%s%s%s heures %s';
    $this->strings['time_a_moment']            = 'un moment';
    $this->strings['meters_per_second']        = ' m�tres par seconde';
    $this->strings['miles_per_hour']           = ' miles par heure';
    $this->strings['meter']                    = ' m�tres';
    $this->strings['meters']                   = ' m�tres';
    $this->strings['feet']                     = ' pieds';
    $this->strings['kilometers']               = ' kilom�tres';
    $this->strings['miles']                    = ' miles';
    $this->strings['and']                      = ' et ';
    $this->strings['plus']                     = ' plus ';
    $this->strings['with']                     = ' avec ';
    $this->strings['wind_blowing']             = 'Le vent soufflait � la vitesse de ';
    $this->strings['wind_with_gusts']          = ' avec des rafales jusq\'� ';
    $this->strings['wind_from']                = ' de ';
    $this->strings['wind_variable']            = ' de direction %svariable%.';
    $this->strings['wind_varying']             = ', variant entre %s%s%s (%s%s&deg;%s) et %s%s%s (%s%s&deg;%s)';
    $this->strings['wind_calm']                = 'Le vent �tait %scalme%s';
    $this->strings['wind_dir'] = array(
	'Nord',
	'Nord/Nord-est',
	'Nord-est',
	'Est/Nord-est',
	'Est',
	'Est/Sud-est',
	'Sud-est',
	'Sud/Sud-est',
	'Sud',
	'Sud/Sud-ouest',
	'Sud-ouest',
	'Ouest/Sud-ouest',
	'Ouest',
	'Ouest/Nord-ouest',
	'Nord-ouest',
	'Nord/Nord-ouest',
	'Nord');
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
      'SSO',
      'SO',
      'OSO',
      'O',
      'ONO',
      'NO',
      'NNO',
      'N');
    $this->strings['wind_dir_short_long'] = array(
      'N'  => 'nord',
      'NE' => 'nord-est',
      'E'  => 'est',
      'SE' => 'sud-est',
      'S'  => 'sud',
      'SO' => 'sud-ouest',
      'O'  => 'ouest',
      'NO' => 'nord-ouest'
      );
    $this->strings['temperature']     = 'La temp�rature �tait de ';
    $this->strings['dew_point']       = ', avec un point de ros�e � ';
    $this->strings['altimeter']       = 'La pression atmosph�rique �tait de ';
    $this->strings['hPa']             = ' hPa';
    $this->strings['inHg']            = ' inHg';
    $this->strings['rel_humidity']    = 'L\'humidit� relative �tait de ';
    $this->strings['feelslike']       = 'La temp�rature ressentie �tait de ';
    $this->strings['cloud_group_beg'] = 'Il y avait ';
    $this->strings['cloud_group_end'] = '.';
    $this->strings['cloud_clear']     = 'Le ciel �tait %sclear%s.';
    $this->strings['cloud_height']    = ' de n�bulosit� � une hauteur de ';
    $this->strings['cloud_overcast']  = 'Le ciel �tait %snuageux%s � partir d\'une hauteur de ';
    $this->strings['cloud_vertical_visibility'] = 'La %svisibilit� verticale%s �tait de ';
    $this->strings['cloud_condition'] =
      array(
	    'SKC' => 'clair',
	    'CLR' => 'clair',
	    'FEW' => '1 � 2/8�',
	    'SCT' => '3 � 4/8�',
	    'BKN' => '5 � 7/8�',
	    'OVC' => '8/8�');
    $this->strings['cumulonimbus']     = ' cumulonimbus';
    $this->strings['towering_cumulus'] = ' cumulus congestus';
    $this->strings['cavok']            = ' pas de nuages en-dessous de %s et pas de cumulonimbus';
    $this->strings['currently']        = 'Actuellement ';
    $this->strings['weather']          =
      array(
	    '-' => ' l�ger/leg�re',
	    ' ' => ' moder�(e) ',
	    '+' => ' fort(e) ',
	    'VC' => ' � proximit�',
	    'PR' => ' partiel(le)',
	    'BC' => ' bancs',
	    'MI' => ' peu dense',
	    'DR' => ' d�rivant',
	    'BL' => ' se d�veloppant',
	    'SH' => ' averses de',
	    'TS' => ' orage',
	    'FZ' => ' givrant',
	    'DZ' => ' bruine',
	    'RA' => ' pluie',
	    'SN' => ' neige',
	    'SG' => ' gr�sil',
	    'IC' => ' cristaux de glace',
	    'PL' => ' granules de glace',
	    'GR' => ' gr�le',
	    'GS' => ' gr�le fine',
	    'UP' => ' inconnu',
	    'BR' => ' brume',
	    'FG' => ' bruillard',
	    'FU' => ' fum�e',
	    'VA' => ' cendre volcanique',
	    'DU' => ' poussi�re r�pandue',
	    'SA' => ' sable',
	    'HZ' => ' brume',
	    'PY' => ' gouttes',
	    'PO' => ' tourbillons de sable',
	    'SQ' => ' grains',
	    'FC' => ' tornade',
	    'SS' => ' temp�te de sable/poussi�re');
    $this->strings['visibility'] = 'La visibilit� globale �tait de ';
    $this->strings['visibility_greater_than']  = 'sup�rieure � ';
    $this->strings['visibility_less_than']     = 'inf�rieure � ';
    $this->strings['visibility_to']            = ' � ';
    $this->strings['runway_upward_tendency']   = ' avec tendance � l\'%am�lioration%s';
    $this->strings['runway_downward_tendency'] = ' avec tendance � la %sd�terioration%s';
    $this->strings['runway_no_tendency']       = ' sans tendance %sdistinctive%s';
    $this->strings['runway_between']           = 'entre ';
    $this->strings['runway_left']              = ' gauche';
    $this->strings['runway_central']           = ' centrale';
    $this->strings['runway_right']             = ' droite';
    $this->strings['runway_visibility']        = 'La visibilit� �tait de ';
    $this->strings['runway_for_runway']        = ' pour la piste ';

    /* We run the parent constructor */
    $this->pw_text($weather, $input);
  }
}

?>
