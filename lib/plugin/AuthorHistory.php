<?php
/**
 * Copyright © 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam
 * Copyright © 2009 Marc-Etienne Vargenau, Alcatel-Lucent
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

/*
 *** EXPERIMENTAL PLUGIN ******************
 Needs a lot of work! Use at your own risk.
 ******************************************

 try this in a page called AuthorHistory:

<<AuthorHistory page=username includeminor=true >>
----
<<AuthorHistory page=all >>

 try this in a subpage of your UserName: (UserName/AuthorHistory)

<<AuthorHistory page=all includeminor=true >>

* Display a list of revision edits by one particular user, for the
* current page, a specified page, or all pages.

* This is a big hack to create a PageList like table. (PageList
* doesn't support page revisions yet, only pages.)

* Make a new subclass of PageHistory to filter changes of one (or all)
* page(s) by a single author?

*/

require_once 'lib/PageList.php';

class WikiPlugin_AuthorHistory extends WikiPlugin
{
    public $_args;

    public function getDescription()
    {
        return _("List all page revisions edited by one user with diff links, or show a PageHistory-like list of a single page for only one user.");
    }

    public function getDefaultArguments()
    {
        global $request;
        return array('exclude' => '',
            'noheader' => false,
            'includeminor' => false,
            'includedeleted' => false,
            'author' => $request->_user->UserName(),
            'page' => '[pagename]',
            'info' => 'version,minor,author,summary,mtime'
        );
    }

    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    public function run($dbi, $argstr, &$request, $basepage)
    {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);

        if (!is_bool($noheader)) {
            if (($noheader == '0') || ($noheader == 'false')) {
                $noheader = false;
            } elseif (($noheader == '1') || ($noheader == 'true')) {
                $noheader = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "noheader"));
            }
        }

        if (!is_bool($includeminor)) {
            if (($includeminor == '0') || ($includeminor == 'false')) {
                $includeminor = false;
            } elseif (($includeminor == '1') || ($includeminor == 'true')) {
                $includeminor = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "includeminor"));
            }
        }

        if (!is_bool($includedeleted)) {
            if (($includedeleted == '0') || ($includedeleted == 'false')) {
                $includedeleted = false;
            } elseif (($includedeleted == '1') || ($includedeleted == 'true')) {
                $includedeleted = true;
            } else {
                return $this->error(sprintf(_("Argument '%s' must be a boolean"), "includedeleted"));
            }
        }

        if (!$author) { // user not signed in and no author specified
            return HTML::p(array('class' => 'error'), _("You are not signed in and no author is specified."));
        }
        if ($page && $page == 'username') { //FIXME: use [username]!!!!!
            $page = $author;
        }

        $nbsp = HTML::raw('&nbsp;');

        global $WikiTheme; // date & time formatting

        $table = HTML::table(array('class' => 'pagelist'));
        $thead = HTML::thead();
        $tbody = HTML::tbody();

        if (!($page == 'all')) {
            $p = $dbi->getPage($page);

            $thead->pushContent(HTML::tr(
                HTML::th(
                array('class' => 'align-right'),
                _("Version")
            ),
                $includeminor ? HTML::th(_("Minor")) : "",
                HTML::th(_("Author")),
                HTML::th(_("Summary")),
                HTML::th(_("Modified"))
            ));

            $allrevisions_iter = $p->getAllRevisions();
            while ($rev = $allrevisions_iter->next()) {
                $isminor = $rev->get('is_minor_edit');
                $authordoesmatch = $author == $rev->get('author');

                if ($authordoesmatch && (!$isminor || ($includeminor && $isminor))) {
                    $difflink = Button(
                        array('action' => 'diff',
                            'previous' => 'minor'),
                        $rev->getVersion(),
                        $rev
                    );
                    $tr = HTML::tr(
                        HTML::td(
                        array('class' => 'align-right'),
                        $difflink,
                        $nbsp
                    ),
                        $includeminor ? (HTML::td($nbsp, ($isminor ? "minor" : "major"), $nbsp)) : "",
                        HTML::td($nbsp, WikiLink(
                            $rev->get('author'),
                            'if_known'
                        ), $nbsp),
                        HTML::td($nbsp, $rev->get('summary')),
                        HTML::td(
                            array('class' => 'align-right'),
                            $WikiTheme->formatDateTime($rev->get('mtime'))
                        )
                    );

                    $class = $isminor ? 'evenrow' : 'oddrow';
                    $tr->setAttr('class', $class);
                    $tbody->pushContent($tr);
                }
            }
            $captext = fmt(
                $includeminor
                           ? "History of all major and minor edits by %s to page %s."
                           : "History of all major edits by %s to page %s.",
                WikiLink($author, 'auto'),
                WikiLink($page, 'auto')
            );
        } else {

            //search all pages for all edits by this author
            $thead->pushContent(HTML::tr(
                HTML::th(_("Page Name")),
                HTML::th(
                    array('class' => 'align-right'),
                    _("Version")
                ),
                $includeminor ? HTML::th(_("Minor")) : "",
                HTML::th(_("Summary")),
                HTML::th(_("Modified"))
            ));

            $allpages_iter = $dbi->getAllPages($includedeleted);
            while ($p = $allpages_iter->next()) {
                $allrevisions_iter = $p->getAllRevisions();
                while ($rev = $allrevisions_iter->next()) {
                    $isminor = $rev->get('is_minor_edit');
                    $authordoesmatch = $author == $rev->get('author');
                    if ($authordoesmatch && (!$isminor || ($includeminor && $isminor))) {
                        $difflink = Button(
                            array('action' => 'diff',
                                'previous' => 'minor'),
                            $rev->getVersion(),
                            $rev
                        );
                        $tr = HTML::tr(
                            HTML::td(
                                $nbsp,
                                ($isminor ? $rev->_pagename : WikiLink($rev->_pagename, 'auto'))
                            ),
                            HTML::td(
                                array('class' => 'align-right'),
                                $difflink,
                                $nbsp
                            ),
                            $includeminor ? (HTML::td($nbsp, ($isminor ? "minor" : "major"), $nbsp)) : "",
                            HTML::td($nbsp, $rev->get('summary')),
                            HTML::td(
                                array('class' => 'align-right'),
                                $WikiTheme->formatDateTime($rev->get('mtime')),
                                $nbsp
                            )
                        );

                        $class = $isminor ? 'evenrow' : 'oddrow';
                        $tr->setAttr('class', $class);
                        $tbody->pushContent($tr);
                    }
                }
            }

            $captext = fmt(
                $includeminor
                           ? "History of all major and minor modifications for any page edited by %s."
                           : "History of major modifications for any page edited by %s.",
                WikiLink($author, 'auto')
            );
        }

        $table->pushContent(HTML::caption($captext));
        $table->pushContent($thead, $tbody);

        return $table;
    }
}
