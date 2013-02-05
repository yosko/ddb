<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

error_reporting(E_ALL);
ini_set('display_errors', 'On');

include_once "inc/functions.php";

$tpl = setRainTpl();

$serverConfig['phpVersion'] = PHP_VERSION;
$serverConfig['phpMinVersion'] = '5.2.0';
$serverConfig['phpIsVersionValid'] = (version_compare(PHP_VERSION, $serverConfig['phpMinVersion']) >= 0);
$serverConfig['pdo'] = extension_loaded('pdo');
$serverConfig['pdoSqlite'] = extension_loaded('pdo_sqlite');
$serverConfig['dbDirectory'] = dirname($_SERVER['SCRIPT_FILENAME']);
$serverConfig['dbPermissions'] = is_writable($serverConfig['dbDirectory']);
$serverConfig['paramFile'] = dirname($_SERVER['SCRIPT_FILENAME'])."/inc/param.php";
$serverConfig['paramPermissions'] = is_writable($serverConfig['paramFile']);

$serverOk = $serverConfig['phpIsVersionValid'] && $serverConfig['pdo'] &&
    $serverConfig['pdoSqlite'] && $serverConfig['dbPermissions'] && $serverConfig['paramPermissions'];

//STEP 3: install done
if(file_exists("database.sqlite")) {
    $step = 3;
    
//STEP 2.5: do install (uses same view as step 2)
} elseif($serverOk && isset($_POST['submitInstall'])) {
    $values = array();
    $errors = array();
    
    $values["login"] = trim($_POST['login']);
    $values["password"] = trim($_POST['password']);
    $values["firstDreamer"] = trim($_POST['firstDreamer']);
    
    $errors["login"] = (!isset($_POST['login']) || trim($_POST['login']) == "");
    $errors["password"] = (!isset($_POST['password']) || trim($_POST['password']) == "");
    $errors["firstDreamer"] = (!isset($_POST['firstDreamer']) || trim($_POST['firstDreamer']) == "");
    
    if(!$errors["login"] && !$errors["password"] && !$errors["firstDreamer"]) {
        //save login/password
        updateParams($values["login"], $values["password"]);
        
        //Create and open database
        try {
            $db = new PDO('sqlite:database.sqlite');
        	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
        	echo $e->getMessage();
        }
        
        //Create tables
        $sql = <<<QUERY

DROP TABLE IF EXISTS ddb_dreamer;
DROP TABLE IF EXISTS ddb_tag;
DROP TABLE IF EXISTS ddb_dream;
DROP TABLE IF EXISTS ddb_dream_tag;

CREATE TABLE IF NOT EXISTS ddb_dreamer (
    'dreamerId'			INTEGER NULL PRIMARY KEY AUTOINCREMENT,
	'dreamerName'		VARCHAR(256) NOT NULL
);

CREATE TABLE IF NOT EXISTS ddb_tag (
	'tagId'				INTEGER PRIMARY KEY AUTOINCREMENT,
	'tagName'			VARCHAR(256) NOT NULL
);

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

CREATE TABLE IF NOT EXISTS ddb_dream_tag (
	'dreamId_FK'		INT NOT NULL,
	'tagId_FK'			INT NOT NULL
);

QUERY;

        try {
        	$db->exec($sql);
        } catch(PDOException $e) {
        	echo $e->getMessage();
        }
        
        //insert first dreamer
        $qry = $db->prepare(
            'INSERT INTO ddb_dreamer (dreamerName) VALUES (:name)');
        $qry->bindParam(':name', trim($values["firstDreamer"]), PDO::PARAM_STR);
        $qry->execute();
        
        //install done: redirect to avoid second execution
        header("Location: $_SERVER[PHP_SELF]");
    } else {
        //go back to step 2 with entered values
        $tpl->assign( "errors", $errors );
        $tpl->assign( "values", $values );
    }
}

//STEP 2: ask parameters
if(!file_exists("database.sqlite") && isset($_GET['step']) && intval($_GET['step']) == 2) {
    $step = 2;
    
//STEP 1: check server configuration
} elseif(!file_exists("database.sqlite") && ((!isset($_GET['step']) || intval($_GET['step']) == 1))) {
    $step = 1;
    
    $tpl->assign( "serverConfig", $serverConfig );
    
}

$tpl->assign( "noLogout", true );   //no DDb command button
$tpl->assign( "step", $step );
$tpl->draw( "install" );

?>