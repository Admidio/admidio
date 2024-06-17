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
try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getRoomUuid = admFuncVariableIsValid($_GET, 'room_uuid', 'uuid');

    // only authorized users are allowed to edit the rooms
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // Create room object
    $room = new TableRooms($gDb);

    if ($getRoomUuid !== '') {
        $headline = $gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_ROOM')));

        $room->readDataByUuid($getRoomUuid);
    } else {
        $headline = $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_ROOM')));
    }

    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    if (isset($_SESSION['rooms_request'])) {
        // due to incorrect input the user has returned to this form
        // now write the previously entered contents into the object
        $roomDescription = admFuncVariableIsValid($_SESSION['rooms_request'], 'room_description', 'html');
        $room->setArray(SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['rooms_request'])));
        $room->setValue('room_description', $roomDescription);
        unset($_SESSION['rooms_request']);
    }

    // create html page object
    $page = new HtmlPage('admidio-rooms-edit', $headline);

    // show form
    $form = new HtmlForm('rooms_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/rooms/rooms_function.php', array('room_uuid' => $getRoomUuid, 'mode' => 'create')), $page);
    $form->openGroupBox('gb_name_properties', $gL10n->get('SYS_NAME') . ' &amp; ' . $gL10n->get('SYS_PROPERTIES'));
    $form->addInput(
        'room_name',
        $gL10n->get('SYS_ROOM'),
        $room->getValue('room_name'),
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
    );
    $form->addInput(
        'room_capacity',
        $gL10n->get('SYS_CAPACITY') . ' (' . $gL10n->get('SYS_SEATING') . ')',
        (int)$room->getValue('room_capacity'),
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'property' => HtmlForm::FIELD_REQUIRED)
    );
    $form->addInput(
        'room_overhang',
        $gL10n->get('SYS_OVERHANG'),
        (int)$room->getValue('room_overhang'),
        array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'helpTextId' => 'SYS_ROOM_OVERHANG')
    );
    $form->closeGroupBox();
    $form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
    $form->addEditor('room_description', '', $room->getValue('room_description'));
    $form->closeGroupBox();

    $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));
    $form->addHtml(admFuncShowCreateChangeInfoById(
        (int)$room->getValue('room_usr_id_create'),
        $room->getValue('room_timestamp_create'),
        (int)$room->getValue('dat_usr_id_change'),
        $room->getValue('room_timestamp_change')
    ));

    // add form to html page and show page
    $page->addHtml($form->show());
    $page->show();
} catch (AdmException|Exception|\Smarty\Exception $e) {
    $gMessage->show($e->getMessage());
}
