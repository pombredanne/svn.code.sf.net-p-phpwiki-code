<?php // -*-php-*-
rcs_id('$Id: RatingsDb.php,v 1.3 2004-06-30 20:05:36 dfrankow Exp $');

/*
 * @author:  Dan Frankowski (wikilens author), Reini Urban (as plugin)
 *
 * TODO: 
 * - fix RATING_STORAGE = WIKIPAGE
 * - fix smart caching
 * - finish mysuggest.c (external engine with data from mysql)
 * - add php_prediction
 * - add the various show modes (esp. TopN queries in PHP)
 */
/*
 CREATE TABLE rating (
        dimension INT(4) NOT NULL,
        raterpage INT(11) NOT NULL,
        rateepage INT(11) NOT NULL,
        ratingvalue FLOAT NOT NULL,
        rateeversion INT(11) NOT NULL,
        isPrivate ENUM('yes','no'),
        tstamp TIMESTAMP(14) NOT NULL,
        PRIMARY KEY (dimension, raterpage, rateepage)
 );
*/

//define('RATING_STORAGE','WIKIPAGE');   // not fully supported yet
define('RATING_STORAGE','SQL');          // only for mysql yet.
// leave undefined for internal, slow php engine.
//define('RATING_EXTERNAL',PHPWIKI_DIR . 'suggest.exe');

class RatingsDb extends WikiDB {

       
    function RatingsDb() {
        global $request;
        $this->_dbi = &$request->_dbi;
        $this->_backend = &$this->_dbi->_backend;
        if (isa($this->_backend, 'WikiDB_backend_PearDB'))
            $this->dbtype = "PearDB";
        else
            $this->dbtype = "ADODB";
        $this->iter_class = "WikiDB_backend_".$this->dbtype."_generic_iter";
        
        extract($this->_backend->_table_names);
        if (empty($rating_tbl)) {
            $rating_tbl = (!empty($GLOBALS['DBParams']['prefix']) 
                           ? $GLOBALS['DBParams']['prefix'] : '') . 'rating';
            $request->_dbi->_backend->_table_names['rating_tbl'] = $rating_tbl;
        }
    }
    
    // this is a singleton.  It ensures there is only 1 ratingsDB.
    function &getTheRatingsDb(){
        static $_theRatingsDb;
        
        if (!isset($_theRatingsDb)){
            $_theRatingsDb = new RatingsDb();
        } 
        //echo "rating db is $_theRatingsDb";
        return $_theRatingsDb;
    }
   

/// *************************************************************************************
// FIXME    
// from Reini Urban's RateIt plugin
    function addRating($rating, $userid, $pagename, $dimension) {
        if (RATING_STORAGE == 'SQL') {
            $page = $this->_dbi->getPage($pagename);
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

    function getUsersRated($dimension=null, $orderby = null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($userid))    $userid = $this->userid; 
        if (is_null($pagename))  $pagename = $this->pagename;
        if (RATING_STORAGE == 'SQL') {
            $ratings_iter = $this->sql_get_users_rated($dimension, $orderby);
            if ($rating = $ratings_iter->next()) {
                return $rating['ratingvalue'];
            } else 
                return false;
        } else {
            return $this->metadata_get_users_rated($dimension, $orderby);
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
    function get_rating($dimension=null, $rater=null, $ratee=null,
                        $orderby = null, $pageinfo = "ratee") {
        if (RATING_STORAGE == 'SQL') {
            $ratings_iter = $this->sql_get_rating($dimension, $rater, $pagename);
            if ($rating = $ratings_iter->next()) {
                return $rating['ratingvalue'];
            } else 
                return false;
               // return $ratings_iter;
        } else {
            return $this->metadata_get_rating($rater, $pagename, $dimension);
        }
        /*
        return $this->_backend->get_rating($dimension, $rater, $ratee,
                                           $orderby, $pageinfo);
        */
    }
    
    function get_users_rated($dimension=null, $orderby = null) {
        if (RATING_STORAGE == 'SQL') {
            $ratings_iter = $this->sql_get_users_rated($dimension, $orderby);
            if ($rating = $ratings_iter->next()) {
               return $rating['ratingvalue'];
            } else 
                return false;
        } else {
            return $this->metadata_get_users_rated($dimension, $orderby);
        }
        /*
        return $this->_backend->get_users_rated($dimension, $orderby);
        */
    }

    /**
     * Like get_rating(), but return a WikiDB_PageIterator
     * FIXME!
     */
    function get_rating_page($dimension=null, $rater=null, $ratee=null,
                        $orderby = null, $pageinfo = "ratee") {
        $result = $this->_backend->get_rating_page($dimension, $rater, $ratee,
                                                   $orderby, $pageinfo);
        return new WikiDB_PageIterator($this, $result);
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
    function delete_rating($rater, $ratee, $dimension) {
        if (RATING_STORAGE == 'SQL') {
            $this->sql_delete_rating($userid, $pagename, $dimension);
        } else {
            $this->metadata_set_rating($userid, $pagename, $dimension, -1);
        }
        /*
        return $this->_backend->delete_rating($rater, $ratee, $dimension);
        */
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
    function rate($rater, $ratee, $rateeversion, $dimension, $rating) {
        if (RATING_STORAGE == 'SQL') {
            $page = $this->_dbi->getPage($pagename);
            $current = $page->getCurrentRevision();
            $rateeversion = $current->getVersion();
            $this->sql_rate($userid, $pagename, $rateeversion, $dimension, $rating);
        } else {
            $this->metadata_set_rating($userid, $pagename, $dimension, $rating);
        }
        /*
        return $this->_backend->rate($rater, $ratee, $rateeversion, $dimension, $rating);
        */
    }
    
    //function getUsersRated(){}
    
//*******************************************************************************
    // TODO:
    // Use wikilens/RatingsUser.php for the php methods.
    //
    // Old:
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
        $dbi = &$this->_dbi->_backend;
        if (isset($pagename))
            $page = $dbi->_get_pageid($pagename);
        else return 0;
        if (isset($userid))
            $user = $dbi->_get_pageid($userid);
        else return 0;
        
        return 0;
        
        if (defined('RATING_EXTERNAL') and RATING_EXTERNAL) {
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
     *       Only the SUGGEST_EstimateAlpha part
     * Take wikilens/RatingsUser.php for the php methods.
     */
    function php_prediction($userid=null, $pagename=null, $dimension=null) {
        if (is_null($dimension)) $dimension = $this->dimension;
        if (is_null($userid))    $userid   = $this->userid; 
        if (is_null($pagename))  $pagename = $this->pagename;
        if (RATING_STORAGE == 'SQL') {
            $rating = 0;
        } else {
            $rating = 0;
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
            $iter = new $this->iter_class($this,$result);
            $row = $iter->next();
            return $row['avg'];
        } else {
            return 2.5;
        }
    }
//*******************************************************************************

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
        return new $this->iter_class($this, $result);
    }

    function sql_get_users_rated($dimension=null, $orderby=null) {
        if (empty($dimension)) $dimension=null;
        $result = $this->_sql_get_rating_result($dimension, null, null, $orderby, "rater");
        return new $this->iter_class($this, $result);
    }

    /**
     * @access private
     * @return result ressource, suitable to the iterator
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
        if (isset($rater) or isset($ratee)) $what = '*';
        // same as _get_users_rated_result()
        else $what = 'DISTINCT p.pagename, r.ratingvalue, r.dimension';

        $query = "SELECT $what"
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
        $where = "WHERE raterpage=$raterid and rateepage=$rateeid";
        if (isset($dimension)) {
            $where .= " AND dimension=$dimension";
        }
        $dbi->_dbh->query("DELETE FROM $rating_tbl $where");
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
    //               ($this->userid, $this->pagename, $this->dimension, $rating);
    function sql_rate($rater, $ratee, $rateeversion, $dimension, $rating) {
        $dbi = &$this->_dbi->_backend;
        extract($dbi->_table_names);
        if (empty($rating_tbl))
            $rating_tbl = $this->_dbi->getParam('prefix') . 'rating';

        //$dbi->lock();
        $raterid = $dbi->_get_pageid($rater, true);
        $rateeid = $dbi->_get_pageid($ratee, true);
        assert($raterid);
        assert($rateeid);
        //we changed back to delete and insert because update didn't work if it was a new rating
        
        $dbi->_dbh->query("DELETE from $rating_tbl WHERE dimension=$dimension AND raterpage=$raterid AND rateepage=$rateeid");
        $where = "WHERE raterpage='$raterid' AND rateepage='$rateeid'";

        $insert = "INSERT INTO $rating_tbl (dimension, raterpage, rateepage, ratingvalue, rateeversion) VALUES ('$dimension', $raterid, $rateeid, '$rating', '$rateeversion')";
        $dbi->_dbh->query($insert);
        
        //$dbi->unlock();
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
   
}

/*
class RatingsDB_backend_PearDB 
extends WikiDB_backend_PearDB {
    function get_rating($dimension=null, $rater=null, $ratee=null,
                        $orderby=null, $pageinfo = "ratee") {
        $result = $this->_get_rating_result(
                         $dimension, $rater, $ratee, $orderby, $pageinfo);
        return new WikiDB_backend_PearDB_generic_iter($this, $result);
    }
    
    function get_users_rated($dimension=null, $orderby=null) {
        $result = $this->_get_users_rated_result(
                         $dimension, $orderby);
        return new WikiDB_backend_PearDB_generic_iter($this, $result);
    }

    function get_rating_page($dimension=null, $rater=null, $ratee=null,
                             $orderby=null, $pageinfo = "ratee") {
        $result = $this->_get_rating_result(
                         $dimension, $rater, $ratee, $orderby, $pageinfo);
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    function _get_rating_result($dimension=null, $rater=null, $ratee=null,
                                $orderby=null, $pageinfo = "ratee") {
        // pageinfo must be 'rater' or 'ratee'
        if (($pageinfo != "ratee") && ($pageinfo != "rater"))
            return;

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $where = "WHERE r." . $pageinfo . "page = p.id";
        if (isset($dimension)) {
            $where .= " AND dimension=$dimension";
        }
        if (isset($rater)) {
            $raterid = $this->_get_pageid($rater, true);
            $where .= " AND raterpage=$raterid";
        }
        if (isset($ratee)) {
        	if(is_array($ratee)){
        		$where .= " AND (";
        		for($i = 0; $i < count($ratee); $i++){
        			$rateeid = $this->_get_pageid($ratee[$i], true);
            		$where .= "rateepage=$rateeid";
        			if($i != (count($ratee) - 1)){
        				$where .= " OR ";
        			}
        		}
        		$where .= ")";
        	} else {
        		$rateeid = $this->_get_pageid($ratee, true);
            	$where .= " AND rateepage=$rateeid";
        	}
        }

        $orderbyStr = "";
        if (isset($orderby)) {
            $orderbyStr = " ORDER BY " . $orderby;
        }

        $query = "SELECT *"
            . " FROM $rating_tbl r, $page_tbl p "
            . $where
            . $orderbyStr;

        $result = $dbh->query($query);

        return $result;
    }
    
    function _get_users_rated_result($dimension=null, $orderby=null) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $where = "WHERE p.id=r.raterpage";
        if (isset($dimension)) {
            $where .= " AND dimension=$dimension";
        }
        $orderbyStr = "";
        if (isset($orderby)) {
            $orderbyStr = " ORDER BY " . $orderby;
        }

        $query = "SELECT DISTINCT p.pagename"
            . " FROM $rating_tbl r, $page_tbl p "
            . $where
            . $orderbyStr;

        $result = $dbh->query($query);

        return $result;
    }
    function delete_rating($rater, $ratee, $dimension) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        $raterid = $this->_get_pageid($rater, true);
        $rateeid = $this->_get_pageid($ratee, true);

        $dbh->query("DELETE FROM $rating_tbl WHERE raterpage=$raterid and rateepage=$rateeid and dimension=$dimension");
        $this->unlock();
        return true;
    }

    function rate($rater, $ratee, $rateeversion, $dimension, $rating, $isPrivate = 'no') {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        $raterid = $this->_get_pageid($rater, true);
        $rateeid = $this->_get_pageid($ratee, true);

        $dbh->query("DELETE FROM $rating_tbl WHERE raterpage=$raterid and rateepage=$rateeid and dimension=$dimension and isPrivate='$isPrivate'");
        // NOTE: Leave tstamp off the insert, and MySQL automatically updates it
        $dbh->query("INSERT INTO $rating_tbl (dimension, raterpage, rateepage, ratingvalue, rateeversion, isPrivate) VALUES ($dimension, $raterid, $rateeid, $rating, $rateeversion, '$isPrivate')");
        $this->unlock();
        return true;
    }
} 
*/

// $Log: not supported by cvs2svn $
// Revision 1.2  2004/06/19 10:22:41  rurban
// outcomment the pear specific methods to let all pages load
//
// Revision 1.1  2004/06/18 14:42:17  rurban
// added wikilens libs (not yet merged good enough, some work for DanFr)
// 

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>