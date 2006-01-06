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

require_once($g_server_path. "/adm_config/config.php");
require_once($g_server_path. "/adm_program/system/function.php");
require_once($g_server_path. "/adm_program/system/date.php");
require_once($g_server_path. "/adm_program/system/string.php");
require_once($g_server_path. "/adm_program/system/tbl_users.php");
require_once($g_server_path. "/adm_program/system/tbl_organizations.php");

 // Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
	$g_tbl_praefix = "adm";

// Defines fuer alle Datenbanktabellen
define("TBL_ANNOUNCEMENTS",    $g_tbl_praefix. "_announcements");
define("TBL_DATES",            $g_tbl_praefix. "_dates");
define("TBL_MEMBERS",          $g_tbl_praefix. "_members");
define("TBL_ORGANIZATIONS",    $g_tbl_praefix. "_organizations");
define("TBL_PHOTOS",           $g_tbl_praefix. "_photos");
define("TBL_ROLE_CATEGORIES",  $g_tbl_praefix. "_role_categories");
define("TBL_ROLE_DEPENDENCIES",$g_tbl_praefix. "_role_dependencies");
define("TBL_ROLES",            $g_tbl_praefix. "_roles");
define("TBL_SESSIONS",         $g_tbl_praefix. "_sessions");
define("TBL_USERS",            $g_tbl_praefix. "_users");
define("TBL_USER_DATA",        $g_tbl_praefix. "_user_data");
define("TBL_USER_FIELDS",      $g_tbl_praefix. "_user_fields");

 // Verbindung zu Datenbank herstellen
$g_adm_con = mysql_connect ($g_adm_srv, $g_adm_usr, $g_adm_pw);
mysql_select_db($g_adm_db, $g_adm_con );

// Verbindung zur Forum-Datenbank herstellen
if($g_forum)
   $g_forum_con = mysql_connect ($g_forum_srv, $g_forum_usr, $g_forum_pw);
else
   $g_forum_con;
   
// die PHP-Session starten
session_start();

// Globale Variablen
$g_session_id      = "";
$g_session_valid   = 0;

$g_current_user  = new TblUsers($g_adm_con);

$g_current_organization = new TblOrganizations($g_adm_con);
$g_current_organization->getOrganization($g_organization);
?>
