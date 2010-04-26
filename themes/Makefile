# $Id: Makefile 6691 2009-03-23 07:43:49Z rurban $
#
# themes/Makefile
#
# minify themes
#

.PHONY: all css js

THEMES =AVL default MonoBook wikilens Sidebar SpaceWiki blog MacOSX gforge \
	Portland shamino_com Crao Wordpress smaller Hawaiian

# http://developer.yahoo.com/yui/compressor/
YUICOMPRESSOR = java -jar h:/Java/yuicompressor-2.4.2.jar

all: 
	$(foreach dir,$(THEMES),make -C $(dir);)
