<?php
/******************************************************************************
 * Script beinhaltet allgemeine Daten / Variablen, die fuer alle anderen
 * Scripte notwendig sind
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if ('common.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('Diese Seite darf nicht direkt aufgerufen werden !');
}

// Konstanten und Konfigurationsdatei einbinden
require_once(substr(__FILE__, 0, strpos(__FILE__, "adm_program")-1). "/config.php");
require_once(substr(__FILE__, 0, strpos(__FILE__, "adm_program")-1). "/adm_program/system/constants.php");

// falls Debug-Kennzeichen nicht in config.php gesetzt wurde, dann hier auf false setzen
if(isset($g_debug) == false || $g_debug != 1)
{
    $g_debug = 0;
}

if($g_debug)
{
    // aktuelles Script mit Uebergaben in Logdatei schreiben
    error_log("--------------------------------------------------------------------------------\n".
              $_SERVER['SCRIPT_FILENAME']. "\n? ". $_SERVER['QUERY_STRING']);
}

 // Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = "adm";
}

// Default-DB-Type ist immer MySql
if(!isset($g_db_type))
{
    $g_db_type = "mysql";
}

// includes OHNE Datenbankverbindung
require_once(SERVER_PATH. "/adm_program/system/". $g_db_type. "_class.php");
require_once(SERVER_PATH. "/adm_program/system/function.php");
require_once(SERVER_PATH. "/adm_program/system/date.php");
require_once(SERVER_PATH. "/adm_program/system/string.php");
require_once(SERVER_PATH. "/adm_program/system/message_class.php");
require_once(SERVER_PATH. "/adm_program/system/message_text.php");
require_once(SERVER_PATH. "/adm_program/system/navigation_class.php");
require_once(SERVER_PATH. "/adm_program/system/user_class.php");
require_once(SERVER_PATH. "/adm_program/system/organization_class.php");
require_once(SERVER_PATH. "/adm_program/system/session_class.php");
require_once(SERVER_PATH. "/adm_program/system/forum/forum.php");

// Variablen von HMTL & PHP-Code befreien
$_REQUEST = array_map("strStripTags", $_REQUEST);
$_GET     = array_map("strStripTags", $_GET);
$_POST    = array_map("strStripTags", $_POST);
$_COOKIE  = array_map("strStripTags", $_COOKIE);

// Anfuehrungszeichen escapen, damit DB-Abfragen sicher sind
if(get_magic_quotes_gpc() == false)
{
    $_REQUEST = strAddSlashesDeep($_REQUEST);
    $_GET     = strAddSlashesDeep($_GET);
    $_POST    = strAddSlashesDeep($_POST);
    $_COOKIE  = strAddSlashesDeep($_COOKIE);
}

// Globale Variablen
$g_valid_login = false;
$g_layout      = array();
$g_message     = new Message();

 // Verbindung zu Datenbank herstellen
$g_db = new MySqlDB();
$g_adm_con = $g_db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

// Script fuer das Forum ermitteln und includen, bevor die Session erstellt wird
includeForumScript($g_db);

// PHP-Session starten
if(headers_sent() == false)
{
    session_name('admidio_php_session_id');
    session_start();
}

// Session-ID ermitteln
if(isset($_COOKIE['admidio_session_id']))
{
    $g_session_id = $_COOKIE['admidio_session_id'];
}
else
{
    $g_session_id = session_id();
}

// globale Klassen mit Datenbankbezug werden in Sessionvariablen gespeichert, 
// damit die Daten nicht bei jedem Script aus der Datenbank ausgelesen werden muessen
if(isset($_SESSION['g_current_organization']) 
&& isset($_SESSION['g_preferences'])
&& $g_organization == $_SESSION['g_current_organization']->getValue("org_shortname"))
{
    $g_current_organization     =& $_SESSION['g_current_organization'];
    $g_current_organization->db =& $g_db;
    $g_preferences              =& $_SESSION['g_preferences'];
}
else
{
    $g_current_organization = new Organization($g_db, $g_organization);
    
    if($g_current_organization->getValue("org_id") == 0)
    {
        // Organisation wurde nicht gefunden
        die("<div style=\"color: #CC0000;\">Error: ". $message_text['missing_orga']. "</div>");
    }
    
    // organisationsspezifische Einstellungen aus adm_preferences auslesen
    $g_preferences = $g_current_organization->getPreferences();
    
    // Daten in Session-Variablen sichern
    $_SESSION['g_current_organization'] =& $g_current_organization;
    $_SESSION['g_preferences']          =& $g_preferences;
}

// Pfad zum gewaehlten Theme zusammensetzen
if(isset($g_preferences['theme']) == false)
{
    $g_preferences['theme'] = "classic";
}
define('THEME_SERVER_PATH', SERVER_PATH. "/adm_themes/". $g_preferences['theme']);
define('THEME_PATH', $g_root_path. "/adm_themes/". $g_preferences['theme']);

// pruefen, ob Datenbank-Version zu den Scripten passt
if(isset($g_preferences['db_version']) == false
|| version_compare($g_preferences['db_version'], ADMIDIO_VERSION) != 0)
{
    unset($_SESSION['g_current_organization']);
    $g_message->addVariableContent($g_preferences['email_administrator'], 1, false);
    $g_message->show("database_invalid");
}

// Daten des angemeldeten Users auch in Session speichern
if(isset($_SESSION['g_current_user']))
{
    $g_current_user =& $_SESSION['g_current_user'];
    $g_current_user->db =& $g_db;
}
else
{
    $g_current_user  = new User($g_db);
    $_SESSION['g_current_user'] =& $g_current_user;
}

// Objekt fuer die Zuruecknavigation in den Modulen
// hier werden die Urls in einem Stack gespeichert
if(isset($_SESSION['navigation']) == false)
{
    $_SESSION['navigation'] = new Navigation();
}

/*********************************************************************************
Session auf Gueltigkeit pruefen bzw. anlegen
/********************************************************************************/

$g_current_session = new Session($g_db, $g_session_id);

// erst einmal pruefen, ob evtl. frueher ein Autologin-Cookie gesetzt wurde
// dann diese Session wiederherstellen

$b_auto_login = false;
if($g_preferences['enable_auto_login'] == 1 && isset($_COOKIE['admidio_data']))
{
    $admidio_data = explode(";", $_COOKIE['admidio_data']);
    
    if($admidio_data[0] == true         // autologin
    && is_numeric($admidio_data[1]))    // user_id 
    {   
        if($g_current_user->getValue("usr_id") != $admidio_data[1])
        {
            // User aus der Autologin-Session wiederherstellen
            require_once(SERVER_PATH. "/adm_program/system/auto_login_class.php");
            $auto_login = new AutoLogin($g_db, $g_session_id);
            
            // User nur herstellen, wenn Cookie-User-Id == gespeicherte DB-User-Id
            if($auto_login->getValue("atl_usr_id") == $admidio_data[1])
            {
                $g_current_user->getUser($auto_login->getValue("atl_usr_id"));
                $b_auto_login = true;
            }
            else
            {
                // irgendwas stimmt nicht -> sicherheitshalber den Auto-Login-Eintrag loeschen
                $auto_login->delete();
            }
        }
        else
        {
            $b_auto_login = true;
        }
        
        if($g_current_session->getValue("ses_id") == 0)
        {
            $g_current_session->setValue("ses_session_id", $g_session_id);
            $g_current_session->setValue("ses_usr_id",  $g_current_user->getValue("usr_id"));
            $g_current_session->save();
        }
    }
}

if($g_current_session->getValue("ses_id") > 0)
{
    // erst einmal pruefen, ob Organisation- oder Userobjekt neu eingelesen werden muessen,
    // da die Daten evtl. von anderen Usern in der DB geaendert wurden
    if($g_current_session->getValue("ses_renew") == 1 || $g_current_session->getValue("ses_renew") == 3)
    {
        // Userobjekt neu einlesen
        $g_current_user->getUser($g_current_user->getValue("usr_id"));
        $g_current_session->setValue("ses_renew", 0);
    }
    if($g_current_session->getValue("ses_renew") == 2 || $g_current_session->getValue("ses_renew") == 3)
    {
        // Organisationsobjekt neu einlesen
        $g_current_organization->getOrganization($g_organization);
        $g_preferences = $g_current_organization->getPreferences();
        $g_current_session->setValue("ses_renew", 0);
    }

    // nun die Session pruefen
    if($g_current_session->getValue("ses_usr_id") > 0)
    {
        if($g_current_session->getValue("ses_usr_id") == $g_current_user->getValue("usr_id"))
        {
            // Session gehoert zu einem eingeloggten User -> pruefen, ob der User noch eingeloggt sein darf
            $time_gap = time() - mysqlmaketimestamp($g_current_session->getValue("ses_timestamp"));
            
            // wenn länger nichts gemacht wurde, als in Orga-Prefs eingestellt ist, dann ausloggen
            if ($time_gap < $g_preferences['logout_minutes'] * 60
            || $b_auto_login == true) 
            {
                // User-Login ist gueltig
                $g_valid_login = true;
                $g_current_session->setValue("ses_timestamp", date("Y-m-d H:i:s", time()));
            }
            else
            {
                // User war zu lange inaktiv -> User aus Session entfernen
                $g_current_user->clear();
                $g_current_session->setValue("ses_usr_id", "");
            }
        }
        else
        {
            // irgendwas stimmt nicht, also alles zuruecksetzen
            $g_current_user->clear();
            $g_current_session->setValue("ses_usr_id", "");
        }
    }
    
    // Update auf Sessionsatz machen (u.a. timestamp aktualisieren)
    $g_current_session->save();
}
else
{
    // Session existierte noch nicht, dann neu anlegen
    $g_current_user->clear();
    $g_current_session->setValue("ses_session_id", $g_session_id);
    $g_current_session->save();
    
    // Alle alten Session loeschen
    $g_current_session->tableCleanup($g_preferences['logout_minutes']);
}

// Homepageseite festlegen
if($g_valid_login)
{
    $g_homepage = $g_root_path. "/". $g_preferences['homepage_login'];
}
else
{
    $g_homepage = $g_root_path. "/". $g_preferences['homepage_logout'];
}

/*********************************************************************************
Verbindung zur Forum-Datenbank herstellen und die Funktionen, sowie Routinen des Forums laden.
/********************************************************************************/

if($g_preferences['enable_forum_interface']) 
{
    // Admidio-Zugangsdaten nehmen, wenn entsprechende Einstellung gesetzt ist
    if($g_preferences['forum_sqldata_from_admidio']) 
    {
        $g_preferences['forum_srv'] = $g_db->server;
        $g_preferences['forum_usr'] = $g_db->user;
        $g_preferences['forum_pw']  = $g_db->password;
        $g_preferences['forum_db']  = $g_db->dbname;
    }
    
    // globale Klassen mit Datenbankbezug werden in Sessionvariablen gespeichert, 
    // damit die Daten nicht bei jedem Script aus der Datenbank ausgelesen werden muessen
    if(isset($_SESSION['g_forum']))
    {
        $g_forum =& $_SESSION['g_forum'];
    }
    else
    {
        $g_forum = createForumObject($g_preferences['forum_version']);
        $_SESSION['g_forum'] =& $g_forum;
    }
        
    if(!$g_forum->connect($g_preferences['forum_srv'], $g_preferences['forum_usr'], $g_preferences['forum_pw'], $g_preferences['forum_db'], $g_db))
    {
        // Verbindungsprobleme, deshalb Forum deaktivieren, damit Admidio ggf. noch funktioniert
        $g_preferences['enable_forum_interface'] = 0;
    }
    else
    {
        // Einstellungen des Forums einlesen
        if(!$g_forum->initialize(session_id(), $g_preferences['forum_praefix'], $g_preferences['forum_export_user'], $g_current_user->getValue("usr_login_name")))
        {
            echo "<div style=\"color: #CC0000;\">Verbindungsfehler zum Forum !<br />
                Das eingegebene Forumpräfix <strong>". $g_preferences['forum_praefix']."</strong> ist nicht korrekt oder<br />
                es wurde die falsche Datenbankverbindung zum Forum angegeben.</div>";
        }
    }    
}

?>
