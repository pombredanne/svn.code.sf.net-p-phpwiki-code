<?php
rcs_id('$Id: IniConfig.php,v 1.3 2004-04-19 23:13:03 zorloc Exp $');

/**************************************************************************
 * A configurator intended to read it's config from a PHP-style INI file,
 * instead of a PHP file.
 *
 * Pass a filename to the IniConfig() function and it will read all it's
 * definitions from there, all by itself, and proceed to do a mass-define
 * of all valid PHPWiki config items.  In this way, we can hopefully be
 * totally backwards-compatible with the old index.php method, while still
 * providing a much tastier on-going experience.
 *
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
 */

/** TODO
 * - Convert the value lists to provide defaults, so that every "if
 *      (defined())" and "if (!defined())" can fuck off to the dismal hole
 *      it belongs in.
 *
 * - Resurrect the larger "config object" code (in config/) so it'll aid the
 *      GUI config writers, and allow us to do proper validation and default
 *      value handling.
 *
 * - Get rid of WikiNameRegexp and KeywordLinkRegexp as globals by finding
 *      everywhere that uses them as variables and modify the code to use
 *      them as constants.  Will involve hacking around
 *      pcre_fix_posix_classes (probably with redefines()).
 */
 
// List of all valid config options to be define()d which take "values" (not
// booleans). Needs to be categorised, and generally made a lot tidier.
$_IC_VALID_VALUE = array('WIKI_NAME', 'ADMIN_USER', 'ADMIN_PASSWD',
        'HTML_DUMP_SUFFIX', 'MAX_UPLOAD_SIZE', 'MINOR_EDIT_TIMEOUT',
        'ACCESS_LOG', 'CACHE_CONTROL', 'CACHE_CONTROL_MAX_AGE',
        'PASSWORD_LENGTH_MINIMUM', 'USER_AUTH_POLICY', 'LDAP_AUTH_HOST',
        'LDAP_BASE_DN', 'LDAP_AUTH_USER', 'LDAP_AUTH_PASSWORD',
        'LDAP_SEARCH_FIELD', 'IMAP_AUTH_HOST', 'POP3_AUTH_HOST',
        'POP3_AUTH_PORT', 'AUTH_USER_FILE', 'AUTH_SESS_USER', 
        'AUTH_SESS_LEVEL', 'GROUP_METHOD',
        'AUTH_GROUP_FILE', 'EDITING_POLICY', 'THEME', 'CHARSET',
        'DEFAULT_LANGUAGE', 'WIKI_PGSRC', 'DEFAULT_WIKI_PGSRC',
        'ALLOWED_PROTOCOLS', 'INLINE_IMAGES', 'SUBPAGE_SEPARATOR',
        'INTERWIKI_MAP_FILE', 'COPYRIGHTPAGE_TITLE', 'COPYRIGHTPAGE_URL',
        'AUTHORPAGE_TITLE', 'AUTHORPAGE_URL', 'SERVER_NAME', 'SERVER_PORT',
        'SCRIPT_NAME', 'DATA_PATH', 'PHPWIKI_DIR', 'VIRTUAL_PATH');

// List of all valid config options to be define()d which take booleans.
$_IC_VALID_BOOL = array('DEBUG', 'ENABLE_USER_NEW', 'JS_SEARCHREPLACE',
        'ENABLE_REVERSE_DNS', 'ENCRYPTED_PASSWD', 'ZIPDUMP_AUTH', 
        'ENABLE_RAW_HTML', 'STRICT_MAILABLE_PAGEDUMPS', 'COMPRESS_OUTPUT',
        'WIKIDB_NOCACHE_MARKUP', 'ALLOW_ANON_USER', 'ALLOW_ANON_EDIT',
        'ALLOW_BOGO_LOGIN', 'ALLOW_USER_PASSWORDS',
        'AUTH_USER_FILE_STORABLE', 'ALLOW_HTTP_AUTH_LOGIN',
        'ALLOW_USER_LOGIN', 'ALLOW_LDAP_LOGIN', 'ALLOW_IMAP_LOGIN',
        'WARN_NONPUBLIC_INTERWIKIMAP', 'USE_PATH_INFO',
        'DISABLE_HTTP_REDIRECT');

function IniConfig($file)
{
        require_once('lib/pear/Config.php');
        
        $config = new Config();
        $root = &$config->parseConfig($file, 'inicommented');
        $out = $root->toArray();

        $rs = &$out['root'];

        global $_IC_VALID_VALUE, $_IC_VALID_BOOL;

        foreach ($_IC_VALID_VALUE as $item)
        {
                if (array_key_exists($item, $rs))
                {
                        define($item, $rs[$item]);
                }
        }

        // Boolean options are slightly special - if they're set to any of
        // 'false', '0', or 'no' (all case-insentitive) then the value will
        // be a boolean false, otherwise if there is anything set it'll
        // be true.
        foreach ($_IC_VALID_BOOL as $item)
        {
                if (array_key_exists($item, $rs))
                {
                        $val = $rs[$item];
                        if (!$val)
                        {
                                define($item, false);
                        }
                        else if (strtolower($val) == 'false' ||
                                strtolower($val) == 'no' ||
                                $val == '0')
                        {
                                define($item, false);
                        }
                        else
                        {
                                define($item, true);
                        }
                }
        }

        // Special handling for some config options
        if ($val = @$rs['INCLUDE_PATH'])
        {
                ini_set('include_path', $val);
        }

        if ($val = @$rs['SESSION_SAVE_PATH'])
        {
                ini_set('session.save_path', $val);
        }

        // Database
        global $DBParams;
        $DBParams['dbtype'] = @$rs['DATABASE_TYPE'];
        $DBParams['prefix'] = @$rs['DATABASE_PREFIX'];
        $DBParams['dsn'] = @$rs['DATABASE_DSN'];
        $DBParams['db_session_table'] = @$rs['DATABASE_SESSION_TABLE'];
        $DBParams['dba_handler'] = @$rs['DATABASE_DBA_HANDLER'];
        $DBParams['directory'] = @$rs['DATABASE_DIRECTORY'];
        $DBParams['timeout'] = @$rs['DATABASE_TIMEOUT'];
        if ($DBParams['dbtype'] == 'SQL' && $DBParams['db_session_table'])
        {
                define('USE_DB_SESSION', true);
        }

        // Expiry stuff
        global $ExpiryParams;

        $ExpiryParams['major'] = array(
                        'max_age' => @$rs['MAJOR_MAX_AGE'],
                        'min_age' => @$rs['MAJOR_MIN_AGE'],
                        'min_keep' => @$rs['MAJOR_MIN_KEEP'],
                        'keep' => @$rs['MAJOR_KEEP'],
                        'max_keep' => @$rs['MAJOR_MAX_KEEP']
                                );

        $ExpiryParams['minor'] = array(
                        'max_age' => @$rs['MINOR_MAX_AGE'],
                        'min_age' => @$rs['MINOR_MIN_AGE'],
                        'min_keep' => @$rs['MINOR_MIN_KEEP'],
                        'keep' => @$rs['MINOR_KEEP'],
                        'max_keep' => @$rs['MINOR_MAX_KEEP']
                                );

        $ExpiryParams['author'] = array(
                        'max_age' => @$rs['AUTHOR_MAX_AGE'],
                        'min_age' => @$rs['AUTHOR_MIN_AGE'],
                        'min_keep' => @$rs['AUTHOR_MIN_KEEP'],
                        'keep' => @$rs['AUTHOR_KEEP'],
                        'max_keep' => @$rs['AUTHOR_MAX_KEEP']
                                );

        // User authentication
        global $USER_AUTH_ORDER;
        $USER_AUTH_ORDER = preg_split('/\s*:\s*/', @$rs['USER_AUTH_ORDER']);

        // LDAP bind options
        global $LDAP_SET_OPTION;
        $optlist = preg_split('/\s*:\s*/', @$rs['LDAP_SET_OPTION']);
        foreach ($optlist as $opt)
        {
                $bits = preg_split('/\s*=\s*/', $opt, 2);

                if (count($bits) == 2)
                {
                        $LDAP_SET_OPTION[$bits[0]] = $bits[1];
                }
                else
                {
                        // Possibly throw some sort of error?
                }
        }

        // Now it's the external DB authentication stuff's turn
        global $DBAuthParams;
        $DBAP_MAP = array('DBAUTH_AUTH_DSN' => 'auth_dsn',
                        'DBAUTH_AUTH_CHECK' => 'auth_check',
                        'DBAUTH_AUTH_USER_EXISTS' => 'auth_user_exists',
                        'DBAUTH_AUTH_CRYPT_METHOD' => 'auth_crypt_method',
                        'DBAUTH_AUTH_UPDATE' => 'auth_update',
                        'DBAUTH_AUTH_CREATE' => 'auth_create',
                        'DBAUTH_PREF_SELECT' => 'pref_select',
                        'DBAUTH_PREF_UPDATE' => 'pref_update',
                        'DBAUTH_IS_MEMBER' => 'is_member',
                        'DBAUTH_GROUP_MEMBERS' => 'group_members',
                        'DBAUTH_USER_GROUPS' => 'user_groups'
                );

        foreach ($DBAP_MAP as $rskey => $apkey)
        {
                $val = @$rs[$rskey];
                if ($val)
                {
                        $DBAuthParams[$apkey] = $val;
                }
        }

        // Default Wiki pages
        global $GenericPages;
        $GenericPages = preg_split('/\s*:\s*/', @$rs['DEFAULT_WIKI_PAGES']);

        // Wiki name regexp.  Should be a define(), but too many places want
        // to use it as a variable for me to be bothered changing them all.
        // Major TODO item, there.
        global $WikiNameRegexp;
        $WikiNameRegexp = @$rs['WIKI_NAME_REGEXP'];

        // Another "too-tricky" redefine
        global $KeywordLinkRegexp;
        $keywords = preg_split('/\s*:\s*/', @$rs['KEYWORDS']);
        $KeywordLinkRegexp = '(?<=' . implode('|^', $keywords) . ')[[:upper:]].*$';
        
        global $DisabledActions;
        $DisabledActions = preg_split('/\s*:\s*/', @$rs['DISABLED_ACTIONS']);

}