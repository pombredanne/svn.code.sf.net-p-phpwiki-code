<html>
<body>
<?php

$include_path = "/home/swain/phpwiki/lib";
if (!empty($include_path)) ini_set('include_path', $include_path);

// Include the FFDB library
include("ffdb.inc.php");

// set path to database
define(DB_HOME, "/tmp");

// define the "tables" of the database
$db[page]     = DB_HOME . "/page";
$db[version]  = DB_HOME . "/version";
$db[recent]   = DB_HOME . "/recent";
$db[nonempty] = DB_HOME . "/nonempty";
$db[link]     = DB_HOME . "/link";
$db[session]  = DB_HOME . "/session";

// define the schema; each "table" is defined by an array of arrays
$schema[page] = array( 
                      array("id", FFDB_INT, "key"),
                      array("pagename", FFDB_STRING),
                      array("hits", FFDB_INT),
                      array("pagedata", FFDB_STRING)                      
                );
                

$schema[version] = array( 
                         array("id", FFDB_INT, "key"),
                         array("version", FFDB_INT),
                         array("mtime", FFDB_INT),
                         array("minor_edit", FFDB_INT),
                         array("content", FFDB_STRING),
                         array("versiondata", FFDB_STRING)
                         );
                         
                         
$schema[recent] = array( 
                        array("id", FFDB_INT, "key"),
                        array("latestversion",  FFDB_INT),
                        array("latestmajor",  FFDB_INT),
                        array("latestminor",  FFDB_INT)
                        );

$schema[nonempty] = array( 
                          array("id", FFDB_INT, "key")
                          );

$schema[link] = array( 
                array("linkfrom", FFDB_INT), 
                array("linkto", FFDB_INT)
                );

$schema[session] = array( 
                array("sess_id", FFDB_STRING, "key"), 
                array("sess_data", FFDB_STRING),
                array("sess_date", FFDB_INT)
                );


// array to hold the ffdb objects
$dbi = array();

while (list($key, $val) = each($db)) {
    echo "$key, $val<br>";
    $dbi[$key] = new FFDB();


    if (!$dbi[$key]->open($db[$key])) {
        // Try and create it... 
        if (!$dbi[$key]->create($db[$key], $schema[$key])) {
            echo "Error creating database\n";
            return;
        }
    }

}


?>