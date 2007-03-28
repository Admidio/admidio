<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Termine
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * dat_id: ID des Termins, der angezeigt werden soll
 * mode:   1 - Neuen Termin anlegen
 *         2 - Termin loeschen
 *         3 - Termin aendern
 *         4 - Termin im iCal-Format exportieren
 *         5 - Frage, ob Termin geloescht werden soll
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
require("../../system/date_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

if($_GET["mode"] != 4)
{
    // Alle Funktionen, ausser Exportieren, duerfen nur eingeloggte User
    require("../../system/login_valid.php");
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!editDate() && $_GET["mode"] != 4)
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_dat_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['dat_id']))
{
    if(is_numeric($_GET['dat_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_dat_id = $_GET['dat_id'];
}

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 5)
{
    $g_message->show("invalid");
}

// Rollenobjekt anlegen
$date = new Date($g_adm_con);

if($req_dat_id > 0)
{
    $date->getDate($req_dat_id);
    
    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($date->getValue("dat_org_shortname") != $g_organization
    && $date->getValue("dat_global") == 0 )
    {
        $g_message->show("norights");
    }
}

$_SESSION['dates_request'] = $_REQUEST;

if($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
    if(strlen(trim($_POST['dat_headline'])) == 0)
    {
        $g_message->show("feld", "&Uuml;berschrift");
    }
    if(strlen(trim($_POST['dat_description'])) == 0)
    {
        $g_message->show("feld", "Beschreibung");
    }
    if(strlen(trim($_POST['date_from'])) == 0)
    {
        $g_message->show("feld", "Datum Beginn");
    }
    
    // Datum und Uhrzeit auf Gueltigkeit pruefen
    
    if(dtCheckDate($_POST['date_from']))
    {
        if(dtCheckTime($_POST['time_from'])
        || strlen($_POST['time_from']) > 0)
        {
            $date_begin = dtFormatDate($_POST['date_from'], "Y-m-d"). " ". dtFormatTime($_POST['time_from']);
            $date->setValue("dat_begin", $date_begin);
        }
        else
        {
            $g_message->show("uhrzeit");
        }
    }
    else
    {
        $g_message->show("datum", "Datum Beginn");
    }

    // wenn Datum-bis nicht gefüllt ist, dann mit Datum-von nehmen
    if(strlen($_POST['date_to'])   == 0)
    {
        $_POST['date_to'] = $_POST['date_from'];
    }
    if(strlen($_POST['time_to']) == 0)
    {
        $_POST['time_to'] = $_POST['time_from'];
    }

    if(dtCheckDate($_POST['date_to']))
    {
        if(dtCheckTime($_POST['time_to'])
        || $_POST['time_to'] == "")
        {
            $date_end = dtFormatDate($_POST['date_to'], "Y-m-d"). " ". dtFormatTime($_POST['time_to']);
            $date->setValue("dat_end", $date_end);
        }
        else
        {
            $g_message->show("uhrzeit");
        }
    }
    else
    {
        $g_message->show("datum", "Datum Ende");
    }

    // Enddatum muss groesser oder gleich dem Startdatum sein
    if(strcmp($_POST['date_from'],$_POST['date_to']) > 0)
    {
        $g_message->show("datum", "Datum Ende oder Uhrzeit Ende");
    }

    if(isset($_POST['dat_global']) == false)
    {
        $_POST['dat_global'] = 0;
    }

    // POST Variablen in das Role-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, "dat_") === 0)
        {
            $date->setValue($key, $value);
        }
    }
    
    // Daten in Datenbank schreiben
    if($req_dat_id > 0)
    {
        $return_code = $date->update($g_current_user->id);
    }
    else
    {
        $return_code = $date->insert($g_current_user->id);
    }

    if($return_code < 0)
    {
        $g_message->show("norights");
    }

    unset($_SESSION['dates_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header("Location: ". $_SESSION['navigation']->getUrl());
    exit();
}
elseif($_GET["mode"] == 2)
{
    $role->delete();
    
    $g_message->setForwardUrl($_SESSION['navigation']->getUrl());
    $g_message->show("delete");
}
elseif($_GET["mode"] == 4)
{
    // Termindaten aus Datenbank holen
    $date = new Date($g_adm_con);
    $date->getDate($_GET["dat_id"]);

    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename="'. $date->getValue("dat_begin"). '.ics"');

    echo $date->getIcal($_SERVER['HTTP_HOST']);
    exit();
}
elseif($_GET["mode"] == 5)
{
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/dates/dates_function.php?dat_id=". $_GET["dat_id"]. "&amp;mode=2");
    $g_message->show("delete_date", utf8_encode($row_dat->dat_headline), "Löschen");
}

?>