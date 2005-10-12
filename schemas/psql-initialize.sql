-- $Id: psql-initialize.sql,v 1.7 2005-10-12 06:08:37 rurban Exp $

\set QUIET


--================================================================
-- Prefix for table names.
--
-- You should set this to the same value you specified for
-- DATABASE_PREFIX in config.ini

\set prefix 	''

--================================================================
-- Which postgres user gets access to the tables?
--
-- You should set this to the name of the postgres
-- user who will be accessing the tables.
-- See DATABASE_DSN in config.ini
--
-- Commonly, connections from php are made under
-- the user name of 'nobody', 'apache' or 'www'.

\set httpd_user	'phpwiki'

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
\echo 'Expect some \'ERROR: relation \'*\'  already exists\' errors '
\echo 'preventing from overwriting existing tables,sequences,indices.'

\set page_tbl 		:prefix 'page'
\set page_id_seq 	:prefix 'page_id_seq'
\set page_id_idx 	:prefix 'page_id_idx'
\set page_name_idx 	:prefix 'page_name_idx'

\set version_tbl 	:prefix 'version'
\set vers_id_idx 	:prefix 'vers_id_idx'
\set vers_mtime_idx 	:prefix 'vers_mtime_idx'

\set recent_tbl		:prefix 'recent'
\set recent_id_idx 	:prefix 'recent_id_idx'

\set nonempty_tbl	:prefix 'nonempty'
\set nonmt_id_idx 	:prefix 'nonmt_id_idx'

\set link_tbl 		:prefix 'link'
\set link_from_idx 	:prefix 'link_from_idx'
\set link_to_idx 	:prefix 'link_to_idx'

\set session_tbl 	:prefix 'session'
\set sess_id_idx 	:prefix 'sess_id_idx'
\set sess_date_idx 	:prefix 'sess_date_idx'
\set sess_ip_idx 	:prefix 'sess_ip_idx'

\set pref_tbl 	 	:prefix 'pref'
\set pref_id_idx 	:prefix 'pref_id_idx'
--\set user_tbl 	 	:prefix 'users'
--\set user_id_idx  	:prefix 'users_id_idx'
\set member_tbl  	:prefix 'member'
\set member_id_idx  	:prefix 'member_id_idx'
\set member_group_idx 	:prefix 'member_group_idx'

\set rating_tbl		:prefix 'rating'
\set rating_id_idx 	:prefix 'rating_id_idx'

\set accesslog_tbl 	:prefix 'accesslog'
\set accesslog_time_idx :prefix 'log_time_idx'
\set accesslog_host_idx :prefix 'log_host_idx'

\echo Creating :page_tbl
CREATE TABLE :page_tbl (
	id 		SERIAL PRIMARY KEY,
        pagename 	VARCHAR(100) NOT NULL UNIQUE CHECK (pagename <> ''),
	hits 		INT4 NOT NULL DEFAULT 0,
        pagedata 	TEXT NOT NULL DEFAULT '',
	cached_html  	bytea DEFAULT ''
);
-- CREATE UNIQUE INDEX :page_id_idx ON :page_tbl (id);
-- CREATE UNIQUE INDEX :page_name_idx ON :page_tbl (pagename);

\echo Creating :version_tbl
CREATE TABLE :version_tbl (
	id		INT4 REFERENCES :page_tbl ON DELETE CASCADE,
        version		INT4 NOT NULL,
	mtime		INT4 NOT NULL,
--FIXME: should use boolean, but that returns 't' or 'f'. not 0 or 1. 
	minor_edit	INT2 DEFAULT 0,
        content		TEXT NOT NULL DEFAULT '',
        versiondata	TEXT NOT NULL DEFAULT ''
);
CREATE UNIQUE INDEX :vers_id_idx ON :version_tbl (id, version);
CREATE INDEX :vers_mtime_idx ON :version_tbl (mtime);

\echo Creating :recent_tbl
CREATE TABLE :recent_tbl (
	id		INT4 REFERENCES :page_tbl ON DELETE CASCADE,
	latestversion	INT4,
	latestmajor	INT4,
	latestminor	INT4
);
CREATE UNIQUE INDEX :recent_id_idx ON :recent_tbl (id);


\echo Creating :nonempty_tbl
CREATE TABLE :nonempty_tbl (
	id		INT4 NOT NULL REFERENCES :page_tbl ON DELETE CASCADE
);
CREATE UNIQUE INDEX :nonmt_id_idx ON :nonempty_tbl (id);

\echo Creating :link_tbl
CREATE TABLE :link_tbl (
        linkfrom	INT4 NOT NULL REFERENCES :page_tbl ON DELETE CASCADE,
        linkto		INT4 NOT NULL REFERENCES :page_tbl ON DELETE CASCADE
);
CREATE INDEX :link_from_idx ON :link_tbl (linkfrom);
CREATE INDEX :link_to_idx   ON :link_tbl (linkto);

-- if you plan to use the wikilens theme
\echo Creating :rating_tbl
CREATE TABLE :rating_tbl (
        dimension INTEGER NOT NULL,
        raterpage INT8 NOT NULL REFERENCES :page_tbl ON DELETE CASCADE,
        rateepage INT8 NOT NULL REFERENCES :page_tbl ON DELETE CASCADE,
        ratingvalue FLOAT NOT NULL,
        rateeversion INT8 NOT NULL,
        tstamp TIMESTAMP NOT NULL
);
CREATE UNIQUE INDEX :rating_id_idx ON :rating_tbl (dimension, raterpage, rateepage);

--================================================================
-- end of page relations
--================================================================

\echo Creating :session_tbl
CREATE TABLE :session_tbl (
	sess_id 	CHAR(32) PRIMARY KEY,
    	sess_data 	bytea NOT NULL,
    	sess_date 	INT4,
    	sess_ip 	CHAR(40) NOT NULL
);
-- CREATE UNIQUE INDEX :sess_id_idx ON :session_tbl (sess_id);
CREATE INDEX :sess_date_idx ON :session_tbl (sess_date);
CREATE INDEX :sess_ip_idx   ON :session_tbl (sess_ip);

-- Optional DB Auth and Prefs
-- For these tables below the default table prefix must be used 
-- in the DBAuthParam SQL statements also.

\echo Creating :pref_tbl
CREATE TABLE :pref_tbl (
  	userid 	CHAR(48) PRIMARY KEY,
  	prefs  	TEXT NULL DEFAULT '',
	passwd  CHAR(48) DEFAULT '',
	groupname CHAR(48) DEFAULT 'users'
);
-- CREATE UNIQUE INDEX :pref_id_idx ON :pref_tbl (userid);

-- Use the member table, if you need it for n:m user-group relations,
-- and adjust your DBAUTH_AUTH_ SQL statements.
CREATE TABLE :member_tbl (
	userid CHAR(48) NOT NULL REFERENCES :pref_tbl ON DELETE CASCADE, 
	groupname CHAR(48) NOT NULL DEFAULT 'users'
);
CREATE INDEX :member_id_idx    ON :member_tbl (userid);
CREATE INDEX :member_group_idx ON :member_tbl (groupname);

-- if ACCESS_LOG_SQL > 0
-- only if you need fast log-analysis (spam prevention, recent referrers)
-- see http://www.outoforder.cc/projects/apache/mod_log_sql/docs-2.0/#id2756178
\echo Creating :accesslog_tbl
CREATE TABLE :accesslog_tbl (
        time_stamp    INT,
	remote_host   VARCHAR(50),
	remote_user   VARCHAR(50),
        request_method VARCHAR(10),
	request_line  VARCHAR(255),
	request_args  VARCHAR(255),
	request_file  VARCHAR(255),
	request_uri   VARCHAR(255),
	request_time  CHAR(28),
	status 	      INT2,
	bytes_sent    INT4,
        referer       VARCHAR(255), 
	agent         VARCHAR(255),
	request_duration FLOAT
);
CREATE INDEX :accesslog_time_idx ON :accesslog_tbl (time_stamp);
CREATE INDEX :accesslog_host_idx ON :accesslog_tbl (remote_host);
-- create extra indices on demand (usually referer. see plugin/AccessLogSql)

--================================================================

\echo You might want to ignore the following errors or run 
\echo /usr/sbin/createuser -S -R -d  :httpd_user

\echo Applying permissions for role :httpd_user
GRANT SELECT,INSERT,UPDATE,DELETE ON :page_tbl		TO :httpd_user;
GRANT SELECT,INSERT,UPDATE,DELETE ON :version_tbl	TO :httpd_user;
GRANT SELECT,INSERT,UPDATE,DELETE ON :recent_tbl	TO :httpd_user;
GRANT SELECT,INSERT,UPDATE,DELETE ON :nonempty_tbl	TO :httpd_user;
GRANT SELECT,INSERT,UPDATE,DELETE ON :link_tbl		TO :httpd_user;

GRANT SELECT,INSERT,UPDATE,DELETE ON :session_tbl	TO :httpd_user;
-- you may want to fine tune this:
GRANT SELECT,INSERT,UPDATE,DELETE ON :pref_tbl		TO :httpd_user;
-- GRANT SELECT ON :user_tbl	TO :httpd_user;
GRANT SELECT ON :member_tbl	TO :httpd_user;
GRANT SELECT,INSERT,UPDATE,DELETE ON :rating_tbl	TO :httpd_user;
GRANT SELECT,INSERT,UPDATE,DELETE ON :accesslog_tbl	TO :httpd_user;
