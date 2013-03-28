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

ALTER TABLE ddb_dream RENAME TO ddb_dream_old;

CREATE TABLE IF NOT EXISTS ddb_dream (
    'dreamId'			INTEGER NULL PRIMARY KEY AUTOINCREMENT,
	'dreamerId_FK'		INT NOT NULL,
	'dreamDate'			DATETIME,
	'dreamTitle'    	VARCHAR(256),
	'dreamCharacters'	TEXT,
	'dreamPlace'    	TEXT,
	'dreamText'			TEXT,
	'dreamPointOfVue'	TEXT,
    'dreamFunFacts'		TEXT,
    'dreamFeelings'		TEXT,
    'dreamCreation'     DATETIME NOT NULL DEFAULT current_timestamp
);

INSERT INTO ddb_dream (dreamId, dreamerId_FK, dreamDate, dreamTitle, dreamCharacters, dreamPlace, dreamText, dreamPointOfVue, dreamFunFacts, dreamFeelings)
SELECT dreamId, dreamerId_FK, dreamDate, dreamTitle, dreamCharacters, dreamPlace, dreamText, dreamPointOfVue, dreamFunFacts, dreamFeelings FROM ddb_dream_old;

DROP TABLE ddb_dream_old;

QUERY;


try {
    $db->beginTransaction();
	$db->exec($sql);
    $db->commit();
} catch(PDOException $e) {
    $db->rollBack();
	echo $e->getMessage();
}

?>