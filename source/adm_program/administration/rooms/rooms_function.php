<?php
/******************************************************************************
 * Verschiedene Funktionen zur Pflege der Raeume
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 * 
 *  mode:   1 - Neuen Raum anlegen
 *          2 - Raum löschen 
 *****************************************************************************/

require('../../system/common.php');
require('../../system/classes/table_rooms.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->isWebmaster())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_room_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['room_id']))
{
    if(is_numeric($_GET['room_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_room_id = $_GET['room_id'];
}

if(is_numeric($_GET['mode']) == false
|| $_GET['mode'] < 1 || $_GET['mode'] > 2)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
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
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_ROOM')));
    }
    if(strlen($_POST['room_capacity']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('ROO_CAPACITY')));
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
    $sql = 'SELECT * FROM '.TBL_DATES.' WHERE dat_room_id = "'.$_GET['room_id'].'"';
    $result = $g_db->query($sql);
    $row = $g_db->num_rows($result);
    if($row == 0)
    {
        $room->delete();
        echo 'done';
    }
    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    
}
?>
