<?php
/******************************************************************************
 * Dieses Script setzt das SQL-Statement fuer die myList zusammen und
 * uebergibt es an das allgemeine Listen-Script
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

require("../../system/common.php");
require("../../system/login_valid.php");
require("search_parser_class.php");

$_SESSION['mylist_request'] = $_REQUEST;

$req_rol_id  = 0;
$sql_select  = "";
$sql_join    = "";
$sql_where   = "";
$sql_orderby = "";

// Uebergabevariablen pruefen

if(isset($_POST["column1"]) == false || strlen($_POST["column1"]) == 0)
{
    $g_message->show("feld", "Feld 1");
}

if(isset($_POST["rol_id"]) == false || $_POST["rol_id"] == 0 || is_numeric($_POST["rol_id"]) == false)
{
    $g_message->show("feld", "Rolle");
}
else
{
    // als erstes wird die Rolle uebergeben
    $req_rol_id = $_POST["rol_id"];
}

// Ehemalige
if(!array_key_exists("former", $_POST))
{
    $act_members = 1;
}
else
{
    $act_members = 0;
}

$value = reset($_POST);
$key   = key($_POST);

// Felder zusammenstringen
for($i = 0; $i < count($_POST); $i++)
{
    if(strlen($value) > 0)
    {
        if(substr_count($key, "column") > 0)
        {
            if(strlen($sql_select) > 0) 
            {
                $sql_select = $sql_select. ", ";
            }
            if(is_numeric($value))
            {
                // ein benutzerdefiniertes Feld
                $table_alias = "f". $value;
                
                // JOIN - Syntax erstellen
                $sql_join = $sql_join. " LEFT JOIN ". TBL_USER_DATA ." $table_alias
                                           ON $table_alias.usd_usr_id = usr_id
                                          AND $table_alias.usd_usf_id = $value ";
                
                // hierbei wird die usf_id als Tabellen-Alias benutzt und vorangestellt
                $act_field = "$table_alias.usd_value";
            }
            else
            {
                $act_field = $value;
            }

            $act_field_name = $value;   // im Gegensatz zu $act_field steht hier IMMER der Feldname drin
            $sql_select = $sql_select. $act_field;
        }
        elseif(substr_count($key, "sort") > 0)
        {
            if(strlen($sql_orderby) > 0) 
            {  
                $sql_orderby = $sql_orderby. ", ";
            }
            $sql_orderby = $sql_orderby. $act_field. " ". $value;
        }
        elseif(substr_count($key, "condition") > 0)
        {
            if(strpos($act_field, ".") > 0)
            {
                // ein benutzerdefiniertes Feld
                
                // Datentyp ermitteln
                $sql = "SELECT usf_type 
						  FROM ". TBL_USER_FIELDS. ", ". TBL_CATEGORIES. "
                         WHERE usf_cat_id = cat_id
						   AND (  cat_org_id = $g_current_organization->id
                               OR cat_org_id IS NULL )
                           AND usf_id     = '$act_field_name' ";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);
                
                $row = mysql_fetch_object($result);
                
                if($row->usf_type == "CHECKBOX")
                {
                    $type = "checkbox";
                    $value = strtoupper($value);
                    
                    // Ja bzw. Nein werden durch 1 bzw. 0 ersetzt, damit Vergleich in DB gemacht werden kann
                    if($value == "JA" || $value == "1" || $value == "TRUE")
                    {
                        $value = "1";
                    }
                    elseif($value == "NEIN" || $value == "0" || $value == "FALSE")
                    {
                        $value = "0";
                    }
                }
                elseif($row->usf_type == "NUMERIC")
                {
                    $type = "int";
                }
                elseif($row->usf_type == "DATE")
                {
                    $type = "date";
                }
                else
                {
                    $type = "string";
                }
            }
            else
            {
                $sql = "SELECT $act_field FROM ". TBL_USERS. " LIMIT 1, 1 ";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);
                $type   = mysql_field_type($result, 0);
            }
            $parser    = new CParser;
            $sql_where = $sql_where. $parser->makeSqlStatement($value, $act_field, $type);
        }
    }
    else
    {
        if(substr_count($key, "column") > 0)
        {
            $act_field = "";
        }
    }

    $value = next($_POST);
    $key   = key($_POST);
}

$main_sql = "SELECT mem_leader, usr_id, $sql_select
               FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    $sql_join
              WHERE rol_id     = $req_rol_id
                AND rol_valid  = 1
		        AND rol_cat_id = cat_id
				AND rol_org_id = $g_current_organization->id
                AND mem_rol_id = rol_id
                AND mem_valid  = $act_members
                AND mem_usr_id = usr_id
                AND usr_valid  = 1
                    $sql_where ";
if(strlen($sql_orderby) > 0)
{
    $main_sql = $main_sql. " ORDER BY mem_leader DESC, $sql_orderby ";
}

// SQL-Statement in Session-Variable schreiben
$_SESSION['mylist_sql'] = $main_sql;
//echo $main_sql; exit();
// weiterleiten zur allgemeinen Listeseite
$location = "Location: $g_root_path/adm_program/modules/lists/lists_show.php?type=mylist&mode=html&rol_id=$req_rol_id";
header($location);
exit();

?>