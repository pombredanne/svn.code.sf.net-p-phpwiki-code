<?php
/**
 * Copyright © 2007 $ThePhpWikiProgrammingTeam
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

/**
 * UriResolver/uri => xml-id
 *   This function transforms a valid url-encoded URI into a string
 *   that can be used as an XML-ID. The mapping should be injective.
 * Inverse to RdfWriter::makeURIfromXMLExportId()
 * Usage: internal
 */

require_once 'lib/SemanticWeb.php';

class WikiPlugin_UriResolver extends WikiPlugin
{
    public function getDescription()
    {
        return _("Converts an URI-escaped identifier back to an unique XML-ID.");
    }

    public function getDefaultArguments()
    {
        return array();
    }

    public function allow_undeclared_arg($name, $value)
    {
        return true;
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        global $WikiTheme;
        if ($WikiTheme->DUMP_MODE) {
            return '';
        }
        $args = $request->getArgs();
        unset($args['pagename']);
        unset($args['action']);
        unset($args['start_debug']);
        // FIXME: ?Test=1 => Test
        $arg = join("/", array_keys($args));
        return RdfWriter::makeXMLExportId($arg);
    }
}
