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

require_once "inc/debug.php";
require_once "inc/rain.tpl.class.php";
require_once "inc/yoslogin.class.php";
require_once "inc/php-github-updater.php";

define("DDB_VERSION", "v1.5");
define("DREAM_STATUS_UNPUBLISHED", 0);
define("DREAM_STATUS_PUBLISHED", 1);
define("BASE_URL", $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']));

function initDDb(&$db, &$settings, &$tpl, &$user, &$publicFeed=false, $rss=false) {
    $db = openDatabase();
    $settings = getSettings();
    $tpl = setRainTpl();

    $tpl->assign( "settings", $settings );

    $publicFeed = false;
    if($rss && isset($_GET['key']) && $_GET['key'] == $settings['appKey']) {
        $publicFeed = true;
    } else {
        $user = logUser($tpl);
    }

    //check for updates
    $version = array(
        'current' => DDB_VERSION,
        'next' => DDB_VERSION,
        'last' => DDB_VERSION,
        'lastCheck' => null,
    );
    if(isset($user['role']) && $user['role'] == 'admin') {
        try {
            $qry = $db->prepare(
                'SELECT * FROM ddb_version'
            );
            $qry->execute();
            $version = $qry->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            //the table doesn't exist yet
            $version = array(
                'current' => DDB_VERSION,
                'next' => DDB_VERSION,
                'last' => DDB_VERSION,
                'lastCheck' => null,
                'mustUdpate' => false,
            );

            //create it
            createTableVersion(DDB_VERSION);
        }

        //perform update check if previous on is older than a week or never occured
        if(is_null($version['lastCheck']) || strtotime('now') > strtotime($version['lastCheck'].'+1 week')) {
            checkForUpdates();
        }
    }

    $tpl->assign( "version", $version );
}

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
    $ddbUrl = BASE_URL;
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

function checkForUpdates() {
    global $db;

    $updater = new PhpGithubUpdater('yosko', 'ddb');
    $current = DDB_VERSION;
    $next = $updater->getNextVersion(DDB_VERSION);
    $latest = $updater->getLatestVersion();
    $mustUpdate = !$updater->isUpToDate(DDB_VERSION);
    if(!is_null($next) && !is_null($latest) && !is_null($mustUpdate)) {
        //insert a dummy check log
        $qry = $db->prepare(
            'UPDATE ddb_version SET current = :current, next = :next, last = :last,'
            .' lastCheck = current_timestamp, mustUpdate = :mustUpdate'
        );
        $qry->bindParam(':current', $current, PDO::PARAM_STR);
        $qry->bindParam(':next', $next, PDO::PARAM_STR);
        $qry->bindParam(':latest', $latest, PDO::PARAM_STR);
        $qry->bindParam(':mustUpdate', $mustUpdate, PDO::PARAM_INT);
        $qry->execute();
    }
}

function createTableVersion($version) {
    global $db;

$sql = <<<QUERY
CREATE TABLE IF NOT EXISTS ddb_version (
    'current'           TEXT     NOT NULL,
    'next'              TEXT     NOT NULL,
    'last'              TEXT     NOT NULL,
    'lastCheck'         DATETIME NOT NULL,
    'mustUpdate'        INTEGER  NOT NULL DEFAULT 0
);
QUERY;

    $db->exec($sql);

    //insert a dummy check log
    $qry = $db->prepare(
        'INSERT INTO ddb_version (current, next, last, lastCheck)'
        .' VALUES(:version, :version, :version, current_timestamp)'
    );
    $qry->bindParam(':version', $version, PDO::PARAM_STR);
    $qry->execute();
}

class DDbLogin extends YosLogin {
    protected function getUser($login) {
        return getUser($login);
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
        $settings['LTDir'],
        DEBUG_MODE
    );

    if(isset($_GET['logout'])) {
        $logger->logOut();
    } elseif(isset($_POST['submitLogin']) && isset($_POST['login']) && isset($_POST['password'])) {
        $user = $logger->logIn(
            htmlspecialchars(trim($_POST['login'])),
            htmlspecialchars(trim($_POST['password'])),
            isset($_POST['remember'])
        );
    } elseif(isset($_POST['submitSecureAccess']) && isset($_POST['password'])) {
        $user = $logger->authUser(
            htmlspecialchars(trim($_POST['password']))
        );
    } else {
        $user = $logger->authUser();
    }

    if(isset($user)) {
        $tpl->assign( "user", $user );
    }
    
    if($user['isLoggedIn'] === false) {
        $tpl->assign( "noLogout", true );
        $tpl->draw( "login" );
    }

    return $user;
}

function getUser($login) {
    global $db;

    $qry = $db->prepare(
        "SELECT userId as id, userLogin as login, userPassword as password, userRole as role FROM ddb_user where lower(userLogin) = lower(:login) LIMIT 1");
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

    date_default_timezone_set($settings['timezone']);

    $time = date('H');
    $settings['isNightTime'] = (
        $settings['useNightSkin'] == true
        && (
            $settings['dusk'] > $settings['dawn']
            && (
                $time >= $settings['dusk']
                || $time < $settings['dawn']
            )
            || $settings['dusk'] < $settings['dawn']
            && (
                $time >= $settings['dusk']
                && $time < $settings['dawn']
            )
        )
    );

    return $settings;
}

function setSettings($settings) {
    global $db;

    //TODO
}

function wikiFormat($string, $paragraphs = true) {
    //turn newlines to paragrpahs
    if($paragraphs == true)
        $string = "<p>".str_replace("\n", "</p>\n\t\t\t<p>", $string)."</p>";

    //wiki syntax inspired by Le Hollandais Volant's Blogotext
    $sourceTags = array(
        '#\[([^[]+)\|([^[]+)\]#',       //url with title: [title|url]
        '#\[(https?://)([^[]+)\]#',     //url with http(s): [url]
        '#\[([0-9]*)\]#',               //url for dream: [dreamId]
        '#\(\(([^ ]*?)\|(.*?)\)\)#',    //image with alt text: (text|image url)
        '#\[img\](.*?)\[/img\]#s',      //image: [img]image url[/img]
        '#\[b\](.*?)\[/b\]#s',          //bold text: [b]text[/b]
        '#\[i\](.*?)\[/i\]#s',          //italic text: [i]text[/i]
        '#\[s\](.*?)\[/s\]#s',          //strike text: [s]text[/s]
        '#\[u\](.*?)\[/u\]#s',          //underlined text: [u]text[/u]
        '#(O|o):-?\)#',
        '#&gt;:-?\)#',
        '#\^_*\^\'#',
        '#(:(-?|o)\)|\^_*\^)#',
        '#:-?(S|s)#',
        '#8-?\)#',
        '#(T_+T|:\'-?\()#',
        '#(o|O)(\.+|_+)(o|O)#',
        '#:-?\*#',
        '#:-?3#',
        '#(:|x|X)-?D#',
        '#:-?(p|P)#',
        '#:-?\$#',
        '#:-?\(#',
        '#&gt;.?&lt;#',
        '#;-?\)#',
        '#:-?&amp;#',
        '#&lt;3#'
    );
    $destinationTags = array(
        '<a href="$2">$1</a>',
        '<a href="$1$2">$2</a>',
        '<a href="dream.php?id=$1">n°$1</a>',
        '<img src="$1" alt="$2" />',
        '<img src="$1" />',
        '<span style="font-weight: bold;">$1</span>',
        '<span style="font-style: italic;">$1</span>',
        '<span style="text-decoration: line-through;">$1</span>',
        '<span style="text-decoration: underline;">$1</span>',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-angel.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-evil.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-sweat.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-confuse.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-cool.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-cry.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-eek.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-kiss.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-kitty.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-lol.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-razz.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-red.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-sad.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-yell.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-wink.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/smiley-zipper.png">',
        '<img class="inline" src="http://'.BASE_URL.'/tpl/img/heart.png">'
    );

    $string = preg_replace($sourceTags, $destinationTags, $string);

    return $string;
}

/**
 * Check if given user is the author of a given dream
 * @param  int     $userId  user id
 * @param  int     $dreamId dream id
 * @return boolean          true if user is the author
 */
function isAuthor($userId, $dreamId) {
    global $db;

    //check wether the user is allowed to access this dream
    $qryAccess = $db->prepare(
        'SELECT CASE WHEN (d.userId_FK=:userId) THEN 1 ELSE 0 END AS isAuthor'
        .' FROM ddb_dream d WHERE d.dreamId=:dreamId'
    );
    $qryAccess->bindParam(':userId', $userId, PDO::PARAM_INT);
    $qryAccess->bindParam(':dreamId', $dreamId, PDO::PARAM_INT);
    $qryAccess->execute();
    $isAuthor = $qryAccess->fetchColumn();

    return ($isAuthor > 0);
}

?>