#!/bin/bash
# translate.sh
#
# This script should be run by
#
#    * PphWiki maintainers, before making a distribution
#    * Translators, after making a translation update
#

ALL_LINGUAS=nl

xgettext -L C++ -o po/phpwiki.pot ../lib/*php
for i in $ALL_LINGUAS; do
	po=po/$i.po
	pot=po/phpwiki.pot
	locale=$i/LC_MESSAGES

	msgmerge -o $po $po $pot
	mkdir -p $i/LC_MESSAGES
	msgfmt -o $locale/phpwiki.mo $po

	awk -- '
	    BEGIN { print ("<?php") }
	    /^msgid ""/ { getline; next }
	    /^msgid "/  { msgid = substr ($0, 7); print ("$locale[" msgid "] ="); next }
	    /^msgstr "/ { msgstr = substr ($0, 8); print ("   " msgstr ";"); next }
	    END { print ("?>") }' $po > $locale/phpwiki.php
done
