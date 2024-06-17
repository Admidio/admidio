<?php
/**
 ***********************************************************************************************
 * Various functions for rooms handling
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * room_uuid : UUID of room, that should be shown
 * mode      : create - create or edit room
 *             delete - delete room
 ***********************************************************************************************
 */
try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getRoomUuid = admFuncVariableIsValid($_GET, 'room_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('create', 'delete')));

    // only authorized users are allowed to edit the rooms
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

    $room = new TableRooms($gDb);

    if ($getRoomUuid !== '') {
        $room->readDataByUuid($getRoomUuid);
    }

    if ($getMode === 'create') {
        $_SESSION['rooms_request'] = $_POST;

        if (!array_key_exists('room_name', $_POST) || $_POST['room_name'] === '') {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_ROOM'));
        }
        if (!array_key_exists('room_capacity', $_POST) || $_POST['room_capacity'] === '') {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_CAPACITY'));
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
    } // delete the room
    elseif ($getMode === 'delete') {
        $sql = 'SELECT 1
              FROM ' . TBL_EVENTS . '
             WHERE dat_room_id = ? -- $room->getValue(\'room_id\') ';
        $statement = $gDb->queryPrepared($sql, array($room->getValue('room_id')));

        if ($statement->rowCount() === 0) {
            $room->delete();
            echo 'done';
            // Delete successful -> return for XMLHttpRequest
        }
    }
} catch (AdmException|Exception|\Smarty\Exception $e) {
    if ($getMode === 'delete') {
        echo $e->getMessage();
    } else {
        $gMessage->show($e->getMessage());
    }
}
