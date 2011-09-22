<?php
/******************************************************************************
 * Create and edit rooms
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 * 
 * room_id  : ID of room, that should be shown
 * headline : headline for room module
 *            (Default) SYS_ROOM
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_rooms.php'); 

if ($gPreferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}

// Initialize and check the parameters
$getRoomId   = admFuncVariableIsValid($_GET, 'room_id', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('SYS_ROOM'));

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

$room = new TableRooms($gDb);
if($getRoomId > 0)
{
    $room->readData($getRoomId);
}

if(isset($_SESSION['rooms_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$room->setArray($_SESSION['rooms_request']);
    unset($_SESSION['rooms_request']);
}

// Html-Kopf ausgeben
if($getRoomId > 0)
{
    $gLayout['title'] = $gL10n->get('SYS_EDIT_VAR', $getHeadline);
}
else
{
    $gLayout['title'] = $gL10n->get('SYS_CREATE_VAR', $getHeadline);
}

//Script für BBCode laden
$javascript = '';
if ($gPreferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('room_description');
}

$gLayout['header'] = $javascript;
require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<form method="post" action="'.$g_root_path.'/adm_program/administration/rooms/rooms_function.php?room_id='.$getRoomId.'&amp;mode=1">
<div class="formLayout" id="edit_dates_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="room_name">'.$gL10n->get('SYS_ROOM').':</label></dt>
                    <dd>
                        <input type="text" id="room_name" name="room_name" style="width: 345px;" maxlength="100" value="'. $room->getValue('room_name'). '" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <hr/>
            <li>
                <dl>
                    <dt><label for="room_capacity">'.$gL10n->get('ROO_CAPACITY').':</label></dt>
                    <dd>
                        <input type="text" id="room_capacity" name="room_capacity" style="width: 40px;" maxlength="5" value="'. $room->getValue('room_capacity'). '" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        &nbsp; '.$gL10n->get('ROO_SEATING').'
                    </dd>
                </dl>
            </li>
             <li>
                <dl>
                    <dt><label for="room_overhang">'.$gL10n->get('ROO_OVERHANG').':</label></dt>
                    <dd>
                        <input type="text" id="room_overhang" name="room_overhang" style="width: 40px;" maxlength="5" value="'. $room->getValue('room_overhang'). '" />';
                        if($gPreferences['dates_show_map_link'])
                        {
                            echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_ROOM_OVERHANG&amp;inline=true"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_ROOM_OVERHANG\',this)" onmouseout="ajax_hideTooltip()"
                                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';
                        }
                        echo ' '.$gL10n->get('ROO_STANDING').' / '.$gL10n->get('ROO_SEATING').'
                    </dd>
                </dl>
            </li><br/>';
             if ($gPreferences['enable_bbcode'] == 1)
            {
               printBBcodeIcons();
            }
            echo'<li>
                <dl>
                    <dt><label for="room_description">'.$gL10n->get('SYS_DESCRIPTION').':</label>';
                        if($gPreferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="room_description" name="room_description" style="width: 345px;" rows="10" cols="40">'. $room->getValue('room_description'). '</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />';

        if($room->getValue('room_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gProfileFields, $room->getValue('room_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $room->getValue('room_timestamp_create'));

                if($room->getValue('room_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gProfileFields, $room->getValue('dat_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $room->getValue('room_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
        </div>   
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';  


require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>
