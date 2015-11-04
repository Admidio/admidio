<?php
/******************************************************************************
 * Various functions for rooms handling
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * room_id : ID of room, that should be shown
 * mode :    1 - create or edit room
 *           2 - delete room
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getRoomId = admFuncVariableIsValid($_GET, 'room_id', 'numeric');
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('requireValue' => true));

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Raumobjekt anlegen
$room = new TableRooms($gDb);
if ($getRoomId > 0)
{
    $room->readDataById($getRoomId);
}

if ($getMode == 1)
{
    $_SESSION['rooms_request'] = $_POST;

    if (!array_key_exists('room_name', $_POST) || $_POST['room_name'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_ROOM')));
    }
    if (!array_key_exists('room_capacity', $_POST) || $_POST['room_capacity'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ROO_CAPACITY')));
    }

    // make html in description secure
    $_POST['room_description'] = admFuncVariableIsValid($_POST, 'room_description', 'html');

    // POST Variablen in das Termin-Objekt schreiben
    foreach ($_POST as $key => $value)
    {
        if (strpos($key, 'room_') === 0)
        {
            $room->setValue($key, $value);
        }
    }
    // Daten in Datenbank schreiben
    $return_code = $room->save();

    unset($_SESSION['rooms_request']);
    $gNavigation->deleteLastUrl();

    header('Location: '. $gNavigation->getUrl());
    exit();
}
// Löschen des Raums
elseif ($getMode == 2)
{
    $sql = 'SELECT * FROM '.TBL_DATES.' WHERE dat_room_id = '.$getRoomId;
    $result = $gDb->query($sql);
    $row = $gDb->num_rows($result);
    if($row === 0)
    {
        $room->delete();
        echo 'done';
    }
    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
}
