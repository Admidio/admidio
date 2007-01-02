<?php
/******************************************************************************
 * Script beinhaltet allgemeine Daten / Variablen, die fuer alle anderen
 * Scripte notwendig sind
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

$g_server_path = substr(__FILE__, 0, strpos(__FILE__, "adm_program")-1);

// includes OHNE Datenbankverbindung
require_once($g_server_path. "/adm_config/config.php");
require_once($g_server_path. "/adm_program/system/function.php");
require_once($g_server_path. "/adm_program/system/date.php");
require_once($g_server_path. "/adm_program/system/string.php");
require_once($g_server_path. "/adm_program/system/message_class.php");
require_once($g_server_path. "/adm_program/system/message_text.php");
require_once($g_server_path. "/adm_program/system/navigation_class.php");
require_once($g_server_path. "/adm_program/system/user_class.php");
require_once($g_server_path. "/adm_program/system/organization_class.php");
require_once($g_server_path. "/adm_program/system/role_dependency_class.php");



 // Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = "adm";
}

// Defines fuer alle Datenbanktabellen
define("TBL_ANNOUNCEMENTS",     $g_tbl_praefix. "_announcements");
define("TBL_CATEGORIES",        $g_tbl_praefix. "_categories");
define("TBL_DATES",             $g_tbl_praefix. "_dates");
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

 // Verbindung zu Datenbank herstellen
$g_adm_con = mysql_connect ($g_adm_srv, $g_adm_usr, $g_adm_pw);
mysql_select_db($g_adm_db, $g_adm_con );

// PHP-Session starten
session_start();

// Globale Variablen
$g_session_id    = "";
$g_session_valid = false;
$g_current_url   = "http://". $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
$g_message       = new Message();

// globale Klassen mit Datenbankbezug werden in Sessionvariablen gespeichert, 
// damit die Daten nicht bei jedem Script aus der Datenbank ausgelesen werden muessen
if(isset($_SESSION['g_current_organizsation']) 
&& isset($_SESSION['g_preferences']))
{
    $g_current_organization = $_SESSION['g_current_organizsation'];
    $g_current_organization->db_connection = $g_adm_con;
    $g_preferences  = $_SESSION['g_preferences'];
}
else
{
    $g_current_organization = new Organization($g_adm_con);
    $g_current_organization->getOrganization($g_organization);
    
    // Einstellungen der Organisation auslesen
    $sql    = "SELECT * FROM ". TBL_PREFERENCES. "
                WHERE prf_org_id = $g_current_organization->id ";
    $result = mysql_query($sql, $g_adm_con);
    if($result == false)
    {
        // Fehler direkt ausgeben, da hier sonst Endlosschleifen entstehen
        echo "<div style=\"color: #CC0000;\">Error: ". mysql_error(). "</div>";
        exit();
    }
    
    $g_preferences = array();
    while($prf_row = mysql_fetch_object($result))
    {
        $g_preferences[$prf_row->prf_name] = $prf_row->prf_value;
    }

    // Daten in Session-Variablen sichern
    $_SESSION['g_current_organizsation'] = $g_current_organization;
    $_SESSION['g_preferences']  = $g_preferences;
}

// Daten des angemeldeten Users auch in Session speichern
if(isset($_SESSION['g_current_user']))
{
    $g_current_user = $_SESSION['g_current_user'];
    $g_current_user->db_connection = $g_adm_con;
}
else
{
    $g_current_user  = new User($g_adm_con);
    $_SESSION['g_current_user'] = $g_current_user;
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

// Cookies einlesen
if(isset($_COOKIE['adm_session']))
{
    $g_session_id = $_COOKIE['adm_session'];
}
else
{
    $g_session_id = "";
}

if(strlen($g_session_id) > 0)
{
    // Session auf Gueltigkeit pruefen

    $sql    = "SELECT * FROM ". TBL_SESSIONS. " WHERE ses_session LIKE {0}";
    $sql    = prepareSQL($sql, array($g_session_id));
    $result = mysql_query($sql, $g_adm_con);

    db_error($result);

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
            db_error($result);
        }
        else
        {
            // User war zu lange inaktiv -> Session loeschen
            $g_current_user->clear();
            $_SESSION['g_current_user'] = $g_current_user;

            $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_session LIKE {0}";
            $sql    = prepareSQL($sql, array($g_session_id));
            $result = mysql_query($sql, $g_adm_con);

            db_error($result);
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

            db_error($result);
        }
    }
}

?>
