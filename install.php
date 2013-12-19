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

$tpl_cache = 'cache/tpl/';
if ( !is_writable(dirname(__FILE__)) ) {
	$tpl_cache = sys_get_temp_dir().'/DDb/';
}
$tpl = setRainTpl('tpl/', $tpl_cache);

$serverConfig['phpVersion'] = PHP_VERSION;
$serverConfig['phpMinVersion'] = '5.3.0';
$serverConfig['phpIsVersionValid'] = (version_compare(PHP_VERSION, $serverConfig['phpMinVersion']) >= 0);
$serverConfig['pdo'] = extension_loaded('pdo');
$serverConfig['pdoSqlite'] = extension_loaded('pdo_sqlite');
$serverConfig['rootDirectory'] = dirname($_SERVER['SCRIPT_FILENAME']);
$serverConfig['rootPermissions'] = is_writable($serverConfig['rootDirectory']);
$serverConfig['cacheTplDirectory'] = $serverConfig['rootDirectory'].'/cache/tpl/';
$serverConfig['cacheTplPermissions'] = is_writable($serverConfig['cacheTplDirectory']);
$serverConfig['cacheSessionDirectory'] = $serverConfig['rootDirectory'].'/cache/session/';
$serverConfig['cacheSessionPermissions'] = is_writable($serverConfig['cacheSessionDirectory']);

$serverOk = $serverConfig['phpIsVersionValid'] && $serverConfig['pdo']
    && $serverConfig['pdoSqlite'] && $serverConfig['rootPermissions']
    && $serverConfig['cacheTplPermissions'] && $serverConfig['cacheSessionPermissions'];

//STEP 3: install done
if(file_exists("database.sqlite")) {
    $step = 3;
    
//STEP 2.5: do install (uses same view as step 2)
} elseif($serverOk && isset($_POST['submitInstall'])) {
    $values = array();
    $errors = array();
    
    $values['login'] = htmlspecialchars(trim($_POST['login']));
    $values['password'] = htmlspecialchars(trim($_POST['password']));
    $values['firstDreamer'] = htmlspecialchars(trim($_POST['firstDreamer']));
    $values['hash'] = YosLoginTools::hashPassword($values['password']);
    
    $errors['login'] = (!isset($_POST['login']) || trim($_POST['login']) == "");
    $errors['password'] = (!isset($_POST['password']) || trim($_POST['password']) == "");
    $errors['firstDreamer'] = (!isset($_POST['firstDreamer']) || trim($_POST['firstDreamer']) == "");
    $errors['hash'] = (strlen($values["hash"]) < 60);

    
    if(!$errors["login"] && !$errors["password"] && !$errors["firstDreamer"] && !$errors["hash"]) {
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
DROP TABLE IF EXISTS ddb_user;
DROP TABLE IF EXISTS ddb_user_dreamer;
DROP TABLE IF EXISTS ddb_settings;

CREATE TABLE IF NOT EXISTS ddb_dreamer (
    'dreamerId'         INTEGER NULL PRIMARY KEY AUTOINCREMENT,
    'dreamerName'       VARCHAR(256) NOT NULL
);

CREATE TABLE IF NOT EXISTS ddb_tag (
    'tagId'             INTEGER PRIMARY KEY AUTOINCREMENT,
    'tagName'           VARCHAR(256) NOT NULL,
    'tagIcon'           VARCHAR(256)
);

CREATE TABLE IF NOT EXISTS ddb_dream (
    'dreamId'           INTEGER NULL PRIMARY KEY AUTOINCREMENT,
    'dreamerId_FK'      INT NOT NULL,
    'dreamDate'         DATETIME,
    'dreamTitle'        VARCHAR(256),
    'dreamCharacters'   TEXT,
    'dreamPlace'        TEXT,
    'dreamText'         TEXT,
    'dreamPointOfVue'   TEXT,
    'dreamFunFacts'     TEXT,
    'dreamFeelings'     TEXT,
    'userId_FK'         INT NOT NULL,
    'dreamCreation'     DATETIME NOT NULL DEFAULT current_timestamp,
    'dreamPublication'  DATETIME NOT NULL DEFAULT current_timestamp,
    'dreamStatus'       INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS ddb_dream_tag (
	'dreamId_FK'		INT NOT NULL,
	'tagId_FK'			INT NOT NULL
);

CREATE TABLE IF NOT EXISTS ddb_comment (
    'commentId'         INTEGER NULL PRIMARY KEY AUTOINCREMENT,
    'dreamId_FK'        INT NOT NULL,
    'userId_FK'         INT NOT NULL,
    'commentText'       TEXT,
    'commentCreation'   DATETIME NOT NULL DEFAULT current_timestamp,
    'commentLastEdit'   DATETIME NOT NULL DEFAULT current_timestamp
);

CREATE TABLE IF NOT EXISTS ddb_user (
    'userId'            INTEGER NULL PRIMARY KEY AUTOINCREMENT,
    'userLogin'         VARCHAR(256) NOT NULL,
    'userPassword'      VARCHAR(256) NOT NULL,
    'userRole'          VARCHAR(50) NOT NULL DEFAULT 'user'
);

CREATE TABLE IF NOT EXISTS ddb_user_dreamer (
    'userId_FK'         INT NOT NULL,
    'dreamerId_FK'      INT NOT NULL
);

CREATE TABLE IF NOT EXISTS ddb_settings (
    'LTDir'             TEXT NOT NULL DEFAULT 'cache/session/',
    'nbLTSession'       INT NOT NULL DEFAULT 200,
    'LTDuration'        INT NOT NULL DEFAULT 2592000,
    'tplDir'            TEXT NOT NULL DEFAULT 'tpl/',
    'tplCache'          TEXT NOT NULL DEFAULT 'cache/tpl/',
    'timezone'          VARCHAR(256) NOT NULL,
    'dusk'              INT NOT NULL DEFAULT 20,
    'dawn'              INT NOT NULL DEFAULT 7,
    'useNightSkin'      INT NOT NULL DEFAULT 0,
    'useTagIcon'        INT NOT NULL DEFAULT 1,
    'appKey'            VARCHAR(42) NOT NULL
);

INSERT INTO ddb_tag (tagName, tagIcon) VALUES ('adulte', 'notification-counter-18.png');
INSERT INTO ddb_tag (tagName, tagIcon) VALUES ('cauchemar', 'skull.png');
INSERT INTO ddb_tag (tagName, tagIcon) VALUES ('lucide', 'brain.png');
INSERT INTO ddb_tag (tagName, tagIcon) VALUES ('récurrent', 'arrow-circle-225.png');

QUERY;

        try {
        	$db->exec($sql);
        } catch(PDOException $e) {
        	echo $e->getMessage();
        }
        
        //insert first dreamer
        $qry = $db->prepare(
            'INSERT INTO ddb_dreamer (dreamerName) VALUES (:name)');
        $qry->bindParam(':name', $values["firstDreamer"], PDO::PARAM_STR);
        $qry->execute();
        
        //insert user
        $qry = $db->prepare(
            'INSERT INTO ddb_user (userLogin, userPassword, userRole) VALUES (:login, :password, "admin")');
        $qry->bindParam(':login', $values["login"], PDO::PARAM_STR);
        $qry->bindParam(':password', $values["hash"], PDO::PARAM_STR);
        $qry->execute();

        //insert settings
        $values['timezone'] = date_default_timezone_get();
        $values['appKey'] = YosLoginTools::generateRandomString(42);
        $qry = $db->prepare(
            'INSERT INTO ddb_settings (timezone, appKey) VALUES (:timezone, :appKey)');
        $qry->bindParam(':timezone', $values['timezone'], PDO::PARAM_STR);
        $qry->bindParam(':appKey', $values['appKey'], PDO::PARAM_STR);
        $qry->execute();
        
        //install done: redirect to avoid second execution
        header("Location: $_SERVER[PHP_SELF]");
    } else {
        //go back to step 2 with entered values
        $tpl->assign( "errors", $errors );
        $tpl->assign( "values", $values );
    }
}

//STEP 2: ask settings
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
