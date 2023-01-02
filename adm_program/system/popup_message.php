<?php
/**
 ***********************************************************************************************
 * Content for modal windows
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * type          - Module short code in which an entry should to be deleted
 * element_id    - HTML id of the HTML element to be removed after deletion
 * database_id   - ID of the entry in the database table
 * database_id_2 - additional ID to better find the entry from the DB if necessary
 * name          - Name of the element displayed in the modal window
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');
require(__DIR__ . '/login_valid.php');

// Initialize and check the parameters
$gMessage->showThemeBody(false);
$getType        = admFuncVariableIsValid($_GET, 'type', 'string', array('requireValue' => true));
$getElementId   = admFuncVariableIsValid($_GET, 'element_id', 'string', array('requireValue' => true));
$getDatabaseId  = admFuncVariableIsValid($_GET, 'database_id', 'string', array('requireValue' => true));
$getDatabaseId2 = admFuncVariableIsValid($_GET, 'database_id_2', 'string');
$getName        = admFuncVariableIsValid($_GET, 'name', 'string');

// initialize local variables
$text = 'SYS_DELETE_ENTRY';
$callbackFunction = '';

// compose URL
switch ($getType) {
    case 'ann':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_function.php', array('mode' => 2, 'ann_uuid' => $getDatabaseId));
        break;
    case 'bac':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/backup/backup_file_function.php', array('job' => 'delete', 'filename' => $getDatabaseId));
        break;
    case 'cat':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_function.php', array('cat_uuid' => $getDatabaseId, 'mode' => 2, 'type' => $getDatabaseId2));

        // get special message for calendars
        if ($getDatabaseId2 === 'DAT') {
            $text = 'SYS_DELETE_ENTRY';
        } else {
            $text = 'SYS_WANT_DELETE_CATEGORY';
        }
        break;
    case 'dat':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates_function.php', array('mode' => 2, 'dat_uuid' => $getDatabaseId));
        break;
    case 'fil':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/documents_files_function.php', array('mode' => 2, 'file_uuid' => $getDatabaseId, 'folder_uuid' => $getDatabaseId2));
        break;
    case 'fol':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/documents_files_function.php', array('mode' => 5, 'folder_uuid' => $getDatabaseId));
        break;
    case 'gbo':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_function.php', array('mode' => 2, 'gbo_uuid' => $getDatabaseId));
        break;
    case 'gbc':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_function.php', array('mode' => 5, 'gbc_uuid' => $getDatabaseId));
        break;
    case 'lnk':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/links/links_function.php', array('mode' => 2, 'link_uuid' => $getDatabaseId));
        break;
    case 'men':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_function.php', array('mode' => 2, 'menu_uuid' => $getDatabaseId));
        break;
    case 'msg':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php', array('msg_uuid' => $getDatabaseId));
        $text = 'SYS_DELETE_MESSAGE';
        break;
    case 'nwu':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/registration/registration_function.php', array('mode' => 4, 'new_user_uuid' => $getDatabaseId));
        break;
    case 'pho':
        $url  = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_function.php', array('job' => 'delete', 'photo_uuid' => $getDatabaseId2, 'photo_nr' => $getDatabaseId));
        $text = 'PHO_WANT_DELETE_PHOTO';
        break;
    case 'pho_album':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_album_function.php', array('mode' => 'delete', 'photo_uuid' => $getDatabaseId));
        break;
    case 'pro_pho':
        $url  = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'delete', 'user_uuid' => $getDatabaseId));
        $text = 'PRO_WANT_DELETE_PHOTO';
        $callbackFunction = 'callbackProfilePhoto';
        break;
    case 'pro_role':
        $url  = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_function.php', array('mode' => 2, 'member_uuid' => $getDatabaseId));
        $text = 'SYS_MEMBERSHIP_DELETE';
        $callbackFunction = 'callbackRoles';
        break;
    case 'pro_future':
        $url  = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_function.php', array('mode' => 3, 'member_uuid' => $getDatabaseId));
        $text = 'SYS_LINK_MEMBERSHIP_DELETE';
        $callbackFunction = 'callbackFutureRoles';
        break;
    case 'pro_former':
        $url  = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_function.php', array('mode' => 3, 'member_uuid' => $getDatabaseId));
        $text = 'SYS_LINK_MEMBERSHIP_DELETE';
        $callbackFunction = 'callbackFormerRoles';
        break;
    case 'rol':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/groups_roles_function.php', array('mode' => 4, 'role_uuid' => $getDatabaseId));
        $text = 'SYS_DELETE_ROLE_DESC';
        break;
    case 'rol_enable':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/groups_roles_function.php', array('mode' => 5, 'role_uuid' => $getDatabaseId));
        $text = 'SYS_ACTIVATE_ROLE_DESC';
        break;
    case 'rol_disable':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/groups_roles_function.php', array('mode' => 3, 'role_uuid' => $getDatabaseId));
        $text = 'SYS_DEACTIVATE_ROLE_DESC';
        break;
    case 'room':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/rooms/rooms_function.php', array('mode' => 2, 'room_uuid' => $getDatabaseId));
        break;
    case 'usf':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields/profile_fields_function.php', array('mode' => 2, 'usf_uuid' => $getDatabaseId));
        break;
    case 'urt':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes_function.php', array('mode' => 2, 'urt_uuid' => $getDatabaseId));
        $text = 'SYS_RELATIONSHIP_TYPE_DELETE';
        break;
    case 'ure':
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/userrelations_function.php', array('mode' => 2, 'ure_id' => $getDatabaseId));
        break;
    default:
        $url = '';
}

if ($callbackFunction !== '') {
    $callbackFunction = ', \''.$callbackFunction.'\'';
}

if ($url === '') {
    $gMessage->showThemeBody(false);
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

header('Content-type: text/html; charset=utf-8');

echo '
<div class="modal-header">
    <h3 class="modal-title">'.$gL10n->get('SYS_NOTE').'</h3>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
</div>
<div class="modal-body row">
    <div class="col-2"><i class="fas fa-times-circle fa-3x" style="color: #f93535;"></i></div>
    <div id="message_text" class="col-10">'.$gL10n->get($text, array($getName, '')).'</div>
</div>
<div class="modal-footer">
    <button id="btn_yes" class="btn btn-primary" type="button" onclick="callUrlHideElement(\''.$getElementId.'\', \''.$url.'\', \''.$gCurrentSession->getCsrfToken().'\''.$callbackFunction.')">
        <i class="fas fa-check-circle"></i>'.$gL10n->get('SYS_YES').'&nbsp;&nbsp;
    </button>
    <button id="btn_no" class="btn btn-secondary" type="button" data-dismiss="modal">
        <i class="fas fa-minus-circle"></i>'.$gL10n->get('SYS_NO').'
    </button>
</div>';
