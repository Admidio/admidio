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
 * mode      : edit - create or edit room
 *             delete - delete room
 ***********************************************************************************************
 */

use Admidio\Events\Entity\Room;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getRoomUuid = admFuncVariableIsValid($_GET, 'room_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete')));

    // only authorized users are allowed to edit the rooms
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $room = new Room($gDb);

    if ($getRoomUuid !== '') {
        $room->readDataByUuid($getRoomUuid);
    }

    if ($getMode === 'edit') {
        // check form field input and sanitized it from malicious content
        $roomsEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $roomsEditForm->validate($_POST);

        // write form values into the room object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'room_')) {
                $room->setValue($key, $value);
            }
        }

        $room->save();

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } // delete the room
    elseif ($getMode === 'delete') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        $sql = 'SELECT 1
              FROM ' . TBL_EVENTS . '
             WHERE dat_room_id = ? -- $room->getValue(\'room_id\') ';
        $statement = $gDb->queryPrepared($sql, array($room->getValue('room_id')));

        if ($statement->rowCount() === 0) {
            $room->delete();
        } else {
            throw new Exception('SYS_ROOM_COULD_NOT_BE_DELETED');
        }
        echo json_encode(array('status' => 'success'));
        exit();
    }
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
