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
$db = openDatabase();

//Create tables
$sql = <<<QUERY

CREATE TABLE IF NOT EXISTS ddb_comment (
    'commentId'         INTEGER NULL PRIMARY KEY AUTOINCREMENT,
    'dreamId_FK'        INT NOT NULL,
    'userId_FK'         INT NOT NULL,
    'commentText'       TEXT,
    'commentCreation'   DATETIME NOT NULL DEFAULT current_timestamp,
    'commentLastEdit'   DATETIME NOT NULL DEFAULT current_timestamp
);

ALTER TABLE ddb_dream ADD COLUMN dreamStatus INTEGER NOT NULL DEFAULT 1;

QUERY;


try {
    $db->beginTransaction();
    $db->exec($sql);
    $db->commit();
    header("Location: index.php");
} catch(PDOException $e) {
    $db->rollBack();
    echo $e->getMessage();
}

?>