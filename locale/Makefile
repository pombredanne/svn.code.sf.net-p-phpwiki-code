# $Id: Makefile,v 1.4 2001-12-17 23:03:42 carstenklapp Exp $
#
# locale/Makefile
#
# This is a replacement for locale/translate.sh to avoid remaking files that
# don't need to be remade.
#

ALL_LINGUAS = nl es de sv it

PO_FILES = $(patsubst %, po/%.po, ${ALL_LINGUAS})
MO_FILES = $(patsubst %, %/LC_MESSAGES/phpwiki.mo, ${ALL_LINGUAS})
PHP_FILES = ${MO_FILES:.mo=.php}

#PHP_SRC := $(wildcard ../lib/*.php ../admin/*.php ../templates/*.html)
PHP_SRC := $(wildcard ../templates/*.html ../lib/plugin/*.php ../lib/*.php ../lib/WikiDB/backend/*.php ../lib/WikiDB/*.php)

.PHONY: all po mo

all	: po mo
po 	: ${PO_FILES}
mo	: ${MO_FILES} ${PHP_FILES}

po/phpwiki.pot : ${PHP_SRC}
	xgettext -L C++ -k_ -o $@ $^

po/%.po : po/phpwiki.pot
	test -f $@ || touch $@
	cp -f $@ $@~
	msgmerge -o $@.new $@~ $^ && mv -f $@.new $@

%/LC_MESSAGES/phpwiki.mo : po/%.po
	mkdir -p $*/LC_MESSAGES
	msgfmt -o $@ $<

# Generate .php from .mo rather than .po to maximize
# the probability of agreement between .mo and .php files.
%/LC_MESSAGES/phpwiki.php : %/LC_MESSAGES/phpwiki.mo
	msgunfmt $< | awk -f make-php-trans.awk > $@

