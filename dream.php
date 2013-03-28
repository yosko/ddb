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
        $dream['id'] = $_GET["id"];

        $editButtons = false;
        if(isAuthor($user['id'], $dream['id']) || $user['role'] == 'admin') {
            //the user is allowed to edit/delete this dream
            $editButtons = true;
        }
        
        //get dream informations
        $qryDream = $db->prepare(
            "SELECT a.dreamerName, a.dreamerId, strftime('%d/%m/%Y', d.dreamDate) AS dreamDate, d.dreamTitle, d.dreamCharacters, d.dreamPlace"
            .", d.dreamText, d.dreamPointOfVue, d.dreamFunFacts, d.dreamFeelings"
            ." FROM ddb_dream d INNER JOIN ddb_dreamer a on d.dreamerId_FK = a.dreamerId"
            ." WHERE dreamId = :dreamId");
        $qryDream->bindParam(':dreamId', $dream['id'], PDO::PARAM_INT);
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
        
        $tpl->assign( "editButtons", $editButtons );
        $tpl->assign( "dream", $dream );
        $tpl->assign( "tagArray", $tagArray );
        $tpl->draw( "dream" );
    }
}

?>