<?php
/**
 ***********************************************************************************************
 * Create and edit rooms
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * room_uuid : UUID of room, that should be shown
 * headline  : headline for room module
 *             (Default) SYS_ROOM
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getRoomUuid = admFuncVariableIsValid($_GET, 'room_uuid', 'string');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_ROOM')));

// only authorized users are allowed to edit the rooms
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Create room object
$room = new TableRooms($gDb);

if ($getRoomUuid !== '') {
    $headline = $gL10n->get('SYS_EDIT_VAR', array($getHeadline));

    $room->readDataByUuid($getRoomUuid);
} else {
    $headline = $gL10n->get('SYS_CREATE_VAR', array($getHeadline));
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
$form = new HtmlForm('rooms_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/rooms/rooms_function.php', array('room_uuid' => $getRoomUuid, 'mode' => '1')), $page);
$form->openGroupBox('gb_name_properties', $gL10n->get('SYS_NAME').' &amp; '.$gL10n->get('SYS_PROPERTIES'));
$form->addInput(
    'room_name',
    $gL10n->get('SYS_ROOM'),
    $room->getValue('room_name'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'room_capacity',
    $gL10n->get('SYS_CAPACITY').' ('.$gL10n->get('SYS_SEATING').')',
    (int) $room->getValue('room_capacity'),
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'room_overhang',
    $gL10n->get('SYS_OVERHANG'),
    (int) $room->getValue('room_overhang'),
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'helpTextIdLabel' => 'DAT_ROOM_OVERHANG')
);
$form->closeGroupBox();
$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
$form->addEditor('room_description', '', $room->getValue('room_description'), array('height' => '150px'));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $room->getValue('room_usr_id_create'),
    $room->getValue('room_timestamp_create'),
    (int) $room->getValue('dat_usr_id_change'),
    $room->getValue('room_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
