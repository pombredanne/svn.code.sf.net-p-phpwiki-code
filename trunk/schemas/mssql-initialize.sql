-- $Id: mssql-initialize.sql,v 1.1 2004-10-12 17:31:34 rurban Exp $

CREATE TABLE page (
	id              INT NOT NULL AUTO_INCREMENT,
        pagename        VARCHAR(100) NOT NULL,
	hits            INT NOT NULL DEFAULT 0,
        pagedata        TEXT NOT NULL DEFAULT '',
        PRIMARY KEY (id),
	UNIQUE (pagename)
);

CREATE TABLE version (
	id              INT NOT NULL,
        version         INT NOT NULL,
	mtime           INT NOT NULL,
	minor_edit      TINYINT DEFAULT 0,
        content         TEXT NOT NULL DEFAULT '',
        versiondata     TEXT NOT NULL DEFAULT '',
        PRIMARY KEY (id,version)
);
CREATE INDEX version_mtime ON version (mtime);

CREATE TABLE recent (
	id              INT NOT NULL,
	latestversion   INT,
	latestmajor     INT,
	latestminor     INT,
        PRIMARY KEY (id)
);

CREATE TABLE nonempty (
	id              INT NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE link (
	linkfrom        INT NOT NULL,
        linkto          INT NOT NULL
);
CREATE INDEX linkfrom ON link (linkfrom);
CREATE INDEX linkto ON link (linkto);

CREATE TABLE session (
    	sess_id 	CHAR(32) NOT NULL DEFAULT '',
    	sess_data 	BLOB NOT NULL,
    	sess_date 	INT UNSIGNED NOT NULL,
    	sess_ip 	CHAR(15) NOT NULL,
    	PRIMARY KEY (sess_id)
);
CREATE INDEX sess_date ON session (sess_date);

-- Optional DB Auth and Prefs
-- For these tables below the default table prefix must be used 
-- in the DBAuthParam SQL statements also.

CREATE TABLE pref (
  	userid 	CHAR(48) NOT NULL UNIQUE,
  	prefs  	TEXT NULL DEFAULT '',
  	PRIMARY KEY (userid)
);

-- better use the extra pref table where such users can be created easily 
-- without password.
--CREATE TABLE user (
--  	userid 	CHAR(48) NOT NULL UNIQUE,
--  	passwd 	CHAR(48) DEFAULT '',
--	prefs  	TEXT NULL DEFAULT '',
--	groupname CHAR(48) DEFAULT 'users',
--  	PRIMARY KEY (userid)
--);

--CREATE TABLE member (
--	userid    CHAR(48) NOT NULL,
--   	groupname CHAR(48) NOT NULL DEFAULT 'users',
--   	INDEX (userid),
--   	INDEX (groupname)
--);
--CREATE INDEX member_userid ON member (userid);
--CREATE INDEX member_groupname ON member (groupname);

-- only if you plan to use the wikilens theme
CREATE TABLE rating (
        dimension INT(4) NOT NULL,
        raterpage INT(11) NOT NULL,
        rateepage INT(11) NOT NULL,
        ratingvalue FLOAT NOT NULL,
        rateeversion INT(11) NOT NULL,
        tstamp TIMESTAMP(14) NOT NULL,
        PRIMARY KEY (dimension, raterpage, rateepage)
);
