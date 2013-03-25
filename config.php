<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

include_once "inc/functions.php";

initDDb($db, $settings, $tpl, $user);

if($user['isLoggedIn']) {

    //reenter password to access the configuration
    if(isset($_POST['submitSecureAccess'])) {
        $errors = array();
        if(isset($user['error'])) {
            $errors = $user['error'];
        }
        $tpl->assign( "errors", $errors );
    }

    //no access beyond this point if the user wasn't granted a secure access
    if($user['secure'] === true) {
        $page = 'homeConfig';
        if(isset($_GET['p'])) {
            if(
                $user['role'] == 'admin' && in_array($_GET['p'], array('import', 'purge', 'renameDreamer', 'renameTag', 'settings', 'users'))
                || in_array($_GET['p'], array('password'))
            ) {
                $page = $_GET['p'];
            }
        }

        if($page == 'password') {

            if (isset($_POST["submitNewPassword"])) {
                $values = array();
                $errors = array();

                $values['password'] = htmlspecialchars(trim($_POST['password']));

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

                //keep values and show errors
                $tpl->assign( "errors", $errors );
                $tpl->assign( "values", $values );
            }

        } elseif($page == 'import') {
            $dreamers = array();
            $tags = array();

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
                    'INSERT INTO ddb_dream (dreamerId_FK, dreamDate, dreamTitle, dreamCharacters, dreamPlace, dreamText, dreamPointOfVue, dreamFunFacts, dreamFeelings, userId_FK)'
                    . ' VALUES (:dreamerId, :dreamDate, :dreamTitle, :dreamCharacters, :dreamPlace, :dreamText, :dreamPointOfVue, :dreamFunFacts, :dreamFeelings, :userId)');
                $insertDream->bindParam(':dreamerId', $dreamerId, PDO::PARAM_INT);
                $insertDream->bindParam(':dreamDate', $dreamDate, PDO::PARAM_STR);
                $insertDream->bindParam(':dreamTitle', $dreamTitle, PDO::PARAM_STR);
                $insertDream->bindParam(':dreamCharacters', $dreamCharacters, PDO::PARAM_STR);
                $insertDream->bindParam(':dreamPlace', $dreamPlace, PDO::PARAM_STR);
                $insertDream->bindParam(':dreamText', $dreamText, PDO::PARAM_STR);
                $insertDream->bindParam(':dreamPointOfVue', $dreamPointOfVue, PDO::PARAM_STR);
                $insertDream->bindParam(':dreamFunFacts', $dreamFunFacts, PDO::PARAM_STR);
                $insertDream->bindParam(':dreamFeelings', $dreamFeelings, PDO::PARAM_STR);
                $insertDream->bindParam(':userId', $user['id'], PDO::PARAM_INT);
                
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
                            $row[$key] = htmlspecialchars(trim(str_replace( '\"', '"', $row[$key] )));
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

        } elseif($page == 'purge') {

            //unused dreamers
            $unusedDreamers = array();
            $qry = $db->prepare(
                "SELECT * FROM ddb_dreamer WHERE dreamerId not in (SELECT DISTINCT dreamerId_FK FROM ddb_dream) ORDER BY dreamerName ASC");
            $qry->execute();
            while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
                $unusedDreamers[] = $row;
            }
            $tpl->assign( "unusedDreamers", $unusedDreamers );

            //unused tags
            $unusedTags = array();
            $qry = $db->prepare(
                "SELECT * FROM ddb_tag WHERE tagId not in (SELECT DISTINCT tagId_FK FROM ddb_dream_tag) ORDER BY tagName ASC");
            $qry->execute();
            while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
                $unusedTags[] = $row;
            }
            $tpl->assign( "unusedTags", $unusedTags );

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

        } elseif($page == 'renameDreamer') {

            //dreamers
            $dreamers = array();
            $qry = $db->prepare(
                "SELECT * FROM ddb_dreamer ORDER BY dreamerName ASC");
            $qry->execute();
            while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
                $dreamers[] = $row;
            }
            $tpl->assign( "dreamers", $dreamers );

            //rename a dreamer
            if (isset($_POST["submitRenameDreamer"])) {
                if(isset($_POST['dreamer']) && !empty($_POST['dreamer'])
                        && isset($_POST['newDreamerName']) && trim($_POST['newDreamerName']) != "") {
                    
                    $values['dreamer'] = $_POST['dreamer'];
                    $values['newDreamerName'] = htmlspecialchars(trim($_POST['newDreamerName']));

                    $qry = $db->prepare(
                        'UPDATE ddb_dreamer SET dreamerName = :dreamerName'
                        . ' WHERE dreamerId = :dreamerId');
                    $qry->bindParam(':dreamerId', $values['dreamer'], PDO::PARAM_INT);
                    $qry->bindParam(':dreamerName', $values['newDreamerName'], PDO::PARAM_STR);
                    $qry->execute();
                }
            }

        } elseif($page == 'renameTag') {

            //tags
            $tags = array();
            $qry = $db->prepare(
                "SELECT * FROM ddb_tag ORDER BY tagName ASC");
            $qry->execute();
            while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
                $tags[] = $row;
            }
            $tpl->assign( "tags", $tags );

            //rename a tag
            if (isset($_POST["submitRenameTag"])) {
                if(isset($_POST['tag']) && !empty($_POST['tag'])
                        && isset($_POST['newTagName']) && trim($_POST['newTagName']) != "") {
                    
                    $values['tag'] = $_POST['tag'];
                    $values['newTagName'] = htmlspecialchars(trim($_POST['newTagName']));
                    
                    $qry = $db->prepare(
                        'UPDATE ddb_tag SET tagName = :tagName'
                        . ' WHERE tagId = :tagId');
                    $qry->bindParam(':tagId', $values['tag'], PDO::PARAM_INT);
                    $qry->bindParam(':tagName', $values['newTagName'], PDO::PARAM_STR);
                    $qry->execute();
                }
            }

        //display settings
        } elseif($page == 'settings') {
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

            //save settings
            if (isset($_POST["submitSettings"])) {
                $sql = 'UPDATE ddb_settings';

                $values['useNightSkin'] = isset($_POST['useNightSkin']);
                $values['timezone'] = htmlspecialchars(trim($_POST['timezone']));
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

        //display user settings
        } elseif($page == 'users') {

            //create new user
            if(isset($_POST['submitNewUser'])) {
                $values = array();
                $errors = array();

                $values['login'] = htmlspecialchars(trim($_POST['login']));
                $values['password'] = htmlspecialchars(trim($_POST['password']));
                $values['role'] = isset($_POST['isAdmin']) ? 'admin' : 'user';

                $errors['login'] = (!isset($_POST['login']) || trim($_POST['login']) == '');
                $errors['password'] = (!isset($_POST['password']) || trim($_POST['password']) == '');

                if(!$errors['login'] && !$errors['password']) {
                    $hash = YosLoginTools::hashPassword($values['password']);

                    if($hash !== false) {
                        //insert the new user
                        $insertUser = $db->prepare(
                            'INSERT INTO ddb_user (userLogin, userPassword, userRole)'
                            .' VALUES (:login, :hash, :role)'
                        );
                        $insertUser->bindParam(':login', $values['login'], PDO::PARAM_STR);
                        $insertUser->bindParam(':hash', $hash, PDO::PARAM_STR);
                        $insertUser->bindParam(':role', $values['role'], PDO::PARAM_STR);
                        $insertUser->execute();

                        $userId = $db->lastInsertId();
                        
                        //logout user to update session context
                        header('Location: ?p=users&id='.$userId);
                    } else {
                        $errors['app'] = true;
                    }
                }

                //keep values and show errors
                $tpl->assign( "errors", $errors );
                $tpl->assign( "values", $values );
            }

            //display or process 'edit user' form
            if(isset($_GET['id']) && is_numeric($_GET['id'])) {

                //update user
                if(isset($_POST['submitEditUser'])) {
                    $values = array();
                    $errors = array();

                    $values['login'] = htmlspecialchars(trim($_POST['login']));
                    $values['password'] = htmlspecialchars(trim($_POST['password']));
                    $values['hash'] = false;
                    //current user must remain admin
                    $values['role'] = (isset($_POST['isAdmin']) || ($user['id'] == (int)$_GET['id'])) ? 'admin' : 'user';
                    $values['id'] = $_GET['id'];
                    $values['dreamers'] = array();
                    foreach($_POST as $key => $value) {
                        if(substr($key, 0, 8) == 'dreamer-' && is_numeric(substr($key, 8))) {
                            $values['dreamers'][] = (int)substr($key, 8);
                        }
                    }

                    $errors['login'] = (!isset($_POST['login']) || trim($_POST['login']) == '');
                    
                    if(!$errors['login']) {
                        $sql = 'UPDATE ddb_user SET userLogin=:login, userRole=:role';
                        //only update password if needed
                        if(!empty($values['password'])) {
                            $values['hash'] = YosLoginTools::hashPassword($values['password']);
                            $sql .= ', userPassword=:hash';
                        }
                        $sql .= ' WHERE userId = :id';

                        //if there is no need to update the password or if it is ready to be updated
                        if(empty($values['password']) || $values['hash'] !== false) {
                            $qry = $db->prepare( $sql );
                            $qry->bindParam(':login', $values['login'], PDO::PARAM_STR);
                            $qry->bindParam(':role', $values['role'], PDO::PARAM_STR);
                            if(!empty($values['hash']))
                                $qry->bindParam(':hash', $values['hash'], PDO::PARAM_STR);
                            $qry->bindParam(':id', $values['id'], PDO::PARAM_INT);
                            $qry->execute();

                            //remove old links between the user and existing dreamers
                            $qry = $db->prepare( 'DELETE FROM ddb_user_dreamer WHERE userId_FK=:userId' );
                            $qry->bindParam(':userId', $values['id'], PDO::PARAM_INT);
                            $qry->execute();
                            
                            //recreate thoses links (admins don't need it)
                            if($values['role'] != 'admin') {
                                foreach($values['dreamers'] as $dreamerId) {
                                    $qry = $db->prepare( 'INSERT INTO ddb_user_dreamer (userId_FK, dreamerId_FK) VALUES (:userId, :dreamerId)' );
                                    $qry->bindParam(':userId', $values['id'], PDO::PARAM_INT);
                                    $qry->bindParam(':dreamerId', $dreamerId, PDO::PARAM_INT);
                                    $qry->execute();
                                }
                            }

                            //if the current user updated his/her own profile
                            if($user['id'] == $_GET['id']) {
                                //logout to update session context
                                header("Location: index.php?logout");
                            } else {
                                // header("Location: config.php?p=users");
                            }
                        } else {
                            $errors['app'] = true;
                        }
                    }

                    //keep values and show errors
                    $tpl->assign( "errors", $errors );
                    $tpl->assign( "values", $values );
                }

                //delete user
                if(isset($_POST['submitDeleteUser'])) {
                    //TODO
                }

                $dream = array();
                $id = trim($_GET['id']);

                $qryUser = $db->prepare("SELECT userId, userLogin, userRole FROM ddb_user WHERE userId=:userId LIMIT 1");
                $qryUser->bindParam(':userId', $id, PDO::PARAM_INT);
                $qryUser->execute();
                $qryUser->bindColumn('userId', $editUser['userId']);
                $qryUser->bindColumn('userLogin', $editUser['userLogin']);
                $qryUser->bindColumn('userRole', $editUser['userRole']);
                $row = $qryUser->fetch(PDO::FETCH_BOUND);
            }

            //show and edit a specific user
            if(isset($editUser) && !empty($editUser)) {
                //list all dreamers and check those linked to the selected user
                $qryDreamers = $db->prepare(
                    'SELECT dr.dreamerId, dr.dreamerName, IFNULL(ud.userId_FK, 0) as linked'
                    .' FROM ddb_dreamer dr'
                    .' LEFT JOIN ddb_user_dreamer ud ON ud.dreamerId_FK = dr.dreamerId'
                    .' ORDER BY dr.dreamerName ASC'
                );
                $qryDreamers->execute();
                $dreamers = $qryDreamers->fetchAll(PDO::FETCH_ASSOC);

                $tpl->assign( "editUser", $editUser );
                $tpl->assign( "dreamers", $dreamers );
            } else {
                //list all users
                $qryUsers = $db->prepare(
                    "SELECT u.userId, u.userLogin, u.userRole FROM ddb_user u ORDER BY u.userRole, u.userLogin"
                );
                $qryUsers->execute();
                $users = $qryUsers->fetchAll(PDO::FETCH_ASSOC);
                $tpl->assign( "users", $users );
            }
        }
    }

    $tpl->assign( "page", $page );
    $tpl->draw( "config" );
}

?>