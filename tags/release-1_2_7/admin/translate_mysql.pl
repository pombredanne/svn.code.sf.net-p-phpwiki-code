
# Convert MySQL wiki database dump to a Microsoft SQL-Server compatible SQL script
# NB This is not a general-purpose MySQL->SQL-Server conversion script

# Author: Andrew K. Pearson
# Date:   01 May 2001

# Example usage: perl translate_mysql.pl dump.sql > dump2.sql

# NB I did not use sed because the version I have is limited to input lines of <1K in size

while (<>)
{
	$newvalue = $_;

	$newvalue =~ s/\\\"/\'\'/g;
	$newvalue =~ s/\\\'/\'\'/g;
	$newvalue =~ s/\\n/\'+char(10)+\'/g;
	$newvalue =~ s/TYPE=MyISAM;//g;
	$newvalue =~ s/int\(.+\)/int/g;
	$newvalue =~ s/mediumtext/text/g;
	$newvalue =~ s/^#/--/g;

	print $newvalue;
}

