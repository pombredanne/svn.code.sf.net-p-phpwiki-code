# gnu make (also with cygwin) version
DB_ADMINUSER=root
DB_ADMINPASS=secret
# get db params from config/config.ini
DATABASE_DSN := $(shell config/make-dbhelper.pl config/config.ini)
#DB_DB=phpwiki
DB_DB   := $(word 1,${DATABASE_DSN})
#DB_USER=wikiuser
DB_USER := $(word 2,${DATABASE_DSN})
#DB_PASS=
DB_PASS := $(word 3,${DATABASE_DSN})


DB_SQLITE_DBFILE = /tmp/phpwiki-sqlite.db
# ****************************************************************************
PHP_SRC := $(wildcard *.php ./lib/*.php ./lib/WikiDB/*.php ./lib/plugin/*.php)

.PHONY: all locale mysql psql sqlite

all:  TAGS

TAGS:  $(PHP_SRC)
#	etags $(PHP_SRC)
	if [ -f $@ ]; then /usr/bin/mv -f $@ $@~; fi
	/usr/bin/find . \( -type d -regex '\(^\./lib/pear\)\|\(^\./lib/WikiDB/adodb\)\|\(^\./lib/nusoap\)\|\(^\./lib/fpdf\)\|\(^\./locale/.*/LC_MESSAGES\)' \) -prune -o -name \*.php | etags -L -

TAGS.full:  $(PHP_SRC)
	if [ -f $@ ]; then /usr/bin/mv -f $@ $@~; fi
	/usr/bin/find . -name \*.php -o -name \*.tmpl | etags -L - --langmap="HTML:.tmpl" -f $@;

locale: 
	cd locale
	make

DB_OPTS=-u$(DB_ADMINUSER) -p$(DB_ADMINPASS)

dbtest:
	echo DB_OPTS=$(DB_OPTS) DB_DB=$(DB_DB) DB_USER=${DB_USER} DB_PASS=${DB_PASS}

# initialize the database
mysql:
	mysqladmin $(DB_OPTS) create $(DB_DB)
	mysql $(DB_OPTS) -e "GRANT select,insert,update,delete,lock tables ON $(DB_DB).* \
TO $(DB_USER)@localhost IDENTIFIED BY '$(DB_PASS)';"
	mysql $(DB_OPTS) $(DB_DB) < schemas/mysql.sql

# initialize the database
psql:
	su postmaster
	createdb $(DB_DB)
	ifeq ($(DB_PASS),"")
	  createuser -D -A -P $(DB_USER)
	else
	  createuser -D -A $(DB_USER)
	endif
	psql $(DB_DB) -f schemas/psql.sql
	logout

# initialize the database
sqlite:
	sqlite $(DB_SQLITE_DBFILE) < schemas/sqlite.sql

# update the database
${DB_SQLITE_DBFILE}: schemas/sqlite.sql
	echo ".dump" | sqlite ${DB_SQLITE_DBFILE} > dump.sql
	mv ${DB_SQLITE_DBFILE} ${DB_SQLITE_DBFILE}.old
	sqlite $(DB_SQLITE_DBFILE) < dump.sql
