<?php 
// $Id: RPC2.php,v 1.2 2002-09-04 19:33:36 dairiki Exp $
/* Copyright (C) 2002, Lawrence Akka <lakka@users.sourceforge.net>
 *
 * LICENCE
 * =======
 * This file is part of PhpWiki.
 * 
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with PhpWiki; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * LIBRARY USED - POSSIBLE PROBLEMS
 * ================================
 * 
 * This file provides an XML-RPC interface for PhpWiki.  It uses the XML-RPC 
 * library for PHP by Edd Dumbill - see http://xmlrpc.usefulinc.com/php.html 
 * for details.
 *
 * PHP >= 4.1.0 includes experimental support for the xmlrpc-epi c library 
 * written by Dan Libby (see http://uk2.php.net/manual/en/ref.xmlrpc.php).  This
 * is not compiled into PHP by default.  If it *is* compiled into your installation
 * (ie you have --with-xmlrpc) there may well be namespace conflicts with the xml-rpc
 * library used by this code, and you will get errors.
 * 
 * INTERFACE SPECIFICTION
 * ======================
 *  
 * The interface specification is that discussed at 
 * http://www.ecyrd.com/JSPWiki/Wiki.jsp?page=WikiRPCInterface
 * 
 * See also http://www.usemod.com/cgi-bin/mb.pl?XmlRpc
 * 
 * NB:  All XMLRPC methods should be prefixed with "wiki."
 * eg  wiki.getAllPages
 * 
 
*/

// ToDo:  
//        Remove all warnings from xmlrpc.inc 
//        Return list of external links in listLinks
 

// Intercept GET requests from confused users.  Only POST is allowed here!
// There is some indication that $HTTP_SERVER_VARS is deprecated in php > 4.1.0
// in favour of $_Server, but as far as I know, it still works.
if ($HTTP_SERVER_VARS['REQUEST_METHOD'] != "POST")  
{
    die('This is the address of the XML-RPC interface.' .
        '  You must use XML-RPC calls to access information here');
}

// Include the php XML-RPC library
include("lib/XMLRPC/xmlrpc.inc");
include("lib/XMLRPC/xmlrpcs.inc");

// Constant defined to indicate to phpwiki that it is being accessed via XML-RPC
define ("WIKI_XMLRPC", "true");
//  API version
define ("WIKI_XMLRPC_VERSION", 1);
// Start up the main code
include_once("index.php");
include_once("lib/main.php");

/**
 * Helper function:  Looks up a page revision (most recent by default) in the wiki database
 * 
 * @param xmlrpcmsg $params :  string pagename [int version]
 * @return WikiDB _PageRevision object, or false if no such page
 */

function _getPageRevision ($params)
{
    global $request;
    $ParamPageName = $params->getParam(0);
    $ParamVersion = $params->getParam(1);
	// ?? really need utf8_decode here?
    $pagename = utf8_decode($ParamPageName->scalarval());
    $version =  ($ParamVersion) ? ($ParamVersion->scalarval()):(0);
    // FIXME:  test for version <=0 ??
    $dbh = $request->getDbh();
    if ($dbh->isWikiPage($pagename)) {
        $page = $dbh->getPage($pagename);
        if (!$version) {
            $revision = $page->getCurrentRevision();
        } else {
            $revision = $page->getRevision($version);
        } 
        return $revision;
    } 
    return false;
} 

// ****************************************************************************
// Main API functions follow
// ****************************************************************************


/**
 * int getRPCVersionSupported(): Returns 1 for this version of the API 
 */

// Method signature:  An array of possible signatures.  Each signature is
// an array of types. The first entry is the return type.  The other 
// entries (if any) are the parameter types
$getRPCVersionSupported_sig = array(array($xmlrpcInt));
// Doc string:  A string containing documentation for the method. The 
// documentation may contain HTML markup
$getRPCVersionSupported_doc = 'Get the version of the wiki API';

// The function must be a function in the global scope which services the XML-RPC
// method.
function getRPCVersionSupported($params)
{
    return new xmlrpcresp(new xmlrpcval(WIKI_XMLRPC_VERSION, "int"));
}

/**
 * array getRecentChanges(Date timestamp) : Get list of changed pages since 
 * timestamp, which should be in UTC. The result is an array, where each element
 * is a struct: 
 *     name (string) : Name of the page. The name is UTF-8 with URL encoding to make it ASCII. 
 *     lastModified (date) : Date of last modification, in UTC. 
 *     author (string) : Name of the author (if available). Again, name is UTF-8 with URL encoding. 
 * 	   version (int) : Current version. 
 * A page MAY be specified multiple times. A page MAY NOT be specified multiple 
 * times with the same modification date.
 */
$getRecentChanges_sig = array(array($xmlrpcArray, $xmlrpcDateTime));
$getRecentChanges_doc = 'Get a list of changed pages since [timestamp]';

function getRecentChanges($params)
{
    global $request, $xmlrpcerruser;
    // Get the first parameter as an ISO 8601 date.  Assume UTC
    $encoded_date = $params->getParam(0);
    $datetime = iso8601_decode($encoded_date->scalarval(), 1);
    $dbh = $request->getDbh();
    $pages = array();
    $iterator = $dbh->mostRecent(array('since' => $datetime));
    while ($page = $iterator->next()) {
        // $page contains a WikiDB_PageRevision object
        // no need to url encode $name, because it is already stored in that format ???
        $name = new xmlrpcval(utf8_encode($page->getPageName())); 
        $lastmodified = new xmlrpcval(iso8601_encode($page->get('mtime')), "dateTime.iso8601");
        $author = new xmlrpcval(utf8_encode($page->get('author')));
        $version = new xmlrpcval($page->getVersion, 'int');

        // Build an array of xmlrpc structs
        $pages[] = new xmlrpcval(array('name'=>$name, 
                                       'lastModified'=>$lastmodified,
                                       'author'=>$author,
                                       'version'=>$version),
                                 'struct');
    } 
    return new xmlrpcresp(new xmlrpcval($pages, "array"));
} 


/**
 * base64 getPage( String pagename ): Get the raw Wiki text of page, latest version. 
 * Page name must be UTF-8, with URL encoding. Returned value is a binary object,
 * with UTF-8 encoded page data.
 */

$getPage_sig = array(array($xmlrpcBase64, $xmlrpcString));
$getPage_doc = 'Get the raw Wiki text of the current version of a page';

function getPage($params)
{
    global $request, $xmlrpcerruser;
    $revision = _getPageRevision($params);

    if ($revision) {
        // fixme : need urlencoding here?
        $content = ($revision->getPackedContent());
        return new xmlrpcresp(new xmlrpcval($content, "base64"));
    }
    else {
        // return an errror response
        return new xmlrpcresp(0, $xmlrpcerruser + 1, "No such page");
    }
}

 

/**
 * base64 getPageVersion( String pagename, int version ): Get the raw Wiki text of page.
 * Returns UTF-8, expects UTF-8 with URL encoding.
 */

$getPageVersion_sig = array(array($xmlrpcBase64, $xmlrpcString, $xmlrpcInt));
$getPageVersion_doc = 'Get the raw Wiki text of a page version';

function getPageVersion($params)
{
    global $request, $xmlrpcerruser;
    return getPage($params);
    // error checking is done in getPage
} 

/**
 * base64 getPageHTML( String pagename ): Return page in rendered HTML. 
 * Returns UTF-8, expects UTF-8 with URL encoding.
 */

$getPageHTML_sig = array(array($xmlrpcString, $xmlrpcString));
$getPageHTML_doc = 'Get the current version of a page rendered in HTML';

function getPageHTML($params)
{
    global $request, $xmlrpcerruser;
    $revision = _getPageRevision($params);
    if ($revision) {
        include_once('lib/display.php');
        // This is a bit hacky.  Start output buffering, fake a request, and get phpWiki
        // to render the page.  Then return it via XMLRPC.
        $request->setArg('pagename',$revision->getPageName());
        $request->setArg('version',$revision->getVersion());
        ob_start();
        displayPage($request);
        $output = ob_get_contents();
        ob_end_clean();
        //        xmlrpc_debugmsg("$output");
        return new xmlrpcresp(new xmlrpcval(utf8_encode($output), "string"));
    }
    else {
        return new xmlrpcresp(0, $xmlrpcerruser + 1, "No such page");
    }
} 

/**
 * base64 getPageHTMLVersion( String pagename, int version ): Return page in rendered HTML, UTF-8.
 */

$getPageHTMLVersion_sig = array(array($xmlrpcBase64, $xmlrpcString, $xmlrpcInt));
$getPageHTMLVersion_doc = 'Get a version of a page rendered in HTML';

function getPageHTMLVersion($params)
{
    global $request, $xmlrpcerruser;
    return getPageHTML($params);
} 

/**
 * getAllPages(): Returns a list of all pages. The result is an array of strings.
 */

$getAllPages_sig = array(array($xmlrpcArray));
$getAllPages_doc = 'Returns a list of all pages as an array of strings'; 
 
function getAllPages($params)
{
    global $request, $xmlrpcerruser;
    $dbh = $request->getDbh();
    $iterator = $dbh->getAllPages();
    $pages = array();
    while ($page = $iterator->next()) {
        $pages[] = new xmlrpcval($page->getName());
    } 
    return new xmlrpcresp(new xmlrpcval($pages, "array"));
} 

/**
 * struct getPageInfo( string pagename ) : returns a struct with elements: 
 *   name (string): the canonical page name 
 *   lastModified (date): Last modification date 
 *   version (int): current version 
 * 	 author (string): author name 
 */

$getPageInfo_sig = array(array($xmlrpcStruct, $xmlrpcString));
$getPageInfo_doc = 'Gets info about the current version of a page';

function getPageInfo($params)
{
    global $xmlrpcerruser;
    $revision = _getPageRevision($params);
    if ($revision) {
        $name = new xmlrpcval($params->getParam(0));
        $version = new xmlrpcval ($revision->getVersion(), "int");
        $lastmodified = new xmlrpcval(iso8601_encode($revision->get('mtime'), 0), "dateTime.iso8601");
        $author = new xmlrpcval($revision->get('author'));

        return new xmlrpcresp(new xmlrpcval(array('name' => $name, 
                                                  'lastModified' => $lastmodified,
                                                  'version' => $version, 
                                                  'author' => $author), 
                                            "struct"));
    }
    else {
        return new xmlrpcresp(0, $xmlrpcerruser + 1, "No such page");
    }
} 

/**
 * struct getPageInfoVersion( string pagename, int version ) : returns a struct just like plain getPageInfo(), 
 * 	but this time for a specific version.
 */

$getPageInfoVersion_sig = array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt));
$getPageInfoVersion_doc = 'Gets info about a page version';

function getPageInfoVersion($params)
{
    global $request, $xmlrpcerruser;
    return getPageInfo($params);
}

 
/*  array listLinks( string pagename ): Lists all links for a given page. The
 *  returned array contains structs, with the following elements: 
 *   	 name (string) : The page name or URL the link is to. 
 *       type (int) : The link type. Zero (0) for internal Wiki link,
 *         one (1) for external link (URL - image link, whatever).
 */

$listLinks_sig = array(array($xmlrpcArray, $xmlrpcString));
$listLinks_doc = 'Lists all links for a given page';

function listLinks($params)
{
    global $request, $xmlrpcerruser;
    $ParamPageName = $params->getParam(0);
    // ?? really need utf8_decode here?
    $pagename = utf8_decode($ParamPageName->scalarval());
    $dbh = $request->getDbh();
    if ($dbh->isWikiPage($pagename)) {
        $page = $dbh->getPage($pagename);
     	$linkiterator = $page->getLinks();
        $linkstruct = array();
        while ($currentpage = $linkiterator->next()) {
            $currentname = $currentpage->getName();
            $name = new xmlrpcval($currentname, "string");    
            // NB no clean way to extract a list of external links yet, so
            // only internal links returned.  ie all type 0.
            $type = new xmlrpcval(0, "int");
            $linkstruct[] = new xmlrpcval(array('name'=> $name,
                                                'type'=> $type
                                                ), "struct");
        }
	    return new xmlrpcresp(new xmlrpcval ($linkstruct, "array"));
    } else
        return new xmlrpcresp(0, $xmlrpcerruser + 1, "No such page");
} 
 
// Construct the server instance, and set up the despatch map, which maps
// the XML-RPC methods onto the wiki functions
$s = new xmlrpc_server(array("wiki.getRPCVersionSupported" =>
                             array("function" => "getRPCVersionSupported",
                                   "signature" => $getRPCVersionSupported_sig,
                                   "docstring" => $getRPCVersionSupported_doc),
                             "wiki.getRecentChanges" =>
                             array("function" => "getRecentChanges",
                                   "signature" => $getRecentChanges_sig,
                                   "docstring" => $getRecentChanges_doc),
                             "wiki.getPage" =>
                             array("function" => "getPage",
                                   "signature" => $getPage_sig,
                                   "docstring" => $getPage_doc),
                             "wiki.getPageVersion" =>
                             array("function" => "getPageVersion",
                                   "signature" => $getPageVersion_sig,
                                   "docstring" => $getPageVersion_doc),
                             "wiki.getPageHTML" =>
                             array("function" => "getPageHTML",
                                   "signature" => $getPageHTML_sig,
                                   "docstring" => $getPageHTML_doc),
                             "wiki.getPageHTMLVersion" =>
                             array("function" => "getPageHTMLVersion",
                                   "signature" => $getPageHTMLVersion_sig,
                                   "docstring" => $getPageHTMLVersion_doc),
                             "wiki.getAllPages" =>
                             array("function" => "getAllPages",
                                   "signature" => $getAllPages_sig,
                                   "docstring" => $getAllPages_doc),
                             "wiki.getPageInfo" =>
                             array("function" => "getPageInfo",
                                   "signature" => $getPageInfo_sig,
                                   "docstring" => $getPageInfo_doc),
                             "wiki.getPageInfoVersion" =>
                             array("function" => "getPageInfoVersion",
                                   "signature" => $getPageInfoVersion_sig,
                                   "docstring" => $getPageInfoVersion_doc),
                             "wiki.listLinks" =>
                             array("function" => "listLinks",
                                   "signature" => $listLinks_sig,
                                   "docstring" => $listLinks_doc)
                             ));
                             
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
