#!/usr/bin/perl -n
# makefile helper to extract the database, user and password from config.ini

if (/^\s*DATABASE_DSN\s*=\s*"?([\w:\/@]+)/) { 
    $dsn = $1;
    $db = $user = $pass = '';
    $dsn =~ /.+\/(.+?)$/ and $db = $1;
    $dsn =~ /:\/\/(\w+):/ and $user = $1;
    $dsn =~ /:\/\/\w+:(\w+)@/ and $pass = $1;
    print "$db $user $pass\n";
    exit;
}

