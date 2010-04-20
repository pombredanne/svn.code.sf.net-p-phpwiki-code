# $Id: Makefile 6691 2009-03-23 07:43:49Z vargenau $
#
# themes/default/Makefile
#
# minify CSS and JS
#

CSS_SRC = IEFixes.css    phpwiki-modern-heavy.css  phpwiki-printer.css        phpwiki.css \
	  highlight.css  phpwiki-modern.css        phpwiki-topbottombars.css  wikiwyg.css \
	  moacdropdown/css/dropdown.css
MAIN    = wikicommon
JS_MAIN = wikicommon.js  sortable.js ajax.js moacdropdown.js toolbar.js
JS_REST = ASCIIsvg.js awstats_misc_tracker.js # delayed.js

CSS_FILES = $(patsubst %.css,%-min.css,$(CSS_SRC))

all	: css js

include ../Makefile.global
