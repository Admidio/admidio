<?php
/******************************************************************************
 * Create and edit rooms
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 * 
 * room_id  : ID of room, that should be shown
 * headline : headline for room module
 *            (Default) SYS_ROOM
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getRoomId   = admFuncVariableIsValid($_GET, 'room_id', 'numeric');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_ROOM')));

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set headline of the script
if($getRoomId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', $getHeadline);
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', $getHeadline);
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// Create room object
$room = new TableRooms($gDb);
if($getRoomId > 0)
{
    $room->readDataById($getRoomId);
}

if(isset($_SESSION['rooms_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$room->setArray($_SESSION['rooms_request']);
    unset($_SESSION['rooms_request']);
}

// create html page object
$page = new HtmlPage();

// add headline and title of module
$page->addHeadline($headline);

// create module menu with back link
$roomsMenu = new HtmlNavbar('menu_rooms_create', $headline, $page);
$roomsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
$page->addHtml($roomsMenu->show(false));

// show form
$form = new HtmlForm('rooms_edit_form', $g_root_path.'/adm_program/modules/rooms/rooms_function.php?room_id='.$getRoomId.'&amp;mode=1', $page);
$form->openGroupBox('gb_name_properties', $gL10n->get('SYS_NAME').' &amp; '.$gL10n->get('SYS_PROPERTIES'));
    $form->addInput('room_name', $gL10n->get('SYS_ROOM'), $room->getValue('room_name'), array('maxLength' => 100, 'property' => FIELD_MANDATORY));
    $form->addInput('room_capacity', $gL10n->get('ROO_CAPACITY').' ('.$gL10n->get('ROO_SEATING').')', $room->getValue('room_capacity'),  array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'property' => FIELD_MANDATORY));
    $form->addInput('room_overhang', $gL10n->get('ROO_OVERHANG'), $room->getValue('room_overhang'), array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'helpTextIdLabel' => 'DAT_ROOM_OVERHANG'));
$form->closeGroupBox();
$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
    $form->addEditor('room_description', null, $room->getValue('room_description'), array('height' => '150px'));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($room->getValue('room_usr_id_create'), $room->getValue('room_timestamp_create'), $room->getValue('dat_usr_id_change'), $room->getValue('room_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
?>
