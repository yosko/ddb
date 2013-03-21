<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

initDDb($db, $settings, $tpl, $user);

if($user['isLoggedIn']) {
    
    $qryTags = $db->prepare(
        "SELECT t.tagId, t.tagName, t.tagIcon, count(dt.dreamId_FK) as nbUse FROM ddb_tag t LEFT JOIN ddb_dream_tag dt on dt.tagId_FK = t.tagId GROUP BY t.tagId, t.tagName ORDER BY tagName ASC");
    $qryTags->execute();
    $tags = $qryTags->fetchAll(PDO::FETCH_ASSOC);
    
    $tpl->assign( "tags", $tags );
    $tpl->draw( "taglist" );
}

?>