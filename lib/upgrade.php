<?php //-*-php-*-
rcs_id('$Id: upgrade.php,v 1.6 2004-05-03 15:05:36 rurban Exp $');

/*
 Copyright 2004 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */


/**
 * Upgrade the WikiDB and config settings after installing a new 
 * PhpWiki upgrade.
 * Status: experimental, no queries for verification yet, no db update,
 *         no merge conflict
 * Installation on an existing PhpWiki database needs some 
 * additional worksteps. Each step will require multiple pages.
 *
 * This is the plan:
 *  1. Check for new or changed database schema and update it 
 *     according to some predefined upgrade tables. (medium)
 *  2. Check for new or changed (localized) pgsrc/ pages and ask 
 *     for upgrading these. Check timestamps, upgrade silently or 
 *     show diffs if existing. Overwrite or merge (easy)
 *  3. Check for new or changed or deprecated index.php settings
 *     and help in upgrading these. (hard)
 *  4. Check for changed plugin invocation arguments. (hard)
 *  5. Check for changed theme variables. (hard)
 *
 * @author: Reini Urban
 */
require_once("lib/loadsave.php");

// see loadsave.php for saving new pages.
function CheckPgsrcUpdate(&$request) {
    echo "<h3>",_("check for necessary pgsrc updates"),"</h3>\n";
    $dbi = $request->getDbh(); 
    $path = FindLocalizedFile(WIKI_PGSRC);
    $pgsrc = new fileSet($path);
    // fixme: verification, ...
    foreach ($pgsrc->getFiles() as $filename) {
        if (substr($filename,-1,1) == '~') continue;
        $pagename = urldecode($filename);
        $page = $dbi->getPage($pagename);
        if ($page->exists()) {
            // check mtime: update automatically if pgsrc is newer
            $rev = $page->getCurrentRevision();
            $page_mtime = $rev->get('mtime');
            $data  = implode("", file($path."/".$filename));
            if (($parts = ParseMimeifiedPages($data))) {
                usort($parts, 'SortByPageVersion');
                reset($parts);
                $pageinfo = $parts[0];
                $stat  = stat($path."/".$filename);
                $new_mtime = @$pageinfo['versiondata']['mtime'];
                if (!$new_mtime)
                    $new_mtime = @$pageinfo['versiondata']['lastmodified'];
                if (!$new_mtime)
                    $new_mtime = @$pageinfo['pagedata']['date'];
                if (!$new_mtime)
                    $new_mtime = $stat[9];
                if ($new_mtime > $page_mtime) {
                    echo "$path/$pagename: newer than the existing page. replace ($new_mtime &gt; $page_mtime)<br />\n";
                    LoadAny($request,$path."/".$filename);
                    echo "<br />\n";
                } else {
                    echo "$path/$pagename: older than the existing page. skipped.<br />\n";
                }
            } else {
                echo "$path/$pagename: unknown format, skipped.<br />\n";
            }
        } else {
            echo "$pagename does not exist<br />\n";
            LoadAny($request,$path."/".$filename);
            echo "<br />\n";
        }
    }
    return;
}

/**
 * Search table definition in appropriate schema
 * and create it.
 * Supported: mysql
 */
function installTable(&$dbh, $table, $backend_type) {
    global $DBParams;
    if (!in_array($DBParams['dbtype'],array('SQL','ADODB'))) return;
    echo _("MISSING")," ... \n";
    $backend = &$dbh->_backend->_dbh;
    $schema = findFile("schemas/${backend_type}.sql");
    if (!$schema) {
        echo "  ",_("FAILED"),": ",sprintf(_("no schema %s found"),"schemas/${backend_type}.sql")," ... <br />\n";
        return false;
    }
    switch ($table) {
    case 'session': 
        break;
    case 'user':
        break;
    case 'pref':
        break;
    case 'member':
        break;
    }
    echo "  ",_("FAILED"),": ",_("not yet implemented")," ... <br />\n";
}

/**
 * currently update only session, user, pref and member
 * jeffs-hacks database api (around 1.3.2) later
 *   people should export/import their pages if using that old versions.
 */
function CheckDatabaseUpdate($request) {
    global $DBParams, $DBAuthParams;
    if (!in_array($DBParams['dbtype'],array('SQL','ADODB'))) return;
    echo "<h3>",_("check for necessary database updates"),"</h3>\n";
    $dbh = &$request->_dbi;
    $backend = &$dbh->_backend->_dbh;
    if ($DBParams['dbtype'] == 'SQL') {
        $tables = $backend->getListOf('tables');
        $backend_type = $backend->phptype;
    } elseif ($DBParams['dbtype'] == 'ADODB') {
        $tables = $backend->MetaTables();
        $backend_type = $backend->databaseType;
    }
    $prefix = isset($DBParams['prefix']) ? $DBParams['prefix'] : '';
    extract($dbh->_backend->_table_names);
    foreach (explode(':','session:user:pref:member') as $table) {
        echo _("check for table $table")," ...";    	
    	if (!in_array($table,$tables)) {
    	    if ($prefix and !in_array($prefix.$table,$tables)) {
    	        installTable(&$dbh, $prefix.$table, $backend_type);
    	    } else {
    	    	installTable(&$dbh, $table, $backend_type);
    	    }
    	} else 
    	    echo "OK <br />\n";
    }
    // 1.3.8 added session.sess_ip
    if (phpwiki_version() >= 1030.08 and USE_DB_SESSION and isset($request->_dbsession)) {
  	echo _("check for new session.sess_ip column")," ...";
  	$database = $DBParams['dbtype'] == 'ADODB' ? $backend->database : $backend->dsn['database'];
        $session_tbl = $prefix . $DBParams['db_session_table'];
  	$fields = mysql_list_fields($database,$session_tbl);
  	$columns = mysql_num_fields($fields);
        for ($i = 0; $i < $columns; $i++) {
            $sess_fields[] = mysql_field_name($fields, $i);
        }
        mysql_free_result($fields);
        if (!in_array("sess_ip",$sess_fields)) {
            echo "<b>",_("ADDING"),"</b>"," ... ";		
            mysql_query("ALTER TABLE $session_tbl ADD CHAR(15) NOT NULL")
                or trigger_error("<br />\nSQL Error: ".mysql_error(),E_USER_WARNING);
        } else {
            echo _("OK");
        }
        echo "<br />\n";
    }
    // 1.3.10 mysql requires page.id auto_increment
    // mysql, mysqli or mysqlt
    if (phpwiki_version() >= 1030.10 and substr($backend_type,0,5) == 'mysql') {
  	echo _("check for page.id auto_increment flag")," ...";
  	$database = $DBParams['dbtype'] == 'ADODB' ? $backend->database : $backend->dsn['database'];
  	$fields = mysql_list_fields($database,$page_tbl);
  	$columns = mysql_num_fields($fields); 
        for ($i = 0; $i < $columns; $i++) {
            if (mysql_field_name($fields, $i) == 'id') {
            	$flags = mysql_field_flags($fields, $i);
            	if (!strstr($flags,"auto_increment")) {
                    echo "<b>",_("ADDING"),"</b>"," ... ";		
                    // MODIFY col_def valid since mysql 3.22.16,
                    // older mysql's need CHANGE old_col col_def
                    mysql_query("ALTER TABLE $page_tbl CHANGE id id INT NOT NULL AUTO_INCREMENT")
                      or trigger_error("<br />\nSQL Error: ".mysql_error(),E_USER_WARNING);
                    $fields = mysql_list_fields($database,$page_tbl);
                    if (!strstr(mysql_field_flags($fields, $i),"auto_increment"))
                        echo " <b><font color=\"red\">",_("FAILED"),"</font></b><br />\n";		
                    else     
                        echo _("OK"),"<br />\n";            		
            	} else {
                    echo _("OK"),"<br />\n";            		
            	}
            	break;
            }
        }
        mysql_free_result($fields);
    }
    return;
}

/**
 * Upgrade: Base class for multipage worksteps
 * identify, validate, display options, next step
 */
class Upgrade {
}

class Upgrade_CheckPgsrc extends Upgrade {
}

class Upgrade_CheckDatabaseUpdate extends Upgrade {
}

// TODO: At which step are we? 
// validate and do it again or go on with next step.

/** entry function from lib/main.php
 */
function DoUpgrade($request) {

    if (!$request->_user->isAdmin()) {
        $request->_notAuthorized(WIKIAUTH_ADMIN);
        $request->finish(
                         HTML::div(array('class' => 'disabled-plugin'),
                                   fmt("Upgrade disabled: user != isAdmin")));
        return;
    }

    StartLoadDump($request, _("Upgrading this PhpWiki"));
    CheckDatabaseUpdate($request);
    CheckPgsrcUpdate($request);
    //CheckThemeUpdate($request);
    EndLoadDump($request);
}


/**
 $Log: not supported by cvs2svn $
 Revision 1.4  2004/05/02 21:26:38  rurban
 limit user session data (HomePageHandle and auth_dbi have to invalidated anyway)
   because they will not survive db sessions, if too large.
 extended action=upgrade
 some WikiTranslation button work
 revert WIKIAUTH_UNOBTAINABLE (need it for main.php)
 some temp. session debug statements

 Revision 1.3  2004/04/29 22:33:30  rurban
 fixed sf.net bug #943366 (Kai Krakow)
   couldn't load localized url-undecoded pagenames

 Revision 1.2  2004/03/12 15:48:07  rurban
 fixed explodePageList: wrong sortby argument order in UnfoldSubpages
 simplified lib/stdlib.php:explodePageList

 */

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
