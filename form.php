<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once 'inc/functions.php';

initDDb($db, $settings, $tpl, $user);

if($user['isLoggedIn']) {

    //if the form was posted, save (new?) dream to ddb
    if( isset($_POST['text']) ) {

        //sanitize entries
        $values['newDreamer'] = htmlspecialchars(trim($_POST['newdreamer']));
        $values['dreamer'] = intval($_POST['dreamer']);
        //user input is dd/mm/yyyy, but we need to use yyyy-mm-dd in the database
        $dateArray = explode('/',$_POST['date']);
        $values['date'] = "$dateArray[2]-$dateArray[1]-$dateArray[0]";
        $values['title'] = htmlspecialchars(trim($_POST['title']));
        $values['characters'] = htmlspecialchars(trim($_POST['characters']));
        $values['place'] = htmlspecialchars(trim($_POST['place']));
        $values['text'] = htmlspecialchars(trim($_POST['text']));
        $values['pointofvue'] = htmlspecialchars(trim($_POST['pointofvue']));
        $values['funfacts'] = htmlspecialchars(trim($_POST['funfacts']));
        $values['feelings'] = htmlspecialchars(trim($_POST['feelings']));
        $values['tags'] = htmlspecialchars(trim($_POST['tags']));
        
        //1- save the new dreamer if exists
        if( !empty($values['newDreamer']) ) {
            $qry = $db->prepare(
                'INSERT INTO ddb_dreamer (dreamerName) VALUES (:name)');
            $qry->bindParam(':name', $values['newDreamer'], PDO::PARAM_STR);
            $qry->execute();
            
            $values['dreamer'] = $db->lastInsertId();

            //link the newly created to the current user if he/she is not admin
            if($user['role'] != 'admin') {
                $qry = $db->prepare(
                    'INSERT INTO ddb_user_dreamer (dreamerId_FK, userId_FK) VALUES (:dreamerId, :userId)');
                $qry->bindParam(':dreamerId', $dreamerId, PDO::PARAM_STR);
                $qry->bindParam(':userId', $user['id'], PDO::PARAM_STR);
                $qry->execute();
            }
        }
        
        //2- save the dream with the right dreamer id
        if( isset($_GET['id']) ) {
            $dreamId = intval($_GET['id']);
            
            $qry = $db->prepare(
                'UPDATE ddb_dream SET dreamerId_FK = :dreamerId, dreamDate = :dreamDate, dreamTitle = :dreamTitle'
                . ', dreamCharacters = :dreamCharacters, dreamPlace = :dreamPlace, dreamText = :dreamText'
                . ', dreamPointOfVue = :dreamPointOfVue, dreamFunFacts = :dreamFunFacts, dreamFeelings = :dreamFeelings'
                . ' WHERE dreamId = :dreamId');
            $qry->bindParam(':dreamerId', $values['dreamer'], PDO::PARAM_INT);
            $qry->bindParam(':dreamDate', $values['date'], PDO::PARAM_STR);
            $qry->bindParam(':dreamTitle', $values['title'], PDO::PARAM_STR);
            $qry->bindParam(':dreamCharacters', $values['characters'], PDO::PARAM_STR);
            $qry->bindParam(':dreamPlace', $values['place'], PDO::PARAM_STR);
            $qry->bindParam(':dreamText', $values['text'], PDO::PARAM_STR);
            $qry->bindParam(':dreamPointOfVue', $values['pointofvue'], PDO::PARAM_STR);
            $qry->bindParam(':dreamFunFacts', $values['funfacts'], PDO::PARAM_STR);
            $qry->bindParam(':dreamFeelings', $values['feelings'], PDO::PARAM_STR);
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
            $qry->bindParam(':dreamerId', $values['dreamer'], PDO::PARAM_INT);
            $qry->bindParam(':dreamDate', $values['date'], PDO::PARAM_STR);
            $qry->bindParam(':dreamTitle', $values['title'], PDO::PARAM_STR);
            $qry->bindParam(':dreamCharacters', $values['characters'], PDO::PARAM_STR);
            $qry->bindParam(':dreamPlace', $values['place'], PDO::PARAM_STR);
            $qry->bindParam(':dreamText', $values['text'], PDO::PARAM_STR);
            $qry->bindParam(':dreamPointOfVue', $values['pointofvue'], PDO::PARAM_STR);
            $qry->bindParam(':dreamFunFacts', $values['funfacts'], PDO::PARAM_STR);
            $qry->bindParam(':dreamFeelings', $values['feelings'], PDO::PARAM_STR);
            $qry->execute();
            
            $dreamId = $db->lastInsertId();
        }
        
        $tags = explode(',', $values['tags']);
        if( count($tags)>0 ) {
            
            //3- save new tags to the tag table
            $tagIds = array();
            for( $i=0; $i < count($tags); $i++ ) {
                $tag = trim($tags[$i]);
                
                if( strlen($tag) > 0 ) {
                    $qry = $db->prepare(
                        'SELECT tagId FROM ddb_tag WHERE tagName = :name');
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
        header('Location: dream.php?id='.$dreamId);
    }
    
    
    //if this is a modification of an existing dream
    if( isset($_GET['id']) ) {
        $dream = array();
        $dream['id'] = $_GET['id'];
        
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
        
        $tagList = '';
        while ( $row = $qry->fetch(PDO::FETCH_ASSOC) ) {
            $tagList .= $row['tagName'].', ';
        }
        $dream['tagList'] = htmlspecialchars($tagList);
        
        $tpl->assign( 'dream', $dream );
    } else {
    }
    
    //feed the dreamer accessible dreamers for the current user
    if($user['role'] == 'admin') {
        $qry = $db->prepare('SELECT dr.dreamerId, dr.dreamerName'
            .' FROM ddb_dreamer dr'
            .' ORDER BY dr.dreamerName ASC');
    } else {
        $qry = $db->prepare('SELECT dr.dreamerId, dr.dreamerName'
            .' FROM ddb_dreamer dr'
            .' INNER JOIN ddb_user_dreamer ud ON ud.dreamerId_FK = dr.dreamerId'
            .' WHERE ud.userId_FK = :userId'
            .' ORDER BY dr.dreamerName ASC');
        $qry->bindParam(':userId', $user['id'], PDO::PARAM_INT);
    }
    $qry->execute();
    $dreamers = $qry->fetchAll(PDO::FETCH_ASSOC);

    //try to guess which dreamer to select by default
    if(!empty($dreamers)) {
        $levenshtein = array();
        for($i = 0; $i < count($dreamers); $i++) {
            $dreamers[$i]['selected'] = false;
            $levenshtein[$i] = levenshtein(strtolower($dreamers[$i]['dreamerName']), strtolower($user['login']));
        }
        //select the one with the lowest Levenshtein distance
        $dreamers[array_keys($levenshtein, min($levenshtein))[0]]['selected'] = true;
        
    }
    
    //feed the tag list with existing tags
    $qry = $db->prepare(
        "SELECT tagName FROM ddb_tag ORDER BY tagName ASC");
    $qry->execute();
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $tags[] = $row['tagName'];
    }
    
    $tpl->assign( 'dreamers', $dreamers );
    $tpl->assign( 'js', true );
    if(empty($tags)) {
        $tagList = '';
        $tags = array();
    }
    $tpl->assign( 'tagList', json_encode($tags) );
    $tpl->assign( 'today', date('d/m/Y') );
    $tpl->draw( 'form' );
}

?>