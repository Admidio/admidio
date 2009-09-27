<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
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
    $g_message->show('norights');
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
        $g_message->show('invalid');
    }
    $req_room_id = $_GET['room_id'];
}

if(!isset($_GET['headline']))
{
    $_GET['headline'] = 'Raum';
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
    $g_layout['title'] = $_GET['headline']. ' bearbeiten';
}
else
{
    $g_layout['title'] = $_GET['headline']. ' anlegen';
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
                    <dt><label for="room_name">Name/Raumnummer:</label></dt>
                    <dd>
                        <input type="text" id="room_name" name="room_name" style="width: 345px;" maxlength="100" value="'. $room->getValue('room_name'). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <hr/>
            <li>
                <dl>
                    <dt><label for="room_capacity">Kapazit&auml;t:</label></dt>
                    <dd>
                        <input type="text" id="room_capacity" name="room_capacity" style="width: 40px;" maxlength="5" value="'. $room->getValue('room_capacity'). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                        &nbsp; Sitzpl&auml;tze
                    </dd>
                </dl>
            </li>
             <li>
                <dl>
                    <dt><label for="room_overhang">&Uuml;berhang:</label></dt>
                    <dd>
                        <input type="text" id="room_overhang" name="room_overhang" style="width: 40px;" maxlength="5" value="'. $room->getValue('room_overhang'). '" />';
                        if($g_preferences['dates_show_map_link'])
                        {
                            echo '<a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=room_overhang&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=200&amp;width=580"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=room_overhang\',this)" onmouseout="ajax_hideTooltip()"
                                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>';
                        }
                        echo ' Steh-/Sitzpl&auml;tze
                    </dd>
                </dl>
            </li><br/>';
             if ($g_preferences['enable_bbcode'] == 1)
            {
               printBBcodeIcons();
            }
            echo'<li>
                <dl>
                    <dt><label for="room_description">Beschreibung:</label>';
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>';
                    if($req_room_id>0)
                    {
                        echo '
                        <textarea id="room_description" name="room_description" style="width: 345px;" rows="10" cols="40">'. $room->getValue('room_description'). '</textarea>';
                    }
                    else
                    {
                        $default = 
                        'kein Beamer, kein WLAN, kein Mikrofon';
                        echo '
                        <textarea id="room_description" name="room_description" style="width: 345px;" rows="10" cols="40">'.$default.'</textarea>';
                    }
                    echo '
                    </dd>
                </dl>
            </li>
            <hr/>
        </ul> 
        <div class="formSubmit">
            <button name="speichern" type="submit" value="speichern"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
        </div>   
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="Zurück" title="Zur&uuml;ck"/></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zur&uuml;ck</a>
        </span>
    </li>
</ul>';  


require(THEME_SERVER_PATH. '/overall_footer.php');
?>
