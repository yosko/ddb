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

include_once 'inc/functions.php';

initDDb($db, $settings, $tpl, $user);

if($user['isLoggedIn']) {

    //if this is a modification of an existing dream
    $dreamEdition = false;
    if( isset($_GET['id']) ) {
        $dreamId = intval($_GET['id']);

        //if the current user is the author of the dream or is an admin
        if(isAuthor($user['id'], $dreamId) || $user['role'] == 'admin') {
            //the user can edit the dream
            $dreamEdition = true;
        } else {
            //the user isn't allowed to edit this dream
            header('Location: dream.php?id='.$dreamId);
            exit;
        }
    }
        

    //if the form was posted, save the new/existing dream to ddb
    if( isset($_POST['text']) ) {
        //sanitize entries
        $values['newDreamer'] = htmlspecialchars(trim($_POST['newdreamer']));
        $values['dreamerId'] = intval(isset($_POST['dreamer'])?$_POST['dreamer']:-1);
        //user input is dd/mm/yyyy, but we need to use yyyy-mm-dd in the database
        $dateArray = explode('/',$_POST['date']);
        $values['date'] = "$dateArray[2]-$dateArray[1]-$dateArray[0]";
        $values['title'] = htmlspecialchars(trim($_POST['title']));
        $values['characters'] = htmlspecialchars(trim($_POST['characters']));
        $values['place'] = htmlspecialchars(trim($_POST['place']));
        $values['text'] = htmlspecialchars(trim($_POST['text']));
        $values['pointOfVue'] = htmlspecialchars(trim($_POST['pointofvue']));
        $values['funFacts'] = htmlspecialchars(trim($_POST['funfacts']));
        $values['feelings'] = htmlspecialchars(trim($_POST['feelings']));
        $values['tagList'] = htmlspecialchars(trim($_POST['tags']));

        $errors['noDreamer'] = (!isset($_POST['dreamer']) && empty($_POST['newdreamer']));
        
        if($errors['noDreamer'] === false) {
            //1- save the new dreamer if set
            if( !empty($values['newDreamer']) ) {
                $qry = $db->prepare(
                    'INSERT INTO ddb_dreamer (dreamerName) VALUES (:name)');
                $qry->bindParam(':name', $values['newDreamer'], PDO::PARAM_STR);
                $qry->execute();
                
                $values['dreamerId'] = $db->lastInsertId();

                //link the newly created to the current user if he/she is not admin
                if($user['role'] != 'admin') {
                    $qry = $db->prepare(
                        'INSERT INTO ddb_user_dreamer (dreamerId_FK, userId_FK) VALUES (:dreamerId, :userId)');
                    $qry->bindParam(':dreamerId', $values['dreamerId'], PDO::PARAM_STR);
                    $qry->bindParam(':userId', $user['id'], PDO::PARAM_STR);
                    $qry->execute();
                }
            }
            
            //2- save the dream with the right dreamer id
            if( $dreamEdition == true ) {
                
                $qry = $db->prepare(
                    'UPDATE ddb_dream SET dreamerId_FK = :dreamerId, dreamDate = :dreamDate, dreamTitle = :dreamTitle'
                    . ', dreamCharacters = :dreamCharacters, dreamPlace = :dreamPlace, dreamText = :dreamText'
                    . ', dreamPointOfVue = :dreamPointOfVue, dreamFunFacts = :dreamFunFacts, dreamFeelings = :dreamFeelings'
                    . ' WHERE dreamId = :dreamId');
                $qry->bindParam(':dreamerId', $values['dreamerId'], PDO::PARAM_INT);
                $qry->bindParam(':dreamDate', $values['date'], PDO::PARAM_STR);
                $qry->bindParam(':dreamTitle', $values['title'], PDO::PARAM_STR);
                $qry->bindParam(':dreamCharacters', $values['characters'], PDO::PARAM_STR);
                $qry->bindParam(':dreamPlace', $values['place'], PDO::PARAM_STR);
                $qry->bindParam(':dreamText', $values['text'], PDO::PARAM_STR);
                $qry->bindParam(':dreamPointOfVue', $values['pointOfVue'], PDO::PARAM_STR);
                $qry->bindParam(':dreamFunFacts', $values['funFacts'], PDO::PARAM_STR);
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
                    'INSERT INTO ddb_dream (dreamerId_FK, dreamDate, dreamTitle, dreamCharacters, dreamPlace, dreamText, dreamPointOfVue, dreamFunFacts, dreamFeelings, userId_FK)'
                    . ' VALUES (:dreamerId, :dreamDate, :dreamTitle, :dreamCharacters, :dreamPlace, :dreamText, :dreamPointOfVue, :dreamFunFacts, :dreamFeelings, :userId)');
                $qry->bindParam(':dreamerId', $values['dreamerId'], PDO::PARAM_INT);
                $qry->bindParam(':dreamDate', $values['date'], PDO::PARAM_STR);
                $qry->bindParam(':dreamTitle', $values['title'], PDO::PARAM_STR);
                $qry->bindParam(':dreamCharacters', $values['characters'], PDO::PARAM_STR);
                $qry->bindParam(':dreamPlace', $values['place'], PDO::PARAM_STR);
                $qry->bindParam(':dreamText', $values['text'], PDO::PARAM_STR);
                $qry->bindParam(':dreamPointOfVue', $values['pointOfVue'], PDO::PARAM_STR);
                $qry->bindParam(':dreamFunFacts', $values['funFacts'], PDO::PARAM_STR);
                $qry->bindParam(':dreamFeelings', $values['feelings'], PDO::PARAM_STR);
                $qry->bindParam(':userId', $user['id'], PDO::PARAM_INT);
                $qry->execute();
                
                $dreamId = $db->lastInsertId();
            }
            
            $tags = explode(',', $values['tagList']);
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
            exit;

        }

        //keep values and show errors
        $values['date'] = $_POST['date'];
        $tpl->assign( "errors", $errors );
        $tpl->assign( "values", $values );
    }
    
    //load the dream to edit
    if( $dreamEdition == true && !isset($values) ) {
        $values = array();
        $values['id'] = $_GET['id'];
        
        //get dream informations
        $qryDream = $db->prepare(
            "SELECT a.dreamerName, a.dreamerId, strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
            .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings"
            ." FROM ddb_dream d LEFT JOIN ddb_dreamer a on d.dreamerId_FK = a.dreamerId"
            ." WHERE dreamId = :dreamId");
        $qryDream->bindParam(':dreamId', $values['id'], PDO::PARAM_INT);
        $qryDream->execute();
        
        $qryDream->bindColumn('dreamerName', $values['dreamerName']);
        $qryDream->bindColumn('dreamerId', $values['dreamerId']);
        $qryDream->bindColumn('dreamDate', $values['date']);
        $qryDream->bindColumn('dreamTitle', $values['title']);
        $qryDream->bindColumn('dreamCharacters', $values['characters']);
        $qryDream->bindColumn('dreamPlace', $values['place']);
        $qryDream->bindColumn('dreamText', $values['text']);
        $qryDream->bindColumn('dreamPointOfVue', $values['pointOfVue']);
        $qryDream->bindColumn('dreamFunFacts', $values['funFacts']);
        $qryDream->bindColumn('dreamFeelings', $values['feelings']);
        
        //read the first line to feed the bind variables
        $row = $qryDream->fetch(PDO::FETCH_BOUND);
        
        $qry = $db->prepare(
            "SELECT t.tagName FROM ddb_dream_tag dt INNER JOIN ddb_tag t on dt.tagId_FK = t.tagId"
            ." WHERE dt.dreamId_FK = :dreamId ORDER BY t.tagName");
        $qry->bindParam(':dreamId', $values['id'], PDO::PARAM_INT);
        $qry->execute();
        
        $tagList = '';
        while ( $row = $qry->fetch(PDO::FETCH_ASSOC) ) {
            $tagList .= $row['tagName'].', ';
        }
        $values['tagList'] = $tagList;
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
        $keys = array_keys($levenshtein, min($levenshtein));
        $dreamers[$keys[0]]['selected'] = true;
        
    }
    
    //feed the tag list with existing tags
    $qry = $db->prepare(
        "SELECT tagName FROM ddb_tag ORDER BY tagName ASC");
    $qry->execute();
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $tags[] = $row['tagName'];
    }
    
    if(isset($values)) {
        $tpl->assign( 'dream', $values );
    }
    $tpl->assign( 'dreamers', $dreamers );
    $tpl->assign( 'js', true );
    if(empty($tags)) {
        $tagList = '';
        $tags = array();
    }

    $tpl->assign( 'editButtons', $dreamEdition );
    $tpl->assign( 'tagList', json_encode($tags) );
    $tpl->assign( 'today', date('d/m/Y') );
    $tpl->draw( 'form' );
}

?>