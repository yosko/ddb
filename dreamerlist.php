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
    
    $qryDreamers = $db->prepare(
        "SELECT dr.dreamerId, dr.dreamerName, count(d.dreamId) as nbDream FROM ddb_dreamer dr LEFT JOIN ddb_dream d on d.dreamerId_FK = dr.dreamerId GROUP BY dr.dreamerId, dr.dreamerName ORDER BY nbDream DESC");
    $qryDreamers->execute();
    $dreamers = $qryDreamers->fetchAll(PDO::FETCH_ASSOC);

    //get the 5 most used tags for each dreamer
    foreach($dreamers as $key => $dreamer) {
        $sql = "SELECT dr.dreamerId, t.tagId, t.tagName, count(d.dreamId) as nbDream"
            ." FROM ddb_dreamer dr"
            ." INNER JOIN ddb_dream d on d.dreamerId_FK = dr.dreamerId"
            ." INNER JOIN ddb_dream_tag dt on dt.dreamId_FK = d.dreamId"
            ." INNER JOIN ddb_tag t on t.tagId = dt.tagId_FK"
            ." WHERE dr.dreamerId = :dreamerId"
            ." GROUP BY dr.dreamerId, t.tagId"
            ." ORDER BY nbDream DESC"
            ." LIMIT 5";
        $qryDreamerTags = $db->prepare( $sql );
        $qryDreamerTags->bindParam(':dreamerId', $dreamer['dreamerId'], PDO::PARAM_INT);
        $qryDreamerTags->execute();
        $dreamerTags = $qryDreamerTags->fetchAll(PDO::FETCH_ASSOC);
        $dreamers[$key]["tags"] = $dreamerTags;
    }
    
    $tpl->assign( "dreamers", $dreamers );
    $tpl->draw( "dreamerlist" );
}

?>