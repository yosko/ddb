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
    
    $qryTags = $db->prepare(
        "SELECT t.tagId, t.tagName, t.tagIcon, count(dt.dreamId_FK) as nbUse FROM ddb_tag t LEFT JOIN ddb_dream_tag dt on dt.tagId_FK = t.tagId GROUP BY t.tagId, t.tagName ORDER BY tagName ASC");
    $qryTags->execute();
    $tags = $qryTags->fetchAll(PDO::FETCH_ASSOC);


    $qryCountTags = $db->prepare(
        "SELECT count(*) FROM ddb_tag");
    $qryCountTags->execute();
    $nbTag = $qryCountTags->fetchColumn();
    
    $qryCountTagUse = $db->prepare(
        "SELECT count(*) FROM ddb_dream_tag");
    $qryCountTagUse->execute();
    $nbTagUse = $qryCountTagUse->fetchColumn();

    $average = $nbTagUse / $nbTag;

    foreach($tags as $key => $value) {
        if($value['nbUse'] > 8*$average ) {
            $tags[$key]['weight'] = 5;
        } elseif($value['nbUse'] > 4*$average ) {
            $tags[$key]['weight'] = 4;
        } elseif($value['nbUse'] > 2*$average ) {
            $tags[$key]['weight'] = 3;
        } elseif($value['nbUse'] > $average ) {
            $tags[$key]['weight'] = 2;
        } else {
            $tags[$key]['weight'] = 1;
        }
    }
    
    $tpl->assign( "average", $average );
    $tpl->assign( "tags", $tags );
    $tpl->draw( "taglist" );
}

?>