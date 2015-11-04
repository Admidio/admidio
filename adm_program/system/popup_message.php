<?php
/******************************************************************************
 * Content for modal windows
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * type        - Modulkuerzel in dem ein Eintrag geloescht werden soll
 * element_id  - ID des HTML-Elements, welches nach dem Loeschen entfernt werden soll
 * database_id - ID des Eintrags in der Datenbanktabelle
 * database_id_2 - weitere ID um ggf. den Eintrag aus der DB besser zu finden
 * name        - Name des Elements, der im Hinweis angezeigt wird
 *
 *****************************************************************************/

require_once('common.php');
require_once('login_valid.php');

// Initialize and check the parameters
$gMessage->showThemeBody(false);
$get_type          = admFuncVariableIsValid($_GET, 'type',          'string', array('requireValue' => true));
$get_element_id    = admFuncVariableIsValid($_GET, 'element_id',    'string', array('requireValue' => true));
$get_database_id   = admFuncVariableIsValid($_GET, 'database_id',   'string', array('requireValue' => true));
$get_database_id_2 = admFuncVariableIsValid($_GET, 'database_id_2', 'string');
$get_name          = admFuncVariableIsValid($_GET, 'name',          'string');

// initialize local variables
$icon = 'error_big.png';
$text = 'SYS_DELETE_ENTRY';
$textVariable     = $get_name;
$textVariable2    = '';
$callbackFunction = '';

// URL zusammensetzen
switch ($get_type)
{
    case 'ann':
        $url = 'announcements_function.php?mode=2&ann_id='.$get_database_id;
        break;
    case 'bac':
        $url = 'backup_file_function.php?job=delete&filename='.$get_database_id;
        break;
    case 'cat':
        $url  = 'categories_function.php?cat_id='.$get_database_id.'&mode=2&type='.$get_database_id_2;

        // get special message for calendars
        if($get_database_id_2 === 'DAT')
        {
            $text = 'SYS_DELETE_ENTRY';
        }
        else
        {
            $text = 'CAT_DELETE_CATEGORY';
        }
        break;
    case 'dat':
        $url = 'dates_function.php?mode=2&dat_id='.$get_database_id;
        break;
    case 'fil':
        $url = 'download_function.php?mode=2&file_id='.$get_database_id;
        break;
    case 'fol':
        $url = 'download_function.php?mode=5&folder_id='.$get_database_id;
        break;
    case 'gbo':
        $url = 'guestbook_function.php?mode=2&id='.$get_database_id;
        break;
    case 'gbc':
        $url = 'guestbook_function.php?mode=5&id='.$get_database_id;
        break;
    case 'lnk':
        $url = 'links_function.php?mode=2&lnk_id='.$get_database_id;
        break;
    case 'nwu':
        $url = 'registration_function.php?mode=4&new_user_id='.$get_database_id;
        break;
    case 'pho':
        $url  = 'photo_function.php?job=delete&pho_id='.$get_database_id_2.'&photo_nr='.$get_database_id;
        $text = 'PHO_WANT_DELETE_PHOTO';
        break;
    case 'pho_album':
        $url = 'photo_album_function.php?mode=delete&pho_id='.$get_database_id;
        break;
    case 'pro_pho':
        $url  = 'profile_photo_edit.php?mode=delete&usr_id='.$get_database_id;
        $text = 'PRO_WANT_DELETE_PHOTO';
        $callbackFunction = 'callbackProfilePhoto';
        break;
    case 'pro_role':
        $url  = 'profile_function.php?mode=2&mem_id='.$get_database_id;
        $text = 'ROL_MEMBERSHIP_DEL';
        $callbackFunction = 'callbackRoles';
        break;
    case 'pro_future':
        $url  = 'profile_function.php?mode=3&mem_id='.$get_database_id;
        $text = 'ROL_LINK_MEMBERSHIP_DEL';
        $callbackFunction = 'callbackFutureRoles';
        break;
    case 'pro_former':
        $url  = 'profile_function.php?mode=3&mem_id='.$get_database_id;
        $text = 'ROL_LINK_MEMBERSHIP_DEL';
        $callbackFunction = 'callbackFormerRoles';
        break;
    case 'room':
        $url = 'rooms_function.php?mode=2&room_id='.$get_database_id;
        break;
    case 'usf':
        $url = 'fields_function.php?mode=2&usf_id='.$get_database_id;
        break;
    case 'msg':
        $url = 'messages.php?msg_id='.$get_database_id;
        $text = 'MSG_DELETE_DESC';
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
}

header('Content-type: text/html; charset=utf-8');

echo '
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">'.$gL10n->get('SYS_NOTE').'</h4>
</div>
<div class="modal-body row">
    <div class="col-xs-2"><img style="width: 32px; height: 32px;" src="'.THEME_PATH.'/icons/'.$icon.'" alt="Icon" /></div>
    <div id="message_text" class="col-xs-10">'.$gL10n->get($text, $textVariable, $textVariable2).'</div>
</div>
<div class="modal-footer">
        <button id="btn_yes" class="btn btn-default" type="button" onclick="callUrlHideElement(\''.$get_element_id.'\', \''.$url.'\''.$callbackFunction.')">
            <img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('SYS_YES').'" />'.$gL10n->get('SYS_YES').'&nbsp;&nbsp;
        </button>
        <button id="btn_no" class="btn btn-default" type="button" data-dismiss="modal">
            <img src="'. THEME_PATH. '/icons/error.png" alt="'.$gL10n->get('SYS_NO').'" />'.$gL10n->get('SYS_NO').'
        </button>
        <button id="btn_close" class="btn btn-default hidden" type="button" data-dismiss="modal">
            <img src="'. THEME_PATH. '/icons/close.png" alt="'.$gL10n->get('SYS_CLOSE').'" />'.$gL10n->get('SYS_CLOSE').'
        </button>
</div>';
