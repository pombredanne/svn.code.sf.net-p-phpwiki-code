<?php
rcs_id('$Id: Values.php,v 1.1 2003-01-28 07:32:24 zorloc Exp $')
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
* This is the master array that holds all of the configuration
* values.
*/
$values = array(); 

/*
This is a template for a constant or variable value.
 
$variable = array(
    'type' => '',
    'name' => '',
    'defaultValue' => ,
    'description' => array(
        'short' => '',
        'full' => ''
    ),
    'validator' => array(
        'type' => '',
    )
);
*/

/**
* This defines the Constant that holds the name of the wiki
*/
$values[] = array(
    'type' => 'Constant',
    'name' => 'WIKI_NAME',
    'defaultValue' => 'PhpWiki',
    'description' => array(
        'short' => 'Name of your Wiki.',
        'full' => 'This can be any string, but it should be short and informative'
    ),
    'validator' => array(
        'type' => 'String'
    )
);

//$Log: not supported by cvs2svn $

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>