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

initDDb($db, $settings, $tpl, $user);

if($user['isLoggedIn']) {
    
    //filters
    $where = "";
    $criteria = array();
    
    //filter by tag
    if(isset($_GET['tag']) && $_GET['tag'] != "") {
        if(is_numeric($_GET['tag']) && is_int(intval($_GET['tag']))) {
            $where .= " AND t.tagId=:tagId";
            
            $tagId = intval($_GET['tag']);
            $qryTag = $db->prepare("SELECT tagName FROM ddb_tag WHERE tagId=:tagId LIMIT 1");
            $qryTag->bindParam(':tagId', $tagId, PDO::PARAM_INT);
            $qryTag->execute();
            $qryTag->bindColumn('tagName', $tagName);
            $row = $qryTag->fetch(PDO::FETCH_BOUND);
            $criteria["tag"] = $tagName;
        } else {
            $where .= " AND t.tagName=:tagName";
            $criteria["tag"] = htmlentities($_GET['tag']);
        }
    }
    
    //filter by dreamer
    if(isset($_GET['dreamer']) && $_GET['dreamer'] != "") {
        if(is_numeric($_GET['dreamer']) && is_int(intval($_GET['dreamer']))) {
            $where .= " AND dr.dreamerId=:dreamerId";
            
            $qryDreamer = $db->prepare("SELECT dreamerName FROM ddb_dreamer WHERE dreamerId=:dreamerId");
            $qryDreamer->bindParam(':dreamerId', $_GET['dreamer'], PDO::PARAM_INT);
            $qryDreamer->execute();
            $qryDreamer->bindColumn('dreamerName', $dreamerName);
            $row = $qryDreamer->fetch(PDO::FETCH_BOUND);
            $criteria["dreamer"] = $dreamerName;
        } else {
            $where .= " AND dr.dreamerName=:dreamerName";
            $criteria["dreamer"] = htmlentities($_GET['dreamer']);
        }
    }
    
    //filter by text search
    if(isset($_GET['text']) && $_GET['text'] != "") {
        $where .= " AND ("
            ."d.dreamTitle LIKE :searchText"
            ." OR d.dreamCharacters LIKE :searchText"
            ." OR d.dreamPlace LIKE :searchText"
            ." OR d.dreamText LIKE :searchText"
            ." OR d.dreamPointOfVue LIKE :searchText"
            ." OR d.dreamFunFacts LIKE :searchText"
            ." OR d.dreamFeelings LIKE :searchText"
            ." OR d.dreamId IN ("
            ."SELECT DISTINCT dt.dreamId_FK FROM ddb_dream_tag dt INNER JOIN ddb_tag t ON t.tagId = dt.tagId_FK"
            ." WHERE t.tagName LIKE :searchText"
            ."))";
        $criteria["text"] = $_GET['text'];
    }

    //specific filter
    if(isset($_GET['filter']) && $_GET['filter'] == 'myDreams') {
        $status = DREAM_STATUS_PUBLISHED;
        $where .= ' AND u.userId=:userId';
    } elseif(isset($_GET['filter']) && $_GET['filter'] == 'myUnpublished') {
        $status = DREAM_STATUS_UNPUBLISHED;
        $where .= ' AND d.dreamStatus = :status AND u.userId=:userId';
    } elseif(isset($_GET['filter']) && $_GET['filter'] == 'all' && $user['role'] == 'admin') {
        //nothing to do here
        //used only to avoid unauthorized user to list all dreams, including unpublished by other users
    } elseif(isset($_GET['filter']) && $_GET['filter'] == 'unpublished' && $user['role'] == 'admin') {
        $status = DREAM_STATUS_UNPUBLISHED;
        $where .= ' AND d.dreamStatus = :status';
    } else {
        //only published dreams, or unpublished by current user
        $status = DREAM_STATUS_PUBLISHED;
        $where .= ' AND (d.dreamStatus = :status OR d.userId_FK = :userId)';
    }

    //replace the first "AND" by a "WHERE"
    $where = preg_replace('/AND/', 'WHERE', $where, 1);
    
    //sort & order
    if(isset($_GET['sortOrder'])) {
        list($sort, $order) = explode("|", $_GET['sortOrder']);
        $orderBy = "";
        if($sort == "date") {
            $orderBy = " ORDER BY qry.dreamDateUnformated";
        } elseif($sort == "dreamer") {
            $orderBy = " ORDER BY qry.dreamerName";
        }
        
        if($orderBy != "" && isset($order) && ($order == 'asc' || $order == 'desc')) {
            $orderBy  .= ' ' . $order;
        }
        
        if($sort != "date") {
            $orderBy  .= ', qry.dreamDateUnformated DESC';
        }
    } else {
        $orderBy = " ORDER BY qry.dreamDateUnformated DESC";
    }
    
    //pagination and limit for RSS
    $limit = "";
    
    $sql = 
        "SELECT dr.dreamerName, dr.dreamerId, d.dreamId"
        .", strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
        .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings, d.dreamCreation, u.userLogin"
        .", d.dreamDate as dreamDateUnformated, d.dreamStatus, count(c.commentId) as nbComments"
        ." FROM ddb_dream d"
        ." LEFT JOIN ddb_dreamer dr on d.dreamerId_FK = dr.dreamerId"
        ." LEFT JOIN ddb_dream_tag dt on d.dreamId = dt.dreamId_FK"
        ." LEFT JOIN ddb_tag t on dt.tagId_FK = t.tagId"
        ." LEFT JOIN ddb_user u on u.userId = d.userId_FK"
        ." LEFT JOIN ddb_comment c on c.dreamId_FK = d.dreamId"
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
    
    //bind parameters
    if(isset($_GET['tag']) && $_GET['tag'] != "") {
        if(is_numeric($_GET['tag']) && is_int(intval($_GET['tag']))) {
            $qryDreams->bindParam(':tagId', $_GET['tag'], PDO::PARAM_INT);
        } else {
            $qryDreams->bindParam(':tagName', $_GET['tag'], PDO::PARAM_STR);
        }
    }
    if(isset($_GET['dreamer']) && $_GET['dreamer'] != "") {
        if(is_numeric($_GET['dreamer']) && is_int(intval($_GET['dreamer']))) {
            $qryDreams->bindParam(':dreamerId', $_GET['dreamer'], PDO::PARAM_INT);
        } else {
            $qryDreams->bindParam(':dreamerName', $_GET['dreamer'], PDO::PARAM_STR);
        }
    }
    if(isset($_GET['text']) && $_GET['text'] != "") {
        $searchText = '%'.$_GET['text'].'%';
        $qryDreams->bindParam(':searchText', $searchText, PDO::PARAM_STR);
    }

    if(isset($_GET['filter']) && $_GET['filter'] == 'myDreams') {
        $qryDreams->bindParam(':userId', $user['id'], PDO::PARAM_INT);
    } elseif(isset($_GET['filter']) && $_GET['filter'] == 'myUnpublished') {
        $qryDreams->bindParam(':status', $status, PDO::PARAM_INT);
        $qryDreams->bindParam(':userId', $user['id'], PDO::PARAM_INT);
    } elseif(isset($_GET['filter']) && $_GET['filter'] == 'all' && $user['role'] == 'admin') {
        //nothing to do here
        //used only to avoid unauthorized user to list all dreams, including unpublished by other users
    } elseif(isset($_GET['filter']) && $_GET['filter'] == 'unpublished' && $user['role'] == 'admin') {
        $qryDreams->bindParam(':status', $status, PDO::PARAM_INT);
    } else {
        //only published dreams, or unpublished by current user
        $qryDreams->bindParam(':status', $status, PDO::PARAM_INT);
        $qryDreams->bindParam(':userId', $user['id'], PDO::PARAM_INT);
    }
    
    $qryDreams->execute();

    if(isset($_GET['csv'])) {
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=ddb_".date("Y-m-d_H-i").".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        while ($row = $qryDreams->fetch(PDO::FETCH_ASSOC)) {
            unset($row['dreamerId']);
            unset($row['dreamId']);
            unset($row['dreamDateUnformated']);
            
            //headers
            if(empty($header)) {
                $header = array_keys($row);
                echo implode(",", $header)."\n";
            }
            
            foreach($row as $key => $value) {
                $row[$key] = str_replace( '"', '\"', htmlspecialchars_decode($row[$key]) );
            }
            
            //dreams
            echo "\"".implode("\",\"", $row)."\"\n";
        }
        
    } else {
        $dreams = $qryDreams->fetchAll(PDO::FETCH_ASSOC);

        //turn tags list to array
        foreach($dreams as $key => $value) {
            $dreams[$key]['tags'] = explode('|', $dreams[$key]['tags']);
            foreach($dreams[$key]['tags'] as $subKey => $subValue) {
                if(!empty($subValue)) {
                    $tag = explode('§', $subValue, 2);
                    $dreams[$key]['tags'][$subKey] = array('tagIcon' => $tag[0], 'tagName' => $tag[1]);
                } else {
                    unset($dreams[$key]['tags'][$subKey]);
                }
            }
        }

        $params = array_merge($_GET, array("csv" => "true"));
        $csvLink = $_SERVER["PHP_SELF"] . "?" . http_build_query($params);
        
        $tpl->assign( "getParam", $_GET );
        $tpl->assign( "dreams", $dreams );
        $tpl->assign( "criteria", $criteria );
        $tpl->assign( "csvLink", $csvLink );
        $tpl->draw( "list" );
    }
}

?>