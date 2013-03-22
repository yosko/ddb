<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

initDDb($db, $settings, $tpl, $user);

if($user['isLoggedIn']) {
    
    //if cancel was clicked
    if( isset($_POST["cancel"]) ) {
        header("Location: dream.php?id=".$_GET["id"]);
    }
    
    //if delete button was clicked
    if( isset($_POST["delete"]) ) {
        $deleteAction = true;
    } else {
        $deleteAction = false;
    }
    
    if( isset($_GET["id"]) ) {
        $dream = array();
        $dream['id'] = $_GET["id"];
        
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