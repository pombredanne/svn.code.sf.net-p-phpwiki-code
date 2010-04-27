# $Id$
#
# themes/Makefile
#
# minify themes
#

.PHONY: all css js

THEMES = default MonoBook wikilens Sidebar SpaceWiki blog MacOSX gforge \
	Portland shamino_com Crao Wordpress smaller Hawaiian

# http://developer.yahoo.com/yui/compressor/
YUICOMPRESSOR = java -jar h:/Java/yuicompressor-2.4.2.jar

all: 
	$(foreach dir,$(THEMES),make -C $(dir);)
