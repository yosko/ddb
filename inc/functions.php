<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

// error_reporting(E_ALL);
// ini_set('display_errors', 'On');

require_once "inc/param.php";
require_once "inc/rain.tpl.class.php";

function setRainTpl() {
    //RainTPL config
    raintpl::configure("base_url", null );
    raintpl::configure("tpl_dir", "tpl/" );
    raintpl::configure("cache_dir", "tmp/" );
    $tpl = new RainTPL;
        
    //define base url for RSS & others
    $ddbUrl = $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']);
    $tpl->assign( "ddbUrl", $ddbUrl );
        
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

//inspired by:
// - http://www.tonylea.com/2011/creating-a-simple-php-login-without-a-database/
// - sebsauvage's shaarli authentication method (http://sebsauvage.net/wiki/doku.php?id=php:shaarli)
function logUser($tpl) {
    global $config;
    $error = false;
    
    //force cookie path
    $cookie=session_get_cookie_params();
    session_set_cookie_params($cookie['lifetime'],dirname($_SERVER['SCRIPT_NAME']).'/');
    
    ini_set('session.use_cookies', 1);       // Use cookies to store session.
    ini_set('session.use_only_cookies', 1);  // Force cookies for session (phpsessionID forbidden in URL)
    ini_set('session.use_trans_sid', false); // Prevent php to use sessionID in URL if cookies are disabled.
    
    //Session management
    session_name('ddb');
    session_start();
    
    //if user is logging out or if IP doesn't match
    if(isset($_GET['logout']) || isset($_SESSION['ip']) && $_SESSION['ip']!=getIpAddress()) {
        //unset long-term session server-side
        unsetLTSession($_SESSION['uid']);
        //delete long-term cookie client-side
        setcookie('ddblt', null, time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
        
        //unset PHP session
        unset($_SESSION['uid']);
        unset($_SESSION['ip']);
        unset($_SESSION['login']);
        
        session_set_cookie_params(time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/');
        session_destroy();
        
        header("Location: index.php");
        
    //user doesn't have a PHP session but have a long-term cookie, reload session
    } elseif(!isset($_SESSION['uid']) && isset($_COOKIE['ddblt'])) {
        
        $LTSession = getLTSession($_COOKIE['ddblt']);
        
        if($LTSession !== false) {
            //set session
            $_SESSION['uid']=$_COOKIE['ddblt']; // generate unique random number (different than phpsessionid)
            $_SESSION['ip']=$LTSession['ip'];
            $_SESSION['login']=$LTSession['login'];
        } else {
            //delete long-term cookie client-side
            setcookie('ddblt', null, time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
        }
        
    //if user trying to log in
    } elseif (isset($_POST['submitLogin']) && isset($_POST['login']) && trim($_POST['login']) != "" && isset($_POST['password']) && trim($_POST['password']) != "") {
        //find user
        $user = getUser($_POST['login']);
        
        //check user/password
        if(!empty($user) && sha1($_POST['password']) == $user['password']) {
            //set session
            $_SESSION['uid']=sha1(uniqid('',true).'_'.mt_rand()); // generate unique random number (different than phpsessionid)
            $_SESSION['ip']=getIpAddress();
            $_SESSION['login']=$config['login'];
            
            if(isset($_POST['remember']) && $_POST['remember'] == "remember") {
                //save the long term session on server-side
                $LTSession = array();
                $LTSession['login'] = $_SESSION['login'];
                $LTSession['ip'] = $_SESSION['ip'];
                setLTSession($_SESSION['uid'], $LTSession);
                
                //delete old sessions
                flushOldLTSessions();
            }
            
            //rederict user
            header("Location: $_SERVER[REQUEST_URI]");
        } else {
            $error = true;
        }
    }
    
    //if user is logged in
    if (!empty($_SESSION['uid'])) {
        //set or update the long term session on client-side
        setcookie('ddblt', $_SESSION['uid'], time()+$config['LTDuration'], dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
        session_regenerate_id(true);
        
        return true;
    } else {
        $tpl->assign( "error", $error );
        $tpl->assign( "noLogout", true );
        $tpl->draw( "login" );
        return false;
    }
}

//get user informations
// returns: array with user's login and passord
function getUser($login) {
    global $config;
    
    $foundUser = array();
    $foundUser['login'] = $config['login'];
    $foundUser['password'] = $config['password'];
    return($foundUser);
}

//save (create/update) a long term session
function setLTSession($sid, $value) {
    global $config;
    
    $fp = fopen($config['LTDir'].$sid, 'w');
    fwrite($fp, gzdeflate(json_encode($value)));
    fclose($fp);
}

//get a long term session informations (when PHP session expires)
function getLTSession($sid) {
    global $config;
    
    $dir = $config['LTDir'];
    
    $value = false;
    if (file_exists($dir.$sid)) {
        
        //unset long-term session if expired
        if(filemtime($dir.$sid)+$config['LTDuration'] <= time()) {
            unsetLTSession($sid);
            $value = false;
        } else {
            $value = json_decode(gzinflate(file_get_contents($dir.$sid)), true);
            //update last access time on file
            touch($dir.$sid);
        }
    }
    return($value);
}

//delete a long term session
function unsetLTSession($sid) {
    global $config;
    
    if (file_exists($config['LTDir'].$sid)) {
        unlink($config['LTDir'].$sid);
    }
}

//flush old long-term sessions exceeding their duration or the maximum number of long-term sessions
//based on http://avinash6784.wordpress.com/2011/05/13/delete-old-files-from-directory-in-php/
function flushOldLTSessions() {
    global $config;
    
    $dir = $config['LTDir'];
    
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
        if ($i > $config['nbLTSession'] || $date+$config['LTDuration'] <= time()) {
            unsetLTSession($file);
        }
        ++$i;
    } 
}

//based on: http://stackoverflow.com/questions/1634782/what-is-the-most-accurate-way-to-retrieve-a-users-correct-ip-address-in-php
function getIpAddress(){
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
        if (array_key_exists($key, $_SERVER) === true){
            foreach (explode(',', $_SERVER[$key]) as $ip){
                $ip = trim($ip); // just to be safe
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                    return $ip;
                }
            }
        }
    }
}

function randomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!#%&()*+,-./:;<=>?@[]^_`{|}~';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function updateParams($login, $password) {
    $string = "<?php\n\n"
    ."/* AUTOMATICALLY GENERATED - DO NOT ADD ANYTHING: IT WILL BE LOST */\n\n"
    ."\$config['login'] = \"".htmlentities(trim($login))."\";\n"
    ."\$config['password'] = \"".sha1($password)."\";\n"
    ."\$config['salt1'] = \"".randomString()."\";\n"
    ."\$config['salt2'] = \"".randomString()."\";\n"
    ."\$config['LTDir'] = 'cache/';\n"
    ."\$config['nbLTSession'] = 200;\n"
    ."\$config['LTDuration'] = 2592000;\n"
    ."\n?>";
    
    $fp = fopen("inc/param.php", "w");
    fwrite($fp, $string);
    fclose($fp);
}

?>