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
    $status = DREAM_STATUS_PUBLISHED;

	if(isset($_GET['comments'])) {
		$where = ' WHERE d.dreamStatus = :status';
		$limit = ' LIMIT 50';
		$orderBy = ' ORDER BY c.commentLastEdit DESC';

		if(isset($_GET['dream']) && is_numeric($_GET['dream'])) {
			$dreamId = (int)$_GET['dream'];
			$where .= ' AND d.dreamId=:dreamId';
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

        $qryComments->bindParam(':status', $status, PDO::PARAM_INT);
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

            $comments[$key]['commentText'] = wikiFormat($value['commentText']);
        }
        
        $tpl->assign( "comments", $comments );
        $tpl->draw( "rssComments" );
    } elseif(isset($_GET['dreams'])) {
        $where = ' WHERE d.dreamStatus = :status';
        $orderBy = ' ORDER BY qry.dreamPublication DESC, qry.dreamDateUnformated DESC, qry.dreamId DESC';
        $limit = ' LIMIT 10';

        $sql = 
            "SELECT dr.dreamerName, d.dreamId"
            .", strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
            .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings, d.dreamCreation, d.dreamPublication, u.userLogin, d.dreamDate as dreamDateUnformated"
            ." FROM ddb_dream d"
            ." LEFT JOIN ddb_dreamer dr on d.dreamerId_FK = dr.dreamerId"
            ." LEFT JOIN ddb_dream_tag dt on d.dreamId = dt.dreamId_FK"
            ." LEFT JOIN ddb_tag t on dt.tagId_FK = t.tagId"
            ." LEFT JOIN ddb_user u on u.userId = d.userId_FK"
            .$where
            ." GROUP BY dr.dreamerName, d.dreamId";

        //add tags with icons
        $sql = "SELECT qry.*, Group_Concat(CASE WHEN ti.tagIcon IS NULL THEN '' ELSE ti.tagIcon END || '§' || ti.tagName,'|') as tags FROM ("
            .$sql
            .") qry"
            ." LEFT JOIN ddb_dream_tag dti on qry.dreamId = dti.dreamId_FK"
            ." LEFT JOIN ddb_tag ti on dti.tagId_FK = ti.tagId"
            ." GROUP BY qry.dreamerName, qry.dreamId"
            .$orderBy
            .$limit;
        
        $qryDreams = $db->prepare($sql);
        $qryDreams->bindParam(':status', $status, PDO::PARAM_INT);
        $qryDreams->execute();
        $dreams = $qryDreams->fetchAll(PDO::FETCH_ASSOC);

        //format creation date to RFC822
        foreach($dreams as $key => $value) {
            $dreams[$key]['formatedPublication'] = gmdate(DATE_RSS, strtotime($dreams[$key]['dreamPublication']));
            
            $dreams[$key]['dreamText'] = wikiFormat($value['dreamText']);
            $dreams[$key]['dreamCharacter'] = wikiFormat($value['dreamCharacter'], false);
            $dreams[$key]['dreamPlace'] = wikiFormat($value['dreamPlace'], false);
            $dreams[$key]['dreamPointOfVue'] = wikiFormat($value['dreamPointOfVue'], false);
            $dreams[$key]['dreamFunFacts'] = wikiFormat($value['dreamFunFacts'], false);
            $dreams[$key]['dreamFeelings'] = wikiFormat($value['dreamFeelings'], false);
        }
        
        $tpl->assign( "dreams", $dreams );
        $tpl->draw( "rss" );
    }
}

?>