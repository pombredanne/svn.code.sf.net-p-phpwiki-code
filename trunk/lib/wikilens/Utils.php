<?php
/**
 * Copyright © 2004 Mike Cassano
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

function addPageTextData($user, $dbi, $new_data, $START_DELIM, $DELIM)
{
    // This is largely lifted from the TranslateText plugin, which performs a
    // similar set of functions (retrieves a user's homepage, modifies it
    // progmatically, and saves the changes)
    $homepage = $user->_HomePagehandle;
    $transpagename = $homepage->getName();
    $page = $dbi->getPage($transpagename);
    $current = $page->getCurrentRevision();
    $version = $current->getVersion();
    if ($version) {
        $text = $current->getPackedContent() . "\n";
        $meta = $current->_data;
    } else {
        $text = '';
        $meta = array('author' => $user->getId());
    }

    // add new data to the appropriate line
    if (preg_match('/^' . preg_quote($START_DELIM, '/') . '/', $text)) {
        // need multiline modifier to match EOL correctly
        $text = preg_replace(
            '/(^' . preg_quote($START_DELIM, '/') . '.*)$/m',
            '$1' . $DELIM . $new_data,
            $text
        );
    } else {
        // handle case where the line does not yet exist
        $text .= "\n" . $START_DELIM . $new_data . "\n";
    }

    // advance version counter, save
    $page->save($text, $version + 1, $meta);
}

function getMembers($groupName, $dbi, $START_DELIM = false, $DELIM = ",")
{
    if (!$START_DELIM) {
        $START_DELIM = _("Members:");
    }
    return getPageTextData($groupName, $dbi, $START_DELIM, $DELIM);
}

function getPageTextData($fromUser, $dbi, $START_DELIM, $DELIM)
{
    if (is_object($fromUser)) {
        $fromUser = $fromUser->getId();
    }
    if ($fromUser == "") {
        return "";
    }
    $userPage = $dbi->getPage($fromUser);
    $transformed = $userPage->getCurrentRevision();
    $pageArray = $transformed->getContent();
    $p = -1;
    for ($i = 0; $i < count($pageArray); $i++) {
        if ($pageArray[$i] != "") {
            if (!((strpos($pageArray[$i], $START_DELIM)) === false)) {
                $p = $i;
                break;
            }
        }
    }
    $retArray = array();
    if ($p >= 0) {
        $singles = $pageArray[$p];
        $singles = substr($singles, strpos($singles, $START_DELIM) + strlen($START_DELIM));

        $retArray = explode($DELIM, $singles);
    }
    for ($i = 0; $i < count($retArray); $i++) {
        $retArray[$i] = trim($retArray[$i]);
    }
    //$retArray = array_filter($retArray, "notEmptyName");
    $retArray = array_unique($retArray);

    return $retArray;
}

function notEmptyName($var)
{
    return $var != "";
}
