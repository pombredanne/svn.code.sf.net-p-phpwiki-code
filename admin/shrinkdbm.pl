#!/usr/bin/perl -w

# $Id: shrinkdbm.pl,v 1.1.2.1.2.3 2005-01-07 14:02:27 rurban Exp $

# shrink a DBM file
# Steve Wainstead, July 2000
# this script is public domain and has no warranty at all.

use strict;
use Fcntl;
use GDBM_File;
use Getopt::Std;
use vars ('$opt_o', '$opt_i');
my (%old_db, %new_db);

# $opt_i == input file
# $opt_o == output file
getopts('i:o:');

# less confusing names
my $input_db_file = $opt_i;
my $output_db_file = $opt_o;


die <<"USAGE" unless ($input_db_file and $output_db_file);
Usage: $0 -i <infile> -o <outfile>
  where: infile is a GDBM file and,
         outfile is the name of the new file to write to.

The idea is to copy the old DB file to a new one and thereby
save space.

USAGE

# open old file
tie (%old_db, "GDBM_File", $input_db_file, O_RDWR, 0666)
  or die "Can't tie $input_db_file: $!\n";

print "There are ", scalar(keys %old_db), " keys in $input_db_file\n";

# open new file, deleting it first if it's already there
if (-e $output_db_file) { unlink $opt_o; }
tie (%new_db, "GDBM_File", $output_db_file, O_RDWR|O_CREAT, 0666)
  or die "Can't tie $input_db_file: $!\n";

# copy the files
while (my($key, $value) = each(%old_db)) {
   $new_db{$key} = $value;
}

print "There are now ", scalar(keys %old_db), " keys in $input_db_file\n";
print "There are ", scalar(keys %new_db), " keys in $output_db_file\n";
untie(%old_db);
untie(%new_db);

