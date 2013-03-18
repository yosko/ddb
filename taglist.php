<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

$db = openDatabase();
$settings = getSettings();
$tpl = setRainTpl();
$user = logUser($tpl);

if($user['isLoggedIn']) {
    
    $qryTags = $db->prepare(
        "SELECT t.tagId, t.tagName, count(dt.dreamId_FK) as nbUse FROM ddb_tag t LEFT JOIN ddb_dream_tag dt on dt.tagId_FK = t.tagId GROUP BY t.tagId, t.tagName ORDER BY tagName ASC");
    $qryTags->execute();
    $tags = $qryTags->fetchAll();
    
    $tpl->assign( "tags", $tags );
    $tpl->draw( "taglist" );
}

?>