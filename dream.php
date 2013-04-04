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
    
    //if form was posted
    if( isset($_GET["id"]) ) {
        $dream = array();
        $values = array();
        $errors = array();
        $dream['id'] = $_GET["id"];

        // post a new comment to this dream
        if( isset($_POST['submitNewComment']) ) {
            $values['text'] = htmlspecialchars(trim($_POST['text']));

            $errors['emptyComment'] = (!isset($_POST['text']) || empty($_POST['text']));

            if($errors['emptyComment'] === false) {
                $qry = $db->prepare(
                    'INSERT INTO ddb_comment (dreamId_FK, userId_FK, commentText)'
                    . ' VALUES (:dreamId, :userId, :commentText)');
                $qry->bindParam(':dreamId', $dream['id'], PDO::PARAM_INT);
                $qry->bindParam(':userId', $user['id'], PDO::PARAM_INT);
                $qry->bindParam(':commentText', $values['text'], PDO::PARAM_STR);
                $qry->execute();
            
                //avoid posting the comment again on "go back" in browser
                header("Location: $_SERVER[REQUEST_URI]");
                exit;
            }
        }

        $editButtons = false;
        if(isAuthor($user['id'], $dream['id']) || $user['role'] == 'admin') {
            //the user is allowed to edit/delete this dream
            $editButtons = true;
        }
        
        //get dream informations
        $status = DREAM_STATUS_PUBLISHED;
        $qryDream = $db->prepare(
            "SELECT a.dreamerName, a.dreamerId, strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
            .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings, u.userLogin, d.dreamStatus"
            ." FROM ddb_dream d INNER JOIN ddb_dreamer a on d.dreamerId_FK = a.dreamerId"
            ." INNER JOIN ddb_user u on u.userId = d.userId_FK"
            ." WHERE dreamId = :dreamId"
            ." AND (d.dreamStatus = :dreamStatus OR d.userId_FK = :userId)"
        );
        $qryDream->bindParam(':dreamId', $dream['id'], PDO::PARAM_INT);
        $qryDream->bindParam(':userId', $user['id'], PDO::PARAM_INT);
        $qryDream->bindParam(':dreamStatus', $status, PDO::PARAM_INT);
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
        $qryDream->bindColumn('userLogin', $dream['author']);
        $qryDream->bindColumn('dreamStatus', $dream['status']);
        
        //read the first line to feed the bind variables
        $row = $qryDream->fetch(PDO::FETCH_BOUND);
        
        //format text
        $dream['text'] = "<p>".str_replace("\n", "</p>\n\t\t\t<p>", $dream['text'])."</p>";
        
        //get dream tags
        $dreamId = intval($dream['id']);
        $qry = $db->prepare(
            "SELECT t.tagId, t.tagName, t.tagIcon FROM ddb_dream_tag dt INNER JOIN ddb_tag t on dt.tagId_FK = t.tagId"
            ." WHERE dt.dreamId_FK = :dreamId ORDER BY t.tagName");
        $qry->bindParam(':dreamId', $dreamId, PDO::PARAM_INT);
        $qry->execute();
        
        $tagArray = array();
        while ( $row = $qry->fetch(PDO::FETCH_ASSOC) ) {
            $tagArray[$row['tagName']] = array('id' => $row['tagId'], 'icon' => $row['tagIcon']);
        }

        //get dream comments
        $qry = $db->prepare(
            "SELECT u.userLogin, c.commentId, c.commentText, strftime('%d/%m/%Y, %H:%M', c.commentCreation) AS commentCreation, strftime('%d/%m/%Y, %H:%M', c.commentLastEdit) AS commentLastEdit"
            ." FROM ddb_comment c INNER JOIN ddb_user u on u.userId = c.userId_FK"
            ." WHERE c.dreamId_FK = :dreamId ORDER BY c.commentCreation");
        $qry->bindParam(':dreamId', $dream['id'], PDO::PARAM_INT);
        $qry->execute();
        $comments = $qry->fetchAll(PDO::FETCH_ASSOC);
        
        $tpl->assign( "editButtons", $editButtons );
        $tpl->assign( "dream", $dream );
        $tpl->assign( "comment", $values );
        $tpl->assign( "errors", $errors );
        $tpl->assign( "comments", $comments );
        $tpl->assign( "tagArray", $tagArray );
        $tpl->draw( "dream" );
    }
}

?>