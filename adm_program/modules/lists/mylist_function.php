<?php
/******************************************************************************
 * Verschiedene Funktionen fuer die eigene Liste
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * lst_id : ID der Liste, die aktuell bearbeitet werden soll
 * name   : (optional) die Liste wird unter diesem Namen gespeichert
 * mode   : 1 - Liste speichern
 *          2 - Liste speichern und anzeigen
 *          3 - liste loeschen
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/member_list.php");


// Uebergabevariablen pruefen
if (array_key_exists("lst_id", $_GET))
{
    if (is_numeric($_GET["lst_id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["lst_id"] = 0;
}

if (array_key_exists("mode", $_GET))
{
    if (is_numeric($_GET["mode"]) == false)
    {
        $g_message->show("invalid");
    }
}

// Mindestens ein Feld sollte zugeordnet sein
if(isset($_POST["column1"]) == false || strlen($_POST["column1"]) == 0)
{
    $g_message->show("feld", "Feld 1");
}

// Rolle muss beim Anzeigen gefuellt sein
if($_GET["mode"] == 2
&& (isset($_POST["rol_id"]) == false || $_POST["rol_id"] == 0 || is_numeric($_POST["rol_id"]) == false))
{
    $g_message->show("feld", "Rolle");
}

// Ehemalige
if(array_key_exists("former", $_POST))
{
    $member_status = 1;
}
else
{
    $member_status = 0;
}

// Listenobjekt anlegen
$list = new MemberList($g_db, $_GET["lst_id"]);

// Liste speichern
if ($_GET["mode"] == 1 || $_GET["mode"] == 2)
{
    // alle vorhandenen Spalten durchgehen
    for($number = 1; isset($_POST["column". $number]); $number++)
    {
        $list->addColumn($number, $_POST["column". $number], $_POST["sort". $number], $_POST["condition". $number]);
    }
    
    if(isset($_GET['name']) && strlen($_GET['name']) > 0)
    {
        $list->setValue("lst_name", $_GET['name']);
    }
    
    $list->save();
    
    if($_GET["mode"] == 1)
    {
        // wieder zur eigenen Liste zurueck
        $location = "Location: $g_root_path/adm_program/modules/lists/mylist.php?lst_id=". $list->getValue("lst_id");
        header($location);
        exit();
    }
    
    // anzuzeigende Rollen in Array schreiben
    $role_ids[] = $_POST["rol_id"];

    // SQL-Statement in Session-Variable schreiben
    $_SESSION['mylist_sql'] = $list->getSQL($role_ids, $member_status);

    // weiterleiten zur allgemeinen Listeseite
    $location = "Location: $g_root_path/adm_program/modules/lists/lists_show.php?type=mylist&mode=html&rol_id=".$_POST["rol_id"];
    header($location);
    exit();
}

?>