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

    /* DREAMERS */
    
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

    /* USERS */
    
    $qryUsers = $db->prepare(
        "SELECT u.userId as id, u.userLogin, d1.nbDream, c1.nbComment"
        ." FROM ddb_user u"
        ." INNER JOIN (SELECT d.userId_FK, count(d.dreamId) as nbDream FROM ddb_dream d GROUP BY d.userId_FK) d1 ON d1.userId_FK = u.userId"
        ." INNER JOIN (SELECT c.userId_FK, count(c.commentId) as nbComment FROM ddb_comment c GROUP BY c.userId_FK) c1 ON c1.userId_FK = u.userId"
        ." ORDER BY d1.nbDream DESC"
    );
    $qryUsers->execute();
    $users = $qryUsers->fetchAll(PDO::FETCH_ASSOC);

    foreach($users as $key => $user) {
        //get nb dreams per dreamer of this user
        $sql = "SELECT dr.dreamerId, dr.dreamerName, count(d.dreamId) as nbDream"
            ." FROM ddb_dream d"
            ." INNER JOIN ddb_dreamer dr ON dr.dreamerId = d.dreamerId_FK"
            ." INNER JOIN ddb_user u ON u.userId = d.userId_FK"
            ." WHERE u.userId = :userId"
            ." GROUP BY u.userId, dr.dreamerId"
            ." ORDER BY nbDream DESC";
        $qryByDreamer = $db->prepare( $sql );
        $qryByDreamer->bindParam(':userId', $user['id'], PDO::PARAM_INT);
        $qryByDreamer->execute();
        $byDreamer = $qryByDreamer->fetchAll(PDO::FETCH_ASSOC);
        $users[$key]["byDreamer"] = $byDreamer;
    }
    
    $tpl->assign( "dreamers", $dreamers );
    $tpl->assign( "users", $users );
    $tpl->draw( "stats" );
}

?>