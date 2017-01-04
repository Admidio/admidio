<?php
/**
 ***********************************************************************************************
 * Overview of room management
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// only administrators are allowed to manage rooms
if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

unset($_SESSION['rooms_request']);

$headline = $gL10n->get('ROO_ROOM_MANAGEMENT');
$textRoom = $gL10n->get('SYS_ROOM');

// Navigation weiterfuehren
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

// get module menu
$roomsMenu = $page->getMenu();
// show back link
$roomsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
// show link to create new room
$roomsMenu->addItem('menu_item_new_room', ADMIDIO_URL.FOLDER_MODULES.'/rooms/rooms_new.php?headline='.$textRoom,
                    $gL10n->get('SYS_CREATE_VAR', $textRoom), 'add.png');

if($gPreferences['system_show_create_edit'] == 1)
{
    // show firstname and lastname of create and last change user
    $additionalFields = '
        cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name,
        cha_firstname.usd_value || \' \' || cha_surname.usd_value AS change_name ';
    $additionalTables = '
        LEFT JOIN '. TBL_USER_DATA .' cre_surname
               ON cre_surname.usd_usr_id = room_usr_id_create
              AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
        LEFT JOIN '. TBL_USER_DATA .' cre_firstname
               ON cre_firstname.usd_usr_id = room_usr_id_create
              AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
        LEFT JOIN '. TBL_USER_DATA .' cha_surname
               ON cha_surname.usd_usr_id = room_usr_id_change
              AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
        LEFT JOIN '. TBL_USER_DATA .' cha_firstname
               ON cha_firstname.usd_usr_id = room_usr_id_change
              AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
}
else
{
    // show username of create and last change user
    $additionalFields = '
        cre_username.usr_login_name AS create_name,
        cha_username.usr_login_name AS change_name ';
    $additionalTables = '
        LEFT JOIN '. TBL_USERS .' cre_username
               ON cre_username.usr_id = room_usr_id_create
        LEFT JOIN '. TBL_USERS .' cha_username
               ON cha_username.usr_id = room_usr_id_change ';
}

// read rooms from database
$sql = 'SELECT room.*, '.$additionalFields.'
          FROM '.TBL_ROOMS.' room
               '.$additionalTables.'
      ORDER BY room_name';
$roomsStatement = $gDb->query($sql);

if($roomsStatement->rowCount() === 0)
{
    // Keine Räume gefunden
    $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
}
else
{
    $room = new TableRooms($gDb);
    // Räume auflisten
    while($row = $roomsStatement->fetch())
    {
        // GB-Objekt initialisieren und neuen DS uebergeben
        $room->clear();
        $room->setArray($row);

        $page->addHtml('
        <div class="panel panel-primary" id="room_'.$room->getValue('room_id').'">
            <div class="panel-heading">
                <div class="pull-left">
                    <img class="admidio-panel-heading-icon" src="'. THEME_URL. '/icons/home.png" alt="'. $room->getValue('room_name'). '" />'
                     . $room->getValue('room_name').'
                </div>
                <div class="pull-right text-right">
                    <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/rooms/rooms_new.php?room_id='. $room->getValue('room_id'). '&amp;headline='.$textRoom.'"><img
                        src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                    <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                        href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=room&amp;element_id=room_'.
                        $room->getValue('room_id').'&amp;name='.urlencode($room->getValue('room_name')).'&amp;database_id='.$room->getValue('room_id').'"><img
                        src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>
                </div>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-2 col-xs-4">'.$gL10n->get('ROO_CAPACITY').'</div>
                    <div class="col-sm-4 col-xs-8"><strong>'.$room->getValue('room_capacity').'</strong></div>');

                    if($room->getValue('room_overhang') > 0)
                    {
                        $page->addHtml('<div class="col-sm-2 col-xs-4">'.$gL10n->get('ROO_OVERHANG').'</div>
                        <div class="col-sm-4 col-xs-8"><strong>'.$room->getValue('room_overhang').'</strong></div>');
                    }
                    else
                    {
                        $page->addHtml('<div class="col-sm-2 col-xs-4">&nbsp;</div>
                        <div class="col-sm-4 col-xs-8">&nbsp;</div>');
                    }

                    //echo $table->getHtmlTable();
                    $page->addHtml('</div>');

                if(strlen($room->getValue('room_description')) > 0)
                {
                    $page->addHtml($room->getValue('room_description'));
                }
            $page->addHtml('</div>
            <div class="panel-footer">'.
                // show information about user who creates the recordset and changed it
                admFuncShowCreateChangeInfoByName(
                    $row['create_name'], $room->getValue('room_timestamp_create'),
                    $row['change_name'], $room->getValue('room_timestamp_change'),
                    $room->getValue('room_usr_id_create'), $room->getValue('room_usr_id_change')
                ).'
            </div>
        </div>');
    }
}

// show html of complete page
$page->show();
