-- http://www.hezmatt.org/~mpalmer/sqlite-phpwiki/sqlite.sql

-- $Id: sqlite.sql,v 1.5 2004-07-05 14:12:59 rurban Exp $

CREATE TABLE page (
	id              INTEGER PRIMARY KEY,
	pagename        VARCHAR(100) NOT NULL,
	hits            INTEGER NOT NULL DEFAULT 0,
	pagedata        MEDIUMTEXT NOT NULL DEFAULT ''
);

CREATE UNIQUE INDEX page_index ON page (pagename);

CREATE TABLE version (
	id              INTEGER NOT NULL,
	version         INTEGER NOT NULL,
	mtime           INTEGER NOT NULL,
	minor_edit      TINYINTEGER DEFAULT 0,
	content         MEDIUMTEXT NOT NULL DEFAULT '',
	versiondata     MEDIUMTEXT NOT NULL DEFAULT '',
	PRIMARY KEY (id,version)
);

CREATE INDEX version_index ON version (mtime);

CREATE TABLE recent (
	id              INTEGER NOT NULL PRIMARY KEY,
	latestversion   INTEGER,
	latestmajor     INTEGER,
	latestminor     INTEGER
);

CREATE TABLE nonempty (
	id              INTEGER NOT NULL
);
CREATE INDEX nonempty_index ON nonempty (id);

CREATE TABLE link (
	linkfrom        INTEGER NOT NULL,
	linkto          INTEGER NOT NULL
);

CREATE INDEX linkfrom_index ON link (linkfrom);
CREATE INDEX linkto_index ON link (linkto);

CREATE TABLE session (
	sess_id   CHAR(32) NOT NULL DEFAULT '' PRIMARY KEY,
	sess_data BLOB NOT NULL,
	sess_date INTEGER UNSIGNED NOT NULL,
	sess_ip   CHAR(15) NOT NULL
);

CREATE INDEX sessdate_index ON session (sess_date);
CREATE INDEX sessip_index ON session (sess_ip);

-- Optional DB Auth and Prefs
-- For these tables below the default table prefix must be used 
-- in the DBAuthParam SQL statements also.

CREATE TABLE pref (
  	userid 	CHAR(48) NOT NULL PRIMARY KEY,
  	prefs  	TEXT NULL DEFAULT ''
);
