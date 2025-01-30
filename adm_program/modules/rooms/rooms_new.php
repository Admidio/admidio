<?php
/**
 ***********************************************************************************************
 * Create and edit rooms
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * room_uuid : UUID of room, that should be shown
 ***********************************************************************************************
 */

use Admidio\Events\Entity\Room;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\Form;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getRoomUuid = admFuncVariableIsValid($_GET, 'room_uuid', 'uuid');

    // only authorized users are allowed to edit the rooms
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Create room object
    $room = new Room($gDb);

    if ($getRoomUuid !== '') {
        $headline = $gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_ROOM')));

        $room->readDataByUuid($getRoomUuid);
    } else {
        $headline = $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_ROOM')));
    }

    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage('admidio-rooms-edit', $headline);

    ChangelogService::displayHistoryButton($page, 'rooms', 'rooms', !empty($getRoomUuid), array('uuid' => $getRoomUuid));

    // show form
    $form = new Form(
        'adm_rooms_edit_form',
        'modules/rooms.edit.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/rooms/rooms_function.php', array('room_uuid' => $getRoomUuid, 'mode' => 'edit')),
        $page
    );
    $form->addInput(
        'room_name',
        $gL10n->get('SYS_ROOM'),
        $room->getValue('room_name'),
        array('maxLength' => 50, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addInput(
        'room_capacity',
        $gL10n->get('SYS_CAPACITY') . ' (' . $gL10n->get('SYS_SEATING') . ')',
        (int)$room->getValue('room_capacity'),
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addInput(
        'room_overhang',
        $gL10n->get('SYS_OVERHANG'),
        (int)$room->getValue('room_overhang'),
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'helpTextId' => 'SYS_ROOM_OVERHANG')
    );
    $form->addEditor('room_description', '', $room->getValue('room_description'));
    $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

    $page->assignSmartyVariable('nameUserCreated', $room->getNameOfCreatingUser());
    $page->assignSmartyVariable('timestampUserCreated', $room->getValue('ann_timestamp_create'));
    $page->assignSmartyVariable('nameLastUserEdited', $room->getNameOfLastEditingUser());
    $page->assignSmartyVariable('timestampLastUserEdited', $room->getValue('ann_timestamp_change'));
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
