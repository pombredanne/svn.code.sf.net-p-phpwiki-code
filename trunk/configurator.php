<?php printf("<?xml version=\"1.0\" encoding=\"%s\"?>\n", 'iso-8859-1'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<!-- $Id: configurator.php,v 1.2 2002-02-25 18:14:11 carstenklapp Exp $ -->
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
</head>
<body>

<h1>Configuration tool for PhpWiki 1.3.x</h1>

<p>This tool is provided for testing purposes only. It's not finished so don't try to use it to configure your server yet.</p>

<?php
/**
 * The Configurator is a php script to aid in the configuration of PhpWiki.
 * Parts of this file are based on PHPWeather's configurator.php file.
 * http://sourceforge.net/projects/phpweather/
 *
 * TO CHANGE THE CONFIGURATION OF YOUR PHPWIKI, DO *NOT* MODIFY THIS FILE!
 * more instructions go here
 * 
 * An index.php will be generated for you which you can also modify later if you wish.
 */


//////////////////////////////
// begin configuration options



$copyright = '
Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam = array(
"Steve Wainstead", "Clifford A. Adams", "Lawrence Akka", 
"Scott R. Anderson", "Jon Åslund", "Neil Brown", "Jeff Dairiki",
"Stéphane Gourichon", "Jan Hidders", "Arno Hollosi", "John Jorgensen",
"Antti Kaihola", "Jeremie Kass", "Carsten Klapp", "Marco Milanesi",
"Grant Morgan", "Jan Nieuwenhuizen", "Aredridel Niothke", 
"Pablo Roca Rozas", "Sandino Araico Sánchez", "Joel Uckelman", 
"Reini Urban", "Tim Voght");

This file is part of PhpWiki.

PhpWiki is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

PhpWiki is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with PhpWiki; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
';



$preamble = '
  This is the starting file for PhpWiki. All this file does is set
  configuration options, and at the end of the file it includes() the
  file lib/main.php, where the real action begins.

  This file is divided into six parts: Parts Zero, One, Two, Three,
  Four and Five. Each one has different configuration settings you can
  change; in all cases the default should work on your system,
  however, we recommend you tailor things to your particular setting.
';



$properties['Part Zero'] =
new part('part0', false, '
Part Zero: If PHP needs help in finding where you installed the
rest of the PhpWiki code, you can set the include_path here.');



$properties['PHP include_path'] =
new iniset('include_path', "\$include_path", "
NOTE: phpwiki uses the PEAR library of php code for SQL database
access. Your PHP is probably already configured to set
include_path so that PHP can find the pear code. If not (or if you
change include_path here) make sure you include the path to the
PEAR code in include_path. (To find the PEAR code on your system,
search for a file named 'PEAR.php'. Some common locations are:
<pre>
  Unixish systems:
    /usr/share/php
    /usr/local/share/php
  Mac OS X:
    /System/Library/PHP
</pre>
The above examples are already included by PhpWiki. You shouldn't
have to change this unless you see a WikiFatalError:
<pre>
    lib/FileFinder.php:82: Fatal[256]: DB.php: file not found
</pre>
Define the include path for this wiki: pear plus the phpwiki path
<pre>
$include_path = '.:/Apache/php/pear:/prog/php/phpwiki';
</pre>
Windows needs ';' as path delimiter. cygwin, mac and unix ':'
<pre>
if (substr(PHP_OS,0,3) == 'WIN') {
    $include_path = implode(';',explode(':',$include_path));
} elseif (substr(PHP_OS,0,6) == 'CYGWIN') {
    $include_path = '.:/usr/local/lib/php/pear:/usr/src/php/phpwiki';
} else {
    ;
}</pre>");



$properties['Part Null'] =
new part('partnullheader', "", "
Part Null: Don't touch this!");



$properties['Part Null Settings'] =
new unchangeable_property('partnullsettings', "
define ('PHPWIKI_VERSION', '1.3.3-jeffs-hacks');
require \"lib/prepend.php\";
rcs_id('\$Id: configurator.php,v 1.2 2002-02-25 18:14:11 carstenklapp Exp $');", "");



$properties['Part One'] =
new part('partone', "///////////////////////////////////////////////////////////////////
", "

Part One:
Authentication and security settings:
");



$properties['Wiki Name'] =
new defines('WIKI_NAME', ''/*'PhpWiki'*/, "
The name of your wiki.
This is used to generate a keywords meta tag in the HTML templates,
in bookmark titles for any bookmarks made to pages in your wiki,
and during RSS generation for the title of the RSS channel.");



$properties['Reverse DNS'] =
new defines_boolean('ENABLE_REVERSE_DNS',
                    array('true'  => 'perform additional reverse dns lookups',
                          'false' => 'just record the address as given by the httpd server'), "
If set, we will perform reverse dns lookups to try to convert the
users IP number to a host name, even if the http server didn't do
it for us.");



$properties['Admin Username'] =
new defines('ADMIN_USER', "", "
Username and password of administrator.
Set these to your preferences. For heaven's sake
pick a good password!");
$properties['Admin Password'] =
new defines_password('ADMIN_PASSWD', "", "");



$properties['ZIPdump Authentication'] =
new defines_boolean('ZIPDUMP_AUTH', 
                    array('false' => 'everyone may download zip dumps',
                          'true'  => 'only admin may download zip dumps'), "
If true, only the admin user can make zip dumps, else zip dumps
require no authentication.");



$properties['Strict Mailable Pagedumps'] =
new defines_boolean('STRICT_MAILABLE_PAGEDUMPS', 
                    array('false' => 'binary',
                          'true'  => 'quoted-printable'), "
If you define this to true, (MIME-type) page-dumps (either zip dumps,
or \"dumps to directory\" will be encoded using the quoted-printable
encoding.  If you're actually thinking of mailing the raw page dumps,
then this might be useful, since (among other things,) it ensures
that all lines in the message body are under 80 characters in length.

Also, setting this will cause a few additional mail headers
to be generated, so that the resulting dumps are valid
RFC 2822 e-mail messages.

Probably, you can just leave this set to false, in which case you get
raw ('binary' content-encoding) page dumps.");



$properties['Maximum Upload Size'] =
new defines_numeric('MAX_UPLOAD_SIZE', "16 * 1024 * 1024", "
The maximum file upload size.");



$properties['Minor Edit Timeout'] =
new defines_numeric('MINOR_EDIT_TIMEOUT', "7 * 24 * 3600", "
If the last edit is older than MINOR_EDIT_TIMEOUT seconds, the
default state for the \"minor edit\" checkbox on the edit page form
will be off.");



//fixme: new property subclass
$properties['Disabled Actions'] =
new property('DisabledActions', "array('dumpserial', 'loadfile')", "
Actions listed in this array will not be allowed.");



//fixme: commented out by default
$properties['Access Log'] =
new defines('ACCESS_LOG', "/tmp/wiki_access_log", "
PhpWiki can generate an access_log (in \"NCSA combined log\" format)
for you. If you want one, define this to the name of the log file.");



$properties['Strict Login'] =
new defines_boolean('ALLOW_BOGO_LOGIN',
                    array('true'  => 'Users may Sign In with any WikiWord',
                          'false' => 'Only admin may Sign In'), "
If ALLOW_BOGO_LOGIN is true, users are allowed to login (with
any/no password) using any userid which: 1) is not the ADMIN_USER,
2) is a valid WikiWord (matches $WikiNameRegexp.)");



$properties['Require Sign In Before Editing'] =
new defines_boolean('REQUIRE_SIGNIN_BEFORE_EDIT',
                    array('false' => 'Do not require Sign In',
                          'true'  => 'Require Sign In'), "
If set, then if an anonymous user attempts to edit a page he will
be required to sign in.  (If ALLOW_BOGO_LOGIN is true, of course,
no password is required, but the user must still sign in under
some sort of BogoUserId.)");



$properties['Path for PHP Session Support'] =
new iniset('session.save_path', 'some_other_directory', "
The login code now uses PHP's session support. Usually, the default
configuration of PHP is to store the session state information in
/tmp. That probably will work fine, but fails e.g. on clustered
servers where each server has their own distinct /tmp (this is the
case on SourceForge's project web server.) You can specify an
alternate directory in which to store state information like so
(whatever user your httpd runs as must have read/write permission
in this directory):");



$properties['Disable PHP Transparent Session ID'] =
new unchangeable_property('session.use_trans_sid', "@ini_set('session.use_trans_sid', 0);", "
If your php was compiled with --enable-trans-sid it tries to
add a PHPSESSID query argument to all URL strings when cookie
support isn't detected in the client browser.  For reasons
which aren't entirely clear (PHP bug) this screws up the URLs
generated by PhpWiki.  Therefore, transparent session ids
should be disabled.  This next line does that.

(At the present time, you will not be able to log-in to PhpWiki,
or set any user preferences, unless your browser supports cookies.)");



$properties['Part Two'] =
new part('parttwo', "///////////////////////////////////////////////////////////////////
", "

Part Two:
Database Selection
");



// MORE CONFIG OPTIONS GO IN HERE



$properties['Page Revisions'] =
new part('parttworevisions', "
", "

The next section controls how many old revisions of each page are
kept in the database.

There are two basic classes of revisions: major and minor. Which
class a revision belongs in is determined by whether the author
checked the \"this is a minor revision\" checkbox when they saved the
page.
 
There is, additionally, a third class of revisions: author
revisions. The most recent non-mergable revision from each distinct
author is and author revision.

The expiry parameters for each of those three classes of revisions
can be adjusted seperately. For each class there are five
parameters (usually, only two or three of the five are actually
set) which control how long those revisions are kept in the
database.
<pre>
   max_keep: If set, this specifies an absolute maximum for the
            number of archived revisions of that class. This is
            meant to be used as a safety cap when a non-zero
            min_age is specified. It should be set relatively high,
            and it's purpose is to prevent malicious or accidental
            database overflow due to someone causing an
            unreasonable number of edits in a short period of time.

  min_age:  Revisions younger than this (based upon the supplanted
            date) will be kept unless max_keep is exceeded. The age
            should be specified in days. It should be a
            non-negative, real number,

  min_keep: At least this many revisions will be kept.

  keep:     No more than this many revisions will be kept.

  max_age:  No revision older than this age will be kept.
</pre>
Supplanted date: Revisions are timestamped at the instant that they
cease being the current revision. Revision age is computed using
this timestamp, not the edit time of the page.

Merging: When a minor revision is deleted, if the preceding
revision is by the same author, the minor revision is merged with
the preceding revision before it is deleted. Essentially: this
replaces the content (and supplanted timestamp) of the previous
revision with the content after the merged minor edit, the rest of
the page metadata for the preceding version (summary, mtime, ...)
is not changed.
");



// MORE CONFIG OPTIONS GO IN HERE



$properties['Part Three'] =
new part('partthree', "///////////////////////////////////////////////////////////////////
", "

Part Three:
Page appearance and layout
");



$properties['Theme'] =
new defines_selection('THEME',
              array('default'  => 'default',
                    'Hawaiian' => 'Hawaiian',
                    'MacOSX'   => 'MacOSX',
                    'Portland' => 'Portland',
                    'Sidebar'  => 'Sidebar',
                    'SpaceWiki' => 'SpaceWiki'), "
THEME

Most of the page appearance is controlled by files in the theme
subdirectory.

There are a number of pre-defined themes shipped with PhpWiki.
Or you may create your own (e.g. by copying and then modifying one of
stock themes.)

Pick one.
<pre>
define('THEME', 'default');
define('THEME', 'Hawaiian');
define('THEME', 'MacOSX');
define('THEME', 'Portland');
define('THEME', 'Sidebar');
define('THEME', 'SpaceWiki');</pre>");




$properties['Character Set'] =
new defines('CHARSET', 'iso-8859-1', "
Select a valid charset name to be inserted into the xml/html pages, 
and to reference links to the stylesheets (css). For more info see: 
http://www.iana.org/assignments/character-sets. Note that PhpWiki 
has been extensively tested only with the latin1 (iso-8859-1) 
character set.

If you change the default from iso-8859-1 PhpWiki may not work 
properly and it will require code modifications. However, character 
sets similar to iso-8859-1 may work with little or no modification 
depending on your setup. The database must also support the same 
charset, and of course the same is true for the web browser. (Some 
work is in progress hopefully to allow more flexibility in this 
area in the future).");



$properties['Language'] =
new selection('LANG',
              array('C'  => 'English',
                    'nl' => 'Nederlands',
                    'es' => 'Español',
                    'fr' => 'Français',
                    'de' => 'Deutsch',
                    'sv' => 'Svenska',
                    'it' => 'Italiano'), "
Select your language/locale - default language is \"C\" for English.
Other languages available:<pre>
English \"C\"  (English    - HomePage)
Dutch   \"nl\" (Nederlands - ThuisPagina)
Spanish \"es\" (Español    - PáginaPrincipal)
French  \"fr\" (Français   - Accueil)
German  \"de\" (Deutsch    - StartSeite)
Swedish \"sv\" (Svenska    - Framsida)
Italian \"it\" (Italiano   - PaginaPrincipale)
</pre>
If you set \$LANG to the empty string, your systems default language
(as determined by the applicable environment variables) will be
used.

Note that on some systems, apprently using these short forms for
the locale won't work. On my home system 'LANG=de' won't result in
german pages. Somehow the system must recognize the locale as a
valid locale before gettext() will work, i.e., use 'de_DE', 'nl_NL'.");



// MORE CONFIG OPTIONS GO IN HERE



$properties['Part Four'] =
new part('partfour', "///////////////////////////////////////////////////////////////////
", "

Part Four:
Mark-up options.
");



//fixme: new property subclass
$properties['Allowed Protocols'] =
new property('AllowedProtocols', "http|https|mailto|ftp|news|nntp|ssh|gopher", "
allowed protocols for links - be careful not to allow \"javascript:\"
URL of these types will be automatically linked.
within a named link [name|uri] one more protocol is defined: phpwiki");



//fixme: new property subclass
$properties['Inline Images'] =
new property('InlineImages', "png|jpg|gif", "
URLs ending with the following extension should be inlined as images");



$properties['WikiName Regexp'] =
new property('WikiNameRegexp', "(?<![[:alnum:]])(?:[[:upper:]][[:lower:]]+){2,}(?![[:alnum:]])", "
Perl regexp for WikiNames (\"bumpy words\")
(?<!..) & (?!...) used instead of '\b' because \b matches '_' as well");



$properties['InterWiki Map File'] =
new defines('INTERWIKI_MAP_FILE', "lib/interwiki.map", "
InterWiki linking -- wiki-style links to other wikis on the web

The map will be taken from a page name InterWikiMap.
If that page is not found (or is not locked), or map
data can not be found in it, then the file specified
by INTERWIKI_MAP_FILE (if any) will be used.");



$properties['Part Five'] =
new part('partfive', "///////////////////////////////////////////////////////////////////
", "

Part Five:
URL options -- you can probably skip this section.
");



// MORE CONFIG OPTIONS GO IN HERE



$end = '

////////////////////////////////////////////////////////////////
// Okay... fire up the code:
////////////////////////////////////////////////////////////////

include "lib/main.php";

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
';



// end of configuration options
///////////////////////////////
// begin class definitions

/**
 * A basic property.
 *
 * Produces a string in the form "$name = value;"
 * e.g.:
 * $InlineImages = "png|jpg|gif";
 */
class property {

    var $config_item_name;
    var $default_value;
    var $description;

    function property($config_item_name, $default_value, $description) {
        $this->config_item_name = $config_item_name;
        $this->description = $description;
        $this->default_value = $default_value;
    }

    function get_config_item_name() {
        return $this->config_item_name;
    }

    function get_description() {
        return $this->description;
    }

    function get_config_line($posted_value) {
        return "\n\$" . $this->get_config_item_name() . " = \"" . $posted_value . "\";";
    }
    function get_config($posted_value) {
        $d = str_replace("<pre>", "", $this->get_description());
        $d = str_replace("</pre>", "", $d);
        $d = str_replace("\n", "\n// ", $d) . $this->get_config_line($posted_value) ."\n";
        return $d;
    }

    function get_instructions($title) {
        $i = "<p><b><h3>" . $title . "</h3></b></p>\n    <p>" . str_replace("\n\n", "</p><p>", $this->get_description()) . "</p>\n";
        return "<tr>\n<td>\n" . $i . "</td>\n";
    }

    function get_html() {
        return "<input type=\"text\" name=\"" . $this->get_config_item_name() . "\" value=\"" . $this->default_value . "\">";
    }
}

class unchangeable_property extends property {
    function get_html() {
        return "";
    }
    function get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        return "${n}".$this->default_value;
    }
    function get_instructions($title) {
        $i = "<p><b><h3>" . $title . "</h3></b></p>\n    <p>" . str_replace("\n\n", "</p><p>", $this->get_description()) . "</p>\n";
        $i = $i ."<em>Not editable.</em><br />\n<pre>" . $this->default_value."</pre>";
        return "<tr>\n<td colspan=\"2\">\n" .$i ."</td></tr>\n";
    }

}

class selection extends property {
    function get_html() {
        $output = '<select name="' . $this->get_config_item_name() . "\">\n";
        /* The first option is the default */
        while(list($option, $label) = each($this->default_value)) {
            $output .= "  <option value=\"$option\">$label</option>\n";
        }
        $output .= "    </select>\n  </td>\n";
        return $output;
    }
}


class defines extends property {
    function get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == '')
            return "${n}//define('".$this->get_config_item_name()."', \"\");";
        else
            return "${n}define('".$this->get_config_item_name()."', '$posted_value');";
    }
}

class defines_selection extends property {
    function get_config_line($posted_value) {
        return defines::get_config_line($posted_value);
    }
    function get_html() {
        return selection::get_html();
    }
}


/**
 * Seed the random number generator.
 *
 * better_srand() ensures the randomizer is seeded only once.
 * 
 * How random do you want it? See:
 * http://www.php.net/manual/en/function.srand.php
 * http://www.php.net/manual/en/function.mt-srand.php
 */
function better_srand($seed = '') {
    static $wascalled = FALSE;
    if (!$wascalled) {
        $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
        srand($seed);
        $wascalled = TRUE;
        //trigger_error("new random seed", E_USER_NOTICE); //debugging
    }
}

function rand_ascii($length = 1) {
    better_srand();
   //srand((double) microtime() * 1000000);
   $s = "";
   for ($i = 1; $i <= $length; $i++) {
       $s .= chr(rand(40, 126)); // return only typeable 7 bit ascii
   }
   return $s;
}
class defines_password extends defines {
    function get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == '')
            return "${n}//define('".$this->get_config_item_name()."', \"\");";
        else {
            if (function_exists('crypt')) {
                $salt_length = max(CRYPT_SALT_LENGTH,
                                    2 * CRYPT_STD_DES,
                                    9 * CRYPT_EXT_DES,
                                    12 * CRYPT_MD5,
                                    16 * CRYPT_BLOWFISH);
                // generate an encrypted password
                $crypt_pass = crypt($posted_value, rand_ascii($salt_length));
                $p = "${n}define('".$this->get_config_item_name()."', '$crypt_pass');";
                $p = $p . "\n// If you used the passencrypt.php utility to encode the password";
                $p = $p . "\n// then uncomment this line:";
                return $p . "\ndefine('ENCRYPTED_PASSWD', true);";
            } else {
                $p = "${n}define('".$this->get_config_item_name()."', '$posted_value');";
                $p = $p . "\n// If you used the passencrypt.php utility to encode the password";
                $p = $p . "\n// then uncomment this line:";
                $p = $p . "\n//define('ENCRYPTED_PASSWD', true);";
                $p = $p . "\n// Encrypted passwords cannot be used:";
                $p = $p . "\n// 'function crypt()' not available in this version of php";
                return $p;
            }
        }
    }
    function get_html() {
        return "<input type=\"password\" name=\"" . $this->get_config_item_name() . "\" value=\"" . $this->default_value . "\">";
    }
}


class defines_numeric extends property {
    function get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == '')
            return "${n}//define('".$this->get_config_item_name()."', 0);";
        else
            return "${n}define('".$this->get_config_item_name()."', $posted_value);";
    }
}

class iniset extends property {
    function get_config_line($posted_value) {
        if ($posted_value)
            return "\nini_set('".$this->get_config_item_name()."', '$posted_value');";
        else
            return "\n//ini_set('".$this->get_config_item_name()."', '".$this->default_value."');";
    }
}

class defines_boolean extends property {
    function get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        return "${n}define('".$this->get_config_item_name()."', $posted_value);";
    }
    function get_html() {
        $output = '<select name="' . $this->get_config_item_name() . "\">\n";
        /* The first option is the default */
        list($option, $label) = each($this->default_value);
        $output .= "  <option value=\"$option\" selected>$label</option>\n";
        /* There can only be two options */
        list($option, $label) = each($this->default_value);
        $output .= "  <option value=\"$option\">$label</option>\n";
        $output .= "</select>\n  </td>\n";
        return $output;
    }
}

class part extends property {
    function get_config($posted_value) {
        $d = str_replace("<pre>", "", $this->get_description());
        $d = str_replace("</pre>", "", $d);
        $separator = "\n/////////////////////////////////////////////////////////////////////";
        return $separator . str_replace("\n", "\n// ", $d) ."\n$this->default_value";
    }
    function get_instructions($title) {
        $i = "<p><b><h2>" . $title . "</h2></b></p>\n    <p>" . str_replace("\n\n", "</p><p>", $this->get_description()) . "</p>\n";
        return "<tr>\n<td colspan=\"2\" bgcolor=\"#eee\">\n" .$i ."</td></tr>\n";
    }
    function get_html() {
        return "";
    }
}

// end of class definitions
/////////////////////////////
// begin auto generation code

if ($action == 'make_config') {

  $timestamp = date ('dS of F, Y H:i:s');

    $config = "<?php
/* This is a local configuration file for PhpWiki.
 * It was automatically generated by the configurator script
 * on the $timestamp.
 */

/*$copyright*/

/////////////////////////////////////////////////////////////////////
/*$preamble*/
";

    $posted = $GLOBALS['HTTP_POST_VARS'];

    echo "<hr /><pre>\n";
    print_r($GLOBALS['HTTP_POST_VARS']);
    echo "</pre><hr />\n";

    /*

    */

    foreach($properties as $option_name => $a) {
        $posted_value = $posted[$a->config_item_name];
        $config .= $properties[$option_name]->get_config($posted_value);
    }

    $diemsg = "The configurator.php is provided for testing purposes only.\nYou can't use this file with your PhpWiki server yet!!";
    $config .= "\ndie(\"$diemsg\");\n";
    $config .= $end;

    /* We first check if the config-file exists. */
    if (file_exists('defaults.php')) {
        /* We make a backup copy of the file */
        $new_filename = 'defaults.' . time() . '.php';
        if (@copy('defaults.php', $new_filename)) {
            $fp = @fopen('defaults.php', 'w');
        }
    } else {
        $fp = @fopen('defaults.php', 'w');
    }

    if ($fp) {
        fputs($fp, $config);
        fclose($fp);
        echo "<p>The configuration was written to <code><b>defaults.php</b></code>. A backup was made to <code><b>$new_filename</b></code>.</p>\n";
    } else {
        echo "<p>A configuration file could <b>not</b> be written. You should copy the above configuration to a file, and manually save it as <code><b>defaults.php</b></code>.</p>\n";
    }

    echo "<hr />\n<p>Here's the configuration file based on your answers:</p>\n<pre>\n";
    echo htmlentities($config);
    echo "</pre>\n<hr />\n";

    echo "<!--If she can stand it, I can. Play it!-->\n";
    echo "<p>Would you like to <a href=\"configurator.php\">play again</a>?</p>\n";

    } else {
        /* No action has been specified - we make a form. */

        echo '
        <form action="configurator.php" method="post">
        <table border="1" cellpadding="4" cellspacing="0">
        <input type="hidden" name="action" value="make_config">
        ';

        while(list($property, $obj) = each($properties)) {
            echo $obj->get_instructions($property);
            if ($h = $obj->get_html())
                echo "<td>".$h."</td>\n";
        }

        echo '
            </table>
            <p><input type="submit" value="Make config-file"> <input type="reset" value="Clear"></p>
            </form>
            ';

    }
?>
</body>
</html>
