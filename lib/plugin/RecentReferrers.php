<?php
/**
 * Copyright © 2004 $ThePhpWikiProgrammingTeam
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
 * Analyze our ACCESS_LOG
 * Check HTTP_REFERER
 *
 */

include_once 'lib/PageList.php';

class WikiPlugin_RecentReferrers extends WikiPlugin
{
    public function getDescription()
    {
        return _("Analyse access log.");
    }

    public function getDefaultArguments()
    {
        return array_merge(
            PageList::supportedArgs(),
            array(
                'limit' => 15,
                'noheader' => false,
            )
        );
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
        if (!ACCESS_LOG) {
            return HTML::div(array('class' => "error"), _("Error: no ACCESS_LOG"));
        }
        $args = $this->getArgs($argstr, $request);

        $noheader = $args['noheader'];
        if (!is_bool($noheader)) {
            if (($noheader == '0') || ($noheader == 'false')) {
                $noheader = false;
            } elseif (($noheader == '1') || ($noheader == 'true')) {
                $noheader = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "noheader"));
            }
        }

        $table = HTML::table(array('class' => 'pagelist'));
        if (!$args['noheader'] and !empty($args['caption'])) {
            $table->pushContent(HTML::caption(array('style' => 'caption-side:top'), $args['caption']));
        }
        $limit = $args['limit'];
        $accesslog =& $request->_accesslog;
        if ($logiter = $accesslog->get_referer($limit, "external_only")
            and $logiter->count()
        ) {
            $table->pushContent(HTML::tr(
                HTML::th(_("Target")),
                HTML::th(_("Referrer")),
                HTML::th(_("Host")),
                HTML::th(_("Date"))
            ));
            while ($logentry = $logiter->next()) {
                $table->pushContent(HTML::tr(
                    HTML::td($logentry['request']),
                    HTML::td($logentry['referer']),
                    HTML::td($logentry['host']),
                    HTML::td($logentry['time'])
                ));
            }
            return $table;
        }
        return HTML::raw('');
    }
}
