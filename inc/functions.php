<?php
/*
	DDb by Yosko (http://www.yosko.net/ddb.php)
	Licence: LGPL
*/

// error_reporting(E_ALL);
// ini_set('display_errors', 'On');

include_once "inc/param.php";
include "inc/rain.tpl.class.php";

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
    
    //if user is logging out or if session has expired or if IP doesn't match
    if(isset($_GET['logout']) || isset($_SESSION['expires_on']) && time()>=$_SESSION['expires_on'] || isset($_SESSION['ip']) && $_SESSION['ip']!=getIpAddress()) {
        unset($_SESSION['uid']);
        unset($_SESSION['ip']);
        unset($_SESSION['login']);
        unset($_SESSION['expires_on']);
    }
    
    //if user trying to log in
    if (isset($_POST['submit'])) {
        if (htmlentities($_POST['login']) == $config['login'] && sha1($_POST['password']) == $config['password']) {
            //set session
            $_SESSION['uid']=sha1(uniqid('',true).'_'.mt_rand()); // generate unique random number (different than phpsessionid)
            $_SESSION['ip']=getIpAddress();
            $_SESSION['login']=$config['login'];
            $_SESSION['remember']=$config['login'];
            $_SESSION['remember']=(isset($_POST['remember']) && $_POST['remember'] == "remember");
            
            header("Location: $_SERVER[PHP_SELF]");
        } else {
            $error = true;
        }
    }
    
    //if user already logged in (session is set and has not expired)
    if (!empty($_SESSION['uid'])) {
        //update timeout
        if(isset($_SESSION['remember']) && $_SESSION['remember']) {
            $_SESSION['expires_on']=time()+31536000;    //1 year
            session_set_cookie_params($_SESSION['expires_on'],dirname($_SERVER["SCRIPT_NAME"]).'/');
        } else {
            $_SESSION['expires_on']=time()+3600;        //1 hour
            session_set_cookie_params(0,dirname($_SERVER["SCRIPT_NAME"]).'/');
        }
        session_regenerate_id(true);
        
        return true;
    } else {
        $tpl->assign( "error", $error );
        $tpl->assign( "noLogout", true );
        $tpl->draw( "login" );
        return false;
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
    ."\n?>";
    
    $fp = fopen("inc/param.php", "w");
    fwrite($fp, $string);
    fclose($fp);
}

/*
//righteous CSV handling in PHP, from http://uk.php.net/manual/en/function.fgetcsv.php#98800
function array_to_csvstring($items, $CSV_SEPARATOR = ';', $CSV_ENCLOSURE = '"', $CSV_LINEBREAK = "\n") {
    $string = '';
    $o = array();
    
    foreach ($items as $item) {
        if (stripos($item, $CSV_ENCLOSURE) !== false) {
            $item = str_replace($CSV_ENCLOSURE, $CSV_ENCLOSURE . $CSV_ENCLOSURE, $item);
        }
        
        if ((stripos($item, $CSV_SEPARATOR) !== false)
            || (stripos($item, $CSV_ENCLOSURE) !== false)
            || (stripos($item, $CSV_LINEBREAK !== false))) {
            $item = $CSV_ENCLOSURE . $item . $CSV_ENCLOSURE;
        }
        
        $o[] = $item;
    }
    
    $string = implode($CSV_SEPARATOR, $o) . $CSV_LINEBREAK;
    
    return $string;
}

//righteous CSV handling in PHP, from http://uk.php.net/manual/en/function.fgetcsv.php#98800
function csvstring_to_array(&$string, $CSV_SEPARATOR = ';', $CSV_ENCLOSURE = '"', $CSV_LINEBREAK = "\n") {
    $o = array();
    
    $cnt = strlen($string);
    $esc = false;
    $escesc = false;
    $num = 0;
    $i = 0;
    while ($i < $cnt) {
        $s = $string[$i];

        if ($s == $CSV_LINEBREAK) {
            if ($esc) {
                $o[$num] .= $s;
            } else {
                $i++;
                break;
            }
        } elseif ($s == $CSV_SEPARATOR) {
            if ($esc) {
                $o[$num] .= $s;
            } else {
                $num++;
                $esc = false;
                $escesc = false;
            }
        } elseif ($s == $CSV_ENCLOSURE) {
            if ($escesc) {
                $o[$num] .= $CSV_ENCLOSURE;
                $escesc = false;
            }
            
            if ($esc) {
                $esc = false;
                $escesc = true;
            } else {
                $esc = true;
                $escesc = false;
            }
        } else {
            if ($escesc) {
                $o[$num] .= $CSV_ENCLOSURE;
                $escesc = false;
            }
            
            $o[$num] .= $s;
        }
        
        $i++;
    }

//  $string = substr($string, $i);

    return $o;
}
*/

?>