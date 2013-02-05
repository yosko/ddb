<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

$tpl = setRainTpl();

$db = openDatabase();

if(logUser($tpl)) {
    
    //edit DDb parameters
    if (isset($_POST["submitLogin"])) {
        $values = array();
        $errors = array();
        
        $values["login"] = trim($_POST['login']);
        $values["password"] = trim($_POST['password']);
        
        $errors["login"] = (!isset($_POST['login']) || trim($_POST['login']) == "");
        $errors["password"] = (!isset($_POST['password']) || trim($_POST['password']) == "");
        
        //if parameters are ok
        if(!$errors["login"] && !$errors["password"]) {
            //save them
            updateParams($_POST["login"], $_POST["password"]);
            
            //redirect to avoid problem on go back
            header("Location: $_SERVER[PHP_SELF]");
        } else {
            //keep values and show errors
            $tpl->assign( "errors", $errors );
            $tpl->assign( "values", $values );
        }
    }
    
    //import dreams from csv file
    if(isset($_POST['import'])) {
        $header = array();
        $dream = array();
        $dreamers = array();
        $tags = array();
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
    
    $tpl->draw( "config" );
}

?>