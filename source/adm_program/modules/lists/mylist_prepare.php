<?php
/******************************************************************************
 * Dieses Script setzt das SQL-Statement fuer die myList zusammen und
 * uebergibt es an das allgemeine Listen-Script
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/condition_parser_class.php");

$_SESSION['mylist_request'] = $_REQUEST;

$req_rol_id  = 0;
$sql_select  = "";
$sql_join    = "";
$sql_where   = "";
$sql_orderby = "";

// Uebergabevariablen pruefen

if(isset($_POST["column0"]) == false || strlen($_POST["column0"]) == 0)
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
$field_row = 0;

// Felder zusammenstringen
foreach($_POST as $key => $value)
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
                // dynamisches Profilfeld
                $table_alias = "row". $field_row. "id". $value;
                
                // JOIN - Syntax erstellen
                $sql_join = $sql_join. " LEFT JOIN ". TBL_USER_DATA ." $table_alias
                                           ON $table_alias.usd_usr_id = usr_id
                                          AND $table_alias.usd_usf_id = $value ";
                
                // hierbei wird die usf_id als Tabellen-Alias benutzt und vorangestellt
                $act_field = "$table_alias.usd_value";
            }
            else
            {
                // Spezialfelder z.B. usr_photo, mem_begin ...
                $act_field = $value;
            }

            $act_field_id = $value;   // im Gegensatz zu $act_field steht hier IMMER der Feldname drin
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
                
                if($g_current_user->getPropertyById($act_field_id, "usf_type") == "CHECKBOX")
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
                elseif($g_current_user->getPropertyById($act_field_id, "usf_type") == "NUMERIC")
                {
                    $type = "int";
                }
                elseif($g_current_user->getPropertyById($act_field_id, "usf_type") == "DATE")
                {
                    $type = "date";
                }
                else
                {
                    $type = "string";
                }
            }
            elseif($act_field == "mem_begin" || $act_field == "mem_begin")
            {
                $type = "date";
            }
            elseif($act_field == "usr_login_name")
            {
                $type = "string";
            }
            elseif($act_field == "usr_photo")
            {
                $type = "";
            }
            
            // Bedingungen aus dem Bedingungsfeld als SQL darstellen
            $parser    = new ConditionParser;
            $sql_where = $sql_where. $parser->makeSqlStatement($value, $act_field, $type);
            if($parser->error() <> 0)
            {
                $g_message->show("mylist_condition");
            }
        }
    }
    else
    {
        if(substr_count($key, "column") > 0)
        {
            $act_field = "";
        }
    }
    $field_row++;
}

$main_sql = "SELECT mem_leader, usr_id, $sql_select
               FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    $sql_join
              WHERE rol_id     = $req_rol_id
                AND rol_valid  = 1
                AND rol_cat_id = cat_id
                AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                AND mem_rol_id = rol_id
                AND mem_valid  = $act_members
                AND mem_usr_id = usr_id
                AND usr_valid  = 1
                    $sql_where 
              ORDER BY mem_leader DESC ";
if(strlen($sql_orderby) > 0)
{
    $main_sql = $main_sql. ", ". $sql_orderby;
}
// SQL-Statement in Session-Variable schreiben
$_SESSION['mylist_sql'] = $main_sql;

// weiterleiten zur allgemeinen Listeseite
$location = "Location: $g_root_path/adm_program/modules/lists/lists_show.php?type=mylist&mode=html&rol_id=$req_rol_id";
header($location);
exit();

?>