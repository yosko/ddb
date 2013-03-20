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
    
    $qryDreamers = $db->prepare(
        "SELECT dr.dreamerId, dr.dreamerName, count(d.dreamId) as nbDream FROM ddb_dreamer dr LEFT JOIN ddb_dream d on d.dreamerId_FK = dr.dreamerId GROUP BY dr.dreamerId, dr.dreamerName ORDER BY dr.dreamerName ASC");
    $qryDreamers->execute();
    $dreamers = $qryDreamers->fetchAll();
    
    $tpl->assign( "dreamers", $dreamers );
    $tpl->draw( "dreamerlist" );
}

?>