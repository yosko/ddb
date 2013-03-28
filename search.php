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