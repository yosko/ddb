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
    $comment['id'] = $_GET["id"];

    $sql = 'SELECT c.commentId as id, c.userId_FK, c.commentText as text, c.dreamId_FK'
        .' FROM ddb_comment c'
        .' WHERE c.commentId = :id';
    $qryComment = $db->prepare( $sql );
    $qryComment->bindParam(':id', $comment['id'], PDO::PARAM_INT);
    $qryComment->execute();
    $comment = $qryComment->fetch(PDO::FETCH_ASSOC);

    //comment exists and user is authorized to edit it
    $comment['editAuthorized'] = $comment != false && ($comment['userId_FK'] == $user['id'] || $user['role'] == 'admin');

    //perform comment update
    if($comment['editAuthorized'] && isset($_POST['submitComment']) ) {
        $comment['text'] = htmlspecialchars(trim($_POST['text']));

        $errors['emptyComment'] = (!isset($_POST['text']) || empty($_POST['text']));

        if($errors['emptyComment'] === false) {
            $qry = $db->prepare(
                'UPDATE ddb_comment'
                .' SET commentText = :text, commentLastEdit = current_timestamp'
                .' WHERE commentId = :id'
            );
            $qry->bindParam(':text', $comment['text'], PDO::PARAM_STR);
            $qry->bindParam(':id', $comment['id'], PDO::PARAM_INT);
            $qry->execute();
        
            //avoid posting the comment again on "go back" in browser
            header('Location: dream.php?id='.$comment['dreamId_FK'].'#'.$comment['id']);
            exit;
        }
    }

    $tpl->assign( "comment", $comment );
    $tpl->draw( "formComment" );
}

?>