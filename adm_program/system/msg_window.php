<?php
/******************************************************************************
 * Popup window with informations
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * message_id    - ID of language text, that should be shown
 * message_title - (optional) title of window (Default: Note)
 * message_var1  - (optional) text, that should be shown in the message
 * inline        - true : message should not be shown in separate window
 *****************************************************************************/

require_once('common.php');

// Initialize and check the parameters
$getMessageId    = admFuncVariableIsValid($_GET, 'message_id',    'string',  array('directOutput' => true, 'requireValue' => true));
$getMessageTitle = admFuncVariableIsValid($_GET, 'message_title', 'string',  array('directOutput' => true, 'defaultValue' => 'SYS_NOTE'));
$getMessageVar1  = admFuncVariableIsValid($_GET, 'message_var1',  'string',  array('directOutput' => true));
$getInlineView   = admFuncVariableIsValid($_GET, 'inline',        'boolean', array('directOutput' => true));

header('Content-type: text/html; charset=utf-8');

// show headline
if($getInlineView)
{
    echo '
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">'.$gL10n->get('SYS_NOTE').'</h4>
        </div>
        <div class="modal-body">';
}

switch ($getMessageId)
{
    case 'CAT_CATEGORY_GLOBAL':
        // show all organizations where this organization is mother or child organization
        $organizations = '- '.$gCurrentOrganization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));
        echo $gL10n->get(strtoupper($getMessageId), $organizations);
        break;

    case 'SYS_DATA_GLOBAL':
        // show all organizations where this organization is mother or child organization
        $organizations = '- '.$gCurrentOrganization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));
        echo $gL10n->get(strtoupper($getMessageId), $organizations);
        break;

    case 'room_detail':
        if(is_numeric($getMessageVar1))
        {
            $room = new TableRooms($gDb, $getMessageVar1);
            echo '
                <div class="row">
                    <div class="col-xs-4"><strong>'.$gL10n->get('SYS_ROOM').':</strong></div>
                    <div class="col-xs-8">'.$room->getValue('room_name').'</div>
                </div>
                <div class="row">
                    <div class="col-xs-4"><strong>'.$gL10n->get('ROO_CAPACITY').':</strong></div>
                    <div class="col-xs-8">'.$room->getValue('room_capacity').'</div>
                </div>
                <div class="row">
                    <div class="col-xs-4"><strong>'.$gL10n->get('ROO_OVERHANG').':</strong></div>
                    <div class="col-xs-8">'.$room->getValue('room_overhang').'</div>
                </div>
                <div class="row">
                    <div class="col-xs-4"><strong>'.$gL10n->get('SYS_DESCRIPTION').':</strong></div>
                    <div class="col-xs-8">'.$room->getValue('room_description').'</div>
                </div>';
        }
        break;

    case 'user_field_description':
        echo $gProfileFields->getProperty($getMessageVar1, 'usf_description');
        break;

    // Eigene Listen

    case 'mylist_condition':
        echo '
            <p>'.$gL10n->get('LST_MYLIST_CONDITION_DESC').'</p>
            <p>'.$gL10n->get('SYS_EXAMPLES').':</p>
            <table class="table table-condensed">
                <thead>
                    <tr>
                        <th style="width: 100px;">'.$gL10n->get('SYS_FIELD').'</th>
                        <th style="width: 130px;">'.$gL10n->get('SYS_CONDITION').'</th>
                        <th>'.$gL10n->get('SYS_DESCRIPTION').'</th>
                    </tr>
                </thead>
                <tbody>
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
                    <tr>
                        <td>'.$gL10n->get('SYS_FIRSTNAME').'</td>
                        <td><b>&lt;&gt; '.$gL10n->get('LST_EXCLUDE_EXAMPLE').'</b></td>
                        <td>'.$gL10n->get('LST_EXCLUDE_EXAMPLE_DESC').'</td>
                    </tr>
                    <tr>
                        <td>'.$gL10n->get('SYS_ADDRESS').'</td>
                        <td><b>'.$gL10n->get('SYS_EMPTY').'</b></td>
                        <td>'.$gL10n->get('LST_EMPTY_EXAMPLE_DESC').'</td>
                    </tr>
                    <tr>
                        <td>'.$gL10n->get('SYS_ADDRESS').'</td>
                        <td><b>'.$gL10n->get('SYS_NOT_EMPTY').'</b></td>
                        <td>'.$gL10n->get('LST_NOT_EMPTY_EXAMPLE_DESC').'</td>
                    </tr>
                    <tr>
                        <td>'.$gL10n->get('SYS_COUNTRY').'</td>
                        <td><b>'.$gL10n->get('SYS_COUNTRY_EG').'</b></td>
                        <td>'.$gL10n->get('LST_COUNTRY_ISO').'</td>
                    </tr>
                </tbody>
            </table>';
        break;

    //Profil

    case 'profile_photo_up_help':
        echo '
            <h3>'.$gL10n->get('SYS_RESTRICTIONS').'</h3>
            <ul>
                <li>'.$gL10n->get('PRO_RESTRICTIONS_HELP_1').'</li>
                <li>'.$gL10n->get('PRO_RESTRICTIONS_HELP_2').'</li>
                <li>'.$gL10n->get('PRO_RESTRICTIONS_HELP_3', round(admFuncProcessableImageSize()/1000000, 2)).'</li>
                <li>'.$gL10n->get('PRO_RESTRICTIONS_HELP_4', round(admFuncMaxUploadSize()/pow(1024, 2), 2)).'</li>
            </ul>';
        break;

    default:
        // im Standardfall wird mit der ID der Text aus der Sprachdatei gelesen
        // falls die Textvariable gefuellt ist, pruefen ob dies auch eine ID aus der Sprachdatei ist
        $msg_var1 = '';
        if($getMessageVar1 !== '')
        {
            if(strpos($getMessageVar1, '_') === 3)
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
    echo '</div></div>';
}
