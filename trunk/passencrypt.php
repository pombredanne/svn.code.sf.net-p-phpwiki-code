<?php printf("<?xml version=\"1.0\" encoding=\"%s\"?>\n", 'iso-8859-1'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<!-- $Id: passencrypt.php,v 1.1 2002-02-24 20:33:24 carstenklapp Exp $ -->
<title>Password Encryption Tool</title>
<!--
Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

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
-->
</head>
<body>
<h1>Password Encryption Tool</h1>
<?php
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
    $s = "";
    for ($i = 1; $i <= $length; $i++) {
        $s .= chr(rand(40, 126)); // return only typeable 7 bit ascii, avoid quotes
    }
    return $s;
}


$posted = $GLOBALS['HTTP_POST_VARS'];
if ($password = $posted['password']) {
    /**
     * http://www.php.net/manual/en/function.crypt.php
     */
    // Use the maximum salt length the system can handle.
    $salt_length = max(CRYPT_SALT_LENGTH,
                        2 * CRYPT_STD_DES,
                        9 * CRYPT_EXT_DES,
                    12 * CRYPT_MD5,
                    16 * CRYPT_BLOWFISH);
    // Generate the encrypted password.
    $encrypted_password = crypt($password, rand_ascii($salt_length));
    $debug = false;
    if ($debug)
        echo "The password was encrypted using a salt length of: $salt_length<br />\n";
    echo "<p>The encrypted password is:<br />\n<br />\n<strong>$encrypted_password</strong></p>\n";
    echo "<hr />\n";
}
?>

<form action="passencrypt.php" method="post">
Enter a password to encrypt: <input type="password" name="password" value="" /> <input type="submit" value="Encrypt" />
</form>
</body>
</html>
