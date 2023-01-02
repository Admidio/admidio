<?php
/**
 ***********************************************************************************************
 * Overview of room management
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// only administrators are allowed to manage rooms
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

unset($_SESSION['rooms_request']);

$headline = $gL10n->get('SYS_ROOM_MANAGEMENT');
$textRoom = $gL10n->get('SYS_ROOM');

// Navigation weiterfuehren
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage('admidio-rooms', $headline);

// show link to create new room
$page->addPageFunctionsMenuItem(
    'menu_item_new_room',
    $gL10n->get('SYS_CREATE_VAR', array($textRoom)),
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/rooms/rooms_new.php', array('headline' => $textRoom)),
    'fa-plus-circle'
);

if ((int) $gSettingsManager->get('system_show_create_edit') === 1) {
    // show firstname and lastname of create and last change user
    $additionalFields = '
        cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name,
        cha_firstname.usd_value || \' \' || cha_surname.usd_value AS change_name,
        cre_user.usr_uuid AS create_uuid, cha_user.usr_uuid AS change_uuid ';
    $additionalTables = '
        LEFT JOIN ' . TBL_USERS . ' AS cre_user
               ON cre_user.usr_id = room_usr_id_create
        LEFT JOIN ' . TBL_USER_DATA .' AS cre_surname
               ON cre_surname.usd_usr_id = room_usr_id_create
              AND cre_surname.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        LEFT JOIN ' . TBL_USER_DATA .' AS cre_firstname
               ON cre_firstname.usd_usr_id = room_usr_id_create
              AND cre_firstname.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
        LEFT JOIN ' . TBL_USERS . ' AS cha_user
               ON cha_user.usr_id = room_usr_id_change
        LEFT JOIN ' . TBL_USER_DATA .' AS cha_surname
               ON cha_surname.usd_usr_id = room_usr_id_change
              AND cha_surname.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        LEFT JOIN ' . TBL_USER_DATA .' AS cha_firstname
               ON cha_firstname.usd_usr_id = room_usr_id_change
              AND cha_firstname.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')';
    $queryParams = array(
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
    );
} else {
    // show username of create and last change user
    $additionalFields = '
        cre_user.usr_login_name AS create_name,
        cha_user.usr_login_name AS change_name,
        cre_user.usr_uuid AS create_uuid, cha_user.usr_uuid AS change_uuid ';
    $additionalTables = '
        LEFT JOIN '. TBL_USERS .' AS cre_user
               ON cre_user.usr_id = room_usr_id_create
        LEFT JOIN '. TBL_USERS .' AS cha_user
               ON cha_user.usr_id = room_usr_id_change ';
    $queryParams = array();
}

// read rooms from database
$sql = 'SELECT room.*, '.$additionalFields.'
          FROM '.TBL_ROOMS.' AS room
               '.$additionalTables.'
      ORDER BY room_name';
$roomsStatement = $gDb->queryPrepared($sql, $queryParams);

if ($roomsStatement->rowCount() === 0) {
    // Keine Räume gefunden
    $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
} else {
    $room = new TableRooms($gDb);
    // Räume auflisten
    while ($row = $roomsStatement->fetch()) {
        // GB-Objekt initialisieren und neuen DS uebergeben
        $room->clear();
        $room->setArray($row);

        $page->addHtml('
        <div class="card admidio-blog" id="room_'.$room->getValue('room_uuid').'">
            <div class="card-header">
                <i class="fas fa-home"></i>' . $room->getValue('room_name') . '
                <div class="dropdown float-right">
                    <a class="" href="#" role="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-chevron-circle-down" data-toggle="tooltip"></i></a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/rooms/rooms_new.php', array('room_uuid' => $room->getValue('room_uuid'), 'headline' => $textRoom)).'">
                            <i class="fas fa-edit" data-toggle="tooltip"></i> '.$gL10n->get('SYS_EDIT').'</a>
                        <a class="dropdown-item btn openPopup" href="javascript:void(0);"
                            data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'room', 'element_id' => 'room_'.$room->getValue('room_uuid'), 'name' => $room->getValue('room_name'), 'database_id' => $room->getValue('room_uuid'))).'">
                            <i class="fas fa-trash-alt" data-toggle="tooltip"></i> '.$gL10n->get('SYS_DELETE').'</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-2 col-4">'.$gL10n->get('SYS_CAPACITY').'</div>
                    <div class="col-sm-4 col-8"><strong>'.(int) $room->getValue('room_capacity').'</strong></div>');

        if ($room->getValue('room_overhang') > 0) {
            $page->addHtml('<div class="col-sm-2 col-4">'.$gL10n->get('SYS_OVERHANG').'</div>
                        <div class="col-sm-4 col-8"><strong>'.(int) $room->getValue('room_overhang').'</strong></div>');
        } else {
            $page->addHtml('<div class="col-sm-2 col-4">&nbsp;</div>
                        <div class="col-sm-4 col-8">&nbsp;</div>');
        }

        //echo $table->getHtmlTable();
        $page->addHtml('</div>');

        if (strlen($room->getValue('room_description')) > 0) {
            $page->addHtml($room->getValue('room_description'));
        }
        $page->addHtml('</div>
            <div class="card-footer">'.
                // show information about user who creates the recordset and changed it
                admFuncShowCreateChangeInfoByName(
                    $row['create_name'],
                    $room->getValue('room_timestamp_create'),
                    $row['change_name'],
                    $room->getValue('room_timestamp_change'),
                    $room->getValue('create_uuid'),
                    $room->getValue('change_uuid')
                ).'
            </div>
        </div>');
    }
}

// show html of complete page
$page->show();
