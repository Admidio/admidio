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
 * mode   : 1 - Listenkonfiguration speichern
 *          2 - Listenkonfiguration speichern und anzeigen
 *          3 - Listenkonfiguration loeschen
 *          4 - Listenkonfiguration zur Systemkonfiguration machen
 *          5 - Listenkonfiguration zur Standardkonfiguratoin machen
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/list_configuration.php");


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
$list = new ListConfiguration($g_db, $_GET["lst_id"]);

// pruefen, ob Benutzer die Rechte hat, diese Liste zu bearbeiten
if($_GET["mode"] != 2)
{
    // globale Listen duerfen nur von Webmastern editiert werden
    if($list->getValue("lst_global") == 1 && $g_current_user->isWebmaster() == false)
    {
        $g_message->show("norights");
    }
    elseif($list->getValue("lst_usr_id") != $g_current_user->getValue("usr_id")
    && $list->getValue("lst_global") == 0
    && $list->getValue("lst_id") > 0)
    {
        $g_message->show("norights");
    }
}

// Liste speichern
if ($_GET["mode"] == 1 || $_GET["mode"] == 2 || $_GET["mode"] == 4)
{
    // alle vorhandenen Spalten durchgehen
    for($number = 1; isset($_POST["column". $number]); $number++)
    {
        if(strlen($_POST["column". $number]) > 0)
        {
            $list->addColumn($number, $_POST["column". $number], $_POST["sort". $number], $_POST["condition". $number]);
        }
        else
        {
            $list->deleteColumn($number, true);
        }
    }
    
    if(isset($_GET['name']) && strlen($_GET['name']) > 0)
    {
        $list->setValue("lst_name", $_GET['name']);
    }
    
    if($_GET["mode"] == 4 && $g_current_user->isWebmaster())
    {
        $list->setValue("lst_global", 1);
    }
    else
    {
        $list->setValue("lst_global", 0);
    }
    
    $list->save();
    
    if($_GET["mode"] == 1 || $_GET["mode"] == 4)
    {
        // wieder zur eigenen Liste zurueck
        header("Location: ".$g_root_path."/adm_program/modules/lists/mylist.php?lst_id=". $list->getValue("lst_id"));
        exit();
    }
    
    // anzuzeigende Rollen in Array schreiben und in Session merken
    $role_ids[] = $_POST["rol_id"];
    $_SESSION['role_ids'] = $role_ids;

    // weiterleiten zur allgemeinen Listeseite
    header("Location: ".$g_root_path."/adm_program/modules/lists/lists_show.php?lst_id=".$list->getValue("lst_id")."&mode=html&show_members=". $_POST['show_members']);
    exit();
}
elseif ($_GET["mode"] == 3)
{
    // Listenkonfiguration loeschen
    $list->delete();

    // weiterleiten zur Listenkonfiguration
    header("Location: ".$g_root_path."/adm_program/modules/lists/mylist.php");
    exit();
}
elseif ($_GET["mode"] == 5)
{
    // Listenkonfiguration zur Standardkonfiguration machen
    $list->setDefault();

    // wieder zur eigenen Liste zurueck
    header("Location: ".$g_root_path."/adm_program/modules/lists/mylist.php?lst_id=". $list->getValue("lst_id"));
    exit();
}

?>