-- $Id: mssql-destroy.sql,v 1.1 2004-10-12 17:31:34 rurban Exp $

DROP TABLE page;
DROP TABLE version;
DROP TABLE recent;
DROP TABLE nonempty;
DROP TABLE link;
DROP TABLE session;

DROP TABLE pref;
--DROP TABLE user;
--DROP TABLE member;

-- wikilens theme
DROP TABLE rating;
