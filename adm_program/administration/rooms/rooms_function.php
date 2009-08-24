<?php
/******************************************************************************
 * Verschiedene Funktionen für Räume
 * 
 *  mode:   1 - Neuen Raum anlegen
 *          2 - Raum löschen 
 *****************************************************************************/
require('../../system/common.php');
require('../../system/classes/table_rooms.php');

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
$req_room_id = 0;
// Uebergabevariablen pruefen

if(isset($_GET['room_id']))
{
    if(is_numeric($_GET['room_id']) == false)
    {
        $g_message->show('invalid');
    }
    $req_room_id = $_GET['room_id'];
}

if(is_numeric($_GET['mode']) == false
|| $_GET['mode'] < 1 || $_GET['mode'] > 2)
{
    $g_message->show('invalid');
}

// Raumobjekt anlegen
$room = new TableRooms($g_db);
if($req_room_id > 0)
{
    $room->readData($req_room_id);

    
}

if($_GET['mode'] == 1)
{
    $_SESSION['rooms_request'] = $_REQUEST;

    if(strlen($_POST['room_name']) == 0)
    {
        $g_message->show('feld', 'Name/Raumnummer');
    }
    if(strlen($_POST['room_capacity']) == 0)
    {
        $g_message->show('feld', 'Kapazit&auml;t');
    }
    // POST Variablen in das Termin-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'room_') === 0)
        {
            $room->setValue($key, $value);
        }
    }
    // Daten in Datenbank schreiben
    $return_code = $room->save();
    
    unset($_SESSION['rooms_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header('Location: '. $_SESSION['navigation']->getUrl());
    exit();
}
//Löschen des Raums
else if($_GET['mode'] == 2) 
{
    $room->delete();
    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
?>
