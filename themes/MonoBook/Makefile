# $Id$
#
# themes/MonoBook/Makefile
#
# minify CSS and JS
#

CSS_SRC = IE50Fixes.css  IE70Fixes.css   KHTMLFixes.css   commonPrint.css  rtl.css \
	  IE55Fixes.css  IEFixes.css     main.css         wikiprintable.css \
	  IE60Fixes.css  IEMacFixes.css  monobook.css     wikistandard.css
MAIN    = wikibits
JS_MAIN = wikibits.js
# JS_REST = IEFixes.js

CSS_FILES = $(patsubst %.css,%-min.css,$(CSS_SRC))

all	: css js

include ../Makefile.global
