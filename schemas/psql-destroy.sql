-- $Id: psql-destroy.sql,v 1.1 2004-07-22 16:49:20 dfrankow Exp $

\set QUIET


--================================================================
-- Prefix for table names.
--
-- You should set this to the same value you specify for
-- $DBParams['prefix'] in index.php.

\set prefix 	''

--================================================================
-- Which postgres user gets access to the tables?
--
-- You should set this to the name of the postgres
-- user who will be accessing the tables.
--
-- Commonly, connections from php are made under
-- the user name of 'nobody', 'apache' or 'www'.

\set httpd_user	'rurban'

--================================================================
--
-- Don't modify below this point unless you know what you are doing.
--
--================================================================

\set qprefix '\'' :prefix '\''
\set qhttp_user '\'' :httpd_user '\''
\echo Initializing PhpWiki tables with:
\echo '       prefix = ' :qprefix
\echo '   httpd_user = ' :qhttp_user
\echo
\echo 'Expect some \'Relation \'*\' does not exists\' errors unless you are'
\echo 'overwriting existing tables.'

\set page_tbl		:prefix 'page'
\set page_id		:prefix 'page_id'
\set page_nm		:prefix 'page_nm'

\set version_tbl	:prefix 'version'
\set vers_id		:prefix 'vers_id'
\set vers_mtime		:prefix 'vers_mtime'

\set recent_tbl		:prefix 'recent'
\set recent_id		:prefix 'recent_id'

\set nonempty_tbl	:prefix 'nonempty'
\set nonmt_id		:prefix 'nonmt_id'

\set link_tbl		:prefix 'link'
\set link_from		:prefix 'link_from'
\set link_to		:prefix 'link_to'

\set session_tbl	:prefix 'session'
\set sess_id		:prefix 'sess_id'
\set sess_date		:prefix 'sess_date'
\set sess_ip		:prefix 'sess_ip'

\set pref_tbl		:prefix 'pref'
\set pref_id		:prefix 'pref_id'

\echo Dropping :page_tbl
DROP TABLE :page_tbl;

\echo Dropping :version_tbl
DROP TABLE :version_tbl;

\echo Dropping :recent_tbl
DROP TABLE :recent_tbl;

\echo Dropping :nonempty_tbl
DROP TABLE :nonempty_tbl;

\echo Dropping :link_tbl
DROP TABLE :link_tbl;

\echo Dropping :session_tbl
DROP TABLE :session_tbl;

-- Optional DB Auth and Prefs
-- For these tables below the default table prefix must be used 
-- in the DBAuthParam SQL statements also.

\echo Dropping :pref_tbl
DROP TABLE :pref_tbl;
