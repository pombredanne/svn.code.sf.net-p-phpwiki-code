<?php
rcs_id('$Id: config.php,v 1.100 2004-04-26 12:15:01 rurban Exp $');
/*
 * NOTE: The settings here should probably not need to be changed.
 * The user-configurable settings have been moved to IniConfig.php
 * The run-time code have been moved to lib/IniConfig.php:fix_configs()
 */
 
if (!defined("LC_ALL")) {
    // Backward compatibility (for PHP < 4.0.5)
    define("LC_ALL",   0);
    define("LC_CTYPE", 2);
}

function isCGI() {
    return @preg_match('/CGI/',$GLOBALS['HTTP_ENV_VARS']['GATEWAY_INTERFACE']);
}

/*
// copy some $_ENV vars to $_SERVER for CGI compatibility. php does it automatically since when?
if (isCGI()) {
    foreach (explode(':','SERVER_SOFTWARE:SERVER_NAME:GATEWAY_INTERFACE:SERVER_PROTOCOL:SERVER_PORT:REQUEST_METHOD:HTTP_ACCEPT:PATH_INFO:PATH_TRANSLATED:SCRIPT_NAME:QUERY_STRING:REMOTE_HOST:REMOTE_ADDR:REMOTE_USER:AUTH_TYPE:CONTENT_TYPE:CONTENT_LENGTH') as $key) {
        $GLOBALS['HTTP_SERVER_VARS'][$key] = &$GLOBALS['HTTP_ENV_VARS'][$key];
    }
}
*/

// essential internal stuff
set_magic_quotes_runtime(0);

/** 
 * Browser Detection Functions
 *
 * Current Issues:
 *  NS/IE < 4.0 doesn't accept < ? xml version="1.0" ? >
 *  NS/IE < 4.0 cannot display PNG
 *  NS/IE < 4.0 cannot display all XHTML tags
 *  NS < 5.0 needs textarea wrap=virtual
 *  IE55 has problems with transparent PNG's
 * @author: ReiniUrban
 */
function browserAgent() {
    static $HTTP_USER_AGENT = false;
    if (!$HTTP_USER_AGENT)
        $HTTP_USER_AGENT = @$GLOBALS['HTTP_SERVER_VARS']['HTTP_USER_AGENT'];
    if (!$HTTP_USER_AGENT) // CGI
        $HTTP_USER_AGENT = $GLOBALS['HTTP_ENV_VARS']['HTTP_USER_AGENT'];
    return $HTTP_USER_AGENT;
}
function browserDetect($match) {
    return strstr(browserAgent(), $match);
}
// returns a similar number for Netscape/Mozilla (gecko=5.0)/IE/Opera features.
function browserVersion() {
    if (strstr(browserAgent(),"Mozilla/4.0 (compatible; MSIE"))
        return (float) substr(browserAgent(),30);
    else
        return (float) substr(browserAgent(),8);
}
function isBrowserIE() {
    return (browserDetect('Mozilla/') and 
            browserDetect('MSIE'));
}
// problem with transparent PNG's
function isBrowserIE55() {
    return (isBrowserIE() and 
            browserVersion() > 5.1 and browserVersion() < 6.0);
}
function isBrowserNetscape() {
    return (browserDetect('Mozilla/') and 
            ! browserDetect('Gecko/') and
            ! browserDetect('MSIE'));
}
// NS3 or less
function isBrowserNS3() {
    return (isBrowserNetscape() and browserVersion() < 4.0);
}

/**
 * Smart? setlocale().
 *
 * This is a version of the builtin setlocale() which is
 * smart enough to try some alternatives...
 *
 * @param mixed $category
 * @param string $locale
 * @return string The new locale, or <code>false</code> if unable
 *  to set the requested locale.
 * @see setlocale
 */
function guessing_setlocale ($category, $locale) {
    if ($res = setlocale($category, $locale))
        return $res;
    $alt = array('en' => array('C', 'en_US', 'en_GB', 'en_AU', 'en_CA', 'english'),
                 'de' => array('de_DE', 'de_DE', 'de_DE@euro', 
                               'de_AT@euro', 'de_AT', 'German_Austria.1252', 'deutsch', 
                               'german', 'ge'),
                 'es' => array('es_ES', 'es_MX', 'es_AR', 'spanish'),
                 'nl' => array('nl_NL', 'dutch'),
                 'fr' => array('fr_FR', 'français', 'french'),
                 'it' => array('it_IT'),
                 'sv' => array('sv_SE'),
                 'ja' => array('ja_JP','ja_JP.eucJP','japanese.euc'),
                 //'zh' => array('zh_TW', 'zh_CN')
                 );
    if (strlen($locale) == 2)
        $lang = $locale;
    else 
        list ($lang) = split('_', $locale);
    if (!isset($alt[$lang]))
        return false;
        
    foreach ($alt[$lang] as $try) {
        if ($res = setlocale($category, $try))
            return $res;
        // Try with charset appended...
        $try = $try . '.' . $GLOBALS['charset'];
        if ($res = setlocale($category, $try))
            return $res;
        foreach (array('@', ".", '_') as $sep) {
            list ($try) = split($sep, $try);
            if ($res = setlocale($category, $try))
                return $res;
        }
    }
    return false;

    // A standard locale name is typically of  the  form
    // language[_territory][.codeset][@modifier],  where  language is
    // an ISO 639 language code, territory is an ISO 3166 country code,
    // and codeset  is  a  character  set or encoding identifier like
    // ISO-8859-1 or UTF-8.
}

function update_locale($loc) {
    require_once(dirname(__FILE__)."/FileFinder.php");
    $newlocale = guessing_setlocale(LC_ALL, $loc);
    if (!$newlocale) {
        //trigger_error(sprintf(_("Can't setlocale(LC_ALL,'%s')"), $loc), E_USER_NOTICE);
        // => LC_COLLATE=C;LC_CTYPE=German_Austria.1252;LC_MONETARY=C;LC_NUMERIC=C;LC_TIME=C
        //$loc = setlocale(LC_CTYPE, '');  // pull locale from environment.
        $newlocale = FileFinder::_get_lang();
        list ($newlocale,) = split('_', $newlocale, 2);
        //$GLOBALS['LANG'] = $loc;
        //$newlocale = $loc;
        //return false;
    }
    //if (substr($newlocale,0,2) == $loc) // don't update with C or failing setlocale
    $GLOBALS['LANG'] = $loc;
    // Try to put new locale into environment (so any
    // programs we run will get the right locale.)
    //
    // If PHP is in safe mode, this is not allowed,
    // so hide errors...
    @putenv("LC_ALL=$newlocale");
    @putenv("LANG=$newlocale");
    @putenv("LANGUAGE=$newlocale");
    
    if (!function_exists ('bindtextdomain'))  {
        // Reinitialize translation array.
        global $locale;
        $locale = array();
        // do reinit to purge PHP's static cache
        if ( ($lcfile = FindLocalizedFile("LC_MESSAGES/phpwiki.php", 'missing_ok','reinit')) ) {
            include($lcfile);
        }
    }

    // To get the POSIX character classes in the PCRE's (e.g.
    // [[:upper:]]) to match extended characters (e.g. GrüßGott), we have
    // to set the locale, using setlocale().
    //
    // The problem is which locale to set?  We would like to recognize all
    // upper-case characters in the iso-8859-1 character set as upper-case
    // characters --- not just the ones which are in the current $LANG.
    //
    // As it turns out, at least on my system (Linux/glibc-2.2) as long as
    // you setlocale() to anything but "C" it works fine.  (I'm not sure
    // whether this is how it's supposed to be, or whether this is a bug
    // in the libc...)
    //
    // We don't currently use the locale setting for anything else, so for
    // now, just set the locale to US English.
    //
    // FIXME: Not all environments may support en_US?  We should probably
    // have a list of locales to try.
    if (setlocale(LC_CTYPE, 0) == 'C') {
        $x = setlocale(LC_CTYPE, 'en_US.' . $GLOBALS['charset']);
    } else {
        $x = setlocale(LC_CTYPE, $newlocale);
    }

    return $newlocale;
}

/** string pcre_fix_posix_classes (string $regexp)
*
* Older version (pre 3.x?) of the PCRE library do not support
* POSIX named character classes (e.g. [[:alnum:]]).
*
* This is a helper function which can be used to convert a regexp
* which contains POSIX named character classes to one that doesn't.
*
* All instances of strings like '[:<class>:]' are replaced by the equivalent
* enumerated character class.
*
* Implementation Notes:
*
* Currently we use hard-coded values which are valid only for
* ISO-8859-1.  Also, currently on the classes [:alpha:], [:alnum:],
* [:upper:] and [:lower:] are implemented.  (The missing classes:
* [:blank:], [:cntrl:], [:digit:], [:graph:], [:print:], [:punct:],
* [:space:], and [:xdigit:] could easily be added if needed.)
*
* This is a hack.  I tried to generate these classes automatically
* using ereg(), but discovered that in my PHP, at least, ereg() is
* slightly broken w.r.t. POSIX character classes.  (It includes
* "\xaa" and "\xba" in [:alpha:].)
*
* So for now, this will do.  --Jeff <dairiki@dairiki.org> 14 Mar, 2001
*/
function pcre_fix_posix_classes ($regexp) {
    global $charset;
    // First check to see if our PCRE lib supports POSIX character
    // classes.  If it does, there's nothing to do.
    if (preg_match('/[[:upper:]]/', 'Ä'))
        return $regexp;

    static $classes = array(
                            'alnum' => "0-9A-Za-z\xc0-\xd6\xd8-\xf6\xf8-\xff",
                            'alpha' => "A-Za-z\xc0-\xd6\xd8-\xf6\xf8-\xff",
                            'upper' => "A-Z\xc0-\xd6\xd8-\xde",
                            'lower' => "a-z\xdf-\xf6\xf8-\xff"
                            );
    if (!isset($charset))
        $charset = CHARSET; // FIXME: get rid of constant. pref is dynamic and language specific
    if (in_array($GLOBALS['LANG'],array('ja','zh')))
        $charset = 'utf-8';
    if (strtolower($charset) == 'utf-8') { // thanks to John McPherson
        // until posix class names/pcre work with utf-8
        // utf-8 non-ascii chars: most common (eg western) latin chars are 0xc380-0xc3bf
        // we currently ignore other less common non-ascii characters
        // (eg central/east european) latin chars are 0xc432-0xcdbf and 0xc580-0xc5be
        // and indian/cyrillic/asian languages
        
        // this replaces [[:lower:]] with utf-8 match (Latin only)
        $regexp = preg_replace('/\[\[\:lower\:\]\]/','(?:[a-z]|\xc3[\x9f-\xbf]|\xc4[\x81\x83\x85\x87])',
                               $regexp);
        // this replaces [[:upper:]] with utf-8 match (Latin only)
        $regexp = preg_replace('/\[\[\:upper\:\]\]/','(?:[A-Z]|\xc3[\x80-\x9e]|\xc4[\x80\x82\x84\x86])',
                               $regexp);
    }
   
    $keys = join('|', array_keys($classes));

    return preg_replace("/\[:($keys):]/e", '$classes["\1"]', $regexp);
}

function deduce_script_name() {
    $s = &$GLOBALS['HTTP_SERVER_VARS'];
    $script = @$s['SCRIPT_NAME'];
    if (empty($script) or $script[0] != '/') {
        // Some places (e.g. Lycos) only supply a relative name in
        // SCRIPT_NAME, but give what we really want in SCRIPT_URL.
        if (!empty($s['SCRIPT_URL']))
            $script = $s['SCRIPT_URL'];
    }
    return $script;
}

function IsProbablyRedirectToIndex () {
    // This might be a redirect to the DirectoryIndex,
    // e.g. REQUEST_URI = /dir/  got redirected
    // to SCRIPT_NAME = /dir/index.php

    // In this case, the proper virtual path is still
    // $SCRIPT_NAME, since pages appear at
    // e.g. /dir/index.php/HomePage.

    $requri = preg_quote($GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'], '%');
    return preg_match("%^${requri}[^/]*$%", $GLOBALS['HTTP_SERVER_VARS']['SCRIPT_NAME']);
}

// $Log: not supported by cvs2svn $
// Revision 1.99  2004/04/21 14:04:24  zorloc
// 'Require lib/FileFinder.php' necessary to allow for call to FindLocalizedFile().
//
// Revision 1.98  2004/04/20 18:10:28  rurban
// config refactoring:
//   FileFinder is needed for WikiFarm scripts calling index.php
//   config run-time calls moved to lib/IniConfig.php:fix_configs()
//   added PHPWIKI_DIR smart-detection code (Theme finder)
//   moved FileFind to lib/FileFinder.php
//   cleaned lib/config.php
//
// Revision 1.97  2004/04/18 01:11:52  rurban
// more numeric pagename fixes.
// fixed action=upload with merge conflict warnings.
// charset changed from constant to global (dynamic utf-8 switching)
//

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
