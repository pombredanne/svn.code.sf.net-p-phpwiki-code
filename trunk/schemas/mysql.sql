-- $Id: mysql.sql,v 1.7 2004-02-07 14:20:18 rurban Exp $

drop table if exists page;
CREATE TABLE page (
	id              INT NOT NULL,
        pagename        VARCHAR(100) BINARY NOT NULL,
	hits            INT NOT NULL DEFAULT 0,
        pagedata        MEDIUMTEXT NOT NULL DEFAULT '',
        PRIMARY KEY (id),
	UNIQUE KEY (pagename)
);

drop table if exists version;
CREATE TABLE version (
	id              INT NOT NULL,
        version         INT NOT NULL,
	mtime           INT NOT NULL,
	minor_edit      TINYINT DEFAULT 0,
        content         MEDIUMTEXT NOT NULL DEFAULT '',
        versiondata     MEDIUMTEXT NOT NULL DEFAULT '',
        PRIMARY KEY (id,version),
	INDEX (mtime)
);

drop table if exists recent;
CREATE TABLE recent (
	id              INT NOT NULL,
	latestversion   INT,
	latestmajor     INT,
	latestminor     INT,
        PRIMARY KEY (id)
);

drop table if exists nonempty;
CREATE TABLE nonempty (
	id              INT NOT NULL,
	PRIMARY KEY (id)
);

drop table if exists link;
CREATE TABLE link (
	linkfrom        INT NOT NULL,
        linkto          INT NOT NULL,
	INDEX (linkfrom),
        INDEX (linkto)
);

drop table if exists session;
CREATE TABLE session (
    	sess_id 	VARCHAR(32) NOT NULL DEFAULT '',
    	sess_data 	BLOB NOT NULL,
    	sess_date 	INT UNSIGNED NOT NULL,
    	PRIMARY KEY (sess_id)
);

-- Optional DB Auth and Prefs
-- For these tables below the default table prefix must be used 
-- in the DBAuthParam SQL statements also.

-- Don't know if you should auth against pref table also. 
-- the password is stored there also.
--drop table if exists pref;
--CREATE TABLE pref (
--  	userid 	CHAR(48) BINARY NOT NULL UNIQUE,
--  	prefs  	TEXT NULL DEFAULT '',
--  	PRIMARY KEY (userid)
--) TYPE=MyISAM;

drop table if exists user;
CREATE TABLE user (
  	userid 	CHAR(48) BINARY NOT NULL UNIQUE,
  	passwd 	CHAR(48) BINARY DEFAULT '',
	prefs  	TEXT NULL DEFAULT '',
--	groupname CHAR(48) BINARY DEFAULT 'users',
  	PRIMARY KEY (userid)
) TYPE=MyISAM;

drop table if exists member;
CREATE TABLE member (
	userid    CHAR(48) BINARY NOT NULL,
   	groupname CHAR(48) BINARY NOT NULL DEFAULT 'users',
   	INDEX (userid),
   	INDEX (groupname)
) TYPE=MyISAM;
