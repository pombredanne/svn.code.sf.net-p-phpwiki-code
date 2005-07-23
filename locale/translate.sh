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

if [ ! -f index.php ]; then
	echo Usage: ./locale/translate.sh
	exit 2
fi

ALL_LINGUAS="nl es de sv it"

xgettext -L PHP --from-code=iso-8859-1 -o locale/po/phpwiki.pot admin.php lib/*php
podir=locale/po
for i in $ALL_LINGUAS; do
	po=$podir/$i.po
	pot=$podir/phpwiki.pot
	locale=locale/$i/LC_MESSAGES

	msgmerge -o $po $po $pot
	mkdir -p $locale
	msgfmt -o $locale/phpwiki.mo $po

	awk -- '
BEGIN {
  msgid=""; msgstr="";
  print ("<?php\n");
}
/^msgid ""/ {
  if (msgid && str)
    print ("$locale[\"" msgid "\"] =\n   \"" str "\";");
  str="";
  next;
}
/^msgid "/ { #"{
  if (msgid && str)
    print ("$locale[\"" msgid "\"] =\n   \"" str "\";");
  str = substr ($0, 8, length ($0) - 8);
  msgstr="";
}
/^msgstr ""/ {
  msgid=str;
  str="";
  next;
}
/^msgstr "/ { #"{
  msgid=str;
  str = substr ($0, 9, length ($0) - 9);
  next;
}
/^"/ { #"{
  str = (str substr ($0, 2, length ($0) - 2));
  next;
}
END {
  if (msgid && str)
    print ("$locale[\"" msgid "\"] =\n   \"" str "\";");
  print ("\n;?>");
}

' $po > $locale/phpwiki.php
done
