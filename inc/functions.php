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

function logUser($tpl) {
    global $config;
    $error = false;
    
    //Session management
    session_start();
    $hash = md5($config['salt1'].$config['password'].$config['salt2']);
    $self = $_SERVER['REQUEST_URI'];
    
    //if user is logging out
    if(isset($_GET['logout']))
    {
        unset($_SESSION['login']);
    }
    
    //if user trying to log in
    if (isset($_POST['submit'])) {
        if (htmlentities($_POST['login']) == $config['login'] && md5($_POST['password']) == $config['password']){
            //set session
            $_SESSION["login"] = $hash;
            header("Location: $_SERVER[PHP_SELF]");
        } else {
            $error = true;
        }
    }
    
    //if user already logged in (session is set)
    if (isset($_SESSION['login']) && $_SESSION['login'] == $hash) {
        return true;
    } else {
        $tpl->assign( "error", $error );
        $tpl->assign( "noLogout", true );
        $tpl->draw( "login" );
        return false;
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
    ."\$config['password'] = \"".md5($password)."\";\n"
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