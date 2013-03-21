<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

initDDb($db, $settings, $tpl, $user);

if($user['isLoggedIn']) {
    $dreamers = array();
    $tags = array();
    $unusedDreamers = array();
    $unusedTags = array();

    //reenter password to access the configuration
    if(isset($_POST['submitSecureAccess'])) {
        $errors = array();
        if(isset($user['error'])) {
            $errors = $user['error'];
        }
        $tpl->assign( "errors", $errors );
    }

    $page = 'homeConfig';
    if(isset($_GET['p'])) {
        if(in_array($_GET['p'], array('password', 'import', 'purge', 'renameDreamer', 'renameTag', 'settings'))) {
            $page = $_GET['p'];
        }
    }
    $tpl->assign( "page", $page );

    //display settings
    if($page = 'settings') {
        $groupedTimezones = array();
        $timezones = DateTimeZone::listIdentifiers();
        foreach($timezones as $timezone) {
            $timezone = explode('/', $timezone, 2);
            if($timezone[0]=='UTC') {
                $groupedTimezones[$timezone[0]] = $timezone[0];
            } else {
                $groupedTimezones[$timezone[0]][] = $timezone[1];
            }
        }
        $time = date('H:m') ;
        $tpl->assign( "timezones", $groupedTimezones );
        $tpl->assign( "time", $time );
        $tpl->assign( "currentTimezone", date_default_timezone_get() );
    }

    //save settings
    if (isset($_POST["submitSettings"])) {
        $sql = 'UPDATE ddb_settings';

        $values['useNightSkin'] = isset($_POST['useNightSkin']);
        $values['timezone'] = trim($_POST['timezone']);
        $values['dusk'] = trim($_POST['dusk']);
        $values['dawn'] = trim($_POST['dawn']);
        $values['useTagIcon'] = isset($_POST['useTagIcon']);

        $set['timezone'] = (in_array($values['timezone'], DateTimeZone::listIdentifiers()));
        $set['dusk+dawn'] = (is_numeric($values['dusk']) && is_numeric($values['dawn'])
                                && (int)$values['dusk'] >=0 && (int)$values['dusk'] <24
                                && (int)$values['dawn'] >=0 && (int)$values['dawn'] <24);

        $sql .= ' SET useNightSkin=:useNightSkin';
        if($set['timezone'])   $sql .= ', timezone=:timezone';
        if($set['dusk+dawn'])  $sql .= ', dusk=:dusk';
        if($set['dusk+dawn'])  $sql .= ', dawn=:dawn';
        $sql .= ', useTagIcon=:useTagIcon';

        $updateSettings = $db->prepare( $sql );
        $updateSettings->bindParam(':useNightSkin', $values['useNightSkin'], PDO::PARAM_INT);
        if($set['timezone'])   $updateSettings->bindParam(':timezone', $values['timezone'], PDO::PARAM_STR);
        if($set['dusk+dawn'])  $updateSettings->bindParam(':dusk', $values['dusk'], PDO::PARAM_INT);
        if($set['dusk+dawn'])  $updateSettings->bindParam(':dawn', $values['dawn'], PDO::PARAM_INT);
        $updateSettings->bindParam(':useTagIcon', $values['useTagIcon'], PDO::PARAM_INT);
        $updateSettings->execute();

        //to make sure the settings are taken into account
        header("Location: $_SERVER[REQUEST_URI]");
    }
    
    //edit DDb parameters
    if (isset($_POST["submitNewPassword"])) {
        $values = array();
        $errors = array();

        $values['password'] = trim($_POST['password']);

        $errors['password'] = (!isset($_POST['password']) || trim($_POST['password']) == "");

        //if login informations are ok
        if(!$errors['password']) {
            //save them
            $hash = YosLoginTools::hashPassword($values['password']);

            if($hash !== false) {
                $updateUser = $db->prepare(
                    'UPDATE ddb_user SET userPassword=:hash'
                    .' WHERE userLogin=:login'
                );
                $updateUser->bindParam(':hash', $hash, PDO::PARAM_STR);
                $updateUser->bindParam(':login', $user['login'], PDO::PARAM_STR);
                $updateUser->execute();
                
                //logout user to update session context
                header("Location: index.php?logout");
            } else {
                $errors['app'] = true;
            }
        }

        if(!empty($errors)) {
            //keep values and show errors
            $tpl->assign( "errors", $errors );
            $tpl->assign( "values", $values );
        }
    }
    
    //import dreams from csv file
    if(isset($_POST['import'])) {
        $header = array();
        $dream = array();
        $dreamerName = "";
        $tagName = "";
        $dreamerId = -1;
        $dreamId = -1;
        $tagId = -1;
        
        $deleteDreamTag = $db->prepare("DELETE FROM ddb_dream_tag");
        $deleteTag = $db->prepare("DELETE FROM ddb_tag");
        $deleteDream = $db->prepare("DELETE FROM ddb_dream");
        $deleteDreamer = $db->prepare("DELETE FROM ddb_dreamer");
        
        $qryDreamer = $db->prepare("SELECT dreamerId FROM ddb_dreamer WHERE dreamerName = :dreamerName LIMIT 1");
        $qryDreamer->bindParam(':dreamerName', $dreamerName, PDO::PARAM_STR);
        
        $insertDreamer = $db->prepare("INSERT INTO ddb_dreamer (dreamerName) VALUES (:dreamerName)");
        $insertDreamer->bindParam(':dreamerName', $dreamerName, PDO::PARAM_STR);
        
        $qryTag = $db->prepare("SELECT tagId FROM ddb_tag WHERE tagName = :tagName LIMIT 1");
        $qryTag->bindParam(':tagName', $tagName, PDO::PARAM_STR);
        
        $insertTag = $db->prepare("INSERT INTO ddb_tag (tagName) VALUES (:tagName)");
        $insertTag->bindParam(':tagName', $tagName, PDO::PARAM_STR);
        
        $insertDream = $db->prepare(
            'INSERT INTO ddb_dream (dreamerId_FK, dreamDate, dreamTitle, dreamCharacters, dreamPlace, dreamText, dreamPointOfVue, dreamFunFacts, dreamFeelings)'
            . ' VALUES (:dreamerId, :dreamDate, :dreamTitle, :dreamCharacters, :dreamPlace, :dreamText, :dreamPointOfVue, :dreamFunFacts, :dreamFeelings)');
        $insertDream->bindParam(':dreamerId', $dreamerId, PDO::PARAM_INT);
        $insertDream->bindParam(':dreamDate', $dreamDate, PDO::PARAM_STR);
        $insertDream->bindParam(':dreamTitle', $dreamTitle, PDO::PARAM_STR);
        $insertDream->bindParam(':dreamCharacters', $dreamCharacters, PDO::PARAM_STR);
        $insertDream->bindParam(':dreamPlace', $dreamPlace, PDO::PARAM_STR);
        $insertDream->bindParam(':dreamText', $dreamText, PDO::PARAM_STR);
        $insertDream->bindParam(':dreamPointOfVue', $dreamPointOfVue, PDO::PARAM_STR);
        $insertDream->bindParam(':dreamFunFacts', $dreamFunFacts, PDO::PARAM_STR);
        $insertDream->bindParam(':dreamFeelings', $dreamFeelings, PDO::PARAM_STR);
        
        $insertDreamTag = $db->prepare(
            'INSERT INTO ddb_dream_tag (dreamId_FK, tagId_FK) VALUES (:dreamId, :tagId)');
        $insertDreamTag->bindParam(':dreamId', $dreamId, PDO::PARAM_INT);
        $insertDreamTag->bindParam(':tagId', $tagId, PDO::PARAM_INT);
        
        $db->beginTransaction();
        if(isset($_POST['replace']) && $_POST['replace'] == "replace") {
            $deleteDreamTag->execute();
            $deleteTag->execute();
            $deleteDream->execute();
            $deleteDreamer->execute();
        }
        
        $fhandle = fopen($_FILES['csvFile']['tmp_name'],'r');
        //while($raw_row = fgets($fhandle)) {
            //$row = csvstring_to_array($raw_row, ',', '"', "\n");
        while(($row = fgetcsv($fhandle)) !== FALSE) {
            if(empty($header)) {
                //headers
                $i = 0;
                foreach ($row as $value) {
                    $header[$value] = $i;
                    $i++;
                }
            } else {
                //remove escape character
                foreach ($row as $key => $value) {
                    $row[$key] = str_replace( '\"', '"', $row[$key] );
                }
                
                //dreamer
                $dreamerName = $row[$header['dreamerName']];
                if (array_key_exists($dreamerName, $dreamers)) {
                    $dreamerId = $dreamers[$dreamerName];
                } else {
                    //create dreamer if not exists
                    $qryDreamer->execute();
                    if(!($dreamer = $qryDreamer->fetch(PDO::FETCH_ASSOC))) {
                        $insertDreamer->execute();
                        $qryDreamer->execute();
                        $dreamer = $qryDreamer->fetch(PDO::FETCH_ASSOC);
                    }
                    $dreamerId = $dreamer['dreamerId'];
                    $dreamers[$dreamerName] = $dreamerId;
                }
                
                //tags
                $dreamTags = explode("|", $row[$header['tags']]);
                $dreamTagsId = array();
                foreach ($dreamTags as $tagName) {
                    if($tagName != "") {
                        $tagName = strtolower ( $tagName );
                        if (array_key_exists($tagName, $tags)) {
                            $tagId = $tags[$tagName];
                        } else {
                            //create tag if not exists
                            $qryTag->execute();
                            if(!($tag = $qryTag->fetch(PDO::FETCH_ASSOC))) {
                                $insertTag->execute();
                                $qryTag->execute();
                                $tag = $qryTag->fetch(PDO::FETCH_ASSOC);
                            }
                            $tagId = $tag['tagId'];
                            $tags[$tagName] = $tagId;
                        }
                        
                        //keep the tag ids of the current dream for future insert in ddb_dream_tag
                        $dreamTagsId[] = $tagId;
                    }
                }
                
                //dream
                if(isset($row[$header['dreamDate']])) {
                    $dateArray = explode("/",$row[$header['dreamDate']]);
                    if(count($dateArray)==1) {
                        $dreamDate = "$dateArray[0]-01-01";
                    } else {
                        $dreamDate = "$dateArray[2]-$dateArray[1]-$dateArray[0]";
                    }
                } else {
                    $dreamDate = "";
                }
                $dreamTitle = isset($row[$header['dreamTitle']])?$row[$header['dreamTitle']]:"";
                $dreamCharacters = isset($row[$header['dreamCharacters']])?$row[$header['dreamCharacters']]:"";
                $dreamPlace = isset($row[$header['dreamPlace']])?$row[$header['dreamPlace']]:"";
                $dreamText = isset($row[$header['dreamText']])?$row[$header['dreamText']]:"";
                $dreamPointOfVue = isset($row[$header['dreamPointOfVue']])?$row[$header['dreamPointOfVue']]:"";
                $dreamFunFacts = isset($row[$header['dreamFunFacts']])?$row[$header['dreamFunFacts']]:"";
                $dreamFeelings = isset($row[$header['dreamFeelings']])?$row[$header['dreamFeelings']]:"";
                
                $insertDream->execute();
                $dreamId = $db->lastInsertId();
                
                //dream tags
                foreach ($dreamTagsId as $tagId) {
                    $insertDreamTag->execute();
                }
            }
        }
        $db->commit();
    }
    
    //purge unused items from DDb
    if (isset($_POST["submitPurge"])) {
        //purge unused dreamers
        if (isset($_POST["purgeDreamers"]) && $_POST["purgeDreamers"] == "purgeDreamers") {
            $qry = $db->prepare(
                "DELETE FROM ddb_dreamer WHERE dreamerId not in (SELECT DISTINCT dreamerId_FK FROM ddb_dream)");
            $qry->execute();
        }
        //purge unused tags
        if (isset($_POST["purgeTags"]) && $_POST["purgeTags"] == "purgeTags") {
            $qry = $db->prepare(
                "DELETE FROM ddb_tag WHERE tagId not in (SELECT DISTINCT tagId_FK FROM ddb_dream_tag)");
            $qry->execute();
        }
    }
    
    //rename a dreamer
    if (isset($_POST["submitRenameDreamer"])) {
        if(isset($_POST['dreamer']) && !empty($_POST['dreamer'])
                && isset($_POST['newDreamerName']) && trim($_POST['newDreamerName']) != "") {
            
            $qry = $db->prepare(
                'UPDATE ddb_dreamer SET dreamerName = :dreamerName'
                . ' WHERE dreamerId = :dreamerId');
            $qry->bindParam(':dreamerId', $_POST['dreamer'], PDO::PARAM_INT);
            $qry->bindParam(':dreamerName', $_POST['newDreamerName'], PDO::PARAM_STR);
            $qry->execute();
        }
    }
    
    //rename a tag
    if (isset($_POST["submitRenameTag"])) {
        if(isset($_POST['tag']) && !empty($_POST['tag'])
                && isset($_POST['newTagName']) && trim($_POST['newTagName']) != "") {
            
            $qry = $db->prepare(
                'UPDATE ddb_tag SET tagName = :tagName'
                . ' WHERE tagId = :tagId');
            $qry->bindParam(':tagId', $_POST['tag'], PDO::PARAM_INT);
            $qry->bindParam(':tagName', $_POST['newTagName'], PDO::PARAM_STR);
            $qry->execute();
        }
    }
    
    //dreamers
    $qry = $db->prepare(
        "SELECT * FROM ddb_dreamer ORDER BY dreamerName ASC");
    $qry->execute();
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $dreamers[] = $row;
    }
    
    //tags
    $qry = $db->prepare(
        "SELECT * FROM ddb_tag ORDER BY tagName ASC");
    $qry->execute();
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $tags[] = $row;
    }
    
    //unused dreamers
    $qry = $db->prepare(
        "SELECT * FROM ddb_dreamer WHERE dreamerId not in (SELECT DISTINCT dreamerId_FK FROM ddb_dream) ORDER BY dreamerName ASC");
    $qry->execute();
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $unusedDreamers[] = $row;
    }
    
    //unused tags
    $qry = $db->prepare(
        "SELECT * FROM ddb_tag WHERE tagId not in (SELECT DISTINCT tagId_FK FROM ddb_dream_tag) ORDER BY tagName ASC");
    $qry->execute();
    while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
        $unusedTags[] = $row;
    }
    
    $tpl->assign( "dreamers", $dreamers );
    $tpl->assign( "tags", $tags );
    $tpl->assign( "unusedDreamers", $unusedDreamers );
    $tpl->assign( "unusedTags", $unusedTags );
    $tpl->draw( "config" );
}

?>