$Id: INSTALL.pgsql,v 1.2 2000-06-18 05:25:56 wainstead Exp $

Installation instructions for PhpWiki with a Postgresql database

createdb wiki
grant all on wiki to nobody;
grant all on archive to nobody;
grant all on wikilinks to nobody;
grant all on hottopics to nobody;
grant all on hitcount to nobody;
commit