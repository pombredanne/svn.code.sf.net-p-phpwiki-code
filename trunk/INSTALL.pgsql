----------
NOTE for the 1.2 release: You may see a few warnings when you first
load the pages. They may look like this:


***
Warning: PostgresSQL query failed: ERROR: ExecAppend: Fail to add null
value in not null attribute pagename in lib/pgsql.php on line 417

Inserting page ConvertSpacesToTabs, version 1 from text file
***

This is not a problem since PhpWiki is trying to update a table that
tracks hits and links.
----------


Installation instructions for PhpWiki with a Postgresql database

Installation of Postgresql will not be discussed here... you can get a
copy from http://www.postgresql.org/. However if you are running 
Red Hat Linux, all you need to do is install the PHP RPM and the 
Postgresql RPM and edit your Apache httpd.conf file, and uncomment 
the lines for all PHP files (and add index.php to the list of directory
files while you're at it... you may also need to add .php as a type
handled by mod_php: 

<IfModule mod_php3.c>
  AddType application/x-httpd-php3 .php3
  AddType application/x-httpd-php3 .php
  AddType application/x-httpd-php3-source .phps
</IfModule>

(This is from a stock 6.2 Red Hat distro, which ships with an rpm of
PHP 3.0.12, but should give you an idea. I had to add the line for
.php).

Also note that Postgresql by default has a hard limit of 8K per
row. This is a Really Bad Thing. You can change that when you compile
Postgresql to allow 32K per row, but supposedly performance
suffers. The 7.x release of Postgresql is supposed to fix this.

It's probably a good idea to install PhpWiki as-is first, running it
off the DBM file. This way you can test most of the functionality of
the Wiki.

Once that's done and you have the basic stuff done that's listed in 
the INSTALL, the time comes to move to Postgresql.

Edit lib/config.php and edit $WhichDatabase for Postgresql. The lines
are clearly commented and you should have no problem with this.

Next you need to create a database called "wiki".

bash$ createdb wiki

Now run the script schemas/schema.psql

bash$ psql wiki -f schemas/schema.psql

For some reason I had to stop/start the database so that these changes took 
effect.. after that just open up the Wiki in your browser and you should
have a brand-new PhpWiki running!

If you find something I missed, please let me know.
Steve Wainstead
swain@wcsb.org

Report bugs to phpwiki-talk@lists.sourceforge.net

$Id: INSTALL.pgsql,v 1.7 2001-02-02 02:55:31 wainstead Exp $