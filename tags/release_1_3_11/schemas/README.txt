These files are used to initialize the database tables for a Wiki.
First create the database using the database commands suitable for
your DBMS. See doc/INSTALL.<DBMS>
Then run the appropriate <DBMS>-initialize.sql to initialize your database.

If you have a database you have been using you should destroy it first
with the appropriate <DBMS>-destroy.sql file.

The separation of files into "initialize" and "destroy" is intended
to give some small measure of additional protection against
accidentally destroying a live database, but BE CAREFUL.
