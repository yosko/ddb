<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

$db = openDatabase();
$settings = getSettings();
$tpl = setRainTpl();

if(logUser($tpl)) {
    //feed the dreamer list with existing dreamers
    $dreamers = array();
    $qry = $db->prepare(
        "SELECT * FROM ddb_dreamer ORDER BY dreamerName ASC");
    $qry->execute();
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $dreamers[] = $row;
    }
    
    //feed the tag list with existing tags
    $tags = array();
    $qry = $db->prepare(
        "SELECT * FROM ddb_tag ORDER BY tagName ASC");
    $qry->execute();
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $tags[] = $row;
    }
    
    $tpl->assign( "dreamers", $dreamers );
    $tpl->assign( "tags", $tags );
    $tpl->draw( "search" );
}

?>