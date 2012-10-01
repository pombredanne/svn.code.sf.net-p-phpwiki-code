# themes/Makefile
#
# minify themes
#

.PHONY: all css js

THEMES = default MonoBook wikilens Sidebar SpaceWiki blog MacOSX fusionforge \
	Portland shamino_com Crao Wordpress smaller Hawaiian

# http://developer.yahoo.com/yui/compressor/
YUICOMPRESSOR = java -jar default/yuicompressor-2.4.7.jar

all: 
	$(foreach dir,$(THEMES),make -C $(dir);)
