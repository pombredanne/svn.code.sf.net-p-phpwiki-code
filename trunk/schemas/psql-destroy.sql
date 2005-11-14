-- $Id: psql-destroy.sql,v 1.7 2005-11-14 22:20:21 rurban Exp $

\set QUIET

--================================================================
-- Prefix for table names.
--
-- You should set this to the same value you specify for
-- $DBParams['prefix'] in index.php.

\set prefix 	''

--================================================================
--
-- Don't modify below this point unless you know what you are doing.
--
--================================================================

\set qprefix '\'' :prefix '\''
\echo Dropping all PhpWiki tables with:
\echo '       prefix = ' :qprefix
\echo

\set page_tbl		:prefix 'page'
\set page_id_seq 	:prefix 'page_id_seq'
\set version_tbl	:prefix 'version'
\set recent_tbl		:prefix 'recent'
\set nonempty_tbl	:prefix 'nonempty'
\set link_tbl		:prefix 'link'
\set session_tbl	:prefix 'session'
\set pref_tbl		:prefix 'pref'
-- \set user_tbl	:prefix 'user'
\set member_tbl 	:prefix 'member'
\set rating_tbl		:prefix 'rating'
\set accesslog_tbl	:prefix 'accesslog'

\echo Dropping :version_tbl
DROP TABLE :version_tbl;

\echo Dropping :recent_tbl
DROP TABLE :recent_tbl;

\echo Dropping :nonempty_tbl
DROP TABLE :nonempty_tbl;

\echo Dropping :link_tbl
DROP TABLE :link_tbl;

--\echo Dropping :user_tbl
-- DROP TABLE :user_tbl;

\echo Dropping :rating_tbl
DROP TABLE :rating_tbl;

\echo Dropping :page_tbl
DROP TABLE :page_tbl;
\echo Dropping :page_id_seq only needed for postgresql < 7.2

\echo Dropping :member_tbl
DROP TABLE :member_tbl;

\echo Dropping :pref_tbl
DROP TABLE :pref_tbl;

\echo Dropping :session_tbl
DROP TABLE :session_tbl;

\echo Dropping :accesslog_tbl
DROP TABLE :accesslog_tbl;

DROP FUNCTION delete_versiondata (INT4, INT4);
DROP FUNCTION set_versiondata (INT4, INT4, INT4, INT2, TEXT, TEXT);
DROP FUNCTION prepare_rename_page (INT4, INT4);
