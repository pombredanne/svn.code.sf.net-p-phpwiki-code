$Id: INSTALL.pgsql,v 1.5 2000-10-08 18:12:13 wainstead Exp $

These instructions are not quite complete yet. If you find something
I missed, please let me know (swain@panix.com).


Installation instructions for PhpWiki with a Postgresql database

Installation of Postgresql will not be discussed here... you can get a
copy from http://www.postgresql.org/. However if you are running 
Red Hat Linux, all you need to do is install the PHP RPM and the 
Postgresql RPM and edit your Apache httpd.conf file, and uncomment 
the lines for all PHP files (and add index.php to the list of directory
files while you're at it! :-)

Also note that Postgresql by default has a hard limit of 8K per
row. This is a Really Bad Thing. You can change that when you compile
Postgresql to allow 32K per row, but supposedly performance
suffers. The 7.x release of Postgresql is supposed to fix this.

It's probably a good idea to install PhpWiki as-is first, running it
off the DBM file. This way you can test most of the functionality of
the Wiki.

Once that's done and you have the basic stuff done that's listed in 
the INSTALL, the time comes to move to Postgresql.

Edit lib/config.php and comment out the lines for DBM file usage; then
uncomment the lines for Postgresql. The lines are clearly commented and 
you should have no problem with this.

Next you need to create a database called "wiki".

bash$ createdb wiki

Now run the script schemas/schema.psql

bash$ psql wiki -f schemas/schema.psql

For some reason I had to stop/start the database so that these changes took 
effect.. after that just open up the Wiki in your browser and you should
have a brand-new PhpWiki running!

Steve Wainstead
swain@panix.com

