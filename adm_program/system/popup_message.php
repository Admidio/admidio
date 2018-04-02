<?php
/**
 ***********************************************************************************************
 * Content for modal windows
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * type        - Modulkuerzel in dem ein Eintrag geloescht werden soll
 * element_id  - ID des HTML-Elements, welches nach dem Loeschen entfernt werden soll
 * database_id - ID des Eintrags in der Datenbanktabelle
 * database_id_2 - weitere ID um ggf. den Eintrag aus der DB besser zu finden
 * name        - Name des Elements, der im Hinweis angezeigt wird
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');
require(__DIR__ . '/login_valid.php');

// Initialize and check the parameters
$gMessage->showThemeBody(false);
$getType        = admFuncVariableIsValid($_GET, 'type',          'string', array('requireValue' => true));
$getElementId   = admFuncVariableIsValid($_GET, 'element_id',    'string', array('requireValue' => true));
$getDatabaseId  = admFuncVariableIsValid($_GET, 'database_id',   'string', array('requireValue' => true));
$getDatabaseId2 = admFuncVariableIsValid($_GET, 'database_id_2', 'string');
$getName        = admFuncVariableIsValid($_GET, 'name',          'string');

if ($getType !== 'bac')
{
    $getDatabaseId = (int) $getDatabaseId;
}
if ($getType !== 'cat')
{
    $getDatabaseId2 = (int) $getDatabaseId2;
}

// initialize local variables
$text = 'SYS_DELETE_ENTRY';
$callbackFunction = '';

// URL zusammensetzen
switch ($getType)
{
    case 'ann':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_function.php', array('mode' => 2, 'ann_id' => $getDatabaseId));
        break;
    case 'bac':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/backup/backup_file_function.php', array('job' => 'delete', 'filename' => $getDatabaseId));
        break;
    case 'cat':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories_function.php', array('cat_id' => $getDatabaseId, 'mode' => 2, 'type' => $getDatabaseId2));

        // get special message for calendars
        if($getDatabaseId2 === 'DAT')
        {
            $text = 'SYS_DELETE_ENTRY';
        }
        else
        {
            $text = 'CAT_DELETE_CATEGORY';
        }
        break;
    case 'dat':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates_function.php', array('mode' => 2, 'dat_id' => $getDatabaseId));
        break;
    case 'fil':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/downloads/download_function.php', array('mode' => 2, 'file_id' => $getDatabaseId, 'folder_id' => $getDatabaseId2));
        break;
    case 'fol':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/downloads/download_function.php', array('mode' => 5, 'folder_id' => $getDatabaseId));
        break;
    case 'gbo':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_function.php', array('mode' => 2, 'id' => $getDatabaseId));
        break;
    case 'gbc':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_function.php', array('mode' => 5, 'id' => $getDatabaseId));
        break;
    case 'lnk':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/links/links_function.php', array('mode' => 2, 'lnk_id' => $getDatabaseId));
        break;
    case 'men':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_function.php', array('mode' => 2, 'men_id' => $getDatabaseId));
        break;
    case 'msg':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php', array('msg_id' => $getDatabaseId));
        $text = 'MSG_DELETE_DESC';
        break;
    case 'nwu':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/registration/registration_function.php', array('mode' => 4, 'new_user_id' => $getDatabaseId));
        break;
    case 'pho':
        $url  = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_function.php', array('job' => 'delete', 'pho_id' => $getDatabaseId2, 'photo_nr' => $getDatabaseId));
        $text = 'PHO_WANT_DELETE_PHOTO';
        break;
    case 'pho_album':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_album_function.php', array('mode' => 'delete', 'pho_id' => $getDatabaseId));
        break;
    case 'pro_pho':
        $url  = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'delete', 'usr_id' => $getDatabaseId));
        $text = 'PRO_WANT_DELETE_PHOTO';
        $callbackFunction = 'callbackProfilePhoto';
        break;
    case 'pro_role':
        $url  = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_function.php', array('mode' => 2, 'mem_id' => $getDatabaseId));
        $text = 'ROL_MEMBERSHIP_DEL';
        $callbackFunction = 'callbackRoles';
        break;
    case 'pro_future':
        $url  = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_function.php', array('mode' => 3, 'mem_id' => $getDatabaseId));
        $text = 'ROL_LINK_MEMBERSHIP_DEL';
        $callbackFunction = 'callbackFutureRoles';
        break;
    case 'pro_former':
        $url  = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_function.php', array('mode' => 3, 'mem_id' => $getDatabaseId));
        $text = 'ROL_LINK_MEMBERSHIP_DEL';
        $callbackFunction = 'callbackFormerRoles';
        break;
    case 'rol':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/roles/roles_function.php', array('mode' => 4, 'rol_id' => $getDatabaseId));
        $text = 'ROL_ROLE_DELETE_DESC';
        break;
    case 'rol_enable':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/roles/roles_function.php', array('mode' => 5, 'rol_id' => $getDatabaseId));
        $text = 'ROL_ENABLE_ROLE_DESC';
        break;
    case 'rol_disable':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/roles/roles_function.php', array('mode' => 3, 'rol_id' => $getDatabaseId));
        $text = 'ROL_DISABLE_ROLE_DESC';
        break;
    case 'room':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/rooms/rooms_function.php', array('mode' => 2, 'room_id' => $getDatabaseId));
        break;
    case 'usf':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/fields_function.php', array('mode' => 2, 'usf_id' => $getDatabaseId));
        break;
    case 'urt':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes_function.php', array('mode' => 2, 'urt_id' => $getDatabaseId));
        $text = 'REL_USER_RELATION_TYPE_DEL';
        break;
    case 'ure':
        $url = safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/userrelations_function.php', array('mode' => 2, 'ure_id' => $getDatabaseId));
        break;
    default:
        $url = '';
        break;
}

if($callbackFunction !== '')
{
    $callbackFunction = ', \''.$callbackFunction.'\'';
}

if($url === '')
{
    $gMessage->showThemeBody(false);
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

header('Content-type: text/html; charset=utf-8');

echo '
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">'.$gL10n->get('SYS_NOTE').'</h4>
</div>
<div class="modal-body row">
    <div class="col-xs-2"><img style="width: 32px; height: 32px;" src="'.THEME_URL.'/icons/error_big.png" alt="Icon" /></div>
    <div id="message_text" class="col-xs-10">'.$gL10n->get($text, array($getName, '')).'</div>
</div>
<div class="modal-footer">
    <button id="btn_yes" class="btn btn-default" type="button" onclick="callUrlHideElement(\''.$getElementId.'\', \''.$url.'\''.$callbackFunction.')">
        <img src="'.THEME_URL.'/icons/ok.png" alt="'.$gL10n->get('SYS_YES').'" />'.$gL10n->get('SYS_YES').'&nbsp;&nbsp;
    </button>
    <button id="btn_no" class="btn btn-default" type="button" data-dismiss="modal">
        <img src="'.THEME_URL.'/icons/error.png" alt="'.$gL10n->get('SYS_NO').'" />'.$gL10n->get('SYS_NO').'
    </button>
    <button id="btn_close" class="btn btn-default hidden" type="button" data-dismiss="modal">
        <img src="'.THEME_URL.'/icons/close.png" alt="'.$gL10n->get('SYS_CLOSE').'" />'.$gL10n->get('SYS_CLOSE').'
    </button>
</div>';
