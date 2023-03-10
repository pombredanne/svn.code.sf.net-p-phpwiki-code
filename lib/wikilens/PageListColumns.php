<?php
/**
 * Copyright © 2004 Mike Cassano
 * Copyright © 2004-2005, 2008-2014 $ThePhpWikiProgrammingTeam
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
 * wikilens specific Custom pagelist columns
 *
 * Rationale: Certain themes should be able to extend the predefined list
 *  of pagelist types. E.g. certain plugins, like MostPopular might use
 *  info=pagename,hits,rating
 *  which displays the rating column whenever the wikilens theme is active.
 *  Similarly as in certain plugins, like WikiAdminRename or WikiTranslation
 */

require_once 'lib/PageList.php';
require_once 'lib/wikilens/RatingsUser.php';
require_once 'lib/plugin/RateIt.php';

/**
 * Column representing the number of backlinks to the page.
 * Perhaps this number should be made a 'field' of a page, in
 * which case this column type would not be necessary.
 * See also info=numbacklinks,numpagelinks at plugin/ListPages.php:_PageList_Column_ListPages_count
 * and info=count at plugin/BackLinks.php:PageList_Column_BackLinks_count
 */
class _PageList_Column_numbacklinks extends _PageList_Column_custom
{
    public function _getValue($page_handle, $revision_handle)
    {
        $theIter = $page_handle->getBackLinks();
        return $theIter->count();
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return $this->_getValue($page_handle, $revision_handle);
    }
}

class _PageList_Column_coagreement extends _PageList_Column_custom
{
    public function __construct($params)
    {
        $this->_pagelist =& $params[3];
        _PageList_Column::__construct($params[0], $params[1], $params[2]);
        $this->_selectedBuddies = $this->_pagelist->getOption('selectedBuddies');
    }

    public function _getValue($page_handle, $revision_handle)
    {
        global $request;

        $pagename = $page_handle->getName();

        $active_user = $request->getUser();
        $active_userId = $active_user->getId();
        $dbi = $request->getDbh();
        $p = CoAgreement($dbi, $pagename, $this->_selectedBuddies, $active_userId);
        if ($p == 1) {
            $p = "yes";
        } elseif ($p == 0) {
            $p = "unsure";
        } elseif ($p == -1) {
            $p = "no";
        } else {
            $p = "error";
        }
        //FIXME: $WikiTheme->getImageURL()
        return HTML::img(array('src' => $WikiTheme->getImageURL($p)));
    }
}

class _PageList_Column_minmisery extends _PageList_Column_custom
{
    public function __construct($params)
    {
        $this->_pagelist =& $params[3];
        _PageList_Column::__construct($params[0], $params[1], $params[2]);
        $this->_selectedBuddies = $this->_pagelist->getOption('selectedBuddies');
    }

    public function _getValue($page_handle, $revision_handle)
    {
        global $request, $WikiTheme;

        $pagename = $page_handle->getName();

        $active_user = $request->getUser();
        $active_userId = $active_user->getId();
        $dbi = $request->getDbh();
        $p = MinMisery($dbi, $pagename, $this->_selectedBuddies, $active_userId);
        $imgFix = floor($p * 2) / 2;
        return HTML::img(array('src' => $WikiTheme->getImageURL("Rateit" . $imgFix)));
    }
}

class _PageList_Column_averagerating extends _PageList_Column_custom
{
    public function __construct($params)
    {
        $this->_pagelist =& $params[3];
        _PageList_Column::__construct($params[0], $params[1], $params[2]);
        $this->_selectedBuddies = $this->_pagelist->getOption('selectedBuddies');
    }

    public function _getValue($page_handle, $revision_handle)
    {
        global $request, $WikiTheme;

        $pagename = $page_handle->getName();

        $active_user = $request->getUser();
        $active_userId = $active_user->getId();
        $dbi = $request->getDbh();
        $p = round(AverageRating($dbi, $pagename, $this->_selectedBuddies, $active_userId), 2);

        $imgFix = floor($p * 2) / 2;
        $html = HTML();
        $html->pushContent(HTML::img(array('src' => $WikiTheme->getImageURL("Rateit" . $imgFix))));
        $html->pushContent($p);
        return $html;
    }
}

/**
 * Show the value of a rating as a digit (or "-" if no value), given the
 * user who is the rater.
 * This requires the RatingsUser as 5th paramater.
 */
class _PageList_Column_ratingvalue extends _PageList_Column
{
    public $_user;
    public $_dimension;

    public function __construct($params)
    {
        $this->_pagelist =& $params[3];
        $this->_user =& $params[4]; //$this->_pagelist->getOption('user');
        if (defined('FUSIONFORGE') && FUSIONFORGE) {
            if (empty($this->_user)) {
                $this->_user =& RatingsUserFactory::getUser($GLOBALS['request']->_user->_userid);
            }
        }
        parent::__construct($params[0], $params[1], $params[2]);
        $this->_dimension = $this->_pagelist->getOption('dimension');
        if (!$this->_dimension) {
            $this->_dimension = 0;
        }
    }

    public function format($pagelist, $page_handle, $revision_handle)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (empty($this->_user)) {
            $this->_user =& RatingsUserFactory::getUser($request->_user->_userid);
        }
        assert(!empty($this->_user));
        $rating = $this->_getValue($page_handle, $revision_handle);
        $mean = $this->_user->mean_rating($this->_dimension);
        $td = HTML::td($this->_tdattr);

        $div = HTML::div();
        if ($rating != '-' && abs($rating - $mean) >= 0.75) {
            $div->setAttr('style', 'color: #' . ($rating > $mean ? '009900' : 'ff3333'));
        }
        $div->pushContent($rating);

        $td->pushContent($div);

        return $td;
    }

    public function _getValue($page_handle, $revision_handle)
    {
        $pagename = $page_handle->getName();

        $tu =& $this->_user;
        $rating = $tu->get_rating($pagename, $this->_dimension);

        // a dash (or *something*) arguably looks better than a big blank space
        return ($rating ? $rating : "-");
    }

    public function hasNoRatings($pages)
    {
        $total = 0;
        $use = & $this->_user;
        foreach ($pages as $page) {
            if ($use->get_rating($page, $this->_dimension)) {
                $total++;
            }
        }
        if ($total == 0) {
            return true;
        }
        return false;
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return $this->_getValue($page_handle, $revision_handle);
    }
}

/**
 * Ratings widget for the logged-in user and the given page
 * This uses the column name "rating".
 */
class _PageList_Column_ratingwidget extends _PageList_Column_custom
{
    public function __construct($params)
    {
        $this->_pagelist =& $params[3];
        _PageList_Column::__construct($params[0], $params[1], $params[2]);
        $this->_dimension = $this->_pagelist->getOption('dimension');
        if (!$this->_dimension) {
            $this->_dimension = 0;
        }
    }

    public function format($pagelist, $page_handle, $revision_handle)
    {
        $plugin = new WikiPlugin_RateIt();
        if (defined('FUSIONFORGE') && FUSIONFORGE) {
            $widget = $plugin->RatingWidgetHtml(
                $page_handle->getName(),
                "",
                "BStar",
                $this->_dimension,
                "small"
            );
        } else {
            $widget = $plugin->RatingWidgetHtml(
                $page_handle->getName(),
                "",
                "Star",
                $this->_dimension,
                "small"
            );
        }
        $td = HTML::td($widget);
        $td->setAttr('nowrap', 'nowrap');
        return $td;
    }

    public function _getValue($page_handle, $revision_handle)
    {
        global $request;

        if (defined('FUSIONFORGE') && FUSIONFORGE) {
            // Returns average rating of a page
            $pagename = $page_handle->getName();
            $rdbi = RatingsDb::getTheRatingsDb();
            return $rdbi->getAvg($pagename, $this->_dimension);
        } else {
            $pagename = $page_handle->getName();
            $active_user = $request->getUser();
            $active_userid = $active_user->_userid;
            $tu = & RatingsUserFactory::getUser($active_userid);
            return $tu->get_rating($pagename, $this->_dimension);
        }
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return $this->_getValue($page_handle, $revision_handle);
    }
}

class _PageList_Column_prediction extends _PageList_Column
{
    public $_active_ratings_user;
    public $_users;

    public function __construct($params)
    {
        global $request;
        $active_user = $request->getUser();
        // This needs to be a reference so things aren't recomputed for this user
        $this->_active_ratings_user =& RatingsUserFactory::getUser($active_user->getId());

        $this->_pagelist =& $params[3];
        parent::__construct($params[0], $params[1], $params[2]);
        $this->_dimension = $this->_pagelist->getOption('dimension');
        if (!$this->_dimension) {
            $this->_dimension = 0;
        }
        $this->_users = $this->_pagelist->getOption('users');
    }

    public function format($pagelist, $page_handle, $revision_handle)
    {
        $pred = $this->_getValue($page_handle, $revision_handle);
        $mean = $this->_active_ratings_user->mean_rating($this->_dimension);
        $td = HTML::td($this->_tdattr);

        $div = HTML::div();
        if ($pred > 0 && abs($pred - $mean) >= 0.75) {
            $div->setAttr('style', 'color: #' . ($pred > $mean ? '009900' : 'ff3333'));
        }
        $div->pushContent($pred);

        $td->pushContent($div);

        return $td;
    }

    public function _getValue($page_handle, $revision_handle)
    {
        $pagename = $page_handle->getName();

        $pred = $this->_active_ratings_user->knn_uu_predict($pagename, $this->_users, $this->_dimension);
        return sprintf("%.1f", min(5, max(0, $pred)));
    }

    public function _getSortableValue($page_handle, $revision_handle)
    {
        return $this->_getValue($page_handle, $revision_handle);
    }
}

class _PageList_Column_top3recs extends _PageList_Column_custom
{
    public $_active_ratings_user;
    public $_users;

    public function __construct($params)
    {
        global $request;
        $active_user = $request->getUser();
        if (is_string($active_user)) {
            //FIXME: try to find the bug at test.php which sets request->_user and ->_group
            trigger_error("request->getUser => string: $active_user", E_USER_WARNING);
            $active_user = new MockUser($active_user, true);
        }
        // No, I don't know exactly why, but this needs to be a reference for
        // the memoization in pearson_similarity and mean_rating to work
        $this->_active_ratings_user = new RatingsUser($active_user->getId());
        _PageList_Column::__construct($params[0], $params[1], $params[2]);

        if (!empty($params[3])) {
            $this->_pagelist =& $params[3];
            $this->_dimension = $this->_pagelist->getOption('dimension');
            if (!$this->_dimension) {
                $this->_dimension = 0;
            }
            $this->_users = $this->_pagelist->getOption('users');
        }
    }

    public function _getValue($page_handle, $revision_handle)
    {
        $ratings = $this->_active_ratings_user->get_ratings();
        $iter = $page_handle->getLinks();
        $recs = array();
        while ($current = $iter->next()) {
            //filter out already rated
            if (!$this->_active_ratings_user->get_rating($current->getName(), $this->_dimension)) {
                $recs[$current->getName()] =
                    $this->_active_ratings_user->knn_uu_predict(
                        $current->getName(),
                        $this->_users,
                        $this->_dimension
                    );
            }
        }
        arsort($recs);
        $counter = 0;
        if (count($recs) >= 3) {
            $numToShow = 3;
        } else {
            // if <3 just show as many as there are
            $numToShow = count($recs);
        }
        $html = HTML();
        foreach ($recs as $key => $val) {
            if (($counter >= $numToShow) || ($val < 3)) {
                break;
            }
            if ($counter > 0) {
                $html->pushContent(" , ");
            }
            $html->pushContent(WikiLink($key));

            $counter++;
        }
        if (count($recs) == 0 || $counter == 0) {
            $html->pushContent(_("None"));
        }

        return $html;
    }
}

// register custom PageList type
global $WikiTheme;
$WikiTheme->addPageListColumn(array(
    'numbacklinks'
    => array('_PageList_Column_numbacklinks', 'custom:numbacklinks',
        _("# things"), 'center'),
    'rating'
    => array('_PageList_Column_ratingwidget', 'custom:rating',
        _("Rate"), false),
    'ratingvalue'
    => array('_PageList_Column_ratingvalue', 'custom:ratingvalue',
        _("Rating"), 'center'),
    'coagreement'
    => array('_PageList_Column_coagreement', 'custom:coagreement',
        _("Go?"), 'center'),
    'minmisery'
    => array('_PageList_Column_minmisery', 'custom:minmisery',
        _("MinMisery"), 'center'),
    'averagerating'
    => array('_PageList_Column_averagerating', 'custom:averagerating',
        _("Avg. Rating"), 'left'),
    'top3recs'
    => array('_PageList_Column_top3recs', 'custom:top3recs',
        _("Top Recommendations"), 'left'),
    /*'prediction'
      => array('_PageList_Column_prediction','custom:prediction',
                _("Prediction"), false),*/
));
