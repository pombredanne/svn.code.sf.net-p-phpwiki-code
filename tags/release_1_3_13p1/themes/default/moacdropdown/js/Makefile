ALLSRC=mobrowser.js modomevent.js modomt.js modomext.js getobject2.js xmlextras.js acdropdown.js

../../moacdropdown.js: $(ALLSRC)
	cat $(ALLSRC) > $@
	perl -pi~ -e 's/^\s+//; s(//.*)();' $@
