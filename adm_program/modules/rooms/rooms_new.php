<?php
/**
 ***********************************************************************************************
 * Create and edit rooms
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * room_id  : ID of room, that should be shown
 * headline : headline for room module
 *            (Default) SYS_ROOM
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getRoomId   = admFuncVariableIsValid($_GET, 'room_id',  'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_ROOM')));

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set headline of the script
if ($getRoomId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', array($getHeadline));
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', array($getHeadline));
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// Create room object
$room = new TableRooms($gDb);
if ($getRoomId > 0)
{
    $room->readDataById($getRoomId);
}

if (isset($_SESSION['rooms_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $room->setArray($_SESSION['rooms_request']);
    unset($_SESSION['rooms_request']);
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$roomsMenu = $page->getMenu();
$roomsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('rooms_edit_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/rooms/rooms_function.php', array('room_id' => $getRoomId, 'mode' => '1')), $page);
$form->openGroupBox('gb_name_properties', $gL10n->get('SYS_NAME').' &amp; '.$gL10n->get('SYS_PROPERTIES'));
$form->addInput(
    'room_name', $gL10n->get('SYS_ROOM'), $room->getValue('room_name'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'room_capacity', $gL10n->get('ROO_CAPACITY').' ('.$gL10n->get('ROO_SEATING').')', $room->getValue('room_capacity'),
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'room_overhang', $gL10n->get('ROO_OVERHANG'), $room->getValue('room_overhang'),
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'helpTextIdLabel' => 'DAT_ROOM_OVERHANG')
);
$form->closeGroupBox();
$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
$form->addEditor('room_description', '', $room->getValue('room_description'), array('height' => '150px'));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $room->getValue('room_usr_id_create'), $room->getValue('room_timestamp_create'),
    (int) $room->getValue('dat_usr_id_change'),  $room->getValue('room_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
