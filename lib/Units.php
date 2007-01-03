<?php rcs_id('$Id: Units.php,v 1.1 2007-01-03 21:22:48 rurban Exp $');
/**
 * Interface to man units(1), /usr/share/units.dat
 *
 * $ units "372.0 mi2"
 *         Definition: 9.6347558e+08 m^2
 * $ units "372.0 mi2" m^2
 *         Definition: 9.6347558e+08 m^2
 *
 * Windows requires the cygwin /usr/bin/units. 
 * CHECK: All successfully parsed unit definitions might be stored in the wikidb,
 * so that subsequent expansions will not require /usr/bin/units be called again.
 * So far even on windows (cygwin) the process is fast enough.
 */

class Units {
    function Units ($UNITSFILE = false) {
    	if (DISABLE_UNITS)
    	    $this->errcode = 1;
    	elseif (defined("UNITS_EXE")) // ignore dynamic check
	    $this->errcode = 0;
	else
    	    exec("units m2",$o,$this->errcode);
    }

    function Definition ($query) {
	static $Definitions = array();
	if (isset($Definitions[$query])) return $Definitions[$query];
	if ($this->errcode)
            return $query;
	$query = preg_replace("/,/","", $query);
	$def = $this->_cmd("\"$query\"");
	if (preg_match("/Definition: (.+)$/",$def,$m))
	    return ($Definitions[$query] = $m[1]);
	else {
	    trigger_error("units: ". $def, E_USER_WARNING);
	    return '';
	}
    }

    function basevalue($query, $def = false) {
	if (!$def) $def = $this->Definition($query);
	if ($def) {
	    if (is_numeric($def)) // e.g. "1 million"
	        return $def;
	    if (preg_match("/^([-0-9].*) \w.*$/",$def,$m))
		return $m[1];
	}
	return '';
    }

    function baseunit($query, $def  = false) {
	if (!$def) $def = $this->Definition($query);
	if ($def) {
	    if (preg_match("/ (.+)$/",$def,$m))
		return $m[1];
	}
	return '';
    }

    function _cmd($args) {
	if ($this->errcode) return $args;
	if (defined("UNITS_EXE")) {
	    $s = UNITS_EXE ." $args";
	    $result = `$s`;
	}
	else 
	    $result = `units $args`;
	return trim($result);
    }
}
