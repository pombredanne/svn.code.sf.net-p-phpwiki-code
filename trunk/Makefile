# gnu make (also with cygwin) version
# TODO: get db params from config/config.ini
DB_ADMINUSER=root
DB_ADMINPASS=secret
DB_DB=phpwiki
DB_USER=wikiuser
DB_PASS=

DB_SQLITE_DBFILE = /tmp/phpwiki-sqlite.db
# ****************************************************************************
PHP_SRC := $(wildcard *.php ./lib/*.php ./lib/WikiDB/*.php ./lib/plugin/*.php)

all:  TAGS

TAGS:  $(PHP_SRC)
#	etags $(PHP_SRC)
	if [ -f TAGS ]; then /usr/bin/mv -f TAGS TAGS~; fi
	/usr/bin/find . . \( -type d -regex '\(^\./lib/pear\)\|\(^\./lib/WikiDB/adodb\)\|\(^\./lib/nusoap\)\|\(^\./locale/.*/LC_MESSAGES\)' \) -prune -o -name \*.php -exec etags -a '{}' \;

locale: 
	cd locale
	make

DB_OPTS=-u$(DB_ADMINUSER) -p$(DB_ADMINPASS)

mysql:
	mysqladmin $(DB_OPTS) create $(DB_DB)
	mysql $(DB_OPTS) -e "GRANT select,insert,update,delete,lock tables ON $(DB_DB).* \
TO $(DB_USER)@localhost IDENTIFIED BY '$(DB_PASS)';"
	mysql $(DB_OPTS) $(DB_DB) < schemas/mysql.sql

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

sqlite:
	sqlite $(DB_SQLITE_DBFILE) < schemas/sqlite.sql
