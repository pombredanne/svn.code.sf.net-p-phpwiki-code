-- $Id: oci8-destroy.sql,v 1.1 2004-07-22 16:50:07 dfrankow Exp $

set verify off
set feedback off

--================================================================
-- Prefix for table names.
--
-- You should set this to the same value you specify for
-- $DBParams['prefix'] in index.php.
--
-- You have to use a prefix, because some phpWiki tablenames are 
-- Oracle reserved words!

define prefix=phpwiki_

--================================================================
--
-- Don't modify below this point unless you know what you are doing.
--
--================================================================

--================================================================
-- Note on Oracle datatypes...
-- 
-- Most of the 'NOT NULL' constraints on the character columns have been 
-- 	dropped since they can contain empty strings which are seen by 
--	Oracle as NULL.
-- Oracle CLOBs are used for TEXTs/MEDUIMTEXTs columns.


prompt Initializing PhpWiki tables with:
prompt        prefix =  &prefix
prompt 
prompt Expect some 'ORA-00942: table or view does not exist' unless you are
prompt overwriting existing tables.
prompt 

define page_tbl=&prefix.page
define page_id=&prefix.page_id
define page_nm=&prefix.page_nm

define version_tbl=&prefix.version
define vers_id=&prefix.vers_id
define vers_mtime=&prefix.vers_mtime

define recent_tbl=&prefix.recent
define recent_id=&prefix.recent_id

define nonempty_tbl=&prefix.nonempty
define nonmt_id=&prefix.nonmt_id

define link_tbl=&prefix.link
define link_from=&prefix.link_from
define link_to=&prefix.link_to

define session_tbl=&prefix.session
define sess_id=&prefix.sess_id
define sess_date=&prefix.sess_date
define sess_ip=&prefix.sess_ip

define pref_tbl=&prefix.pref
define pref_id=&prefix.pref_id

define user_tbl=&prefix.user
define user_id=&prefix.user_id

define member_tbl=&prefix.member
define member_userid=&prefix.member_userid
define member_groupname=&prefix.member_groupname

define rating_tbl=&prefix.rating
define rating_id=&prefix.rating_id


prompt Dropping &page_tbl
DROP TABLE &page_tbl;

prompt Dropping &version_tbl
DROP TABLE &version_tbl;

prompt Dropping &recent_tbl
DROP TABLE &recent_tbl;

prompt Dropping &nonempty_tbl
DROP TABLE &nonempty_tbl;

prompt Dropping &link_tbl
DROP TABLE &link_tbl;

prompt Dropping &session_tbl
DROP TABLE &session_tbl;

-- Optional DB Auth and Prefs
-- For these tables below the default table prefix must be used 
-- in the DBAuthParam SQL statements also.

prompt Dropping &pref_tbl
DROP TABLE &pref_tbl;

-- better use the extra pref table where such users can be created easily 
-- without password.

prompt Dropping &user_tbl
DROP TABLE &user_tbl;

prompt Dropping &member_tbl
DROP TABLE &member_tbl;

-- if you plan to use the wikilens theme
prompt Dropping &rating_tbl
DROP TABLE &rating_tbl;
