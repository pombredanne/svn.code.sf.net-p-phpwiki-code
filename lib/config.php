<?php
   if (!function_exists('rcs_id')) {
      function rcs_id($id) { echo "<!-- $id -->\n"; };
   }
   rcs_id('$Id: config.php,v 1.9 2000-10-28 17:44:00 ahollosi Exp $');

   /*
      Constants and settings. Edit the values below for
      your site. You need two image files, a banner and 
      a signature. The dbm file MUST be writable by the
      web server or this won't work. If you configure your
      server to allow index.php as an index file, you 
      can just give the URL without the script name.
   */

   // If you need to access your Wiki from assorted locations and
   // you use DHCP, this setting might work for you:

   //$ServerAddress = "http:";

   // It works quite well thanks to relative URIs. (Yes, that's just
   // 'http:'). If find that you want an explicit address (recommended), 
   // you can set one yourself by changing and uncommenting:

   //$ServerAddress = "http://your.hostname.org/phpwiki/";

   // Or you could use the if/else statement below to deduce
   // the $ServerAddress dynamically. (Default)

   if (preg_match("#(.*?)([^/]*$)#", $REQUEST_URI, $matches)) {
      $ServerAddress = "http://$SERVER_NAME:$SERVER_PORT" . $matches[1];
   } else {
      $ServerAddress = "http://$SERVER_NAME:$SERVER_PORT$REQUEST_URI";
   }

   //  Select your language here
 
   $LANG="C"; // (What should be the) Default: English
   // $LANG="nl";  // We all speak dutch, no?
   // $LANG="es";  // We all speak spanish, no?

   if (!function_exists ('gettext')) {
      $lcfile = "locale/$LANG/LC_MESSAGES/phpwiki.php";
      if(file_exists($lcfile)) {
         include($lcfile);
      } else {
         $locale = array();
      }

      function gettext ($text) { 
         global $locale;
         if (!empty ($locale[$text]))
           return $locale[$text];
         return $text;
      }
   } else {
      putenv ("LANG=$LANG");
      bindtextdomain ("phpwiki", "./locale");
      textdomain ("phpwiki");
   }

   // if you are using MySQL instead of a DBM to store your
   // Wiki pages, use mysql.php instead of dbmlib.php
   // See INSTALL.mysql for details on using MySQL

   // if you are using Postgressl instead of a DBM to store your
   // Wiki pages, use pgsql.php instead of dbmlib.php
   // See INSTALL.pgsql for details on using Postgresql

   // if you are using mSQL instead of a DBM to store your
   // Wiki pages, use msql.php instead of dbmlib.php
   // See INSTALL.mysql for details on using mSQL


   // DBM settings (default)
   include "lib/dbmlib.php";
   $DBMdir = "/tmp";
   $WikiPageStore = "wiki";
   $ArchivePageStore = "archive";
   $WikiDB['wiki']      = "$DBMdir/wikipagesdb";
   $WikiDB['archive']   = "$DBMdir/wikiarchivedb";
   $WikiDB['wikilinks'] = "$DBMdir/wikilinksdb";
   $WikiDB['hottopics'] = "$DBMdir/wikihottopicsdb";
   $WikiDB['hitcount']  = "$DBMdir/wikihitcountdb";

/*
   // MySQL settings (thanks Arno Hollosi! <ahollosi@iname.com>)
   // Comment out the lines above (for the DBM) if you use these
   include "lib/mysql.php";
   $WikiPageStore = "wiki";
   $ArchivePageStore = "archive";
   $mysql_server = 'localhost';
   $mysql_user = 'root';
   $mysql_pwd = '';
   $mysql_db = 'wiki';
*/

/*
   // PostgreSQL settings. 
   include "lib/pgsql.php";
   $WikiDataBase  = "wiki"; // name of the database in Postgresql
   $WikiPageStore = "wiki"; // name of the table where pages are stored
   $ArchivePageStore = "archive"; // name of the table where pages are archived
   $WikiLinksPageStore = "wikilinks";
   $HotTopicsPageStore = "hottopics";
   $HitCountPageStore = "hitcount";
   $pg_dbhost    = "localhost";
   $pg_dbport    = "5432";
*/


/*
   // MiniSQL (mSQL) settings.
   include "lib/msql.php";
   $msql_db = "wiki";
   // should be the same as wikipages.line
   define("MSQL_MAX_LINE_LENGTH", 128);
   $WikiPageStore = array();
   $ArchivePageStore = array();

   $WikiPageStore['table']         = "wiki";
   $WikiPageStore['page_table']    = "wikipages";
   $ArchivePageStore['table']      = "archive";
   $ArchivePageStore['page_table'] = "archivepages";
   // end mSQL settings
*/

/*
   // Filesystem DB settings
   include "lib/db_filesystem.php";
   $DBdir = "/tmp/wiki";
   $WikiPageStore = "wiki";
   $ArchivePageStore = "archive";
   $WikiDB['wiki']      = "$DBdir/pages";
   $WikiDB['archive']   = "$DBdir/archive";
   $WikiDB['wikilinks'] = "$DBdir/links";
   $WikiDB['hottopics'] = "$DBdir/hottopics";
   $WikiDB['hitcount']  = "$DBdir/hitcount";
   // End Filsystem Settings
*/


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
   define('WIKI_PGSRC', gettext("./pgsrc")); // Default (old) behavior.
   //define('WIKI_PGSRC', './wiki.zip'); // New style.

   // DEFAULT_WIKI_PGSRC is only used when the language is *not*
   // the default (English) and when reading from a directory:
   // in that case some English pages are inserted into the wiki as well
   // DEFAULT_WIKI_PGSRC defines where the English pages reside 
   define('DEFAULT_WIKI_PGSRC', "./pgsrc");
  
   $ScriptName = "index.php";

   $SignatureImg = "images/signature.png";
   $logo = "images/wikibase.png";

   // Template files (filenames are relative to script position)
   $templates = array(
   	"BROWSE" =>    gettext("templates/browse.html"),
	"EDITPAGE" =>  gettext("templates/editpage.html"),
	"EDITLINKS" => gettext("templates/editlinks.html"),
	"MESSAGE" =>   gettext("templates/message.html")
	);

   // date & time formats used to display modification times, etc.
   // formats are given as format strings to PHP date() function
   $datetimeformat = "F j, Y";	// may contain time of day
   $dateformat = "F j, Y";	// must not contain time

   // allowed protocols for links - be careful not to allow "javascript:"
   $AllowedProtocols = "http|https|mailto|ftp|news|gopher";

   // URLs ending with the following extension should be inlined as images
   $InlineImages = "png|jpg|gif";

   // Perl regexp for WikiNames
   $WikiNameRegexp = "\b([A-Z][a-z]+){2,}\b";

   // this defines how many page names to list when displaying
   // the MostPopular pages; i.e. setting this to 20 will show
   // the 20 most popular pages
   define("MOST_POPULAR_LIST_LENGTH", 20);

   // this defines how many page names to list when displaying
   // scored related pages
   define("NUM_RELATED_PAGES", 5);

   // number of user-defined external links, i.e. "[1]"
   define("NUM_LINKS", 12);

   // try this many times if the dbm is unavailable
   define("MAX_DBM_ATTEMPTS", 20);


   //////////////////////////////////////////////////////////////////////

   // you shouldn't have to edit anyting below this line

   $ScriptUrl = $ServerAddress . $ScriptName;
   $LogoImage = "<img src='${ServerAddress}$logo' border='0'>";
   $LogoImage = "<a href='$ScriptUrl'>$LogoImage</a>";

   $FieldSeparator = "\263";

   // Apache won't show REMOTE_HOST unless the admin configured it
   // properly. We'll be nice and see if it's there.
   empty($REMOTE_HOST) ?
      ($remoteuser = $REMOTE_ADDR) : ($remoteuser = $REMOTE_HOST);

   // constants used for HTML output. List tags like UL and 
   // OL have a depth of one, PRE has a depth of 0.
   define("ZERO_DEPTH", 0);
   define("SINGLE_DEPTH", 1);

   // constants for flags in $pagehash
   define("FLAG_PAGE_LOCKED", 1);
?>
