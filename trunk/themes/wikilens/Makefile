# $Id: Makefile 6691 2009-03-23 07:43:49Z rurban $
#
# themes/wikilens/Makefile
#
# minify CSS and JS
#

CSS_SRC = wikilens.css    
MAIN    = wikilens
JS_MAIN = wikilens.js
JS_REST = 

CSS_FILES = $(patsubst %.css,%-min.css,$(CSS_SRC))

all	: css js

include ../Makefile.global
