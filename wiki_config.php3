<?
function rcs_id($id) {
  global $RcsIdentifiers;
  $RcsIdentifiers .= "$id\n";
};

rcs_id('$Id: wiki_config.php3,v 1.19.2.2 2000-07-29 00:36:45 dairiki Exp $');

   /*
      Constants and settings. Edit the values below for
      your site. You need two image files, a banner and 
      a signature. The dbm file MUST be writable by the
      web server or this won't work. If you configure your
      server to allow index.php3 as an index file, you 
      can just give the URL without the script name.
   */

   // You should set the $ServerAddress below.  But you probably don't
   // have to: if you don't it will be figured out dynamically.

   //$ServerAddress = "http://127.0.0.1:8080/phpwiki/";

   if (!$ServerAddress) {
      $ServerAddress = "http://$HTTP_HOST"
	   . preg_replace(':[^/]*$:', '', $SCRIPT_NAME);
   }

   // if you are using MySQL instead of a DBM to store your
   // Wiki pages, use wiki_mysql.php3 instead of wiki_dbmlib.php3
   // See INSTALL.mysql for details on using MySQL

   // if you are using Postgressl instead of a DBM to store your
   // Wiki pages, use wiki_pgsql.php3 instead of wiki_dbmlib.php3
   // See INSTALL.pgsql for details on using Postgresql

   // if you are using mSQL instead of a DBM to store your
   // Wiki pages, use wiki_msql.php3 instead of wiki_dbmlib.php3
   // See INSTALL.mysql for details on using mSQL

   // DBM settings (default)
/**/
   $dbparams =array('dbtype' => 'dbm',
		    'dbfile' => '/tmp/phpwikidb');
/**/		    

   // MySQL settings (thanks Arno Hollosi! <ahollosi@iname.com>)
   // Comment out the lines above (for the DBM) if you use these
/*
   $dbparams = array('dbtype' => 'mysql',
		     'server' => 'localhost',
		     'user' => 'guest',
		     'password' => '',
		     'database' => 'test');
*/

/*
   // PostgreSQL settings. 
   include "wiki_pgsql.php3";
   $WikiDataBase = "wiki";
   $ArchiveDataBase = "archive";
   $pg_dbhost    = "localhost";
   $pg_dbport    = "5432";
*/


/*
   // MiniSQL (mSQL) settings.
   include "wiki_msql.php3";
   $msql_db = "wiki";
   // should be the same as wikipages.line
   define("MSQL_MAX_LINE_LENGTH", 128);
   $WikiDataBase = array();
   $ArchiveDataBase = array();

   $WikiDataBase['table']         = "wiki";
   $WikiDataBase['page_table']    = "wikipages";
   $ArchiveDataBase['table']      = "archive";
   $ArchiveDataBase['page_table'] = "archivepages";
   // end mSQL settings
*/

   /* WIKI_PAGENAME_IN_PATHINFO
    *
    * If true, then wiki page names are encoded in the PATH_INFO part
    * of link URLS.  Eg.:
    *   http://some.host.com/path/index.php3/FrontPage
    *   http://some.host.com/path/index.php3/FrontPage?action=edit
    *
    * If false, then page names go into QUERY_STRING (this is the old
    * behavior.):
    *
    *   http://some.host.com/path/index.php3?FrontPage
    *   http://some.host.com/path/index.php3?edit=FrontPage
    */  
   define('WIKI_PAGENAME_IN_PATHINFO', true);

   /* WIKI_PGSRC
    *
    * This constant specifies the source for the initial page contents
    * of the Wiki.  The setting of WIKI_PGSRC only has effect when
    * the wiki is accessed for the first time (or after clearing the
    * database.)
    *
    * The WIKI_PGSRC can either name a directory or a zip file.
    * In either case WIKI_PGSRC is scanned for files --- one file per page.
    *
    * FIXME: this documentation needs to be clarified.
    *
    * If the files appear to be MIME formatted messages, they are
    * scanned for application/x-phpwiki content-types.  Any suitable
    * content is added to the wiki.
    *
    * The files can also be plain text files, in which case the page name
    * is taken from the file name.
    */
   define('WIKI_PGSRC', './pgsrc'); // Default (old) behavior.
   //define('WIKI_PGSRC', './wiki.zip'); // New style.
  
   $ScriptName = "index.php3";
   $AdminName = "admin.php3";


//$SignatureImg = "$ServerAddress/signature.png";
// $LogoURL = "$ServerAddress/wikibase.png";

   // date & time formats used to display modification times, etc.
   // formats are given as format strings to PHP date() function
   //$datetimeformat = "F j, Y";	// may contain time of day
   $datetimeformat = "D M d H:i T Y";	// may contain time of day
   $dateformat = "F j, Y";	// must not contain time

   // allowed protocols for links - be careful not to allow "javascript:"
   $AllowedProtocols = "http|https|mailto|ftp|news|gopher";
   
   // you shouldn't have to edit anything below this line

   $ScriptUrl = $ServerAddress . $ScriptName;

   // Apache won't show REMOTE_HOST unless the admin configured it
   // properly. We'll be nice and see if it's there.
   $remoteuser =  empty($REMOTE_HOST) ? $REMOTE_ADDR : $REMOTE_HOST;

   // Template files (filenames are relative to script position)
   define('WIKI_TEMPLATE_DIR', "templates");
   $TemplateFiles = array(
       "WRAPPER"   => "wrapper.html",
       "BROWSE"    => "browse.html",
       "BROWSEOLD" => "browseold.html",
       "EDIT"      => "edit.html",
       "LINKS"     => "links.html",
       "SAVE"      => "save.html",
       "SEARCH"    => "search.html",
       "FULL"      => "fullsearch.html",
       "BACKLINKS" => "backlinks.html",
       "HISTORY"   => "history.html",
       "DIFF"      => "diff.html",
       "INFO"      => "info.html",
       "ERROR"     => "error.html",
       "EDITPROBLEM" => "editproblem.html",
       "MISC"      => "misc.html"
       );

   $TemplateVars = array(
       'RemoteUser'   => $remoteuser,
       'AllowedProtocols' => $AllowedProtocols,
       'BgColor'      => 'linen',
       'SignatureImgUrl' => $ServerAddress . "signature.png",
       'LogoImgUrl'   => $ServerAddress . "wikibase.png",
       'FrontPageUrl' => $ServerAddress . "index.php3/FrontPage",
       'AdminUrl'     => $ServerAddress . "admin.php3",
       'ScriptName'   => $ScriptName,
       'ScriptUrl'    => $ScriptUrl,
       'Administrator' => ''
    );

   // number of user-defined external links, i.e. "[1]"
   define("NUM_LINKS", 12);

   // try this many times if the dbm is unavailable
   define("MAX_DBM_ATTEMPTS", 20);

   // constants for flags in $pagehash
   define("FLAG_PAGE_LOCKED", 1);

   // Some useful regexps.
   /*
    * A pagename can contain any printable characters from ISO Latin1
    * except for the characters [, ], \, or |.  It may also contain spaces,
    * but the pagename may not begin or end with a space.
    *
    * Some issues: tabs? multiple adjacent spaces? backslashses?
    *              maximum length of a page name?
    */
   define('PAGENAME_REGEXP', '(?!\s)[ !-Z^-{}~\xa1-\xff]{1,100}(?<!\s)');
   define('SAFE_URL_REGEXP', '(?:' . $AllowedProtocols . '):[^][\s<>"()]+');
?>
