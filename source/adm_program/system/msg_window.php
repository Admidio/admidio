<?php
/******************************************************************************
 * Popup window with informations
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * message_id    - ID of language text, that should be shown
 * message_title - (optional) title of window (Default: Note)
 * message_var1  - (optional) text, that should be shown in the message
 * inline        - true : message should not be shown in separate window
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_rooms.php');

// Initialize and check the parameters
$getMessageId    = admFuncVariableIsValid($_GET, 'message_id', 'string', null, true, null, true);
$getMessageTitle = admFuncVariableIsValid($_GET, 'message_title', 'string', 'SYS_NOTE', false, null, true);
$getMessageVar1  = admFuncVariableIsValid($_GET, 'message_var1', 'string', '', false, null, true);
$getInlineView   = admFuncVariableIsValid($_GET, 'inline', 'boolean', 0, false, null, true);

// show headline
if($getInlineView)
{
    echo '
    <div class="formLayout" id="message_window">
            <div class="formHead">'.$gL10n->get($getMessageTitle).'</div>
            <div class="formBody">';
}

switch ($getMessageId)
{
    case 'CAT_CATEGORY_GLOBAL':
        // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
        $organizations = '- '.$gCurrentOrganization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $gCurrentOrganization->getReferenceOrganizations(true, true, true));
        echo $gL10n->get(strtoupper($getMessageId), $organizations);
        break;

    case 'SYS_DATA_GLOBAL':
        // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
        $organizations = '- '.$gCurrentOrganization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $gCurrentOrganization->getReferenceOrganizations(true, true, true));
        echo $gL10n->get(strtoupper($getMessageId), $organizations);
        break;
    
    case 'room_detail':
        if(is_numeric($getMessageVar1))
        {
            $room = new TableRooms($gDb, $getMessageVar1);
            echo '
            <table>
                <tr>
                    <td><strong>'.$gL10n->get('SYS_ROOM').':</strong></td>
                    <td>'.$room->getValue('room_name').'</td>
                </tr>
                <tr>
                    <td><strong>'.$gL10n->get('ROO_CAPACITY').':</strong></td>
                    <td>'.$room->getValue('room_capacity').'</td>
                </tr>
                <tr>
                    <td><strong>'.$gL10n->get('ROO_OVERHANG').':</strong></td>
                    <td>'.$room->getValue('room_overhang').'</td>
                </tr>
                <tr>
                    <td><strong>'.$gL10n->get('SYS_DESCRIPTION').':</strong></td>
                    <td>'.$room->getValue('room_description').'</td>
                </tr>
            </table>';
        }
        break;

    case 'user_field_description':
        echo $gProfileFields->getProperty($getMessageVar1, 'usf_description');
        break;

	// Eigene Listen

    case 'mylist_condition':
        echo $gL10n->get('LST_MYLIST_CONDITION_DESC').'<br /><br />
              '.$gL10n->get('SYS_EXAMPLES').':<br /><br />
              <table class="tableList" style="width: 100%;" cellspacing="0">
                 <tr>
                    <th style="width: 75px;">'.$gL10n->get('SYS_FIELD').'</th>
                    <th style="width: 110px;">'.$gL10n->get('SYS_CONDITION').'</th>
                    <th>'.$gL10n->get('SYS_DESCRIPTION').'</th>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_LASTNAME').'</td>
                    <td><b>'.$gL10n->get('LST_SEARCH_LASTNAME_EXAMPLE').'</b></td>
                    <td>'.$gL10n->get('LST_SEARCH_LASTNAME_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_LASTNAME').'</td>
                    <td><b>'.$gL10n->get('LST_SEARCH_LASTNAME_BEGINS_EXAMPLE').'</b></td>
                    <td>'.$gL10n->get('LST_SEARCH_LASTNAME_BEGINS_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_BIRTHDAY').'</td>
                    <td><b>&gt; '.$gL10n->get('LST_SEARCH_DATE_EXAMPLE').'</b></td>
                    <td>'.$gL10n->get('LST_SEARCH_DATE_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_BIRTHDAY').'</td>
                    <td><b>&gt; '.$gL10n->get('LST_SEARCH_AGE_EXAMPLE').'</b></td>
                    <td>'.$gL10n->get('LST_SEARCH_AGE_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_GENDER').'</td>
                    <td><b>'.$gL10n->get('SYS_MALE').'</b></td>
                    <td>'.$gL10n->get('LST_SEARCH_GENDER_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_LOCATION').'</td>
                    <td><b>'.$gL10n->get('LST_SEARCH_LOCATION_EXAMPLE').'</b></td>
                    <td>'.$gL10n->get('LST_SEARCH_LOCATION_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_PHONE').'</td>
                    <td><b>'.$gL10n->get('LST_SEARCH_TELEFON_EXAMPLE').'</b></td>
                    <td>'.$gL10n->get('LST_SEARCH_TELEFON_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('LST_SEARCH_YES_NO_FIELD').'</td>
                    <td><b>'.$gL10n->get('SYS_YES').'</b></td>
                    <td>'.$gL10n->get('LST_SEARCH_YES_NO_FIELD_DESC').'</td>
                 </tr>
              </table>';
        break;

    case 'mylist_config_webmaster':
        echo '<h3>'.$gL10n->get('LST_PRESET_CONFIGURATION').'</h3>
            '.$gL10n->get('LST_PRESET_CONFIGURATION_DESC', '<img src="'. THEME_PATH. '/icons/list_global.png" alt="list_global" />').'
            <h3>'.$gL10n->get('LST_DEFAULT_CONFIGURATION').'</h3>
            '.$gL10n->get('LST_DEFAULT_CONFIGURATION_DESC', '<img src="'. THEME_PATH. '/icons/star.png" alt="star" />');
        break;

    //Fotomodulhifen

   case 'photo_up_help':
        echo '<ul>
                <li>'.$gL10n->get('PHO_UPLOAD_HELP_1', $gL10n->get('SYS_BROWSE')).'</li>
                <li>'.$gL10n->get('PHO_UPLOAD_HELP_2').'</li>
                <li>'.$gL10n->get('PHO_UPLOAD_HELP_3', $gL10n->get('PHO_UPLOAD_PHOTOS')).'</li>
            </ul>  
            <h3>'.$gL10n->get('SYS_RESTRICTIONS').':</h3>
            <ul>
                <li>'.$gL10n->get('PHO_RESTRICTIONS_HELP_1').'</li>
                <li>'.$gL10n->get('PHO_RESTRICTIONS_HELP_2', round(admFuncProcessableImageSize()/1000000, 2)).'</li>
                <li>'.$gL10n->get('PHO_RESTRICTIONS_HELP_3', round(admFuncMaxUploadSize()/pow(1024, 2), 2)).'</li>
                <li>'.$gL10n->get('PHO_RESTRICTIONS_HELP_4', $gPreferences['photo_save_scale']).'</li>
                <li>'.$gL10n->get('PHO_RESTRICTIONS_HELP_5').'</li>
                <li>'.$gL10n->get('PHO_RESTRICTIONS_HELP_6', $gPreferences['photo_save_scale']).'</li>
            </ul>
            ';
        break;

    //Profil

    case 'profile_photo_up_help':
        echo '<ul>
                <li>'.$gL10n->get('PRO_UPLOAD_HELP_1', $gL10n->get('SYS_BROWSE')).'</li>
                <li>'.$gL10n->get('PRO_UPLOAD_HELP_2', $gL10n->get('PRO_UPLOAD_PHOTO')).'</li>
            </ul>
            <h3>'.$gL10n->get('SYS_RESTRICTIONS').':</h3>
            <ul>
                <li>'.$gL10n->get('PRO_RESTRICTIONS_HELP_1').'</li>
                <li>'.$gL10n->get('PRO_RESTRICTIONS_HELP_2').'</li>
                <li>'.$gL10n->get('PRO_RESTRICTIONS_HELP_3', round(admFuncProcessableImageSize()/1000000, 2)).'</li>
                <li>'.$gL10n->get('PRO_RESTRICTIONS_HELP_4', round(admFuncMaxUploadSize()/pow(1024, 2), 2)).'</li>
            </ul>
            ';
        break;

    default:
        // im Standardfall wird mit der ID der Text aus der Sprachdatei gelesen
        // falls die Textvariable gefuellt ist, pruefen ob dies auch eine ID aus der Sprachdatei ist
        $msg_var1 = '';
        if(strlen($getMessageVar1) > 0)
        {
			if(strpos($getMessageVar1, '_') == 3)
			{
				$msg_var1 = $gL10n->get($getMessageVar1);
			}
            else
            {
                $msg_var1 = $getMessageVar1;
            }
        }
        echo $gL10n->get(strtoupper($getMessageId), $msg_var1);
        break;
}

if($getInlineView)
{
    echo '</div>
    </div>';
}
?>