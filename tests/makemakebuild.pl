#!/usr/bin/perl

#  Write out a Makefile and a build.xml file based on the *.inputs files
#  in the current directory. Steve Wainstead, April 2001.

# $Id: makemakebuild.pl,v 1.1 2001-09-20 20:52:22 wainstead Exp $

# read in all the input files, loop over each one and build up 
# text blocks that we will subsitute into the skeletons for Makefile
# and build.xml.

my @files = <*.inputs>;
chomp(@files); # prolly unnecessary, but oh well.

print "Found ", scalar(@files), " input files.\n";

foreach $inputfile (@files) {
  $inputfile =~ m/\.inputs$/;
  $javafile = "$`.java";
  $classname = $`;

  $test_make_target_names .= "$javafile ";
  $test_make_targets .=<<"EOLN";
$javafile: $inputfile
\tmaketest.pl $inputfile

EOLN

  $test_ant_targets .= <<"EOLN";
  <target name="$classname">
    <echo message="Testing with $classname..."/>
    <java classname="$classname"></java>
  </target>

EOLN

  push @test_dependency_names, $classname;

}

$test_dependency_names = join(',', @test_dependency_names);

#  print <<"SHOW_RESULTS";
#    make's targets: $test_make_target_names

#    make's acutual targets:
#  $test_make_targets

#    ant's target names: $test_dependency_names

#    ant's targets:
#  $test_ant_targets

#  SHOW_RESULTS


# these are the skeleton files for the Makefile and the build.xml file

$makefile = <<MAKEFILE_SKEL;
# Generate new test classes if their input files have changed.
# This makefile is called from an Ant build.xml though you can run
# it by hand.

tests = $test_make_target_names

all: \$(tests)

$test_make_targets

.PHONY: clean
clean:
\t-rm -f *.java

MAKEFILE_SKEL


$buildxml = <<"BUILDXML_SKEL";
<project name="test" default="all">
	
   <target 
      name="all"
      depends="init,generate,compile,test">
   </target>


   <target name="init">
      <tstamp/>
   </target>

   <target name="generate" depends="init">
      <exec executable="make">
      </exec>
   </target>



   <target name="compile" depends="generate">
      <javac srcdir="." destdir="." />
   </target>


   <target name="test" depends="compile,$test_dependency_names">
   </target>


   <target name="clean">

      <exec executable="make">
         <arg line="clean"/>
      </exec>

      <delete>
         <fileset dir="." includes="*.class"/>
      </delete>

   </target>


   <!-- individual test files are compiled here -->

$test_ant_targets

</project>
BUILDXML_SKEL


print "Writing Makefile...\n";
open MAKEFILE, ">./Makefile" or die $!;
print MAKEFILE $makefile;

print "Writing build.xml...\n";
open BUILDXML, ">./build.xml" or die $!;
print BUILDXML $buildxml;

print "Done.\n";
