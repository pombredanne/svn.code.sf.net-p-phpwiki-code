<?php // -*-php-*-
rcs_id('$Id: UpLoad.php,v 1.4 2004-02-21 19:12:59 rurban Exp $');
/*
 Copyright 2002 $ThePhpWikiProgrammingTeam

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

 */

/**
 * UpLoad:  Allow Administrator to upload files to a special directory,
 *          which should preferably be added to the InterWikiMap
 * Usage:   <?plugin UpLoad ?>
 * Author:  NathanGass <gass@iogram.ch>
 * Changes: ReiniUrban <rurban@x-ray.at>,
 *          qubit <rtryon@dartmouth.edu>
 * Note:    See also Jochen Kalmbach's plugin/UserFileManagement.php
 */

    /* Change these config variables to your needs. Paths must end with "/".
     */

class WikiPlugin_UpLoad
extends WikiPlugin
{
    //var $file_dir = PHPWIKI_DIR . "/img/";
    //var $url_prefix = DATA_PATH . "/img/";
    //what if the above are not set in index.php? seems to fail...

    var $disallowed_extensions = array('.php', '.pl', '.sh', '.cgi', '.exe');
    var $only_authenticated = true;

    function getName () {
        return "UpLoad";
    }

    function getDescription () {
        return _("Simple Plugin to load files up to server");
    }

    function getDefaultArguments() {
        return array();
    }

    function run($dbi, $argstr, &$request, $basepage) {
        $file_dir = defined('PHPWIKI_DIR') ? 
            PHPWIKI_DIR . "/uploads/" : "uploads/";
        $url_prefix = SERVER_NAME.DATA_PATH; 

        $action = $request->getURLtoSelf();
        $userfile = $request->getUploadedFile('userfile');
        $form = HTML::form(array('action' => $action,
                                 'enctype' => 'multipart/form-data',
                                 'method' => 'post'));
        $contents = HTML::div(array('class' => 'wikiaction'));
        //$contents = HTML();
        $contents->pushContent(HTML::input(array('type' => 'hidden',
                                                 'name' => 'MAX_FILE_SIZE',
                                                 'value' => MAX_UPLOAD_SIZE)));
        $contents->pushContent(HTML::input(array('name' => 'userfile',
                                                 'type' => 'file',
                                                 'size' => '50')));
        //$contents->pushContent(HTML::br());
        $contents->pushContent(HTML::raw(" "));
        $contents->pushContent(HTML::input(array('value' => _("Upload"),
                                                 'type' => 'submit')));
        $form->pushContent($contents);

        //$message = HTML::div(array('class' => 'wikiaction'));
        $message = HTML();

        if ($userfile) {
            $userfile_name = $userfile->getName();
            $userfile_name = basename($userfile_name);
            $userfile_tmpname = $userfile->getTmpName();

            if ($this->only_authenticated) {
                // Make sure that the user is logged in.
                // (NOTE: It's probably overkill to make sure that
                // they're both signed in AND authenticated, and
                // I'm not exactly sure of the difference between
                // the two, but I'm using both of them)
                //
                $user = $request->getUser();
                $signed_in = $user->isSignedIn();
                $authenticated = $user->isAuthenticated();
                if (!$signed_in || !$authenticated) {
                    $message->pushContent(_("ACCESS DENIED: Please log in to upload files"));
                    $message->pushContent(HTML::br());
                    $message->pushContent(HTML::br());

                    $result = HTML();
                    $result->pushContent($form);
                    $result->pushContent($message);
                    return $result;
                }
            }

            if (preg_match("/(" . join("|", $this->disallowed_extensions) . ")\$/",
                           $userfile_name)) {

                $message->pushContent(fmt("Files with extension %s are not allowed",
                                          join(", ", $this->disallowed_extensions)));
                $message->pushContent(HTML::br());
                $message->pushContent(HTML::br());
            }
            elseif (file_exists($file_dir . $userfile_name)) {
                $message->pushContent(fmt("There is already a file with name %s uploaded",
                                            $userfile_name));
                $message->pushContent(HTML::br());
                $message->pushContent(HTML::br());
            }
            elseif ($userfile->getSize() > (MAX_UPLOAD_SIZE)) {
                $message->pushContent(_("Sorry but this file is too big"));
                $message->pushContent(HTML::br());
                $message->pushContent(HTML::br());
            }
            elseif (move_uploaded_file($userfile_tmpname, $file_dir . $userfile_name)) {
                $message->pushContent(_("File successfully uploaded to location:"));
                $message->pushContent(HTML::br());
                $message->pushContent("$url_prefix$userfile_name");
                $message->pushContent(HTML::br());

                // the upload was a success and we need to mark this event in the "upload log"
                $upload_log = $file_dir . "file_list.txt";
                if (!is_writable($upload_log)) {
                    $message->pushContent(_("Error: the upload log is not writable"));
                    $message->pushContent(HTML::br());
                }
                elseif (!$log_handle = fopen ($upload_log, "a")) {
                    $message->pushContent(_("Error: can't open the upload logfile"));
                    $message->pushContent(HTML::br());
                }
                else {        // file size in KB; precision of 0.1
                    $file_size = round(($userfile->getSize())/1024, 1);
                    if ($file_size <= 0) {
                        $file_size = "&lt; 0.1";
                    }
                    fwrite($log_handle,
                           "\n"    // the newline makes it easier to read the log file
                           . "<tr><td><a href=$userfile_name>$userfile_name</a></td>"
                           . "<td align=right>$file_size</td>"
                           . "<td>&nbsp;&nbsp;" . date("M j, Y") . "</td>"
                           . "<td>&nbsp;&nbsp;<em>" . $user->getId() . "</em></td></tr>");
                    fclose($log_handle);
                }
            }
            else {
                $message->pushContent(HTML::br());
                $message->pushContent(_("Uploading failed."));
                $message->pushContent(HTML::br());
            }
        }
        else {
            $message->pushContent(HTML::br());
            $message->pushContent(HTML::br());
        }

        //$result = HTML::div( array( 'class' => 'wikiaction' ) );
        $result = HTML();
        $result->pushContent($form);
        $result->pushContent($message);
        return $result;
    }
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

// $Log: not supported by cvs2svn $
// Revision 1.3  2004/02/17 12:11:36  rurban
// added missing 4th basepage arg at plugin->run() to almost all plugins. This caused no harm so far, because it was silently dropped on normal usage. However on plugin internal ->run invocations it failed. (InterWikiSearch, IncludeSiteMap, ...)
//
// Revision 1.2  2004/01/26 09:18:00  rurban
// * changed stored pref representation as before.
//   the array of objects is 1) bigger and 2)
//   less portable. If we would import packed pref
//   objects and the object definition was changed, PHP would fail.
//   This doesn't happen with an simple array of non-default values.
// * use $prefs->retrieve and $prefs->store methods, where retrieve
//   understands the interim format of array of objects also.
// * simplified $prefs->get() and fixed $prefs->set()
// * added $user->_userid and class '_WikiUser' portability functions
// * fixed $user object ->_level upgrading, mostly using sessions.
//   this fixes yesterdays problems with loosing authorization level.
// * fixed WikiUserNew::checkPass to return the _level
// * fixed WikiUserNew::isSignedIn
// * added explodePageList to class PageList, support sortby arg
// * fixed UserPreferences for WikiUserNew
// * fixed WikiPlugin for empty defaults array
// * UnfoldSubpages: added pagename arg, renamed pages arg,
//   removed sort arg, support sortby arg
//
// Revision 1.1  2003/11/04 18:41:41  carstenklapp
// New plugin which was submitted to the mailing list some time
// ago. (This is the best UpLoad function I have seen for PhpWiki so
// far. Cleaned up text formatting and typos from the version on the
// mailing list. Still needs a few adjustments.)
//
?>
