#!/bin/bash
# translate.sh
#
# Usage:
#
#   ./locale/translate.sh
#
# This script should be run by
#
#    * PphWiki maintainers, before making a distribution
#    * Translators, after making a translation update
#

ALL_LINGUAS=nl

xgettext -L C++ -o locale/po/phpwiki.pot lib/*php
podir=locale/po
for i in $ALL_LINGUAS; do
	po=$podir/$i.po
	pot=$podir/phpwiki.pot
	locale=locale/$i/LC_MESSAGES

	msgmerge -o $po $po $pot
	mkdir -p locale/$i/LC_MESSAGES
	msgfmt -o $locale/phpwiki.mo $po

	awk -- '
	    BEGIN { print ("<?php") }
	    /^msgid ""/ { getline; next }
	    /^msgid "/  { msgid = substr ($0, 7); print ("$locale[" msgid "] ="); next }
	    /^msgstr "/ { msgstr = substr ($0, 8); print ("   " msgstr ";"); next }
	    END { print ("?>") }' $po > $locale/phpwiki.php
done
