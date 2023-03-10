<!DOCTYPE html>
<html xml:lang="en" lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Password Encryption Tool</title>
    <!--
    Copyright © 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

    This file is part of PhpWiki.

    PhpWiki is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    PhpWiki is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License along
    with PhpWiki; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

    SPDX-License-Identifier: GPL-2.0-or-later

    -->
</head>
<body>
<h1>Password Encryption Tool</h1>
<?php
function rand_ascii($length = 1)
{
    $s = "";
    for ($i = 1; $i <= $length; $i++) {
        // return only typeable 7 bit ascii, avoid quotes
        $s .= chr(mt_rand(40, 126));
    }
    return $s;
}

////
// Function to create better user passwords (much larger keyspace),
// suitable for user passwords.
// Sequence of random ASCII numbers, letters and some special chars.
// Note: There exist other algorithms for easy-to-remember passwords.
function random_good_password($minlength = 5, $maxlength = 8)
{
    $newpass = '';
    // assume ASCII ordering (not valid on EBCDIC systems!)
    $valid_chars = "!#%&+-.0123456789=@ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz";
    $start = ord($valid_chars);
    $end = ord(substr($valid_chars, -1));
    $length = mt_rand($minlength, $maxlength);
    while ($length > 0) {
        $newchar = mt_rand($start, $end);
        if (!strrpos($valid_chars, $newchar)) {
            continue;
        } // skip holes
        $newpass .= sprintf("%c", $newchar);
        $length--;
    }
    return $newpass;
}

/** PHP5 deprecated old-style globals if !(bool)ini_get('register_long_arrays').
 *  See Bug #1180115
 * We want to work with those old ones instead of the new superglobals,
 * for easier coding.
 */
foreach (array('SERVER', 'GET', 'POST', 'ENV') as $k) {
    if (!isset($GLOBALS['HTTP_' . $k . '_VARS']) and isset($GLOBALS['_' . $k])) {
        $GLOBALS['HTTP_' . $k . '_VARS'] =& $GLOBALS['_' . $k];
    }
}
unset($k);

$posted = $GLOBALS['HTTP_POST_VARS'];
if (!empty($posted['create'])) {
    $new_password = random_good_password();
    echo "<p>The newly created random password is:<br />\n<br />&nbsp;&nbsp;&nbsp;\n<samp><strong>",
    htmlentities($new_password), "</strong></samp></p>\n";
    $posted['password'] = $new_password;
    $posted['password2'] = $new_password;
}

if (($posted['password'] != "")
    && ($posted['password'] == $posted['password2'])
) {
    $password = $posted['password'];
    /**
     * https://www.php.net/manual/en/function.crypt.php
     */
    // Use the maximum salt length the system can handle.
    $salt_length = max(
        CRYPT_SALT_LENGTH,
        2 * CRYPT_STD_DES,
        9 * CRYPT_EXT_DES,
        12 * CRYPT_MD5,
        16 * CRYPT_BLOWFISH
    );
    // Generate the encrypted password.
    $encrypted_password = crypt($password, rand_ascii($salt_length));
    $debug = $HTTP_GET_VARS['debug'];
    if ($debug) {
        echo "The password was encrypted using a salt length of: $salt_length<br />\n";
    }
    echo "<p>The encrypted password is:<br />\n<br />&nbsp;&nbsp;&nbsp;\n<samp><strong>",
    htmlentities($encrypted_password), "</strong></samp></p>\n";
    echo "<hr />\n";
} elseif ($posted['password'] != "") {
    echo "The passwords did not match. Please try again.<br />\n";
}
if (empty($REQUEST_URI)) {
    $REQUEST_URI = $HTTP_ENV_VARS['REQUEST_URI'];
}
if (empty($REQUEST_URI)) {
    $REQUEST_URI = $_SERVER['REQUEST_URI'];
}
?>

<form action="<?php echo $REQUEST_URI ?>" method="post">
    <fieldset>
        <legend>Encrypt</legend>
        Enter a password twice to encrypt it:<br/>
        <input type="password" name="password" value=""/><br/>
        <input type="password" name="password2" value=""/> <input type="submit" value="Encrypt"/>
    </fieldset>
    <br/>
    or:<br/>
    <br/>
    <fieldset>
        <legend>Generate</legend>
        Create a new random password: <input type="submit" name="create" value="Create"/>
    </fieldset>
</form>
</body>
</html>
