<?php // -*-php-*-
rcs_id('$Id: flatfile.php,v 1.1 2007-01-02 13:19:47 rurban Exp $');

/**
 Copyright 1999,2005,2006 $ThePhpWikiProgrammingTeam

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
 * Backend for handling file storage as pure, readable flatfiles, 
 * as with PageDump. All other methods are taken from file, which handles serialized pages.
 *
 * Author: Reini Urban, based on the file backend by Jochen Kalmbach
 */

require_once('lib/WikiDB/backend/file.php');
require_once('lib/loadsave.php');

class WikiDB_backend_flatfile
extends WikiDB_backend_file
{
    // *********************************************************************
    // common file load / save functions:
    // FilenameForPage is from loadsave.php
    function _pagename2filename($type, $pagename, $version) {
    	 return $this->_dir_names[$type].'/'.FilenameForPage($pagename);
/*       if ($version == 0)
             return $this->_dir_names[$type].'/'.FilenameForPage($pagename);
         else
             return $this->_dir_names[$type].'/'.FilenameForPage($pagename).'--'.$version;
*/             
    }

    // Load/Save Page-Data
    function _loadPageData($pagename) {
       if ($this->_page_data != NULL) {
            if ($this->_page_data['pagename'] == $pagename) {
                return $this->_page_data;
             }
       }
        //$pd = $this->_loadPage('page_data', $pagename, 0);
        
       $filename = $this->_pagename2filename('page_data', $pagename, 0);
       if (!file_exists($filename)) return NULL;
       if (!filesize($filename)) return array();
       if ($fd = @fopen($filename, "rb")) {
	   $locked = flock($fd, 1); // Read lock
	   if (!$locked) { 
	       ExitWiki("Timeout while obtaining lock. Please try again"); 
	   }
	   if ($data = fread($fd, filesize($filename))) {

	       // This is the only difference from file:
	       if ($parts = ParseMimeifiedPages($data)) {
		   $pd = $parts[0];
	       }
	       //if ($set_pagename == true)
	       $pd['pagename'] = $pagename;
	       //if ($version != 0) $pd['version'] = $version;
	       if (!is_array($pd))
		   ExitWiki(sprintf(gettext("'%s': corrupt file"),
				    htmlspecialchars($filename)));
	   }
	   fclose($fd);
       }
        
       if ($pd != NULL)
            $this->_page_data = $pd;
       if ($this->_page_data != NULL) {
            if ($this->_page_data['pagename'] == $pagename) {
                return $this->_page_data;
             }
       }
       return array();  // no values found
    }
    
    function _saveVersionData($pagename, $version, $data) {
        // check if this is a newer version:
        if ($this->_getLatestVersion($pagename) < $version) {
            // write new latest-version-info
            $this->_setLatestVersion($pagename, $version);
            $this->_savePageData($pagename, $data);
        } else {
            $this->_savePage('ver_data', $pagename, $version, $data);
        }
    }
    
    // This is different to file.
    function _savePageData($pagename, $data) {

        $type = 'page_data';
        $version = 1;
        $filename = $this->_pagename2filename($type, $pagename, $version);

        // Construct a dummy page_revision object
        $page = new WikiDB_Page($this->_wikidb, $pagename);
        if (USECACHE and empty($data['pagedata'])) {
            $cache =& $this->_wikidb->_cache;
            if (!empty($cache->_pagedata_cache[$pagename]) 
                and is_array($cache->_pagedata_cache[$pagename])) 
            {
                $cachedata = &$cache->_pagedata_cache[$pagename];
                foreach($data as $key => $val)
                    $cachedata[$key] = $val;
            } else {
                $cache->_pagedata_cache[$pagename] = $data;
            }
        }
        //unset ($data['pagedata']);
        if (empty($data['versiondata']))
            $data['versiondata'] = false;
        $current = new WikiDB_PageRevision($wikidb, $pagename, $version, $data['versiondata']);
        unset ($data['versiondata']);
        /*if (!empty($data) and is_array($data))
            foreach ($data as $k => $v) {
                $current->_data["%$k"] = $v;
          }
        */
        $pagedata = "Date: " . Rfc2822DateTime($current->get('mtime')) . "\r\n";
        $pagedata .= sprintf("Mime-Version: 1.0 (Produced by PhpWiki %s)\r\n",
                         PHPWIKI_VERSION);
        $pagedata .= MimeifyPageRevision($page, $current);
        
        $len = strlen($pagedata);
        if ($fd = fopen($filename, 'a+b')) {
	    $locked = flock($fd, 2); // Exclusive blocking lock 
	    if (!$locked) { 
		ExitWiki("Timeout while obtaining lock. Please try again"); 
	    }
	    rewind($fd);
	    ftruncate($fd, 0);
	    $num = fwrite($fd, $pagedata, $len); 
	    assert($num == $len);
	    fclose($fd);
        } else {
	    ExitWiki("Error while writing page '$pagename'");
        }
    }
};

//class WikiDB_backend_flatfile_iter extends WikiDB_backend_file_iter {};

// $Log: not supported by cvs2svn $
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
