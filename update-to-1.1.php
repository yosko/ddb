<?php


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