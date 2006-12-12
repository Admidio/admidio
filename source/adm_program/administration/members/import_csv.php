<?php
/******************************************************************************
 * User werden aus einer CSV-Datei importiert
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
 
require("../../system/common.php");
require("../../system/login_valid.php");

$err_code = "";
$err_text = "";

// nur berechtigte User duerfen User importieren
if(!editUser())
{
    $g_message->show("norights");
}

// Felder prüfen

if($_POST["usr_last_name"] == 0)
{
    $g_message->show("feld", "Nachname");
}
if($_POST["usr_first_name"] == 0)
{
    $g_message->show("feld", "Vorname");
}

if(array_key_exists("first_row", $_POST))
{
    $first_row_title = true;
}
else
{
    $first_row_title = false;
}

// die organisationsspezifischen Felder in ein Array schreiben, 
// damit man spaeter schneller darauf zugreifen kann
$sql = "SELECT *
          FROM ". TBL_USER_FIELDS. "
         WHERE (  usf_org_shortname = '$g_organization'
               OR (   usf_org_shortname IS NULL
                  AND usf_type      = 'MESSENGER' ))
         ORDER BY usf_org_shortname DESC, usf_name ASC ";
$result_field = mysql_query($sql, $g_adm_con);
db_error($result_field);

while($row = mysql_fetch_object($result_field))
{
    $arr_user_fields[$row->usf_id] = $row->usf_id;
}

// jede Zeile aus der Datei einzeln durchgehen und den Benutzer in der DB anlegen
$line = reset($_SESSION["file_lines"]);
$user = new User($g_adm_con);
$start_row    = 0;
$count_import = 0;

if($first_row_title == true)
{
    // erste Zeile ueberspringen, da hier die Spaltenbezeichnungen stehen
    $line = next($_SESSION["file_lines"]);
    $start_row = 1;
}

for($i = $start_row; $i < count($_SESSION["file_lines"]); $i++)
{
    $user->clear();
    $arr_columns = explode($_SESSION["value_separator"], $line);
    $value = reset($arr_columns);    

    for($j = 1; $j <= count($arr_columns); $j++)
    {
        // Hochkomma und Spaces entfernen
        $value = trim(str_replace("\"", "", $value));
        
        if($j == $_POST["usr_last_name"])
        {
            $user->last_name = $value;
        }
        else if($j == $_POST["usr_first_name"])
        {
            $user->first_name = $value;
        }
        else if($j == $_POST["usr_address"])
        {
            $user->address = $value;
        }
        else if($j == $_POST["usr_zip_code"])
        {
            $user->zip_code = $value;
        }
        else if($j == $_POST["usr_city"])
        {
            $user->city = $value;
        }
        else if($j == $_POST["usr_country"])
        {
            $user->country = $value;
        }
        else if($j == $_POST["usr_phone"])
        {
            $user->phone = $value;
        }
        else if($j == $_POST["usr_mobile"])
        {
            $user->mobile = $value;
        }
        else if($j == $_POST["usr_fax"])
        {
            $user->fax = $value;
        }
        else if($j == $_POST["usr_email"])
        {
            $user->email = $value;
        }
        else if($j == $_POST["usr_homepage"])
        {
            $user->homepage = $value;
        }
        else if($j == $_POST["usr_birthday"])
        {
            if(strlen($value) > 0
            && dtCheckDate($value))
            {
                $user->birthday = dtFormatDate($value, "Y-m-d");
            }
        }
        else if($j == $_POST["usr_gender"])
        {
            $user->gender = $value;
        }
        
        $value = next($arr_columns);
    }
    
    // schauen, ob schon User mit dem Namen existieren
    $sql = "SELECT usr_id FROM ". TBL_USERS. "
             WHERE usr_last_name  = '$user->last_name'
               AND usr_first_name = '$user->first_name'
               AND usr_valid      = 1 ";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $dup_users = mysql_num_rows($result);
    
    if($dup_users > 0 && $_SESSION["user_import_mode"] == 3)
    {
        // alle vorhandene User mit dem Namen loeschen
        while($row = mysql_fetch_object($result))
        {            
            $duplicate_user = new User($g_adm_con);
            $duplicate_user->GetUser($row->usr_id);
            $duplicate_user->delete();
        }
    }
        
    if( $dup_users == 0
    || ($dup_users  > 0 && $_SESSION["user_import_mode"] > 1) )
    {
        // Usersatz anlegen
        $user->insert($g_current_user->id);
        $count_import++;
        
        $usf_id = reset($arr_user_fields);    

        for($j = 1; $j <= count($arr_user_fields); $j++)
        {        
            // wenn dem Orga-Feld eine Spalte aus der Datei zugeordnet wurde
            if($_POST[$usf_id] > 0)
            {
                $arr_index = $_POST[$usf_id]-1;
                $value = $arr_columns["$arr_index"];
                $value = trim(str_replace("\"", "", $value));
                
                if(strlen($value) > 0)
                {
                    // Inhalt des Orga-Feldes schreiben
                    $sql = "INSERT INTO ". TBL_USER_DATA. " (usd_usr_id, usd_usf_id, usd_value)
                                                     VALUES ($user->id, $usf_id, '$value') ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);
                }
            }
            $usf_id = next($arr_user_fields);
        }

        // Rolle dem User zuordnen
        $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin, mem_valid)
                                       VALUES (". $_SESSION['rol_id']. ", $user->id, NOW(), 1) ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);        
    }
    
    $line = next($_SESSION["file_lines"]);
}

// Session-Variablen wieder initialisieren
$_SESSION["role"]            = "";
$_SESSION["user_import_mode"] = "";
$_SESSION["file_lines"]      = "";
$_SESSION["value_separator"] = "";

$g_message->setForwardUrl("$g_root_path/adm_program/administration/members/members.php");
$g_message->show("import", $count_import);
?>