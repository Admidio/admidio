<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/table_rooms.php'); 

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->isWebmaster())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_room_id   = 0;
if ($g_preferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}
if(isset($_GET['room_id']))
{
    if(is_numeric($_GET['room_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_room_id = $_GET['room_id'];
}

if(!isset($_GET['headline']))
{
    $_GET['headline'] = $g_l10n->get('SYS_ROOM');
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

$room = new TableRooms($g_db);
if($req_room_id > 0)
{
    $room->readData($req_room_id);
}

// Html-Kopf ausgeben
if($req_room_id > 0)
{
    $g_layout['title'] = $g_l10n->get('SYS_EDIT_VAR', $_GET['headline']);
}
else
{
    $g_layout['title'] = $g_l10n->get('SYS_CREATE_VAR', $_GET['headline']);
}

//Script für BBCode laden
$javascript = '';
if ($g_preferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('room_description');
}

$g_layout['header'] = $javascript;
require(THEME_SERVER_PATH. '/overall_header.php');

echo '
<form method="post" action="'.$g_root_path.'/adm_program/administration/rooms/rooms_function.php?room_id='.$req_room_id.'&amp;mode=1">
<div class="formLayout" id="edit_dates_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="room_name">'.$g_l10n->get('SYS_ROOM').':</label></dt>
                    <dd>
                        <input type="text" id="room_name" name="room_name" style="width: 345px;" maxlength="100" value="'. $room->getValue('room_name'). '" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <hr/>
            <li>
                <dl>
                    <dt><label for="room_capacity">'.$g_l10n->get('ROO_CAPACITY').':</label></dt>
                    <dd>
                        <input type="text" id="room_capacity" name="room_capacity" style="width: 40px;" maxlength="5" value="'. $room->getValue('room_capacity'). '" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        &nbsp; '.$g_l10n->get('ROO_SEATING').'
                    </dd>
                </dl>
            </li>
             <li>
                <dl>
                    <dt><label for="room_overhang">'.$g_l10n->get('ROO_OVERHANG').':</label></dt>
                    <dd>
                        <input type="text" id="room_overhang" name="room_overhang" style="width: 40px;" maxlength="5" value="'. $room->getValue('room_overhang'). '" />';
                        if($g_preferences['dates_show_map_link'])
                        {
                            echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_ROOM_OVERHANG&amp;inline=true"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_ROOM_OVERHANG\',this)" onmouseout="ajax_hideTooltip()"
                                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';
                        }
                        echo ' '.$g_l10n->get('ROO_STANDING').' / '.$g_l10n->get('ROO_SEATING').'
                    </dd>
                </dl>
            </li><br/>';
             if ($g_preferences['enable_bbcode'] == 1)
            {
               printBBcodeIcons();
            }
            echo'<li>
                <dl>
                    <dt><label for="room_description">'.$g_l10n->get('SYS_DESCRIPTION').':</label>';
                        if($g_preferences['enable_bbcode'] == 1)
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
                $user_create = new User($g_db, $room->getValue('room_usr_id_create'));
                echo $g_l10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $room->getValue('room_timestamp_create'));

                if($room->getValue('room_usr_id_change') > 0)
                {
                    $user_change = new User($g_db, $room->getValue('dat_usr_id_change'));
                    echo '<br />'.$g_l10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $room->getValue('room_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
        </div>   
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';  


require(THEME_SERVER_PATH. '/overall_footer.php');
?>
