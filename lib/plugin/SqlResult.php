<?php // -*-php-*-
rcs_id('$Id: SqlResult.php,v 1.3 2004-09-06 08:36:28 rurban Exp $');
/*
 Copyright 2004 $ThePhpWikiProgrammingTeam
 
 This file is (not yet) part of PhpWiki.

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
 * This plugin displays results of arbitrary SQL select statements 
 * in table form.
 * The database definition, the DSN, must be defined in the local file 
 * lib/plugin/SqlResult.ini
 *   A simple textfile with alias = dsn lines.
 *
 * Optional template file to format the result and handle some logic.
 * Template vars: %%where%%, %%sortby%%, %%limit%%
 * TODO: paging
 *
 * Usage:
 *   <?plugin SqlResult alias=mysql
 *            SELECT 'mysql password for string "xx":',
 *                   PASSWORD('xx')
 *   ?>
 *   <?plugin SqlResult alias=videos template=videos
 *            SELECT rating,title,date 
 *                   FROM video 
 *                   ORDER BY rating DESC 
 *                   LIMIT 5
 *   ?>
  <?plugin SqlResult alias=imdb template=imdbmovies where||="Davies, Jeremy%"
SELECT m.title, m.date, n.name, c.role
  FROM movies as m, names as n, jobs as j, characters as c
  WHERE n.name LIKE "%%where%%"
  AND m.title_id = c.title_id
  AND n.name_id = c.name_id
  AND c.job_id = j.job_id
  AND j.description = 'Actor'
  ORDER BY m.date DESC
?>
 *
 * @author: ReiniUrban
 */

class WikiPlugin_SqlResult
extends WikiPlugin
{
    var $_args;	
    
    function getName () {
        return _("SqlResult");
    }

    function getDescription () {
        return _("Display arbitrary SQL result tables");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.3 $");
    }

    function getDefaultArguments() {
        return array(
                     'alias'       => false, // DSN database specification
                     'ordered'     => false, // if to display as <ol> list: single col only without template
                     'template'    => false, // use a custom <theme>/template.tmpl
                     'where'       => false, // custom filter for the query
                     'sortby'      => false, // for paging, default none
                     'limit'       => false, // for paging, default: only the first 50 
                    );
    }

    function getDsn($alias) {
        $ini = parse_ini_file(FindFile("lib/plugin/SqlResult.ini"));
        return $ini[$alias];
    }

    /** Get the SQL statement from the rest of the lines
     */
    function handle_plugin_args_cruft($argstr, $args) {
    	$this->_sql = str_replace("\n"," ",$argstr);
        return;
    }
   
    function run($dbi, $argstr, &$request, $basepage) {
        global $DBParams;
    	//$request->setArg('nocache','1');
        extract($this->getArgs($argstr, $request));
        if (!$alias)
            return $this->error(_("No DSN alias for SqlResult.ini specified"));
	$sql = $this->_sql;

        // apply custom filters
        if ($where and strstr($sql,"%%where%%"))
            $sql = str_replace("%%where%%", $where, $sql);
        if (strstr($sql,"%%limit%%")) // default: only the first 50 
            $sql = str_replace("%%limit%%", $limit ? $limit : "0,50", $sql);
        else {
            if (strstr($sql,"LIMIT")) // default: only the first 50 
                $sql = str_replace("%%limit%%", $limit ? $limit : "0,50", $sql);
            else
                $sql .= " LIMIT 0,50";
        }
        if (strstr($sql,"%%sortby%%")) {
            if (!$sortby)
                $sql = preg_replace("/ORDER BY .*%%sortby%%\s/m", "", $sql);
            else
                $sql = str_replace("%%sortby%%", $sortby, $sql);
        }

        $inidsn = $this->getDsn($alias);
        if (!$inidsn)
            return $this->error(sprintf(_("No DSN for alias %s in SqlResult.ini found"),
                                        $alias));
        // adodb or pear? adodb as default, since we distribute per default it. 
        // for pear there may be overrides.
        if ($DBParams['dbtype'] == 'SQL') {
            $dbh = DB::connect($inidsn);
            $all = $dbh->getAll($sql);
        } else {
            if ($DBParams['dbtype'] != 'ADODB') {
                // require_once('lib/WikiDB/adodb/adodb-errorhandler.inc.php');
                require_once('lib/WikiDB/adodb/adodb.inc.php');
            }
            $parsed = parseDSN($inidsn);
            $dbh = &ADONewConnection($parsed['phptype']); 
            $conn = $dbh->Connect($parsed['hostspec'],$parsed['username'], 
                                  $parsed['password'], $parsed['database']); 
            $GLOBALS['ADODB_FETCH_MODE'] = ADODB_FETCH_ASSOC;
            $dbh->SetFetchMode(ADODB_FETCH_ASSOC);

            $all = $dbh->getAll($sql);

            $GLOBALS['ADODB_FETCH_MODE'] = ADODB_FETCH_NUM;
            $dbh->SetFetchMode(ADODB_FETCH_NUM);
        }

        // if ($limit) ; // TODO: fill paging vars (see PageList)

        if ($template) {
            $args = array('SqlResult' => $all,   // the resulting array of rows
                          'ordered' => $ordered, // whether to display as <ul>/<dt> or <ol> 
                          'where'   => $where,
                          'sortby'  => $sortby,  // paging params (could also be taken from request...)
                          'limit'   => $limit);
            return Template($template, $args);
        } else {
            // if ($limit) ; // do paging via pagelink template
            if ($ordered) {
                $html = HTML::ol(array('class'=>'sqlresult'));
                foreach ($all as $row) {
                    $html->pushContent(HTML::li(array('class'=> $i++ % 2 ? 'evenrow' : 'oddrow'), $row[0]));
                }
            } else {
                $html = HTML::table(array('class'=>'sqlresult'));
                $i = 0;
                foreach ($all as $row) {
                    $tr = HTML::tr(array('class'=> $i++ % 2 ? 'evenrow' : 'oddrow'));
                    foreach ($row as $col) {
                        $tr->pushContent(HTML::td($col));
                    }
                    $html->pushContent($tr);
                }
            }
            // if ($limit) ; // do paging via pagelink template
        }
        return $html;
    }

};

// $Log: not supported by cvs2svn $
// Revision 1.2  2004/05/03 21:57:47  rurban
// locale updates: we previously lost some words because of wrong strings in
//   PhotoAlbum, german rewording.
// fixed $_SESSION registering (lost session vars, esp. prefs)
// fixed ending slash in listAvailableLanguages/Themes
//
// Revision 1.1  2004/05/03 20:44:58  rurban
// fixed gettext strings
// new SqlResult plugin
// _WikiTranslation: fixed init_locale
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