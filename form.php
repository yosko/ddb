<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

$db = openDatabase();
$settings = getSettings();
$tpl = setRainTpl();
$tpl->assign( "settings", $settings );
$user = logUser($tpl);

if($user['isLoggedIn']) {

    //if the form was posted, save (new?) dream to ddb
    if( isset($_POST["text"]) ) {
        
        //1- save the new dreamer if exists
        if( strlen($_POST["newdreamer"]) > 0 ) {
            $qry = $db->prepare(
                'INSERT INTO ddb_dreamer (dreamerName) VALUES (:name)');
            $qry->bindParam(':name', $_POST["newdreamer"], PDO::PARAM_STR);
            $qry->execute();
            
            $dreamerId = $db->lastInsertId();
            /*
            $qry = $db->prepare(
                "SELECT dreamerId FROM ddb_dreamer WHERE dreamerName = :name");
            $qry->bindParam(':name', $_POST["newdreamer"], PDO::PARAM_STR);
            $qry->execute();
            $dreamerId = $qry->fetchColumn();
            */
        } else {
            $dreamerId = intval($_POST["dreamer"]);
        }
            
        //user input is dd/mm/yyyy, but we need to use yyyy-mm-dd in the database
        $dateArray = explode("/",$_POST["date"]);
        $date = "$dateArray[2]-$dateArray[1]-$dateArray[0]";
        
        //2- save the dream with the right dreamer id
        if( isset($_GET["id"]) ) {
            $dreamId = intval($_GET["id"]);
            
            $qry = $db->prepare(
                'UPDATE ddb_dream SET dreamerId_FK = :dreamerId, dreamDate = :dreamDate, dreamTitle = :dreamTitle'
                . ', dreamCharacters = :dreamCharacters, dreamPlace = :dreamPlace, dreamText = :dreamText'
                . ', dreamPointOfVue = :dreamPointOfVue, dreamFunFacts = :dreamFunFacts, dreamFeelings = :dreamFeelings'
                . ' WHERE dreamId = :dreamId');
            $qry->bindParam(':dreamerId', $dreamerId, PDO::PARAM_INT);
            $qry->bindParam(':dreamDate', $date, PDO::PARAM_STR);
            $qry->bindParam(':dreamTitle', $_POST["title"], PDO::PARAM_STR);
            $qry->bindParam(':dreamCharacters', $_POST["characters"], PDO::PARAM_STR);
            $qry->bindParam(':dreamPlace', $_POST["place"], PDO::PARAM_STR);
            $qry->bindParam(':dreamText', $_POST["text"], PDO::PARAM_STR);
            $qry->bindParam(':dreamPointOfVue', $_POST["pointofvue"], PDO::PARAM_STR);
            $qry->bindParam(':dreamFunFacts', $_POST["funfacts"], PDO::PARAM_STR);
            $qry->bindParam(':dreamFeelings', $_POST["feelings"], PDO::PARAM_STR);
            $qry->bindParam(':dreamId', $dreamId, PDO::PARAM_INT);
            $qry->execute();
            
            //delete all tags attached to the dream (will be recreated)
            $qry = $db->prepare(
                'DELETE FROM ddb_dream_tag WHERE dreamId_FK = :dreamId');
            $qry->bindParam(':dreamId', $dreamId, PDO::PARAM_INT);
            $qry->execute();
        } else {
            $qry = $db->prepare(
                'INSERT INTO ddb_dream (dreamerId_FK, dreamDate, dreamTitle, dreamCharacters, dreamPlace, dreamText, dreamPointOfVue, dreamFunFacts, dreamFeelings)'
                . ' VALUES (:dreamerId, :dreamDate, :dreamTitle, :dreamCharacters, :dreamPlace, :dreamText, :dreamPointOfVue, :dreamFunFacts, :dreamFeelings)');
            $qry->bindParam(':dreamerId', $dreamerId, PDO::PARAM_INT);
            $qry->bindParam(':dreamDate', $date, PDO::PARAM_STR);
            $qry->bindParam(':dreamTitle', $_POST["title"], PDO::PARAM_STR);
            $qry->bindParam(':dreamCharacters', $_POST["characters"], PDO::PARAM_STR);
            $qry->bindParam(':dreamPlace', $_POST["place"], PDO::PARAM_STR);
            $qry->bindParam(':dreamText', $_POST["text"], PDO::PARAM_STR);
            $qry->bindParam(':dreamPointOfVue', $_POST["pointofvue"], PDO::PARAM_STR);
            $qry->bindParam(':dreamFunFacts', $_POST["funfacts"], PDO::PARAM_STR);
            $qry->bindParam(':dreamFeelings', $_POST["feelings"], PDO::PARAM_STR);
            $qry->execute();
            
            $dreamId = $db->lastInsertId();
        }
        
        $tags = explode(",", $_POST["tags"]);
        if( count($tags)>0 ) {
            
            //3- save new tags to the tag table
            $tagIds = array();
            for( $i=0; $i < count($tags); $i++ ) {
                $tag = trim($tags[$i]);
                
                if( strlen($tag) > 0 ) {
                    $qry = $db->prepare(
                        "SELECT tagId FROM ddb_tag WHERE tagName = :name");
                    $qry->bindParam(':name', $tag, PDO::PARAM_STR);
                    $qry->execute();
                    
                    if( !($tagId = $qry->fetchColumn()) ) {
                        $qry = $db->prepare(
                            'INSERT INTO ddb_tag (tagName) VALUES (:name)');
                        $qry->bindParam(':name', $tag, PDO::PARAM_STR);
                        $qry->execute();
                        
                        $tagId = $db->lastInsertId();
                    }
                    $tagIds[] = intval($tagId);
                }
            }
            
            //4- save the dream tags
            foreach( $tagIds as $tagId ) {
                $qry = $db->prepare(
                    'INSERT INTO ddb_dream_tag (dreamId_FK, tagId_FK) VALUES (:dreamId, :tagId)');
                $qry->bindParam(':dreamId', $dreamId, PDO::PARAM_INT);
                $qry->bindParam(':tagId', $tagId, PDO::PARAM_INT);
                $qry->execute();
            }
        }
        
        //go to the dream page
        header("Location: dream.php?id=".$dreamId);
    }
    
    
    //if this is a modification of an existing dream
    if( isset($_GET["id"]) ) {
        $dream = array();
        $dream['id'] = $_GET["id"];
        
        //get dream informations
        $qryDream = $db->prepare(
            "SELECT a.dreamerName, a.dreamerId, strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
            .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings"
            ." FROM ddb_dream d INNER JOIN ddb_dreamer a on d.dreamerId_FK = a.dreamerId"
            ." WHERE dreamId = :dreamId");
        $qryDream->bindParam(':dreamId', $dream['id'], PDO::PARAM_INT);
        $qryDream->execute();
        
        $qryDream->bindColumn('dreamerName', $dream['dreamerName']);
        $qryDream->bindColumn('dreamerId', $dream['dreamerId']);
        $qryDream->bindColumn('dreamDate', $dream['date']);
        $qryDream->bindColumn('dreamTitle', $dream['title']);
        $qryDream->bindColumn('dreamCharacters', $dream['characters']);
        $qryDream->bindColumn('dreamPlace', $dream['place']);
        $qryDream->bindColumn('dreamText', $dream['text']);
        $qryDream->bindColumn('dreamPointOfVue', $dream['pointOfVue']);
        $qryDream->bindColumn('dreamFunFacts', $dream['funFacts']);
        $qryDream->bindColumn('dreamFeelings', $dream['feelings']);
        
        //read the first line to feed the bind variables
        $row = $qryDream->fetch(PDO::FETCH_BOUND);
        
        $dream['title'] = htmlspecialchars($dream['title']);
        $dream['characters'] = htmlspecialchars($dream['characters']);
        $dream['place'] = htmlspecialchars($dream['place']);
        //$dream['text'] = htmlspecialchars($dream['text']);
        $dream['pointOfVue'] = htmlspecialchars($dream['pointOfVue']);
        $dream['funFacts'] = htmlspecialchars($dream['funFacts']);
        $dream['feelings'] = htmlspecialchars($dream['feelings']);
        
        $qry = $db->prepare(
            "SELECT t.tagName FROM ddb_dream_tag dt INNER JOIN ddb_tag t on dt.tagId_FK = t.tagId"
            ." WHERE dt.dreamId_FK = :dreamId ORDER BY t.tagName");
        $qry->bindParam(':dreamId', $dream['id'], PDO::PARAM_INT);
        $qry->execute();
        
        $tagList = "";
        while ( $row = $qry->fetch(PDO::FETCH_ASSOC) ) {
            $tagList .= $row['tagName'].", ";
        }
        $dream['tagList'] = htmlspecialchars($tagList);
        
        $tpl->assign( "dream", $dream );
    } else {
    }
    
    //feed the dreamer list with existing dreamers
    $qry = $db->prepare(
        "SELECT * FROM ddb_dreamer ORDER BY dreamerName ASC");
    $qry->execute();
    
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $dreamers[] = $row;
    }
    
    //feed the tag list with existing tags
    $qry = $db->prepare(
        "SELECT tagName FROM ddb_tag ORDER BY tagName ASC");
    $qry->execute();
    
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $tags[] = $row['tagName'];
    }
    
    $tpl->assign( "dreamers", $dreamers );
    $tpl->assign( "js", true );
    if(empty($tags)) {
        $tagList = '';
        $tags = array();
    }
    $tpl->assign( "tagList", json_encode($tags) );
    $tpl->assign( "today", date("d/m/Y") );
    $tpl->draw( "form" );
}

?>