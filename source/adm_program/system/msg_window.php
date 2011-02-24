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

// lokale Variablen der Uebergabevariablen initialisieren
$req_message_id    = '';
$req_message_title = 'SYS_NOTE';
$req_message_var1  = '';
$inlineView        = false;

// Uebergabevariablen pruefen

if(isset($_GET['message_id']) && strlen($_GET['message_id']) > 0)
{
    $req_message_id = strStripTags($_GET['message_id']);
}
else
{
    $g_message->setExcludeThemeBody();
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['message_title']))
{
    $req_message_title = strStripTags($_GET['message_title']);
}

if(isset($_GET['message_var1']))
{
    $req_message_var1 = strStripTags($_GET['message_var1']);
}

if (isset($_GET['inline']) && $_GET['inline'] == true)
{
    $inlineView = true;
}

// Html-Kopf ausgeben
if($inlineView)
{
    // Html des Modules ausgeben
    echo '
    <div class="formLayout" id="message_window">
            <div class="formHead">'.$g_l10n->get($req_message_title).'</div>
            <div class="formBody">';
}

switch ($req_message_id)
{
    case 'bbcode':
        echo $g_l10n->get('SYS_BBCODE_DESC').'<br /><br />
              <table class="tableList" style="width: auto;" cellspacing="0">
                 <tr>
                    <th style="width: 155px;">'.$g_l10n->get('SYS_EXAMPLE').'</th>
                    <th>'.$g_l10n->get('SYS_BBCODE').'</th>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_BOLD_TEXT_REPRESENT', '<b>', '</b>').'</td>
                    <td>'.$g_l10n->get('SYS_BOLD_TEXT_REPRESENT', '<b>[b]</b>', '<b>[/b]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_UNDERLINE_TEXT', '<u>', '</u>').'</td>
                    <td>'.$g_l10n->get('SYS_UNDERLINE_TEXT', '<b>[u]</b>', '<b>[/u]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_TEXT_ITALIC', '<i>', '</i>').'</td>
                    <td>'.$g_l10n->get('SYS_TEXT_ITALIC', '<b>[i]</b>', '<b>[/i]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_REPRESENT_LARGE_TEXT', '<span style="font-size: 14pt;">', '</span>').'</td>
                    <td>'.$g_l10n->get('SYS_REPRESENT_LARGE_TEXT', '<b>[big]</b>', '<b>[/big]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_REPRESENT_SMALL_TEXT', '<span style="font-size: 8pt;">', '</span>').'</td>
                    <td>'.$g_l10n->get('SYS_REPRESENT_SMALL_TEXT', '<b>[small]</b>', '<b>[/small]</b>').'</td>
                 </tr>
                 <tr>
                    <td style="text-align: center;">'.$g_l10n->get('SYS_CENTERED_TEXT_REPRESENT').'</td>
                    <td><b>[center]</b>'.$g_l10n->get('SYS_CENTERED_TEXT_REPRESENT').'<b>[/center]</b></td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_SET_LINK', '<a href="http://www.admidio.org">', '</a>').'</td>
                    <td>'.$g_l10n->get('SYS_SET_LINK', '<b>[url=</b>http://www.admidio.org<b>]</b>', '<b>[/url]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_SPECIFY_EMAIL_ADDRESS', '<a href="mailto:webmaster@admidio.org">', '</a>').'</td>
                    <td>'.$g_l10n->get('SYS_SPECIFY_EMAIL_ADDRESS', '<b>[email=</b>webmaster@admidio.org<b>]</b>', '<b>[/email]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_SHOW_IMAGE', '<img src="'.THEME_PATH.'/images/admidio_logo_20.png" alt="logo" />').'</td>
                    <td>'.$g_l10n->get('SYS_SHOW_IMAGE', '<b>[img]</b>http://www.admidio.org/bild.jpg<b>[/img]</b>').'</td>
                 </tr>
              </table>';
        break;

    case 'CAT_CATEGORY_GLOBAL':
        // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
        $organizations = '- '.$g_current_organization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $g_current_organization->getReferenceOrganizations(true, true, true));
        echo $g_l10n->get(strtoupper($req_message_id), $organizations);
        break;

    case 'SYS_DATA_GLOBAL':
        // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
        $organizations = '- '.$g_current_organization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $g_current_organization->getReferenceOrganizations(true, true, true));
        echo $g_l10n->get(strtoupper($req_message_id), $organizations);
        break;
    
    case 'room_detail':
        if(is_numeric($req_message_var1))
        {
            $room = new TableRooms($g_db);
            $room->readData($req_message_var1);
            echo '
            <table>
                <tr>
                    <td><strong>'.$g_l10n->get('SYS_ROOM').':</strong></td>
                    <td>'.$room->getValue('room_name').'</td>
                </tr>
                <tr>
                    <td><strong>'.$g_l10n->get('ROO_CAPACITY').':</strong></td>
                    <td>'.$room->getValue('room_capacity').'</td>
                </tr>
                <tr>
                    <td><strong>'.$g_l10n->get('ROO_OVERHANG').':</strong></td>
                    <td>'.$room->getValue('room_overhang').'</td>
                </tr>
                <tr>
                    <td><strong>'.$g_l10n->get('SYS_DESCRIPTION').':</strong></td>
                    <td>'.$room->getDescription('HTML').'</td>
                </tr>
            </table>';
        }
        break;

    case 'user_field_description':
        echo $g_current_user->getProperty($req_message_var1, 'usf_description');
        break;

	// Eigene Listen

    case 'mylist_condition':
        echo $g_l10n->get('LST_MYLIST_CONDITION_DESC').'<br /><br />
              '.$g_l10n->get('SYS_EXAMPLES').':<br /><br />
              <table class="tableList" style="width: 100%;" cellspacing="0">
                 <tr>
                    <th style="width: 75px;">'.$g_l10n->get('SYS_FIELD').'</th>
                    <th style="width: 110px;">'.$g_l10n->get('SYS_CONDITION').'</th>
                    <th>'.$g_l10n->get('SYS_DESCRIPTION').'</th>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_LASTNAME').'</td>
                    <td><b>'.$g_l10n->get('LST_SEARCH_LASTNAME_EXAMPLE').'</b></td>
                    <td>'.$g_l10n->get('LST_SEARCH_LASTNAME_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_LASTNAME').'</td>
                    <td><b>'.$g_l10n->get('LST_SEARCH_LASTNAME_BEGINS_EXAMPLE').'</b></td>
                    <td>'.$g_l10n->get('LST_SEARCH_LASTNAME_BEGINS_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_BIRTHDAY').'</td>
                    <td><b>&gt; '.$g_l10n->get('LST_SEARCH_DATE_EXAMPLE').'</b></td>
                    <td>'.$g_l10n->get('LST_SEARCH_DATE_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_BIRTHDAY').'</td>
                    <td><b>&gt; '.$g_l10n->get('LST_SEARCH_AGE_EXAMPLE').'</b></td>
                    <td>'.$g_l10n->get('LST_SEARCH_AGE_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_GENDER').'</td>
                    <td><b>'.$g_l10n->get('LST_SEARCH_GENDER_EXAMPLE').'</b></td>
                    <td>'.$g_l10n->get('LST_SEARCH_GENDER_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_LOCATION').'</td>
                    <td><b>'.$g_l10n->get('LST_SEARCH_LOCATION_EXAMPLE').'</b></td>
                    <td>'.$g_l10n->get('LST_SEARCH_LOCATION_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_PHONE').'</td>
                    <td><b>'.$g_l10n->get('LST_SEARCH_TELEFON_EXAMPLE').'</b></td>
                    <td>'.$g_l10n->get('LST_SEARCH_TELEFON_DESC').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('LST_SEARCH_YES_NO_FIELD').'</td>
                    <td><b>'.$g_l10n->get('SYS_YES').'</b></td>
                    <td>'.$g_l10n->get('LST_SEARCH_YES_NO_FIELD_DESC').'</td>
                 </tr>
              </table>';
        break;

    case 'mylist_config_webmaster':
        echo '<h3>'.$g_l10n->get('LST_PRESET_CONFIGURATION').'</h3>
            '.$g_l10n->get('LST_PRESET_CONFIGURATION_DESC', '<img src="'. THEME_PATH. '/icons/list_global.png" alt="list_global" />').'
            <h3>'.$g_l10n->get('LST_DEFAULT_CONFIGURATION').'</h3>
            '.$g_l10n->get('LST_DEFAULT_CONFIGURATION_DESC', '<img src="'. THEME_PATH. '/icons/star.png" alt="star" />');
        break;

    //Fotomodulhifen

   case 'photo_up_help':
        echo '<ul>
                <li>'.$g_l10n->get('PHO_UPLOAD_HELP_1', $g_l10n->get('SYS_BROWSE')).'</li>
                <li>'.$g_l10n->get('PHO_UPLOAD_HELP_2').'</li>
                <li>'.$g_l10n->get('PHO_UPLOAD_HELP_3', $g_l10n->get('PHO_UPLOAD_PHOTOS')).'</li>
            </ul>  
            <h3>'.$g_l10n->get('SYS_RESTRICTIONS').':</h3>
            <ul>
                <li>'.$g_l10n->get('PHO_RESTRICTIONS_HELP_1').'</li>
                <li>'.$g_l10n->get('PHO_RESTRICTIONS_HELP_2', round(processableImageSize()/1000000, 2)).'</li>
                <li>'.$g_l10n->get('PHO_RESTRICTIONS_HELP_3', round(maxUploadSize()/pow(1024, 2), 2)).'</li>
                <li>'.$g_l10n->get('PHO_RESTRICTIONS_HELP_4', $g_preferences['photo_save_scale']).'</li>
                <li>'.$g_l10n->get('PHO_RESTRICTIONS_HELP_5').'</li>
                <li>'.$g_l10n->get('PHO_RESTRICTIONS_HELP_6', $g_preferences['photo_save_scale']).'</li>
            </ul>
            ';
        break;

    //Profil

    case 'profile_photo_up_help':
        echo '<ul>
                <li>'.$g_l10n->get('PRO_UPLOAD_HELP_1', $g_l10n->get('SYS_BROWSE')).'</li>
                <li>'.$g_l10n->get('PRO_UPLOAD_HELP_2', $g_l10n->get('PRO_UPLOAD_PHOTO')).'</li>
            </ul>
            <h3>'.$g_l10n->get('SYS_RESTRICTIONS').':</h3>
            <ul>
                <li>'.$g_l10n->get('PRO_RESTRICTIONS_HELP_1').'</li>
                <li>'.$g_l10n->get('PRO_RESTRICTIONS_HELP_2').'</li>
                <li>'.$g_l10n->get('PRO_RESTRICTIONS_HELP_3', round(processableImageSize()/1000000, 2)).'</li>
                <li>'.$g_l10n->get('PRO_RESTRICTIONS_HELP_4', round(maxUploadSize()/pow(1024, 2), 2)).'</li>
            </ul>
            ';
        break;

    default:
        // im Standardfall wird mit der ID der Text aus der Sprachdatei gelesen
        // falls die Textvariable gefuellt ist, pruefen ob dies auch eine ID aus der Sprachdatei ist
        $msg_var1 = '';
        if(strlen($req_message_var1) > 0)
        {
            $msg_var1 = $g_l10n->get($req_message_var1);
            if(strlen($msg_var1) == 0)
            {
                $msg_var1 = $req_message_var1;
            }
        }
        echo $g_l10n->get(strtoupper($req_message_id), $msg_var1);
        break;
}

if($inlineView)
{
    echo '</div>
    </div>';
}
?>