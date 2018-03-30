<?php
/**
 ***********************************************************************************************
 * Various functions for rooms handling
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * room_id : ID of room, that should be shown
 * mode :    1 - create or edit room
 *           2 - delete room
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getRoomId = admFuncVariableIsValid($_GET, 'room_id', 'int');
$getMode   = admFuncVariableIsValid($_GET, 'mode',    'int', array('requireValue' => true));

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Raumobjekt anlegen
$room = new TableRooms($gDb);
if ($getRoomId > 0)
{
    $room->readDataById($getRoomId);
}

if ($getMode === 1)
{
    $_SESSION['rooms_request'] = $_POST;

    if (!array_key_exists('room_name', $_POST) || $_POST['room_name'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_ROOM'))));
        // => EXIT
    }
    if (!array_key_exists('room_capacity', $_POST) || $_POST['room_capacity'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ROO_CAPACITY'))));
        // => EXIT
    }

    // make html in description secure
    $_POST['room_description'] = admFuncVariableIsValid($_POST, 'room_description', 'html');

    // POST Variablen in das Termin-Objekt schreiben
    foreach ($_POST as $key => $value) // TODO possible security issue
    {
        if (StringUtils::strStartsWith($key, 'room_'))
        {
            $room->setValue($key, $value);
        }
    }
    // Daten in Datenbank schreiben
    $room->save();

    unset($_SESSION['rooms_request']);
    $gNavigation->deleteLastUrl();

    admRedirect($gNavigation->getUrl());
    // => EXIT
}
// Löschen des Raums
elseif ($getMode === 2)
{
    $sql = 'SELECT 1
              FROM '.TBL_DATES.'
             WHERE dat_room_id = ?';
    $statement = $gDb->queryPrepared($sql, array($getRoomId));

    if($statement->rowCount() === 0)
    {
        $room->delete();
        echo 'done';
    }
    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
}
