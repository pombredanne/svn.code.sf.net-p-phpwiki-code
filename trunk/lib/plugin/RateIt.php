<?php // -*-php-*-
rcs_id('$Id: RateIt.php,v 1.1 2004-03-30 02:38:06 rurban Exp $');
/*
 Copyright 2004 $ThePhpWikiProgrammingTeam

 This file is (not yet) part of PhpWiki.

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

//define('RATING_STORAGE','WIKIPAGE');
define('RATING_STORAGE','SQL');
// leave undefined for internal, slow php engine.
define('RATING_EXTERNAL',PHPWIKI_DIR . 'suggest.exe');

/**
 * RateIt: A recommender system, based on MovieLens and suggest.
 * Store user ratings per pagename. The wikilens theme displays a navbar image bar
 * with some nice javascript magic and this plugin shows various recommendations.
 *
 * There should be two methods to store ratings:
 * In a SQL database as in wikilens http://dickens.cs.umn.edu/dfrankow/wikilens
 *
 * The most important fact: A page has more than one rating. There can
 * be (and will be!) many ratings per page (ratee): different raters
 * (users), in different dimensions. Are those stored per page
 * (ratee)? Then what if I wish to access the ratings per rater
 * (user)? 
 * wikilens plans several user-centered applications like:
 * a) show my ratings
 * b) show my buddies' ratings
 * c) show how my ratings are like my buddies'
 * d) show where I agree/disagree with my buddy
 * e) show what this group of people agree/disagree on
 *
 * If the ratings are stored in a real DB in a table, we can index the
 * ratings by rater and ratee, and be confident in
 * performance. Currently MovieLens has 80,000 users, 7,000 items,
 * 10,000,000 ratings. This is an average of 1400 ratings/page if each
 * page were rated equally. However, they're not: the most popular
 * things have tens of thousands of ratings (e.g., "Pulp Fiction" has
 * 42,000 ratings). If ratings are stored per page, you would have to
 * save/read huge page metadata every time someone submits a
 * rating. Finally, the movie domain has an unusually small number of
 * items-- I'd expect a lot more in music, for example.
 *
 * For a simple rating system one can also store the rating in the page 
 * metadata (default).
 *
 * Usage:    <?plugin RateIt ?>              to enable rating on this page
 * Note: The wikilens theme must be enabled, to enable this plugin!
 * Or use a sidebar based theme with the box method.
 *           <?plugin RateIt show=ratings ?> to show my ratings
 *           <?plugin RateIt show=buddies ?> to show my buddies
 *           <?plugin RateIt show=ratings dimension=1 ?>
 *
 * @author:  Dan Frankowski (wikilens author), Reini Urban (as plugin)
 *
 */
/*
 CREATE TABLE rating (
        dimension INT(4) NOT NULL,
        raterpage INT(11) NOT NULL,
        rateepage INT(11) NOT NULL,
        ratingvalue FLOAT NOT NULL,
        rateeversion INT(11) NOT NULL,
        tstamp TIMESTAMP(14) NOT NULL,
        PRIMARY KEY (dimension, raterpage, rateepage)
 );
*/

require_once("lib/WikiPlugin.php");

class WikiPlugin_RateIt
extends WikiPlugin
{
    function getName() {
        return _("RateIt");
    }
    function getDescription() {
        return _("Recommendation system. Store user ratings per page");
    }
    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function RatingWidgetJavascript() {
        global $Theme;

        for ($i = 0; $i < 2; $i++) {
            $nk[$i] = $Theme->_findData("images/RateItNk$i.png");
            $ok[$i] = $Theme->_findData("images/RateItOk$i.png");
        }
        $js = "
function displayRating(imgPrefix, ratingvalue) {
  for (i=1; i<=10; i++) {
    imgName = imgPrefix + i;
    if (i<=(ratingvalue*2)) {
      document[imgName].src = (i%2) ? '" . $ok[1] . "' : '" . $ok[0] ."';
    } else {
      document[imgName].src = (i%2) ? '" . $nk[1] . "' : '" . $nk[0] ."';
    }
  }
}

function click(actionImg, pagename, version, imgPrefix, dimension, rat) {
  if (rat == 'X') {
    deleteRating(actionImg, pagename, dimension);
    displayRating(imgPrefix, 0);
  } else {
    submitRating(actionImg, pagename, version, dimension, rat);
    displayRating(imgPrefix, rat);
  }
}

function submitRating(actionImg, page, version, dimension, rating) {
  var myRand = Math.round(Math.random()*(1000000));
  var imgSrc = page + '?version=' + version + '&action=".urlencode(_("RateIt"))."&mode=add&rating=' + rating + '&dimension=' + dimension + '&nopurge=cache&rand=' + myRand;
  //alert('submitRating(' + page + ', ' + version + ', ' + dimension + ', ' + rating + ') => '+imgSrc);
  document[actionImg].src= imgSrc;
}

function deleteRating(actionImg, page, dimension) {
  var myRand = Math.round(Math.random()*(1000000));
  var imgSrc = page + '?action=".urlencode(_("RateIt"))."&mode=delete&dimension=' + dimension + '&nopurge=cache&rand=' + myRand;
  //alert('deleteRating(' + page + ', ' + version + ', ' + dimension + ')');
  document[actionImg].src= imgSrc;
}
";
        return JavaScript($js);
    }

    function actionImgPath() {
        global $Theme;
        return $Theme->_findFile("images/RateItAction.png");
    }

    /**
     * Take a string and quote it sufficiently to be passed as a Javascript
     * string between ''s
     */
    function _javascript_quote_string($s) {
        return str_replace("'", "\'", $s);
    }

    function getDefaultArguments() {
        return array( 'pagename'  => '[pagename]',
                      'version'   => false,
                      'id'        => 'rateit',
                      'imgPrefix' => '',
                      'dimension' => false,
                      'smallWidget' => false,
                      'show'      => false,
                      'mode'      => false,
                      );
    }

    function head() { // early side-effects (before body)
        global $Theme;
        $Theme->addMoreHeaders($this->RatingWidgetJavascript());
    }

    // todo: only for signed users
    // todo: set rating dbi for external rating database
    function run($dbi, $argstr, $request, $basepage) {
        global $Theme;
        $this->_request = & $request;
        $this->_dbi = & $dbi;
        $user = & $request->getUser();
        if (!$user->isSignedIn())
            return $this->error(_("You must sign in"));
        $this->userid = $user->UserName();
        $args = $this->getArgs($argstr, $request);
        $this->dimension = $args['dimension'];
        if ($this->dimension == '') $this->dimension = null;
        if ($args['pagename']) {
            // Expand relative page names.
            $page = new WikiPageName($args['pagename'], $basepage);
            $args['pagename'] = $page->name;
        }
        if (empty($args['pagename'])) {
            return $this->error(_("no page specified"));
        }
        $this->pagename = $args['pagename'];

        if (RATING_STORAGE == 'SQL') {
            $dbi = &$this->_dbi->_backend;
            extract($dbi->_table_names);
            if (empty($rating_tbl)) {
                $rating_tbl = (!empty($GLOBALS['DBParams']['prefix']) 
                               ? $GLOBALS['DBParams']['prefix'] : '') . 'rating';
                $dbi->_table_names['rating_tbl'] = $rating_tbl;
            }
        }

        if ($args['mode'] === 'add') {
            global $Theme;
            $actionImg = $Theme->_path . $this->actionImgPath();
            $this->addRating($request->getArg('rating'));
            ob_end_clean();  // discard any previous output
            // delete the cache
            $page = $request->getPage();
            $page->set('_cached_html', false);
            $request->cacheControl('MUST-REVALIDATE');
            //fake validators without args
            $request->appendValidators(array('wikiname' => WIKI_NAME,
                                             'args'     => hash('')));
            header('Content-type: image/png');
            readfile($actionImg);
            exit();
        } elseif ($args['mode'] === 'delete') {
            global $Theme;
            $actionImg = $Theme->_path . $this->actionImgPath();
            $this->deleteRating();
            ob_end_clean();  // discard any previous output
            // delete the cache
            $page = $request->getPage();
            $page->set('_cached_html', false);
            $request->cacheControl('MUST-REVALIDATE');
            //fake validators without args
            $request->appendValidators(array('wikiname' => WIKI_NAME,
                                             'args'     => hash('')));
            header('Content-type: image/png');
            readfile($actionImg);
            exit();
        } elseif (! $args['show'] ) {
            // we must use the head method instead, because <body> is already printed.
            // $Theme->addMoreHeaders($this->RatingWidgetJavascript()); 
            // or we change the header in the ob_buffer.

            //Todo: add a validator based on the users last rating mtime
            if ( $rating = $this->getRating() ) {
                //$page = $request->getPage();
                //$page->set('_cached_html', false);
                $request->cacheControl('REVALIDATE');
            }
            $args['rating'] = $rating;
            return $this->RatingWidgetHtml($args);
        } else {
            extract($args);
            $rating = $this->getRating();
            $html = HTML::p(sprintf(_("Rated by %d users | Average rating %.1f stars"),
                                    $this->getNumUsers($this->pagename,$this->dimension),
                                    $this->getAvg($this->pagename,$this->dimension)),
                            HTML::br());
            if ($rating !== false)
                $html->pushContent(sprintf(_("Your rating was %.1f"),
                                           $rating));
            else {
            	$rating = $this->getPrediction($this->userid,$this->pagename,$this->dimension);
            	if (is_string($rating))
                    $html->pushContent(sprintf(_("%s prediction for you is %s stars"),
                                               WIKI_NAME, $rating));
                else
                    $html->pushContent(sprintf(_("%s prediction for you is %.1f stars"),
                                               WIKI_NAME, $rating));
            }
            $html->pushContent(HTML::p());
            $html->pushContent(HTML::em("(Experimental: This is entirely bogus data)"));
            return $html;
        }
    }

    // box is used to display a fixed-width, narrow version with common header
    function box($args=false, $request=false, $basepage=false) {
        if (!$request) $request =& $GLOBALS['request'];
        if (!isset($args)) $args = array();
        $args['smallWidget'] = 1;
        return $this->makeBox(WikiLink(_("RateIt"),'',_("Rate It")),
                              $this->RatingWidgetHtml($args));
    }

    function addRating($rating, $userid=null, $pagename=null, $dimension=null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($userid))    $userid = $this->userid; 
        if (is_null($pagename))  $pagename = $this->pagename;
        if (RATING_STORAGE == 'SQL') {
            $page = $this->_dbi->getPage($this->pagename);
            $current = $page->getCurrentRevision();
            $rateeversion = $current->getVersion();
            $this->sql_rate($userid, $pagename, $rateeversion, $dimension, $rating);
        } else {
            $this->metadata_set_rating($userid, $pagename, $dimension, $rating);
        }
    }

    function deleteRating($userid=null, $pagename=null, $dimension=null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($userid))    $userid = $this->userid; 
        if (is_null($pagename))  $pagename = $this->pagename;
        if (RATING_STORAGE == 'SQL') {
            $this->sql_delete_rating($userid, $pagename, $dimension);
        } else {
            $this->metadata_set_rating($userid, $pagename, $dimension, -1);
        }
    }

    function getRating($userid=null, $pagename=null, $dimension=null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($userid))    $userid = $this->userid; 
        if (is_null($pagename))  $pagename = $this->pagename;
        if (RATING_STORAGE == 'SQL') {
            $ratings_iter = $this->sql_get_rating($dimension, $userid, $pagename);
            if ($rating = $ratings_iter->next()) {
                return $rating['ratingvalue'];
            } else 
                return false;
        } else {
            return $this->metadata_get_rating($userid, $pagename, $dimension);
        }
    }

    // TODO
    // Currently we have to call the "suggest" CGI
    //   http://www-users.cs.umn.edu/~karypis/suggest/
    // until we implement a simple recommendation engine.
    // Note that "suggest" is only free for non-profit organizations.
    // I am currently writing a binary CGI using suggest, which loads 
    // data from mysql.
    function getPrediction($userid=null, $pagename=null, $dimension=null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($userid))    $userid   = $this->userid; 
        if (is_null($pagename))  $pagename = $this->pagename;
        $user = $this->dbi->_getpageid($userid);
        $page = $this->dbi->_getpageid($pagename);
        if (defined('RATING_EXTERNAL')) {
            // how call suggest.exe? as CGI or natively
            //$rating = HTML::Raw("<!--#include virtual=".RATING_ENGINE." -->");
            $args = "-u$user -p$page -malpha"; // --top 10
            if (isset($dimension))
                $args .= " -d$dimension";
            $rating = passthru(RATING_EXTERNAL . " $args");
        } else {
            $rating = $this->php_prediction($userid, $pagename, $dimension);
        }
        return $rating;
    }

    /**
     * TODO: slow item-based recommendation engine, similar to suggest RType=2.
     */
    function php_prediction($userid=null, $pagename=null, $dimension=null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($userid))    $userid   = $this->userid; 
        if (is_null($pagename))  $pagename = $this->pagename;
        if (RATING_STORAGE == 'SQL') {
            $rating = 2.5;
        } else {
            $rating = 2.5;
        }
        return $rating;
    }
    
    function getNumUsers($pagename=null, $dimension=null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($pagename))  $pagename = $this->pagename;
        if (RATING_STORAGE == 'SQL') {
            $ratings_iter = $this->sql_get_rating($dimension, null, $pagename,
                                                  null, "ratee");
            return $ratings_iter->count();
        } else {
            $page = $this->_dbi->getPage($pagename);
            $data = $page->get('rating');
            if (!empty($data[$dimension]))
                return count($data[$dimension]);
            else 
                return 0;
        }
    }
    // TODO: metadata method
    function getAvg($pagename=null, $dimension=null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($pagename))  $pagename = $this->pagename;
        if (RATING_STORAGE == 'SQL') {
            $dbi = &$this->_dbi->_backend;
            $where = "WHERE 1";
            if (isset($pagename)) {
                $raterid = $dbi->_get_pageid($pagename, true);
                $where .= " AND raterpage=$raterid";
            }
            if (isset($dimension)) {
                $where .= " AND dimension=$dimension";
            }
            //$dbh = &$this->_dbi;
            extract($dbi->_table_names);
            $query = "SELECT AVG(ratingvalue) as avg"
                   . " FROM $rating_tbl r, $page_tbl p "
                   . $where. " GROUP BY raterpage";
            $result = $dbi->_dbh->query($query);
            $iter = new WikiDB_backend_PearDB_generic_iter($this,$result);
            $row = $iter->next();
            return $row['avg'];
        } else {
            return 2.5;
        }
    }

    /**
     * Get ratings.
     *
     * @param dimension  The rating dimension id.
     *                   Example: 0
     *                   [optional]
     *                   If this is null (or left off), the search for ratings
     *                   is not restricted by dimension.
     *
     * @param rater  The page id of the rater, i.e. page doing the rating.
     *               This is a Wiki page id, often of a user page.
     *               Example: "DanFr"
     *               [optional]
     *               If this is null (or left off), the search for ratings
     *               is not restricted by rater.
     *               TODO: Support an array
     *
     * @param ratee  The page id of the ratee, i.e. page being rated.
     *               Example: "DudeWheresMyCar"
     *               [optional]
     *               If this is null (or left off), the search for ratings
     *               is not restricted by ratee.
     *               TODO: Support an array
     *
     * @param orderby An order-by clause with fields and (optionally) ASC
     *                or DESC.
     *               Example: "ratingvalue DESC"
     *               [optional]
     *               If this is null (or left off), the search for ratings
     *               has no guaranteed order
     *
     * @param pageinfo The type of page that has its info returned (i.e.,
     *               'pagename', 'hits', and 'pagedata') in the rows.
     *               Example: "rater"
     *               [optional]
     *               If this is null (or left off), the info returned
     *               is for the 'ratee' page (i.e., thing being rated).
     *
     * @return DB iterator with results 
     */
    function sql_get_rating($dimension=null, $rater=null, $ratee=null,
                            $orderby=null, $pageinfo = "ratee") {
        if (empty($dimension)) $dimension=null;
        $result = $this->_sql_get_rating_result($dimension, $rater, $ratee, $orderby, $pageinfo);
        return new WikiDB_backend_PearDB_generic_iter($this, $result);
    }

    /**
     * Like get_rating(), but return a result suitable for WikiDB_PageIterator
     */
    function _sql_get_rating_page($dimension=null, $rater=null, $ratee=null,
                                  $orderby=null, $pageinfo = "ratee") {
        if (empty($dimension)) $dimension=null;
        $result = $this->_sql_get_rating_result($dimension, $rater, $ratee, $orderby, $pageinfo);
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /**
     * @access private
     * @return DB iterator with results
     */
    function _sql_get_rating_result($dimension=null, $rater=null, $ratee=null,
                                    $orderby=null, $pageinfo = "ratee") {
        // pageinfo must be 'rater' or 'ratee'
        if (($pageinfo != "ratee") && ($pageinfo != "rater"))
            return;

        $dbi = &$this->_dbi->_backend;
        //$dbh = &$this->_dbi;
        extract($dbi->_table_names);
        $where = "WHERE r." . $pageinfo . "page = p.id";
        if (isset($dimension)) {
            $where .= " AND dimension=$dimension";
        }
        if (isset($rater)) {
            $raterid = $dbi->_get_pageid($rater, true);
            $where .= " AND raterpage=$raterid";
        }
        if (isset($ratee)) {
            $rateeid = $dbi->_get_pageid($ratee, true);
            $where .= " AND rateepage=$rateeid";
        }
        $orderbyStr = "";
        if (isset($orderby)) {
            $orderbyStr = " ORDER BY " . $orderby;
        }

        $query = "SELECT *"
               . " FROM $rating_tbl r, $page_tbl p "
               . $where
               . $orderbyStr;

        $result = $dbi->_dbh->query($query);
        return $result;
    }

    /**
     * Delete a rating.
     *
     * @param rater  The page id of the rater, i.e. page doing the rating.
     *               This is a Wiki page id, often of a user page.
     * @param ratee  The page id of the ratee, i.e. page being rated.
     * @param dimension  The rating dimension id.
     *
     * @access public
     *
     * @return true upon success
     */
    function sql_delete_rating($rater, $ratee, $dimension) {
        //$dbh = &$this->_dbi;
        $dbi = &$this->_dbi->_backend;
        extract($dbi->_table_names);

        $dbi->lock();
        $raterid = $dbi->_get_pageid($rater, true);
        $rateeid = $dbi->_get_pageid($ratee, true);
        $dbi->_dbh->query("DELETE FROM $rating_tbl WHERE raterpage=$raterid and rateepage=$rateeid and dimension=$dimension");
        $dbi->unlock();
        return true;
    }

    /**
     * Rate a page.
     *
     * @param rater  The page id of the rater, i.e. page doing the rating.
     *               This is a Wiki page id, often of a user page.
     * @param ratee  The page id of the ratee, i.e. page being rated.
     * @param rateeversion  The version of the ratee page.
     * @param dimension  The rating dimension id.
     * @param rating The rating value (a float).
     *
     * @access public
     *
     * @return true upon success
     */
    //$this->userid, $this->pagename, $this->dimension, $rating);
    function sql_rate($rater, $ratee, $rateeversion, $dimension, $rating) {
        $dbi = &$this->_dbi->_backend;
        extract($dbi->_table_names);
        if (empty($rating_tbl))
            $rating_tbl = (!empty($GLOBALS['DBParams']['prefix']) ? $GLOBALS['DBParams']['prefix'] : '') . 'rating';

        $dbi->lock();
        $raterid = $dbi->_get_pageid($rater, true);
        $rateeid = $dbi->_get_pageid($ratee, true);
        $where = "WHERE raterpage=$raterid AND rateepage=$rateeid";
        if (isset($dimension)) $where .= " AND dimension=$dimension";
        $dbi->_dbh->query("DELETE FROM $rating_tbl $where");
        // NOTE: Leave tstamp off the insert, and MySQL automatically updates it (only if MySQL is used)
        $dbi->_dbh->query("INSERT INTO $rating_tbl (dimension, raterpage, rateepage, ratingvalue, rateeversion) VALUES ('$dimension', $raterid, $rateeid, '$rating', '$rateeversion')");
        $dbi->unlock();
        return true;
    }
   

    function metadata_get_rating($userid, $pagename, $dimension) {
    	$page = $this->_dbi->getPage($pagename);
        $data = $page->get('rating');
        if (!empty($data[$dimension][$userid]))
            return (float)$data[$dimension][$userid];
        else 
            return false;
    }

    function metadata_set_rating($userid, $pagename, $dimension, $rating = -1) {
    	$page = $this->_dbi->getPage($pagename);
        $data = $page->get('rating');
        if ($rating == -1)
            unset($data[$dimension][$userid]);
        else {
            if (empty($data[$dimension][$userid]))
                $data[$dimension] = array($userid => (float)$rating);
            else
                $data[$dimension][$userid] = $rating;
        }
        $page->set('rating',$data);
    }

    /**
     * HTML widget display
     *
     * This needs to be put in the <body> section of the page.
     *
     * @param pagename    Name of the page to rate
     * @param version     Version of the page to rate (may be "" for current)
     * @param imgPrefix   Prefix of the names of the images that display the rating
     *                    You can have two widgets for the same page displayed at
     *                    once iff the imgPrefix-s are different.
     * @param dimension   Id of the dimension to rate
     * @param smallWidget Makes a smaller ratings widget if non-false
     */
    function RatingWidgetHtml($args) {
        global $Theme, $request;
        extract($args);
        $imgPrefix = $pagename . $imgPrefix;
        $actionImgName = $imgPrefix . 'RateItAction';
        $dbi =& $GLOBALS['request']->getDbh();
        $version = $dbi->_backend->get_latest_version($pagename);

        // Protect against 's, though not \r or \n
        $reImgPrefix     = $this->_javascript_quote_string($imgPrefix);
        $reActionImgName = $this->_javascript_quote_string($actionImgName);
        $rePagename      = $this->_javascript_quote_string(WikiUrl($pagename,0,1));
        //$dimension = $args['pagename'] . "rat";
    
        $html = HTML::span(array("id" => $id));
        for ($i=0; $i < 2; $i++) {
            $nk[$i] = $Theme->_findData("images/RateItNk$i.png");
        }

        if (!$smallWidget) {
            $html->pushContent(Button(_("RateIt"),_("RateIt"),$pagename));
            $html->pushContent(HTML::raw('&nbsp;'));
        }
        
        for ($i = 1; $i <= 10; $i++) {
            $a1 = HTML::a(array('href' => 'javascript:click(\'' . $reActionImgName . '\', \'' . $rePagename . '\', \'' . $version . '\', \'' . $reImgPrefix . '\', \'' . $dimension . '\', ' . ($i/2) . ')'));
            $img_attr = array();
            $img_attr['src'] = $nk[$i%2];
            $img_attr['name'] = $imgPrefix . $i;
            $img_attr['border'] = 0;
            $a1->pushContent(HTML::img($img_attr));
            $html->pushContent($a1);
            //This adds a space between the rating smilies:
            // if (($i%2) == 0) $html->pushContent(' ');
        }

        $html->pushContent(' ');
        $a0 = HTML::a(array('href' => 'javascript:click(\'' . $reActionImgName . '\', \'' . $rePagename . '\', \'' . $version . '\', \'' . $reImgPrefix . '\', \'' . $dimension . '\', \'X\')'));

        $user = $request->getUser();
        $userid = $user->getId();
        if (!isset($args['rating']))
            $rating = $this->getRating($userid, $pagename, $dimension);

        if ($rating) {
            $msg = _("Cancel rating");
            $a0->pushContent(HTML::img(array('src' => $Theme->getImageUrl("RateItCancel"),
                                             'alt' => $msg)));
            $a0->addToolTip($msg);
            $html->pushContent($a0);
        } else {
            $msg = _("Cancel rating (no rating set)");
            $a0->pushContent(HTML::img(array('src' => $Theme->getImageUrl("RateItCancelN"),
                                             'alt' => $msg)));
            $a0->addToolTip($msg);
            $html->pushContent($a0);
        }
        $img_attr = array();
        $img_attr['src'] = $Theme->_findData("images/RateItAction.png");
        $img_attr['name'] = $actionImgName;
        //$img_attr['class'] = 'k' . $i;
        $img_attr['border'] = 0;
        $html->pushContent(HTML::img($img_attr));
        // Display the current rating if there is one
        if ($rating) 
            $html->pushContent(JavaScript('displayRating(\'' . $reImgPrefix . '\', ' .$rating .')'));
        return $html;
    }

};


// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
