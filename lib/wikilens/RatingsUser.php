<?php
/**
 * Copyright © 2004 Dan Frankowski
 * Copyright © 2010 Roger Guignard, Alcatel-Lucent
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

require_once 'lib/wikilens/RatingsDb.php';

/**
 * Get a RatingsUser instance (possibly from a cache).
 */

class RatingsUserFactory
{
    public static function & getUser($userid)
    {
        //print "getUser($userid) ";
        global $_ratingsUserCache;
        if (!isset($_ratingsUserCache)) {
            $_ratingsUserCache = array();
        }
        if (!array_key_exists($userid, $_ratingsUserCache)) {
            //print "MISS ";
            $_ratingsUserCache[$userid] = new RatingsUser($userid);
        } else {
            //print "HIT ";
        }
        return $_ratingsUserCache[$userid];
    }
}

/**
 * This class represents a user that gets ratings
 */
class RatingsUser
{
    public $_userid;
    public $_ratings_loaded;
    public $_ratings;
    public $_num_ratings;
    public $_mean_ratings;
    public $_pearson_sims;

    public function __construct($userid)
    {
        $this->_userid = $userid;
        $this->_ratings_loaded = false;
        $this->_ratings = array();
        $this->_num_ratings = 0;
        $this->_mean_ratings = array();
        $this->_pearson_sims = array();
    }

    public function getId()
    {
        return $this->_userid;
    }

    public function & _get_rating_dbi()
    {
        // This is a hack, because otherwise this object doesn't know about a
        // DBI at all.  Perhaps all this ratings stuff should live somewhere
        // else that's less of a base class.
        if (isset($this->_rdbi)) {
            return $this->_rdbi;
        }
        $this->_rdbi = RatingsDb::getTheRatingsDb();
        return $this->_rdbi;
    }

    // XXX: may want to think about caching ratings in the PHP session
    // since a WikiUser is created for *every* access, in which case rate.php
    // will want to change to use this object instead of direct db access

    /**
     * Check whether $user is allowed to view this user's ratings
     *
     * @return bool True if $user can view this user's ratings, false otherwise
     */
    public function allow_view_ratings($user)
    {
        return true;
    }

    /**
     * Gets this user's ratings
     *
     * @return array Assoc. array [page_name][dimension] = _UserRating object
     */
    public function get_ratings()
    {
        $this->_load_ratings();
        return $this->_ratings;
    }

    /**
     * Gets this user's mean rating across a dimension
     *
     * @return float Mean rating
     */
    public function mean_rating($dimension = 0)
    {
        // use memoized result if available
        if (isset($this->_mean_ratings[$dimension])) {
            return $this->_mean_ratings[$dimension];
        }

        $ratings = $this->get_ratings();
        $total = 0;
        $n = 0;

        // walk the ratings and aggregate those in this dimension
        foreach ($ratings as $page => $rating) {
            if (isset($rating[$dimension])) {
                $total += $rating[$dimension]->get_rating();
                $n++;
            }
        }

        // memoize and return result
        $this->_mean_ratings[$dimension] = ($n == 0 ? 0 : $total / $n);
        return $this->_mean_ratings[$dimension];
    }

    // Note: the following has_rated, get_rating, set_rating, and unset_rating
    // methods are colossally inefficient as they do a full ratings load from
    // the database before performing their intended operation -- as such, the
    // rate.php script still uses the direct database methods (plus, it's very
    // ephemeral and doesn't particularly care about the ratings count or any
    // other features that these methods might provide down the road)

    public function has_rated($pagename, $dimension = null)
    {
        // XXX: does this really want to do a full ratings load?  (scalability?)
        $this->_load_ratings();
        if (isset($dimension)) {
            if (isset($this->_ratings[$pagename][$dimension])) {
                return true;
            }
        } else {
            if (isset($this->_ratings[$pagename])) {
                return true;
            }
        }
        return false;
    }

    public function get_rating($pagename, $dimension = 0)
    {
        // XXX: does this really want to do a full ratings load?  (scalability?)
        if (RATING_STORAGE == 'SQL') {
            $this->_load_ratings();
        } else {
            $rdbi = $this->_get_rating_dbi();
            return $rdbi->metadata_get_rating($this->getId(), $pagename, $dimension);
        }

        if ($this->has_rated($pagename, $dimension)) {
            return $this->_ratings[$pagename][$dimension]->get_rating();
        }
        return false;
    }

    public function set_rating($pagename, $dimension, $rating)
    {
        // XXX: does this really want to do a full ratings load?  (scalability?)
        $this->_load_ratings();

        // XXX: what to do on failure?
        $dbi = $this->_get_rating_dbi();
        if (!($dbi->rate($this->_userid, $pagename, $dimension, $rating))) {
            return;
        }

        if ($this->has_rated($pagename, $dimension)) {
            $this->_ratings[$pagename][$dimension]->set_rating($rating);
        } else {
            $this->_num_ratings++;
            $this->_ratings[$rating['pagename']][$rating['dimension']]
                = new _UserRating($this->_userid, $pagename, $dimension, $rating);
        }
    }

    public function unset_rating($pagename, $dimension)
    {
        // XXX: does this really want to do a full ratings load?  (scalability?)
        $this->_load_ratings();
        if ($this->has_rated($pagename, $dimension)) {
            // XXX: what to do on failure?
            if ($this->_dbi->delete_rating($this->_userid, $pagename, $dimension)) {
                $this->_num_ratings--;
                unset($this->_ratings[$pagename][$dimension]);
                if (!count($this->_ratings[$pagename])) {
                    unset($this->_ratings[$pagename]);
                }
            }
        }
    }

    public function pearson_similarity($user, $dimension = 0)
    {
        // use memoized result if available
        if (isset($this->_pearson_sims[$user->getId()][$dimension])) {
            return $this->_pearson_sims[$user->getId()][$dimension];
        }

        $ratings1 = $this->get_ratings();
        $mean1 = $this->mean_rating($dimension);
        // XXX: sanify user input?
        $ratings2 = $user->get_ratings();
        $mean2 = $user->mean_rating($dimension);

        // swap if it would speed things up a bit
        if (count($ratings1) < count($ratings2)) {
            $tmp = $ratings1;
            $ratings1 = $ratings2;
            $ratings2 = $tmp;
            $tmp = $mean1;
            $mean1 = $mean2;
            $mean2 = $tmp;
        }

        list($sum11, $sum22, $sum12, $n) = array(0, 0, 0, 0);

        // compute sum(x*x), sum(y*y), sum(x*y) over co-rated items
        foreach ($ratings1 as $page => $rating1) {
            if (isset($rating1[$dimension]) && isset($ratings2[$page])) {
                $rating2 = $ratings2[$page];
                if (isset($rating2[$dimension])) {
                    $r1 = $rating1[$dimension]->get_rating();
                    $r2 = $rating2[$dimension]->get_rating();
                    // print "co-rating with " . $user->getId() . " $page $r1 $r2<BR>";

                    $r1 -= $mean1;
                    $r2 -= $mean2;

                    $sum11 += $r1 * $r1;
                    $sum22 += $r2 * $r2;
                    $sum12 += $r1 * $r2;
                    $n++;
                }
            }
        }

        // this returns both the computed similarity and the number of co-rated
        // items that the similarity was based on

        // prevent division-by-zero
        if (sqrt($sum11) == 0 || sqrt($sum12) == 0) {
            $sim = array(0, $n);
        } else {
            // Pearson similarity
            $sim = array($sum12 / (sqrt($sum11) * sqrt($sum22)), $n);
        }

        // print "sim is " . $sim[0] . "<BR><BR>";

        // memoize result
        $this->_pearson_sims[$user->getId()][$dimension] = $sim;
        return $this->_pearson_sims[$user->getId()][$dimension] = $sim;
    }

    public function knn_uu_predict($pagename, &$neighbors, $dimension = 0)
    {
        /*
        print "<PRE>";
        var_dump($this->_pearson_sims);
        var_dump($this->_ratings);
        print "</PRE>";
        print "pred for $pagename<BR>";
        */
        $total = 0;
        $total_sim = 0;

        if ($neighbors == null) {
            return 0;
        }

        for ($i = 0; $i < count($neighbors); $i++) {
            // more silly PHP references...
            $nbor =& $neighbors[$i];

            // ignore self-neighbor
            if ($this->getId() == $nbor->getId()) {
                continue;
            }

            if ($nbor->has_rated($pagename, $dimension)) {
                list($sim, $n_items) = $this->pearson_similarity($nbor);
                // ignore absolute sims below 0.1, negative sims??
                // XXX: no filtering done... small-world = too few neighbors
                if (1 || ($sim > 0 && abs($sim) >= 0.1)) {
                    // n/50 sig weighting
                    if ($n_items < 50) {
                        $sim *= $n_items / 50;
                    }
                    /*
                    print "neighbor is " . $nbor->getId() . "<BR>";
                    print "weighted sim is " . $sim . "<BR>";
                    print "dev from mean is " . ($nbor->get_rating($pagename, $dimension) - $nbor->mean_rating($dimension)) . "<BR>";
                    */
                    $total += $sim * ($nbor->get_rating($pagename, $dimension) - $nbor->mean_rating($dimension));
                    $total_sim += abs($sim);
                }
            }
        }

        $my_mean = $this->mean_rating($dimension);
        /*
        print "your mean is $my_mean<BR>";
        print "pred dev from mean is " . ($total_sim == 0 ? -1 : ($total / $total_sim)) . "<BR>";
        print "pred is " . ($total_sim == 0 ? -1 : ($total / $total_sim + $my_mean)) . "<BR><BR>";
        */
        // XXX: what to do if no neighbors have rated pagename?
        return ($total_sim == 0 ? 0 : ($total / $total_sim + $my_mean));
    }

    public function _load_ratings($force = false)
    {
        if (!$this->_ratings_loaded || $force) {
            // print "load " . $this->getId() . "<BR>";
            $this->_ratings = array();
            $this->_num_ratings = 0;
            // only signed-in users have ratings (XXX: authenticated?)

            // passing null as first parameter to indicate all dimensions
            $dbi = $this->_get_rating_dbi();

            //$rating_iter = $dbi->sql_get_rating(null, $this->_userid, null);
            //($dimension=null, $rater=null, $ratee=null, $orderby = null, $pageinfo = "ratee")
            $rating_iter = $dbi->get_rating_page(null, $this->_userid);

            while ($rating = $rating_iter->next()) {
                if (defined('FUSIONFORGE') && FUSIONFORGE) {
                    global $page_prefix;
                    $rating['pagename'] = preg_replace('/^' . $page_prefix . '/', '', $rating['pagename']);
                }
                $this->_num_ratings++;
                $this->_ratings[$rating['pagename']][$rating['dimension']]
                    = new _UserRating(
                        $this->_userid,
                        $rating['pagename'],
                        $rating['dimension'],
                        $rating['ratingvalue']
                    );
            }

            $this->_ratings_loaded = true;
        }
    }
}

/** Represent a rating. */
class _UserRating
{
    public function __construct($rater, $ratee, $dimension, $rating)
    {
        $this->rater = (string)$rater;
        $this->ratee = (string)$ratee;
        $this->dimension = (int)$dimension;
        $this->rating = (float)$rating;
    }

    public function get_rater()
    {
        return $this->rater;
    }

    public function get_ratee()
    {
        return $this->ratee;
    }

    public function get_rating()
    {
        return $this->rating;
    }

    public function get_dimension()
    {
        return $this->dimension;
    }

    public function set_rater()
    {
        $this->rater = (string)$rater;
    }

    public function set_ratee()
    {
        $this->ratee = (string)$ratee;
    }

    public function set_rating()
    {
        $this->rating = (float)$rating;
    }

    public function set_dimension()
    {
        $this->dimension = (int)$dimension;
    }
}
