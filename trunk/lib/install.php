<?php //-*-php-*-
rcs_id('$Id: install.php,v 1.1 2004-12-06 19:49:58 rurban Exp $');

/*
 Copyright 2004 $ThePhpWikiProgrammingTeam

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
 * Loaded when config/config.ini was not found.
 * So we have no main loop and no request object yet.
 */

function init_install() {
    // setup default settings
    IniConfig(dirname(__FILE__)."/../config/config-dist.ini");
}

function run_install() {
    // display a screen of various settings:
    // 1. convert from older index.php configuration
    // 2. database and admin_user setup based on configurator.php
    include(dirname(__FILE__)."/../configurator.php");
    // dump the current settings to config/config.ini.
}

init_install();
run_install();

/**
 $Log: not supported by cvs2svn $

 */

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>