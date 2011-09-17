<?php
/******************************************************************************
 * Popup-Fenster mit Informationen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * message_id    - ID des Sprachtextes, der angezeigt werden soll
 * message_title - (optional) Titel des Fensters (Default: Hinweis)
 * message_var1  - (optional) Text, der innerhalb einer Meldung angezeigt werden kann
 * inline        - true wenn die Nachricht nicht in einem separaten Fenster angezeigt wird
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_rooms.php');

// Initialize and check the parameters
$getMessageId    = admFuncVariableIsValid($_GET, 'message_id', 'string', null, true, null, true);
$getMessageTitle = admFuncVariableIsValid($_GET, 'message_title', 'string', 'SYS_NOTE', false, null, true);
$getMessageVar1  = admFuncVariableIsValid($_GET, 'message_var1', 'string', '', false, null, true);
$getInlineView   = admFuncVariableIsValid($_GET, 'inline', 'boolean', 0, false, null, true);

// Html-Kopf ausgeben
if($getInlineView)
{
    // Html des Modules ausgeben
    echo '
    <div class="formLayout" id="message_window">
            <div class="formHead">'.$gL10n->get($getMessageTitle).'</div>
            <div class="formBody">';
}

switch ($getMessageId)
{
    case 'bbcode':
        echo $gL10n->get('SYS_BBCODE_DESC').'<br /><br />
              <table class="tableList" style="width: auto;" cellspacing="0">
                 <tr>
                    <th style="width: 155px;">'.$gL10n->get('SYS_EXAMPLE').'</th>
                    <th>'.$gL10n->get('SYS_BBCODE').'</th>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_BOLD_TEXT_REPRESENT', '<b>', '</b>').'</td>
                    <td>'.$gL10n->get('SYS_BOLD_TEXT_REPRESENT', '<b>[b]</b>', '<b>[/b]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_UNDERLINE_TEXT', '<u>', '</u>').'</td>
                    <td>'.$gL10n->get('SYS_UNDERLINE_TEXT', '<b>[u]</b>', '<b>[/u]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_TEXT_ITALIC', '<i>', '</i>').'</td>
                    <td>'.$gL10n->get('SYS_TEXT_ITALIC', '<b>[i]</b>', '<b>[/i]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_REPRESENT_LARGE_TEXT', '<span style="font-size: 14pt;">', '</span>').'</td>
                    <td>'.$gL10n->get('SYS_REPRESENT_LARGE_TEXT', '<b>[big]</b>', '<b>[/big]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_REPRESENT_SMALL_TEXT', '<span style="font-size: 8pt;">', '</span>').'</td>
                    <td>'.$gL10n->get('SYS_REPRESENT_SMALL_TEXT', '<b>[small]</b>', '<b>[/small]</b>').'</td>
                 </tr>
                 <tr>
                    <td style="text-align: center;">'.$gL10n->get('SYS_CENTERED_TEXT_REPRESENT').'</td>
                    <td><b>[center]</b>'.$gL10n->get('SYS_CENTERED_TEXT_REPRESENT').'<b>[/center]</b></td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_SET_LINK', '<a href="http://www.admidio.org">', '</a>').'</td>
                    <td>'.$gL10n->get('SYS_SET_LINK', '<b>[url=</b>http://www.admidio.org<b>]</b>', '<b>[/url]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_SPECIFY_EMAIL_ADDRESS', '<a href="mailto:webmaster@admidio.org">', '</a>').'</td>
                    <td>'.$gL10n->get('SYS_SPECIFY_EMAIL_ADDRESS', '<b>[email=</b>webmaster@admidio.org<b>]</b>', '<b>[/email]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$gL10n->get('SYS_SHOW_IMAGE', '<img src="'.THEME_PATH.'/images/admidio_logo_20.png" alt="logo" />').'</td>
                    <td>'.$gL10n->get('SYS_SHOW_IMAGE', '<b>[img]</b>http://www.admidio.org/bild.jpg<b>[/img]</b>').'</td>
                 </tr>
              </table>';
        break;

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
            $room = new TableRooms($gDb);
            $room->readData($getMessageVar1);
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
                    <td>'.$room->getDescription('HTML').'</td>
                </tr>
            </table>';
        }
        break;

    case 'user_field_description':
        echo $gCurrentUser->getProperty($getMessageVar1, 'usf_description');
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
                    <td><b>'.$gL10n->get('LST_SEARCH_GENDER_EXAMPLE').'</b></td>
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
            $msg_var1 = $gL10n->get($getMessageVar1);
            if(strlen($msg_var1) == 0)
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