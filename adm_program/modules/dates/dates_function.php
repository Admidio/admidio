<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Termine
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * dat_id: ID des Termins, der angezeigt werden soll
 * mode:   1 - Neuen Termin anlegen/aendern
 *         2 - Termin loeschen
 *         4 - Termin im iCal-Format exportieren
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/classes/table_date.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}

if($_GET['mode'] != 4 || $g_preferences['enable_dates_module'] == 2)
{
    // Alle Funktionen, ausser Exportieren, duerfen nur eingeloggte User
    require('../../system/login_valid.php');
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editDates() && $_GET['mode'] != 4)
{
    $g_message->show('norights');
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_dat_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['dat_id']))
{
    if(is_numeric($_GET['dat_id']) == false)
    {
        $g_message->show('invalid');
    }
    $req_dat_id = $_GET['dat_id'];
}

if(is_numeric($_GET['mode']) == false
|| $_GET['mode'] < 1 || $_GET['mode'] > 5)
{
    $g_message->show('invalid');
}

// Terminobjekt anlegen
$date = new TableDate($g_db);

if($req_dat_id > 0)
{
    $date->readData($req_dat_id);

    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($date->editRight() == false )
    {
        $g_message->show('norights');
    }
}

if($_GET['mode'] == 1)
{
    $_SESSION['dates_request'] = $_REQUEST;

    if(strlen($_POST['dat_headline']) == 0)
    {
        $g_message->show('feld', 'Überschrift');
    }
    if(strlen($_POST['dat_description']) == 0)
    {
        $g_message->show('feld', 'Beschreibung');
    }
    if(strlen($_POST['date_from']) == 0)
    {
        $g_message->show('feld', 'Datum Beginn');
    }
    if(strlen($_POST['date_to']) == 0)
    {
        $g_message->show('feld', 'Datum Ende');
    }
    if(strlen($_POST['time_from']) == 0 && isset($_POST['dat_all_day']) == false)
    {
        $g_message->show('feld', 'Uhrzeit Beginn');
    }
    if(strlen($_POST['time_to']) == 0 && isset($_POST['dat_all_day']) == false)
    {
        $g_message->show('feld', 'Uhrzeit Ende');
    }
    if(strlen($_POST['dat_cat_id']) == 0)
    {
        $g_message->show('feld', 'Kalender');
    }

    if(isset($_POST['dat_all_day']))
    {
        $_POST['time_from'] = '00:00';
        $_POST['time_to'] = '00:00';
        $date->setValue('dat_all_day', 1);
    }

    // Datum und Uhrzeit auf Gueltigkeit pruefen

    if(dtCheckDate($_POST['date_from']))
    {
        if(strlen($_POST['time_from']) > 0 && dtCheckTime($_POST['time_from']))
        {
            // Datum & Uhrzeit formatiert zurueckschreiben
            $date_arr = explode('.', $_POST['date_from']);
            $time_arr = explode(':', $_POST['time_from']);
            $date_from_timestamp = mktime($time_arr[0],$time_arr[1],0,$date_arr[1],$date_arr[0],$date_arr[2]);
            $date_begin = date('Y-m-d H:i:s', $date_from_timestamp);
            $date->setValue('dat_begin', $date_begin);
        }
        else
        {
            $g_message->show('uhrzeit');
        }
    }
    else
    {
        $g_message->show('date_invalid', 'Datum Beginn');
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
        if(strlen($_POST['time_to']) > 0 && dtCheckTime($_POST['time_to']))
        {
            // Datum & Uhrzeit formatiert zurueckschreiben
            $date_arr = explode('.', $_POST['date_to']);
            $time_arr = explode(':', $_POST['time_to']);
            $date_to_timestamp = mktime($time_arr[0],$time_arr[1],0,$date_arr[1],$date_arr[0],$date_arr[2]);
            $date_end = date('Y-m-d H:i:s', $date_to_timestamp);
            $date->setValue('dat_end', $date_end);
        }
        else
        {
            $g_message->show('uhrzeit');
        }
    }
    else
    {
        $g_message->show('date_invalid', 'Datum Ende');
    }

    // Enddatum muss groesser oder gleich dem Startdatum sein
    if($date_from_timestamp > $date_to_timestamp)
    {
        $g_message->show('startvorend', 'Datum Ende oder Uhrzeit Ende');
    }

    if(isset($_POST['dat_global']) == false)
    {
        $_POST['dat_global'] = 0;
    }
    if(isset($_POST['dat_all_day']) == false)
    {
        $_POST['dat_all_day'] = 0;
    }

    // das Land nur zusammen mit dem Ort abspeichern
    if(strlen($_POST['dat_location']) == 0)
    {
        $_POST['dat_country'] = '';
    }

    // POST Variablen in das Termin-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'dat_') === 0)
        {
            $date->setValue($key, $value);
        }
    }

    // Daten in Datenbank schreiben
    $return_code = $date->save();

    if($return_code < 0)
    {
        $g_message->show('norights');
    }

    unset($_SESSION['dates_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header('Location: '. $_SESSION['navigation']->getUrl());
    exit();
}
elseif($_GET['mode'] == 2)
{
    // Termin loeschen, wenn dieser zur aktuellen Orga gehoert
	if($date->getValue('cat_org_id') == $g_current_organization->getValue('org_id'))
    {
        $date->delete();

        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}
elseif($_GET['mode'] == 4)
{
    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename='. $date->getValue('dat_headline'). '.ics');

    echo $date->getIcal($_SERVER['HTTP_HOST']);
    exit();
}

?>