<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

$db = openDatabase();
$settings = getSettings();
$tpl = setRainTpl();

if(isset($_GET['feed']) || logUser($tpl)) {
    
    //filters
    $where = "";
    $criteria = array();
    
    //filter by tag
    if(isset($_GET['tag']) && $_GET['tag'] != "") {
        if(is_numeric($_GET['tag']) && is_int(intval($_GET['tag']))) {
            $where .= " AND t.tagId=:tagId";
            
            $qryTag = $db->prepare("SELECT tagName FROM ddb_tag WHERE tagId=:tagId LIMIT 1");
            $qryTag->bindParam(':tagId', intval($_GET['tag']), PDO::PARAM_INT);
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
    
    //replace the first "AND" by a "WHERE"
    $where = preg_replace('/AND/', 'WHERE', $where, 1);
    
    //sort & order
    if(isset($_GET['sortOrder'])) {
        list($sort, $order) = explode("|", $_GET['sortOrder']);
        $orderBy = "";
        if($sort == "date") {
            $orderBy = " ORDER BY d.dreamDate";
        } elseif($sort == "dreamer") {
            $orderBy = " ORDER BY dr.dreamerName";
        }
        
        if($orderBy != "" && isset($order) && ($order == 'asc' || $order == 'desc')) {
            $orderBy  .= ' ' . $order;
        }
        
        if($sort != "date") {
            $orderBy  .= ', d.dreamDate DESC';
        }
    } else {
        $orderBy = " ORDER BY d.dreamDate DESC";
    }
    
    //pagination and limit for RSS
    $limit = "";
    if(isset($_GET['feed'])) {
        $orderBy = " ORDER BY d.dreamCreation DESC, d.dreamId DESC";
        $limit = " LIMIT 10";
    }
    
    $sql = 
        "SELECT dr.dreamerName, dr.dreamerId, d.dreamId"
        .", strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
        .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings, d.dreamCreation"
        .", Group_Concat(t.tagName,'|') as tags"
        ." FROM ddb_dream d"
        ." LEFT JOIN ddb_dreamer dr on d.dreamerId_FK = dr.dreamerId"
        ." LEFT JOIN ddb_dream_tag dt on d.dreamId = dt.dreamId_FK"
        ." LEFT JOIN ddb_tag t on dt.tagId_FK = t.tagId"
        .$where
        ." GROUP BY dr.dreamerName, d.dreamId"
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
    
    $qryDreams->execute();
    
    if(isset($_GET['feed'])) {
        header("Content-Type: application/rss+xml; charset=UTF-8");
        $dreams = $qryDreams->fetchAll();
        //format creation date to RFC822
        foreach($dreams as $key => $value) {
            $dreams[$key]['dreamCreation'] = gmdate(DATE_RSS, strtotime($dreams[$key]['dreamCreation']));
        }
        
        $tpl->assign( "dreams", $dreams );
        $tpl->assign( "criteria", $criteria );
        $tpl->draw( "rss" );
        
    } elseif(isset($_GET['csv'])) {
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=ddb_".date("Y-m-d_H-i").".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        while ($row = $qryDreams->fetch(PDO::FETCH_ASSOC)) {
            unset($row['dreamerId']);
            unset($row['dreamId']);
            
            //headers
            if(empty($header)) {
                $header = array_keys($row);
                echo implode(",", $header)."\n";
            }
            
            foreach($row as $key => $value) {
                $row[$key] = str_replace( '"', '\"', $row[$key] );
            }
            
            //dreams
            echo "\"".implode("\",\"", $row)."\"\n";
        }
        
    } else {
        $dreams = $qryDreams->fetchAll();
        
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