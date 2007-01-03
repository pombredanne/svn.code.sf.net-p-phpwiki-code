<?php // -*- php -*-
// $Id: XmlRpcClient.php,v 1.2 2007-01-03 21:25:43 rurban Exp $
/* Copyright (C) 2002, Lawrence Akka <lakka@users.sourceforge.net>
 * Copyright (C) 2004,2005,2006 $ThePhpWikiProgrammingTeam
 */
// All these global declarations that this file
// XmlRpcClient.php can be included within a function body
// (not in global scope), and things will still work.

global $xmlrpcI4, $xmlrpcInt, $xmlrpcBoolean, $xmlrpcDouble, $xmlrpcString;
global $xmlrpcDateTime, $xmlrpcBase64, $xmlrpcArray, $xmlrpcStruct;
global $xmlrpcTypes;
global $xmlEntities;
global $xmlrpcerr, $xmlrpcstr;
global $xmlrpc_defencoding;
global $xmlrpcName, $xmlrpcVersion;
global $xmlrpcerruser, $xmlrpcerrxml;
global $xmlrpc_backslash;
global $_xh;
global $_xmlrpcs_debug;

define('XMLRPC_EXT_LOADED', true);
if (loadPhpExtension('xmlrpc')) { // fast c lib
    global $xmlrpc_util_path;
    $xmlrpc_util_path = dirname(__FILE__)."/XMLRPC/";
    include_once("lib/XMLRPC/xmlrpc_emu.inc"); 
 } else { // slow php lib
    // Include the php XML-RPC library
    include_once("lib/XMLRPC/xmlrpc.inc");
}

//  API version
define ("WIKI_XMLRPC_VERSION", 2);

/*
 * Helper functions for encoding/decoding strings.
 *
 * According to WikiRPC spec, all returned strings take one of either
 * two forms.  Short strings (page names, and authors) are converted to
 * UTF-8, then rawurlencode()d, and returned as XML-RPC <code>strings</code>.
 * Long strings (page content) are converted to UTF-8 then returned as
 * XML-RPC <code>base64</code> binary objects.
 */

/**
 * Urlencode ASCII control characters.
 *
 * (And control characters...)
 *
 * @param string $str
 * @return string
 * @see urlencode
 */
function UrlencodeControlCharacters($str) {
    return preg_replace('/([\x00-\x1F])/e', "urlencode('\\1')", $str);
}

/**
 * Convert a short string (page name, author) to xmlrpcval.
 */
function short_string ($str) {
    return new xmlrpcval(UrlencodeControlCharacters(utf8_encode($str)), 'string');
}

/**
 * Convert a large string (page content) to xmlrpcval.
 */
function long_string ($str) {
    return new xmlrpcval(utf8_encode($str), 'base64');
}

/**
 * Decode a short string (e.g. page name)
 */
function short_string_decode ($str) {
    return utf8_decode(urldecode($str));
}

function wiki_xmlrpc_post($method, $args = null, $url = null) {
    if (is_null($url)) {
	//$url = deduce_script_name();
	$url = DATA_PATH . "/RPC2.php";
    }
    $debug = 0;
    $server = parse_url($url);
    if (empty($server['host'])) $server['host'] = 'localhost';
    // xmlrpc remote debugging
    if ((DEBUG & _DEBUG_REMOTE) and !empty($_GET['start_debug'])) { 
	$debug = 2;
	$server['path'] .= '?start_debug=1';
    }
    $result = xu_rpc_http_concise(array('method' => $method,
					'args'   => $args, 
					'host'   => $server['host'], 
					'uri'    => $server['path'], 
					'debug'  => $debug,
					'output' => null));
    return $result;
}

/*
 $Log: not supported by cvs2svn $
 Revision 1.1  2007/01/02 13:21:12  rurban
 split client from server

 */

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
