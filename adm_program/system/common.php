<?php
/******************************************************************************
 * Script beinhaltet allgemeine Daten / Variablen, die fuer alle anderen
 * Scripte notwendig sind
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

if ('common.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('Diese Seite darf nicht direkt aufgerufen werden !');
}

define('SERVER_PATH', substr(__FILE__, 0, strpos(__FILE__, "adm_program")-1));
define('ADMIDIO_VERSION', '1.5 Beta');  // die Versionsnummer bitte nicht aendern !!!

// Konfigurationsdatei einbinden
require_once(SERVER_PATH. "/adm_config/config.php");

// falls Debug-Kennzeichen nicht in config.php gesetzt wurde, dann hier auf false setzen
if(isset($g_debug) == false || $g_debug != 1)
{
    $g_debug = 0;
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
if($g_forum_integriert)
{
    require_once(SERVER_PATH. "/adm_program/system/forum_class_phpbb.php");
}

// Defines fuer alle Datenbanktabellen
define("TBL_ANNOUNCEMENTS",     $g_tbl_praefix. "_announcements");
define("TBL_CATEGORIES",        $g_tbl_praefix. "_categories");
define("TBL_DATES",             $g_tbl_praefix. "_dates");
define("TBL_FILES",             $g_tbl_praefix. "_files");
define("TBL_FOLDERS",           $g_tbl_praefix. "_folders");
define("TBL_FOLDER_ROLES",      $g_tbl_praefix. "_folder_roles");
define("TBL_GUESTBOOK",         $g_tbl_praefix. "_guestbook");
define("TBL_GUESTBOOK_COMMENTS",$g_tbl_praefix. "_guestbook_comments");
define("TBL_LINKS",             $g_tbl_praefix. "_links");
define("TBL_MEMBERS",           $g_tbl_praefix. "_members");
define("TBL_ORGANIZATIONS",     $g_tbl_praefix. "_organizations");
define("TBL_PHOTOS",            $g_tbl_praefix. "_photos");
define("TBL_PREFERENCES",       $g_tbl_praefix. "_preferences");
define("TBL_ROLE_DEPENDENCIES", $g_tbl_praefix. "_role_dependencies");
define("TBL_ROLES",             $g_tbl_praefix. "_roles");
define("TBL_SESSIONS",          $g_tbl_praefix. "_sessions");
define("TBL_TEXTS",             $g_tbl_praefix. "_texts");
define("TBL_USERS",             $g_tbl_praefix. "_users");
define("TBL_USER_DATA",         $g_tbl_praefix. "_user_data");
define("TBL_USER_FIELDS",       $g_tbl_praefix. "_user_fields");

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
$g_layout        = array();
$g_current_url   = "http://". $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
$g_message       = new Message();

// PHP-Session starten
session_name('admidio_php_session_id');
session_start();

// Session-ID ermitteln
if(isset($_COOKIE['admidio_session_id']))
{
    $g_session_id = $_COOKIE['admidio_session_id'];
}
else
{
    $g_session_id = session_id();
}

 // Verbindung zu Datenbank herstellen
$g_db = new MySqlDB();
$g_adm_con = $g_db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

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

if($g_current_session->getValue("ses_id") > 0)
{
    // erst einmal pruefen, ob Organisation- oder Userobjekt neu eingelesen werden muessen,
    // da die Daten evtl. von anderen Usern in der DB geaendert wurden
    if($g_current_session->getValue("ses_renew") == 1)
    {
        // Userobjekt neu einlesen
        $g_current_user->getUser($g_current_user->getValue("usr_id"));
        $g_current_session->setValue("ses_renew", 0);
    }
    if($g_current_session->getValue("ses_renew") == 2)
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
            $valid    = false;
            $time_gap = time() - mysqlmaketimestamp($g_current_session->getValue("ses_timestamp"));
            // wenn länger nichts gemacht wurde, als in Orga-Prefs eingestellt ist, dann ausloggen
            if ($time_gap < $g_preferences['logout_minutes'] * 60) 
            {
                $valid = true;
            }

            if($valid)
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
    $g_current_session->setValue("ses_session", $g_session_id);
    $g_current_session->save();
    
    // Alle alten Session loeschen
    $g_current_session->tableCleanup($g_preferences['logout_minutes']);
}

/*********************************************************************************
Verbindung zur Forum-Datenbank herstellen und die Funktionen, sowie Routinen des Forums laden.
/********************************************************************************/

if($g_forum_integriert) 
{
    // globale Klassen mit Datenbankbezug werden in Sessionvariablen gespeichert, 
    // damit die Daten nicht bei jedem Script aus der Datenbank ausgelesen werden muessen
    if(isset($_SESSION['g_forum']))
    {
        $g_forum =& $_SESSION['g_forum'];
        $g_forum->connect();
        $g_forum->adm_con = $g_adm_con;
    }
    else
    {
        $g_forum = new Forum($g_forum_srv, $g_forum_db, $g_forum_usr, $g_forum_pw);
        $_SESSION['g_forum'] =& $g_forum;
        $g_forum->praefix     = $g_forum_praefix;
        $g_forum->export      = $g_forum_export;
        $g_forum->version     = $g_forum_version;
        $g_forum->session_id  = session_id();
        $g_forum->adm_con     = $g_adm_con;
        $g_forum->adm_db      = $g_adm_db;
        $g_forum->preferences();
    }
    
    // Forum Session auf Gueltigkeit pruefen
    $g_forum->checkSession($g_valid_login);
}
else
{
    $g_forum_con = false;
}
?>
