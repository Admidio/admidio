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

// includes OHNE Datenbankverbindung
require_once(SERVER_PATH. "/adm_config/config.php");
require_once(SERVER_PATH. "/adm_program/system/function.php");
require_once(SERVER_PATH. "/adm_program/system/date.php");
require_once(SERVER_PATH. "/adm_program/system/string.php");
require_once(SERVER_PATH. "/adm_program/system/message_class.php");
require_once(SERVER_PATH. "/adm_program/system/message_text.php");
require_once(SERVER_PATH. "/adm_program/system/navigation_class.php");
require_once(SERVER_PATH. "/adm_program/system/user_class.php");
require_once(SERVER_PATH. "/adm_program/system/organization_class.php");
if($g_forum_integriert)
{
    require_once(SERVER_PATH. "/adm_program/system/forum_class_phpbb.php");
}

// falls Debug-Kennzeichen nicht in config.php gesetzt wurde, dann hier auf false setzen
if(!defined('DEBUG'))
{
    define('DEBUG', '0');
}

 // Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = "adm";
}

// Defines fuer alle Datenbanktabellen
define("TBL_ANNOUNCEMENTS",     $g_tbl_praefix. "_announcements");
define("TBL_CATEGORIES",        $g_tbl_praefix. "_categories");
define("TBL_DATES",             $g_tbl_praefix. "_dates");
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

 // Verbindung zu Datenbank herstellen
$g_adm_con = mysql_connect ($g_adm_srv, $g_adm_usr, $g_adm_pw);
mysql_select_db($g_adm_db, $g_adm_con );

// PHP-Session starten
session_name('admidio_php_session_id');
session_start();

// Globale Variablen
$g_session_id    = "";
$g_session_valid = false;
$g_current_url   = "http://". $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
$g_message       = new Message();

// Cookies einlesen
if(isset($_COOKIE['admidio_session_id']))
{
    $g_session_id = $_COOKIE['admidio_session_id'];
}

// globale Klassen mit Datenbankbezug werden in Sessionvariablen gespeichert, 
// damit die Daten nicht bei jedem Script aus der Datenbank ausgelesen werden muessen
if(isset($_SESSION['g_current_organization']) 
&& isset($_SESSION['g_preferences']))
{
    $g_current_organization =& $_SESSION['g_current_organization'];
    $g_current_organization->db_connection = $g_adm_con;
    $g_preferences  =& $_SESSION['g_preferences'];
}
else
{
    $g_current_organization = new Organization($g_adm_con);
    $g_current_organization->getOrganization($g_organization);
    if($g_current_organization->id == 0)
    {
        // Organisation wurde nicht gefunden
        die("<div style=\"color: #CC0000;\">Error: ". $message_text['missing_orga']. "</div>");
    }
    
    // Einstellungen der Organisation auslesen
    $sql    = "SELECT * FROM ". TBL_PREFERENCES. "
                WHERE prf_org_id = $g_current_organization->id ";
    $result = mysql_query($sql, $g_adm_con);
    if($result == false)
    {
        // Fehler direkt ausgeben, da hier sonst Endlosschleifen entstehen
        die("<div style=\"color: #CC0000;\">Error: ". mysql_error(). "</div>");
    }
    
    $g_preferences = array();
    while($prf_row = mysql_fetch_object($result))
    {
        $g_preferences[$prf_row->prf_name] = $prf_row->prf_value;
    }
    
    // Daten in Session-Variablen sichern
    $_SESSION['g_current_organization'] =& $g_current_organization;
    $_SESSION['g_preferences']          =& $g_preferences;
}

// Daten des angemeldeten Users auch in Session speichern
if(isset($_SESSION['g_current_user']))
{
    $g_current_user =& $_SESSION['g_current_user'];
    $g_current_user->db_connection = $g_adm_con;
}
else
{
    $g_current_user  = new User($g_adm_con);
    $_SESSION['g_current_user'] =& $g_current_user;
}

// Objekt fuer die Zuruecknavigation in den Modulen
// hier werden die Urls in einem Stack gespeichert
if(isset($_SESSION['navigation']) == false)
{
    $_SESSION['navigation'] = new Navigation();
}

/*********************************************************************************
Aktuelle Session auf Gueltigkeit pruefen
/********************************************************************************/

if(strlen($g_session_id) > 0)
{
    // Session auf Gueltigkeit pruefen

    $sql    = "SELECT * FROM ". TBL_SESSIONS. " WHERE ses_session LIKE {0}";
    $sql    = prepareSQL($sql, array($g_session_id));
    $result = mysql_query($sql, $g_adm_con);

    db_error($result,__FILE__,__LINE__);

    $session_found = mysql_num_rows($result);
    $row           = mysql_fetch_object($result);

    if ($session_found == 1)
    {    
        $valid    = false;
        $time_gap = time() - mysqlmaketimestamp($row->ses_timestamp);
        // wenn länger nichts gemacht wurde, als in Orga-Prefs eingestellt ist, dann ausloggen
        if ($time_gap < $g_preferences['logout_minutes'] * 60) 
        {
            $valid = true;
        }

        if($valid)
        {
            $g_session_valid = true;
            // falls bisher ein anderer User in der Session gespeichert wurde -> neu einlesen
            if($g_current_user->id != $row->ses_usr_id)
            {
                $g_current_user->getUser($row->ses_usr_id);
                $_SESSION['g_current_user'] = $g_current_user;
            }

            // Datetime der Session muss aktualisiert werden

            $act_datetime   = date("Y-m-d H:i:s", time());

            $sql    = "UPDATE ". TBL_SESSIONS. " SET ses_timestamp = '$act_datetime' 
                        WHERE ses_session LIKE {0}";
            $sql    = prepareSQL($sql, array($g_session_id));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
        }
        else
        {
            // User war zu lange inaktiv -> Session loeschen
            $g_current_user->clear();
            $_SESSION['g_current_user'] = $g_current_user;

            $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_session LIKE {0}";
            $sql    = prepareSQL($sql, array($g_session_id));
            $result = mysql_query($sql, $g_adm_con);

            db_error($result,__FILE__,__LINE__);
        }
    }
    else
    {
        $g_current_user->clear();

        if ($session_found != 0)
        {
            // ID mehrfach vergeben -> Fehler und IDs loeschen
            $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_session LIKE {0}";
            $sql    = prepareSQL($sql, array($g_session_id));
            $result = mysql_query($sql, $g_adm_con);

            db_error($result,__FILE__,__LINE__);
        }
    }
}

// Verbindung zur Forum-Datenbank herstellen und die Funktionen, sowie Routinen des Forums laden.
if($g_forum_integriert) 
{
    $g_forum_con = mysql_connect ($g_forum_srv, $g_forum_usr, $g_forum_pw);
    
    // globale Klassen mit Datenbankbezug werden in Sessionvariablen gespeichert, 
    // damit die Daten nicht bei jedem Script aus der Datenbank ausgelesen werden muessen
    if(isset($_SESSION['g_forum']))
    {
        $g_forum =& $_SESSION['g_forum'];
        $g_forum->forum_db_connection   = $g_forum_con;
        $g_forum->adm_con               = $g_adm_con;
    }
    else
    {
        $g_forum = new Forum($g_forum_con);
        $g_forum->praefix               = $g_forum_praefix;
        $g_forum->export                = $g_forum_export;
        $g_forum->version               = $g_forum_version;
        $g_forum->session_id            = session_id();
        $g_forum->forum_db              = $g_forum_db;
        $g_forum->adm_con               = $g_adm_con;
        $g_forum->adm_db                = $g_adm_db;
        $g_forum->preferences();
        $_SESSION['g_forum']            = $g_forum;
    }
    
    // Forum Session auf Gueltigkeit pruefen
    // Nur wenn die Admidio Session valid ist, wird auf die Forum Session valid sein
    if($g_session_valid)
    {
        // Wenn die Forum Session bereits valid ist, wird diese Abfrage uebersprungen
        if($g_forum->session_valid != TRUE)
        { 
            $g_forum->session_valid = $g_forum->userCheck($g_current_user->login_name);
        }
    
        // Wenn die Forumssession gueltig ist, Userdaten holen und gueltige Session im Forum updaten. 
        if($g_forum->session_valid)
        {
            // Fuer diesen User neue PMs pruefen
            $g_forum->userPM($g_current_user->login_name);
            
            // Sofern die Admidio Session gueltig ist, ist auch die Forum Session gueltig
            $g_forum->session("update", $g_forum->userid);
        }
    }
    else
    {
        // Die Admidio Session ist nicht valid, also ist die Forum Session ebenfalls nicht valid
        if($g_forum->session_valid)
        {
            // Admidio Session ist abgelaufen, ungueltig oder ein logoff, also im Forum logoff
            $g_forum->session("logoff", $g_forum->userid);
        }
        $g_forum->session_valid = FALSE;
    }
    
    // Daten in Session-Variablen sichern
    $_SESSION['g_forum'] = $g_forum;
}
else
{
    $g_forum_con = false;
}
?>
