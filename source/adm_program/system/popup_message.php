<?php
/******************************************************************************
 * Anlegen neuer Mitglieder
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
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

// lokale Variablen der Uebergabevariablen initialisieren
$req_type          = '';
$req_element_id    = 0;
$req_database_id   = 0;
$req_database_id_2 = 0;
$req_name          = '';
$icon = 'error_big.png';
$text = 'SYS_DELETE_ENTRY';
$callbackSuccess   = '';

// Uebergabevariablen pruefen

if(isset($_GET['type']) && strlen($_GET['type']) > 0)
{
    $req_type = strStripTags($_GET['type']);
}
else
{
    $g_message->setExcludeThemeBody();
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['element_id']) && strlen($_GET['element_id']) > 0)
{
    $req_element_id = strStripTags($_GET['element_id']);
}
else
{
    $g_message->setExcludeThemeBody();
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['database_id']))
{
    $req_database_id = strStripTags($_GET['database_id']);
}
else
{
    $g_message->setExcludeThemeBody();
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['database_id_2']))
{
    $req_database_id_2 = strStripTags($_GET['database_id_2']);
}

if(isset($_GET['name']))
{
    $req_name = strStripTags($_GET['name']);
}

// URL zusammensetzen
switch ($req_type)
{
    case 'ann':
        $url = 'announcements_function.php?mode=2&ann_id='.$req_database_id;
        break;
    case 'bac':
        $url = 'backup_file_function.php?job=delete&file_id='.$req_database_id;
        break;
    case 'dat':
        $url = 'dates_function.php?mode=2&dat_id='.$req_database_id;
        break;
    case 'fil':
        $url = 'download_function.php?mode=2&file_id='.$req_database_id;
        break;
    case 'fol':
        $url = 'download_function.php?mode=5&folder_id='.$req_database_id;
        break;
    case 'gbo':
        $url = 'guestbook_function.php?mode=2&id='.$req_database_id;
        break;
    case 'gbo_mod':
        $url = 'guestbook_function.php?mode=9&id='.$req_database_id;
        $icon = 'information_big.png';
        $text = 'SYS_APPROVE_ENTRY';
        break;    
    case 'gbc':
        $url = 'guestbook_function.php?mode=5&id='.$req_database_id;
        break;
    case 'gbc_mod':
        $url = 'guestbook_function.php?mode=10&id='.$req_database_id;
        $icon = 'information_big.png';
        $text = 'SYS_APPROVE_ENTRY';
        break;    
    case 'lnk':
        $url = 'links_function.php?mode=2&lnk_id='.$req_database_id;
        break;
    case 'nwu':
        $url = 'new_user_function.php?mode=4&new_user_id='.$req_database_id;
        break;
    case 'pho':
        $url  = 'photo_function.php?job=do_delete&pho_id='.$req_database_id_2.'&bild='.$req_database_id;
        $text = 'PHO_WANT_DELETE_PHOTO';
        break;
    case 'pho_album':
        $url = 'photo_album_function.php?job=delete&pho_id='.$req_database_id;
        break;
    case 'pro_pho':
        $url = 'profile_photo_edit.php?job=delete&usr_id='.$req_database_id;
        $callbackSuccess = '
           var img_src = $("#profile_picture").attr("src");
           var timestamp = new Date().getTime();
           $("#profile_picture").attr("src",img_src+"&"+timestamp);';
        $text = 'PRO_WANT_DELETE_PHOTO';
        break;
    case 'pro_role':
        $url = 'profile_function.php?mode=2&user_id='.$req_database_id_2.'&rol_id='.$req_database_id;
        $callbackSuccess = 'if(profileJS) {
						profileJS.formerRoleCount++;
						profileJS.reloadFormerRoleMemberships();
					};';
        $text = 'ROL_MEMBERSHIP_DEL';
        break;
    case 'pro_former':
        $url = 'profile_function.php?mode=3&user_id='.$req_database_id_2.'&rol_id='.$req_database_id;
        $callbackSuccess = 'if(profileJS) {
						profileJS.formerRoleCount--;
						if(profileJS.formerRoleCount == 0) {
							$("#profile_former_roles_box").fadeOut("slow");
						}
					};';
        $text = 'ROL_LINK_MEMBERSHIP_DEL';
        break;
    case 'room':
        $url = 'rooms_function.php?mode=2&room_id='.$req_database_id;
        break;
    case 'usf':
        $url = 'fields_function.php?mode=2&usf_id='.$req_database_id;
        break;
    default:
        $url = '';
        break;
}

if(strlen($url) == 0)
{
    $g_message->setExcludeThemeBody();
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
error_log($url);
echo '
<script type="text/javascript"><!--
var entryDeleted;

function deleteEntry()
{
    entryDeleted = document.getElementById("'.$req_element_id.'");

    // RequestObjekt abschicken und Eintrag loeschen
    $.get("'.$url.'", function(data) {
        if(data == "done")
        {
            $.colorbox.close();
            $(entryDeleted).fadeOut("slow");
        }
        else
        {
            $("#msgText").html("'.$g_l10n->get('SYS_ERROR_ENTRY_NOT_DELETED').'");
        }
        '.$callbackSuccess.'
    });
}
//--></script>

<form id="frmMembersCreateUser" method="post" action="'.$g_root_path.'/adm_program/administration/members/members_assign.php" >
<div class="formLayout">
    <div class="formHead">'. $g_l10n->get('SYS_NOTE'). '</div>
    <div class="formBody">
        <div style="display: block;">
            <div style="float: left; width: 75px; min-height: 60px;">
                <br /><img src="'.THEME_PATH.'/icons/'.$icon.'" alt="Icon" />
            </div>
            <div id="msgText" style="min-height: 60px;"><br />'.$g_l10n->get($text, $req_name).'</div>
        </div>

        <div class="formSubmit" style="display: block; margin: 20px 0px 20px 0px;">
            <button id="btnYes" type="button" onclick="javascript:deleteEntry()"><img src="'. THEME_PATH. '/icons/ok.png" 
                alt="'.$g_l10n->get('SYS_YES').'" />&nbsp;&nbsp;'.$g_l10n->get('SYS_YES').'&nbsp;&nbsp;&nbsp;</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button id="btnNo" type="button" onclick="javascript:$.colorbox.close();"><img src="'. THEME_PATH. '/icons/error.png" 
                alt="'.$g_l10n->get('SYS_NO').'" />&nbsp;'.$g_l10n->get('SYS_NO').'</button>
        </div>
    </div>
</form>';
?>