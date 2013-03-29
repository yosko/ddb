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
    $qryLastDreams = $db->prepare(
        "SELECT dr.dreamerName, dr.dreamerId, d.dreamId"
        .", strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
        .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings, count(c.commentId) as nbComments"
        ." FROM ddb_dream d LEFT JOIN ddb_dreamer dr on d.dreamerId_FK = dr.dreamerId"
        ." LEFT JOIN ddb_comment c on c.dreamId_FK = d.dreamId"
        ." GROUP BY d.dreamId, dr.dreamerId"
        ." ORDER BY d.dreamCreation DESC, d.dreamDate DESC LIMIT 10"
    );
    $qryLastDreams->execute();
    $lastDreams = $qryLastDreams->fetchAll();
    //$lastDreams = array_reverse($qryLastDreams->fetchAll());
    
    $qryLastTags = $db->prepare(
        "SELECT t.tagId, t.tagName, t.tagIcon, count(dt.dreamId_FK) as nbUse FROM ddb_tag t LEFT JOIN ddb_dream_tag dt on dt.tagId_FK = t.tagId GROUP BY t.tagId, t.tagName ORDER BY tagId DESC LIMIT 10");
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
    
    $qryComments = $db->prepare(
        "SELECT count(*) FROM ddb_comment");
    $qryComments->execute();
    $nbComments = $qryComments->fetchColumn();
    
    
    $tpl->assign( "lastDreams", $lastDreams );
    $tpl->assign( "lastTags", $lastTags );
    $tpl->assign( "nbDreamers", $nbDreamers );
    $tpl->assign( "nbDreams", $nbDreams );
    $tpl->assign( "nbTags", $nbTags );
    $tpl->assign( "nbDreamTags", $nbDreamTags );
    $tpl->assign( "nbComments", $nbComments );
    $tpl->draw( "home" );
}

?>