<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Ankuendigungen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * ann_id:   ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neue Ankuendigung anlegen/aendern
 *           2 - Ankuendigung loeschen
 *           4 - Frage, ob Ankuendigung geloescht werden soll
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/announcement.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// pruefen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editAnnouncements())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["ann_id"]) && is_numeric($_GET["ann_id"]) == false)
{
    $g_message->show("invalid");
}

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 4)
{
    $g_message->show("invalid");
}

// Ankuendigungsobjekt anlegen
$announcement = new Announcement($g_db);

if($_GET["ann_id"] > 0)
{
    $announcement->readData($_GET["ann_id"]);
    
    // Pruefung, ob die Ankuendigung zur aktuellen Organisation gehoert bzw. global ist
    if($announcement->editRight() == false)
    {
        $g_message->show("norights");
    }
}

$_SESSION['announcements_request'] = $_REQUEST;

if($_GET["mode"] == 1)
{
    if(strlen($_POST['ann_headline']) == 0)
    {
        $g_message->show("feld", "Überschrift");
    }
    if(strlen($_POST['ann_description']) == 0)
    {
        $g_message->show("feld", "Beschreibung");
    }

    if(isset($_POST['ann_global']) == false)
    {
        $_POST['ann_global'] = 0;
    }
    
    // POST Variablen in das Ankuendigungs-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, "ann_") === 0)
        {
            $announcement->setValue($key, $value);
        }
    }
    
    // Daten in Datenbank schreiben
    $return_code = $announcement->save();

    if($return_code < 0)
    {
        $g_message->show("norights");
    }
    
    unset($_SESSION['announcements_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header("Location: ". $_SESSION['navigation']->getUrl());
    exit();
}
elseif($_GET["mode"] == 2)
{
    // Ankuendigung loeschen, wenn sie zur aktuellen Orga gehoert
    if($announcement->getValue("ann_org_shortname") == $g_current_organization->getValue("org_shortname"))
    {
        $announcement->delete();
    
        $g_message->setForwardUrl($_SESSION['navigation']->getUrl());
        $g_message->show("delete");
    }
    else
    {
        $g_message->show("norights");
    }
}
elseif($_GET["mode"] == 4)
{
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/announcements/announcements_function.php?ann_id=". $_GET["ann_id"]. "&amp;mode=2");
    $g_message->show("delete_announcement", $announcement->getValue("ann_headline"), "Löschen");
}

?>