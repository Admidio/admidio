<?php
/******************************************************************************
 * Popup-Fenster 
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
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

// Initialize and check the parameters
$gMessage->setExcludeThemeBody();
$get_type          = admFuncVariableIsValid($_GET, 'type', 'string', null, true);
$get_element_id    = admFuncVariableIsValid($_GET, 'element_id', 'string', null, true);
$get_database_id   = admFuncVariableIsValid($_GET, 'database_id', 'string', null, true);
$get_database_id_2 = admFuncVariableIsValid($_GET, 'database_id_2', 'string', '0');
$get_name          = admFuncVariableIsValid($_GET, 'name', 'string', '');

// initialize local variables
$icon = 'error_big.png';
$text = 'SYS_DELETE_ENTRY';
$textVariable    = $get_name;
$textVariable2   = '';
$callbackSuccess = '';

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
		require_once('classes/table_category.php');
        $url  = 'categories_function.php?cat_id='.$get_database_id.'&mode=2&type='.$get_database_id_2;
		$text = 'CAT_DELETE_CATEGORY';
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
    case 'gbo_mod':
        $url = 'guestbook_function.php?mode=9&id='.$get_database_id;
        $icon = 'information_big.png';
        $text = 'SYS_APPROVE_ENTRY';
        break;    
    case 'gbc':
        $url = 'guestbook_function.php?mode=5&id='.$get_database_id;
        $callbackSuccess = '
            $("#gbc_'.$get_database_id.'").remove();
            $("#comments_'.$get_database_id_2.' > br").remove();
            
            var count = 0;
            $("#comments_'.$get_database_id_2.' > .groupBox").each( function(index, value) { 
                count++;
            });

            if (count == 0) {
                $("#admCommentsVisible_'.$get_database_id_2.'").hide();
                $("#admCommentsInvisible_'.$get_database_id_2.'").hide();
            }
            else {
                var msgOrg = "'.$gL10n->get('GBO_SHOW_COMMENTS_ON_ENTRY').'";
                var msg = msgOrg.replace("%VAR1%",count);
                $("#admCommentsInvisible_'.$get_database_id_2.' span.iconTextLink > a:nth-child(2)").html(msg);
            }   
            ';
        break;
    case 'gbc_mod':
        $url = 'guestbook_function.php?mode=10&id='.$get_database_id;
        $icon = 'information_big.png';
        $text = 'SYS_APPROVE_ENTRY';
        break;    
    case 'lnk':
        $url = 'links_function.php?mode=2&lnk_id='.$get_database_id;
        break;
    case 'nwu':
        $url = 'new_user_function.php?mode=4&new_user_id='.$get_database_id;
        break;
    case 'pho':
        $url  = 'photo_function.php?job=delete&pho_id='.$get_database_id_2.'&photo_nr='.$get_database_id;
        $text = 'PHO_WANT_DELETE_PHOTO';
        break;
    case 'pho_album':
        $url = 'photo_album_function.php?job=delete&pho_id='.$get_database_id;
        break;
    case 'pro_pho':
        $url = 'profile_photo_edit.php?job=delete&usr_id='.$get_database_id;
        $callbackSuccess = '
           var img_src = $("#profile_picture").attr("src");
           var timestamp = new Date().getTime();
           $("#profile_picture").attr("src",img_src+"&"+timestamp);';
        $text = 'PRO_WANT_DELETE_PHOTO';
        break;
    case 'pro_role':
        $url = 'profile_function.php?mode=2&mem_id='.$get_database_id;
        $callbackSuccess = 'if(profileJS) {
						profileJS.formerRoleCount++;
						profileJS.reloadFormerRoleMemberships();
					};';
        $text = 'ROL_MEMBERSHIP_DEL';
        break;
    case 'pro_future':
        $url = 'profile_function.php?mode=3&mem_id='.$get_database_id;
        $callbackSuccess = 'if(profileJS) {
						profileJS.futureRoleCount--;
						if(profileJS.futureRoleCount == 0) {
							$("#profile_future_roles_box").fadeOut("slow");
						}
					};';
        $text = 'ROL_LINK_MEMBERSHIP_DEL';
        break;
    case 'pro_former':
        $url = 'profile_function.php?mode=3&mem_id='.$get_database_id;
        $callbackSuccess = 'if(profileJS) {
						profileJS.formerRoleCount--;
						if(profileJS.formerRoleCount == 0) {
							$("#profile_former_roles_box").fadeOut("slow");
						}
					};';
        $text = 'ROL_LINK_MEMBERSHIP_DEL';
        break;
    case 'room':
        $url = 'rooms_function.php?mode=2&room_id='.$get_database_id;
        break;
    case 'usf':
        $url = 'fields_function.php?mode=2&usf_id='.$get_database_id;
        break;
    default:
        $url = '';
        break;
}

if(strlen($url) == 0)
{
    $gMessage->setExcludeThemeBody();
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

header('Content-type: text/html; charset=utf-8'); 

echo '
<script type="text/javascript"><!--
var entryDeleted;

function deleteEntry()
{
    entryDeleted = document.getElementById("'.$get_element_id.'");

    // send RequestObjekt and delete entry
    $.get("'.$url.'", function(data) {
        if(data == "done")
        {
            $.colorbox.close();
            $(entryDeleted).fadeOut("slow", function() { 
			'.$callbackSuccess.'
            });
        }
        else
        {
			// entry could not be deleted, than show content of data or an common error message
			$("#admYesNoButtons").css("display","none");
			$("#admCloseButton").css("display","");
			if(data.length > 0) {
				$("#admMessageText").html(data);
			} else {
				$("#admMessageText").html("'.$gL10n->get('SYS_ERROR_ENTRY_NOT_DELETED').'");
			}
        }
    });
}
//--></script>

<form id="admFormPopupMessage" method="post" action="'.$g_root_path.'/adm_program/administration/members/members_assign.php" >
<div class="formLayout">
    <div class="formHead">'. $gL10n->get('SYS_NOTE'). '</div>
    <div class="formBody">
        <table>
			<tr>
				<td style="width: 70px; text-align: center;"><br /><img style="width: 32px; height: 32px;" src="'.THEME_PATH.'/icons/'.$icon.'" alt="Icon" /></td>
				<td id="admMessageText"><br />'.$gL10n->get($text, $textVariable, $textVariable2).'</td>
			</tr>
			<tr id="admYesNoButtons">
				<td>&nbsp;</td>
				<td style="padding-top: 30px;">
					<button id="admButtonYes" type="button" onclick="javascript:deleteEntry()"><img src="'. THEME_PATH. '/icons/ok.png" 
						alt="'.$gL10n->get('SYS_YES').'" />&nbsp;&nbsp;'.$gL10n->get('SYS_YES').'&nbsp;&nbsp;&nbsp;</button>
					&nbsp;&nbsp;&nbsp;&nbsp;
					<button id="admButtonNo" type="button" onclick="javascript:$.colorbox.close();"><img src="'. THEME_PATH. '/icons/error.png" 
						alt="'.$gL10n->get('SYS_NO').'" />&nbsp;'.$gL10n->get('SYS_NO').'</button>
				</td>
			</tr>
			<tr id="admCloseButton" style="display: none;">
				<td>&nbsp;</td>
				<td style="padding-top: 30px;">
					<button id="admButtonClose" type="button" onclick="javascript:$.colorbox.close();"><img src="'. THEME_PATH. '/icons/close.png" 
						alt="'.$gL10n->get('SYS_CLOSE').'" />&nbsp;'.$gL10n->get('SYS_CLOSE').'</button>
				</td>
			</tr>
        </table>
    </div>
</form>';
?>