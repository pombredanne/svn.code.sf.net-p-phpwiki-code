<?php // -*-php-*-
rcs_id('$Id: _WikiTranslation.php,v 1.1 2004-03-14 16:45:10 rurban Exp $');
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
 * _WikiTranslation:  Display pagenames and other strings in various languages
 * Can be used to let a favorite translation service translate a whole page. 
 * Current favorite: translate.google.com
 *
 * Usage:   <?plugin _WikiTranslation what=pages ?>
 * @author:  Reini Urban
 *
 * TODO:
 *   other from_lang than en
 *   page translation
 */

require_once('lib/PageList.php');

class WikiPlugin__WikiTranslation
extends WikiPlugin
{
    function getName() {
        return _("_WikiTranslation");
    }

    function getDescription() {
        return _("Show translations of various words or pages");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return 
            array( 'languages'  => '',  // comma delimited string of de,en,sv,...
                   'string'     => '',  
                   'page'       => '',  // use a translation service
                   'what'       => 'pages', // or 'buttons', 'plugins' or 'wikiwords'

                   'from_lang'     => false,
                   'include_empty' => false,
                   'exclude'       => '',
                   'sortby'        => '',
                   'limit'         => 0,
                   'debug'         => false
                 );
    }

    //fixme: other from_lang than "en"
    function translate($text,$to_lang,$from_lang=false) {
        if (!$from_lang) $from_lang = $this->lang; // current locale
        if ($from_lang == $to_lang) return $text;
        if ($from_lang != 'en') {
            // FIXME! reverse gettext: translate to english
            trigger_error("Internal Error: Cannot yet translate into english");
            return $text;

            $en = $this->translate($text,'en',$from_lang);
            update_locale($to_lang);
            // and then to target
            $result = gettext($en);
            update_locale($from_lang);
        } else {
            if ($from_lang != $to_lang) {
                update_locale($to_lang);
            }
            $result = gettext($text);
            if ($from_lang != $to_lang) {
                update_locale($from_lang);
            }
        }
        return $result;
    }
                
    function run($dbi, $argstr, $request, $basepage) {
        extract($this->getArgs($argstr, $request));
        $this->request = &$request;
        if (!$from_lang) $from_lang = $request->getPref('lang');
        if (!$from_lang) $from_lang = $GLOBALS['LANG'];
        $this->lang = $from_lang;

        if (empty($languages)) {
            // from lib/plugin/UserPreferences.php
            $available_languages = array('en');
            $dir_root = 'locale/';
            if (defined('PHPWIKI_DIR'))
                $dir_root = PHPWIKI_DIR . "/$dir_root";
            $dir = dir($dir_root);
            if ($dir) {
                while($entry = $dir->read()) {
                    if (is_dir($dir_root.$entry)
                        && (substr($entry,0,1) != '.')
                        && $entry != 'po'
                        && $entry != 'CVS') {
                        array_push($available_languages, $entry);
                    }
                }
                $dir->close();
            }
            if (in_array($from_lang,$available_languages))
                $languages = $available_languages;
            else
            	$languages = array_merge($available_languages,array($from_lang));
        } elseif (strstr($languages,',')) {
            $languages = explode(',',$languages);
        } else {
            $languages = array($languages);
        }
        if (!empty($string)) {
            return $this->translate($string,$languages[0],$from_lang);
        }
        switch ($what) {
        case 'pages':
            $info = '';
            $pagelist = new PageList($info, $exclude, $this->getArgs($argstr, $request));
            //$pagelist->_columns[0]->_field = "custom:$from_lang";
            $pagelist->_columns[0]->_heading = "$from_lang";
            foreach ($languages as $lang) {
            	if ($lang == $from_lang) continue;
                $field = "custom:$lang";
                $column = new _PageList_Column_custom($field,$from_lang);
                $pagelist->_types["custom"] = $column;
                $pagelist->_addColumn($field);
            }
            $pagelist->addPages( $dbi->getAllPages($include_empty, $sortby, $limit) );
            break;
        }
        return $pagelist;
    }
};

class _PageList_Column_custom extends _PageList_Column {
    function _PageList_Column_custom($field, $from_lang) {
        $this->_field = $field;
        $this->_from_lang = $from_lang;
        $this->_iscustom = substr($field, 0, 7) == 'custom:';
        if ($this->_iscustom)
            $this->_field = substr($field, 7);
        $heading = $field;
        $this->dbi = &$GLOBALS['request']->getDbh();
        $this->_PageList_Column_base($field, $heading);
    }
    function _getValue($page_handle, &$revision_handle) {
        if (is_object($page_handle) and $this->dbi->isWikiPage($page_handle->getName()))
            return WikiLink(WikiPlugin__WikiTranslation::translate($page_handle->getName(),$this->_field,$this->_from_lang));
        else
            return WikiPlugin__WikiTranslation::translate($page_handle,$this->_field,$this->_from_lang);
    }
}

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
