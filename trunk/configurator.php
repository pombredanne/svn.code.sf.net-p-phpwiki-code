<?php printf("<?xml version=\"1.0\" encoding=\"%s\"?>\n", 'iso-8859-1'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<!-- $Id: configurator.php,v 1.1 2002-02-22 07:12:09 carstenklapp Exp $ -->
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



$properties['part0'] =
new part('Part Zero', false, '
Part Zero: If PHP needs help in finding where you installed the
rest of the PhpWiki code, you can set the include_path here.');



$properties['PHP include_path'] =
new iniset('include_path', false, "
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



$properties['partnull'] =
new part('Part Null', "
define ('PHPWIKI_VERSION', '1.3.2-jeffs-hacks');
require \"lib/prepend.php\";
rcs_id('$Id: configurator.php,v 1.1 2002-02-22 07:12:09 carstenklapp Exp $');
", "
Part Null: Don't touch this!");


$properties['partone'] =
new part('Part One', "///////////////////////////////////////////////////////////////////
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
new defines('ADMIN_PASSWD', "", "");



$properties['ZIPdump Authentication'] =
new defines_boolean('ZIPDUMP_AUTH', 
                    array('false' => 'everyone',
                          'true'  => 'only admin'), "
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










// MORE CONFIG OPTIONS GO IN HERE



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
            return "\n//ini_set('".$this->get_config_item_name()."', '\$".$this->get_config_item_name()."');";
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
        $separator = "\n/////////////////////////////////////////////////////////////////////";
        return $separator . str_replace("\n", "\n// ", $this->get_description()) ."\n$this->default_value";
    }
    function get_instructions($title) {
        $i = "<h2>".$this->get_config_item_name()."</h2>\n$this->description\n";
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
    $config .= "\ndie(\"$diemsg\");";
    $config .= "\n?>\n";

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
