<?php

/**
 * DDb - Copyright 2013 Yosko (www.yosko.net)
 * 
 * This file is part of DDb.
 * 
 * DDb is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * DDb is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with DDb.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */

include_once "inc/functions.php";

initDDb($db, $settings, $tpl, $user, $publicFeed, true);

if($publicFeed) {
    header("Content-Type: application/rss+xml; charset=UTF-8");

	if(isset($_GET['comments'])) {
		$where = '';
		$limit = ' LIMIT 10';
		$orderBy = ' ORDER BY c.commentLastEdit DESC';

		if(isset($_GET['dream']) && is_numeric($_GET['dream'])) {
			$dreamId = (int)$_GET['dream'];
			$where = ' WHERE d.dreamId=:dreamId';
		}

	    $sql = "SELECT u.userLogin, c.commentId, c.commentText, d.dreamId, d.dreamTitle, dr.dreamerName"
	    	.", c.commentCreation, c.commentLastEdit"
	        ." FROM ddb_comment c"
	        ." LEFT JOIN ddb_user u on u.userId = c.userId_FK"
	        ." LEFT JOIN ddb_dream d on d.dreamId = c.dreamId_FK"
	        ." LEFT JOIN ddb_dreamer dr on dr.dreamerId = d.dreamerId_FK"
	        .$where
	        .$orderBy
	        .$limit;
	    
	    $qryComments = $db->prepare($sql);

    	if(isset($dreamId)) {
            $qryComments->bindParam(':dreamId', $dreamId, PDO::PARAM_INT);
    	}

    	$qryComments->execute();
        $comments = $qryComments->fetchAll(PDO::FETCH_ASSOC);

        //format creation date to RFC822
        foreach($comments as $key => $value) {
        	
        	if($comments[$key]['commentCreation'] > $comments[$key]['commentLastEdit']) {
        		$itemDate = $comments[$key]['commentCreation'];
        	} else {
        		$itemDate = $comments[$key]['commentLastEdit'];
        	}
            $comments[$key]['itemDate'] = gmdate(DATE_RSS, strtotime($itemDate));
        }
        
        $tpl->assign( "comments", $comments );
        $tpl->draw( "rssComments" );
	}

}

?>