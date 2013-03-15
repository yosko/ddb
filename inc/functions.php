<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

// error_reporting(E_ALL);
// ini_set('display_errors', 'On');

require_once "inc/rain.tpl.class.php";
require_once "inc/yoslogin.class.php";

function setRainTpl($tplDir = '', $tplCache = '') {
    if(empty($tplDir) || empty($tplCache)) {
        global $settings;
        $tplDir = $settings['tplDir'];
        $tplCache = $settings['tplCache'];
    }

    //RainTPL config
    raintpl::configure('base_url', null );
    raintpl::configure('tpl_dir', $tplDir );
    raintpl::configure('cache_dir', $tplCache );
    $tpl = new RainTPL;
        
    //define base url for RSS & others
    $ddbUrl = $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']);
    $tpl->assign( 'ddbUrl', $ddbUrl );
        
    return $tpl;
}

function openDatabase() {
    //check if database exists
    if (file_exists("database.sqlite")) {
        //open ddb
        try {
            $db = new PDO('sqlite:database.sqlite');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $db;
        } catch(PDOException $e) {
            echo $e->getMessage();
            return false;
        }
    } else {
        //install wasn't done yet
        header("Location: install.php");
        
        //avoid call to logUser which also does some header("Location: ...")
        exit;
    }
}

class DDbLogin extends YosLogin {
    protected function getUser($login) {
        return getUser($login);
        // global $settings;
        
        // $foundUser = array();
        // if($settings['login'] == $login) {
        //     $foundUser['login'] = $settings['login'];
        //     $foundUser['password'] = $settings['password'];
        // }

        // return($foundUser);
    }

    protected function setLTSession($login, $sid, $value) {
        $fp = fopen($this->LTDir.$login.'_'.$sid.'.ses', 'w');
        fwrite($fp, gzdeflate(json_encode($value)));
        fclose($fp);
    }

    protected function getLTSession($cookieValue) {
        $value = false;
        $file = $this->LTDir.$cookieValue.'.ses';
        if (file_exists($file)) {
            
            //unset long-term session if expired
            if(filemtime($file)+$this->LTDuration <= time()) {
                unsetLTSession($cookieValue);
                $value = false;
            } else {
                $value = json_decode(gzinflate(file_get_contents($file)), true);
                //update last access time on file
                touch($file);
            }
        }
        return($value);
    }

    protected function unsetLTSession($cookieValue) {
        $filePath = $this->LTDir.$cookieValue.'.ses';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    protected function unsetLTSessions($login) {
        $files = glob( $this->LTDir.$login.'_*', GLOB_MARK );
        foreach( $files as $file ) {
            unlink( $file );
        }
    }

    protected function flushOldLTSessions() {
        $dir = $this->LTDir;
        
        //list all the session files
        $files = array();
        if ($dh = opendir($dir)) {
            while ($file = readdir($dh)) {
                if(!is_dir($dir.$file)) {
                    if ($file != "." && $file != "..") {
                        $files[$file] = filemtime($dir.$file);
                    }
                }
            }
            closedir($dh);
        }
        
        //sort files by date (descending)
        arsort($files);
        
        //check each file
        $i = 1;
        foreach($files as $file => $date) {
            if ($i > $this->nbLTSession || $date+$this->LTDuration <= time()) {
                $this->unsetLTSession(basename($file));
            }
            ++$i;
        }
    }
}

function logUser($tpl) {
    global $settings;

    $logger = new DDbLogin(
        'ddb',
        $settings['nbLTSession'],
        $settings['LTDuration'],
        $settings['LTDir']
    );

    if(isset($_GET['logout'])) {
        $logger->logOut();
    } elseif(isset($_POST['submitLogin']) && isset($_POST['login']) && isset($_POST['password'])) {
        $user = $logger->logIn($_POST['login'], $_POST['password'], isset($_POST['remember']));
    } elseif(isset($_POST['submitPassword']) && isset($_POST['password'])) {
        $user = $logger->authUser();
    } else {
        $user = $logger->authUser();
    }

    if(isset($user)) {
        $tpl->assign( "user", $user );
    }
    
    if($user['isLoggedIn'] === false) {
        $tpl->assign( "noLogout", true );
        $tpl->draw( "login" );
        return false;
    }

    return true;
}

function getUser($login) {
    global $db;

    $qry = $db->prepare(
        "SELECT userLogin as login, userPassword as password FROM ddb_user where userLogin = :login LIMIT 1");
    $qry->bindParam(':login', $login, PDO::PARAM_STR);
    $qry->execute();
    $user = $qry->fetch(PDO::FETCH_ASSOC);

    return $user;
}

function createUser() {
    global $db;

    //TODO
}

function updateUser($login) {
    global $db;

    //TODO
}

function getSettings() {
    global $db;

    $qry = $db->prepare(
        "SELECT * FROM ddb_settings LIMIT 1");
    $qry->execute();
    $settings = $qry->fetch(PDO::FETCH_ASSOC);
    return $settings;
}

function setSettings($settings) {
    global $db;

    //TODO
}

function updateParams($login, $password) {
    $count = 3; $hashedPassword = '';
    while($count > 0 && strlen($hashedPassword) < 200) {
        $hashedPassword = YosLoginTools::hashPassword($password);
        $count--;
    }
    $string = "<?php\n\n"
    ."/* AUTOMATICALLY GENERATED - DO NOT ADD ANYTHING: IT WILL BE LOST */\n\n"
    ."\$param['login'] = \"".htmlentities(trim($login))."\";\n"
    ."\$param['password'] = \"".YosLoginTools::hashPassword($password)."\";\n"
    ."\$param['LTDir'] = 'cache/';\n"
    ."\$param['nbLTSession'] = 200;\n"
    ."\$param['LTDuration'] = 2592000;\n"
    ."\n?>";
    
    $fp = fopen("inc/param.php", "w");
    fwrite($fp, $string);
    fclose($fp);
}

?>