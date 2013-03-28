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
    
    //if delete button was clicked
    if( isset($_POST["delete"]) ) {
        $deleteAction = true;
    } else {
        $deleteAction = false;
    }
    
    if( isset($_GET["id"]) ) {
        $dream = array();
        $dream['id'] = $_GET["id"];

        //if cancel was clicked or if user isn't allowed to delete the dream
        if( isset($_POST["cancel"]) || (!isAuthor($user['id'], $dream['id']) && $user['role'] != 'admin') ) {
            header("Location: dream.php?id=".$_GET["id"]);
            exit;
        } else {
            $editButtons = true;
            $tpl->assign( "editButtons", $editButtons );
        }
        
        //get dream informations
        $qryDream = $db->prepare(
            "SELECT a.dreamerName, strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle"
            ." FROM ddb_dream d LEFT JOIN ddb_dreamer a on d.dreamerId_FK = a.dreamerId"
            ." WHERE dreamId = :dreamId");
        $qryDream->bindParam(':dreamId', $dream['id'], PDO::PARAM_INT);
        $qryDream->execute();
        
        $qryDream->bindColumn('dreamerName', $dream['dreamerName']);
        $qryDream->bindColumn('dreamDate', $dream['date']);
        $qryDream->bindColumn('dreamTitle', $dream['title']);
        
        //read the first line to feed the bind variables
        $exists = $qryDream->fetch(PDO::FETCH_BOUND);
        
        //delete the dream
        if($deleteAction && $exists) {
            //delete the tags attached to the dream
            $qry = $db->prepare(
                'DELETE FROM ddb_dream_tag WHERE dreamId_FK = :dreamId');
            $qry->bindParam(':dreamId', $_GET["id"], PDO::PARAM_INT);
            $qry->execute();
            
            //delete the dream itself
            $qry = $db->prepare(
                'DELETE FROM ddb_dream WHERE dreamId = :dreamId');
            $qry->bindParam(':dreamId', $_GET["id"], PDO::PARAM_INT);
            $qry->execute();
        }
        
        $tpl->assign( "deleteAction", $deleteAction );
        $tpl->assign( "dream", $dream );
        
    } else {
        $exists = false;
    }
    
    $tpl->assign( "exists", $exists );
    $tpl->draw( "delete" );
}

?>