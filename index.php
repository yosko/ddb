<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
	DDb uses Rain TPL (under LGPL too)
*/

include_once "inc/functions.php";

$tpl = setRainTpl();

$db = openDatabase();

if(logUser($tpl)) {
    $qryLastDreams = $db->prepare(
        "SELECT dr.dreamerName, dr.dreamerId, d.dreamId"
        .", strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
        .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings"
        ." FROM ddb_dream d LEFT JOIN ddb_dreamer dr on d.dreamerId_FK = dr.dreamerId ORDER BY d.dreamId DESC LIMIT 10");
    $qryLastDreams->execute();
    $lastDreams = $qryLastDreams->fetchAll();
    //$lastDreams = array_reverse($qryLastDreams->fetchAll());
    
    $qryLastTags = $db->prepare(
        "SELECT t.tagId, t.tagName, count(dt.dreamId_FK) as nbUse FROM ddb_tag t LEFT JOIN ddb_dream_tag dt on dt.tagId_FK = t.tagId GROUP BY t.tagId, t.tagName ORDER BY tagId DESC LIMIT 10");
    $qryLastTags->execute();
    $lastTags = $qryLastTags->fetchAll();
    //$lastTags = array_reverse($qryLastTags->fetchAll());
    
    
    $qryDreamers = $db->prepare(
        "SELECT count(*) FROM ddb_dreamer");
    $qryDreamers->execute();
    $nbDreamers = $qryDreamers->fetchColumn();
    
    $qryDreams = $db->prepare(
        "SELECT count(*) FROM ddb_dream");
    $qryDreams->execute();
    $nbDreams = $qryDreams->fetchColumn();
    
    $qryTags = $db->prepare(
        "SELECT count(*) FROM ddb_tag");
    $qryTags->execute();
    $nbTags = $qryTags->fetchColumn();
    
    
    $qryDreamTags = $db->prepare(
        "SELECT count(*) FROM ddb_dream_tag");
    $qryDreamTags->execute();
    $nbDreamTags = $qryDreamTags->fetchColumn();
    
    
    $tpl->assign( "lastDreams", $lastDreams );
    $tpl->assign( "lastTags", $lastTags );
    $tpl->assign( "nbDreamers", $nbDreamers );
    $tpl->assign( "nbDreams", $nbDreams );
    $tpl->assign( "nbTags", $nbTags );
    $tpl->assign( "nbDreamTags", $nbDreamTags );
    $tpl->draw( "home" );
}

?>