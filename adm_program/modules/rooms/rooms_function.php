<?php
/**
 ***********************************************************************************************
 * Various functions for rooms handling
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * room_uuid : UUID of room, that should be shown
 * mode      : 1 - create or edit room
 *             2 - delete room
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getRoomUuid = admFuncVariableIsValid($_GET, 'room_uuid', 'string');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true));

// only authorized users are allowed to edit the rooms
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    if ($getMode === 1) {
        $exception->showHtml();
    } else {
        $exception->showText();
    }
    // => EXIT
}

$room = new TableRooms($gDb);

if ($getRoomUuid !== '') {
    $room->readDataByUuid($getRoomUuid);
}

if ($getMode === 1) {
    $_SESSION['rooms_request'] = $_POST;

    if (!array_key_exists('room_name', $_POST) || $_POST['room_name'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_ROOM'))));
        // => EXIT
    }
    if (!array_key_exists('room_capacity', $_POST) || $_POST['room_capacity'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_CAPACITY'))));
        // => EXIT
    }

    // make html in description secure
    $_POST['room_description'] = admFuncVariableIsValid($_POST, 'room_description', 'html');

    // POST variables to the room object
    foreach ($_POST as $key => $value) { // TODO possible security issue
        if (str_starts_with($key, 'room_')) {
            $room->setValue($key, $value);
        }
    }

    $room->save();

    unset($_SESSION['rooms_request']);
    $gNavigation->deleteLastUrl();

    admRedirect($gNavigation->getUrl());
// => EXIT
}
// delete the room
elseif ($getMode === 2) {
    $sql = 'SELECT 1
              FROM '.TBL_DATES.'
             WHERE dat_room_id = ? -- $room->getValue(\'room_id\') ';
    $statement = $gDb->queryPrepared($sql, array($room->getValue('room_id')));

    if ($statement->rowCount() === 0) {
        $room->delete();
        echo 'done';
        // Delete successful -> return for XMLHttpRequest
    }
}
