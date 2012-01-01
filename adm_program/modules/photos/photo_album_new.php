<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 * pho_id: id des Albums das bearbeitet werden soll
 * job:    - new (neues Formular)
 *         - change (Formular fuer Aenderunmgen)
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_photos.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', 0);
$getJob     = admFuncVariableIsValid($_GET, 'job', 'string', null, true, array('new', 'change'));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoalbumobjekt anlegen
$photo_album = new TablePhotos($gDb);

// nur Daten holen, wenn Album editiert werden soll
if ($getJob == 'change')
{
    $photo_album->readData($getPhotoId);

    // Pruefung, ob das Fotoalbum zur aktuellen Organisation gehoert
    if($photo_album->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['photo_album_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$photo_album->setArray($_SESSION['photo_album_request']);
    unset($_SESSION['photo_album_request']);
}

// die Albenstruktur fuer eine Auswahlbox darstellen und das aktuelle Album vorauswählen
function subfolder($parent_id, $vorschub, $photo_album, $pho_id)
{
    global $gDb;
    global $gCurrentOrganization;
    $vorschub = $vorschub.'&nbsp;&nbsp;&nbsp;';
    $pho_id_condition = '';
    $parentPhotoAlbum = new TablePhotos($gDb);

    //Erfassen des auszugebenden Albums
    if($parent_id > 0)
    {
        $pho_id_condition .= ' AND pho_pho_id_parent = \''.$parent_id.'\' ';
    }
    else
    {
        $pho_id_condition .= ' AND pho_pho_id_parent IS NULL ';
    }

    $sql = 'SELECT *
              FROM '. TBL_PHOTOS. '
             WHERE pho_id <> '. $photo_album->getValue('pho_id').
                   $pho_id_condition
                   .' AND pho_org_shortname LIKE \''.$gCurrentOrganization->getValue('org_shortname').'\'';
    $result_child = $gDb->query($sql);

    while($adm_photo_child = $gDb->fetch_array($result_child))
    {
        $selected = '';
        
        $parentPhotoAlbum->clear();
        $parentPhotoAlbum->setArray($adm_photo_child);
        
        //Wenn die Elternveranstaltung von pho_id dann selected
        if(($parentPhotoAlbum->getValue('pho_id') == $photo_album->getValue('pho_pho_id_parent'))
        ||  $parentPhotoAlbum->getValue('pho_id') == $pho_id)
        {
            $selected = 'selected="selected"';
        }

        // Ausgabe des Albums in der Liste der Auswahlbox
        echo'<option value="'.$parentPhotoAlbum->getValue('pho_id').'" '.$selected.'>'.
        $vorschub.'&#151; '.$parentPhotoAlbum->getValue('pho_name')
        .'&nbsp('.$parentPhotoAlbum->getValue('pho_begin', 'Y').')</option>';

        subfolder($parentPhotoAlbum->getValue('pho_id'), $vorschub, $photo_album, $pho_id);
    }//while
}//function

/******************************HTML-Kopf******************************************/

if($getJob=='new')
{
    $gLayout['title'] = $gL10n->get('PHO_CREATE_ALBUM');
}
elseif($getJob=='change')
{
    $gLayout['title'] = $gL10n->get('PHO_EDIT_ALBUM');
}
$gLayout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/date-functions.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
    <link rel="stylesheet" href="'.THEME_PATH. '/css/calendar.css" type="text/css" />
    <script type="text/javascript"><!--
        var calPopup = new CalendarPopup("calendardiv");
        calPopup.setCssPrefix("calendar");
        
        $(document).ready(function() 
		{
            $("#pho_name").focus();
	 	}); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');


/****************************Formular***********************************************/

echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/photos/photo_album_function.php?pho_id='. $getPhotoId. '&amp;job='. $getJob. '">
<div class="formLayout" id="photo_album_new_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">';
        //Album
        echo'
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="pho_name">'.$gL10n->get('PHO_ALBUM').':</label></dt>
                    <dd>
                        <input type="text" id="pho_name" name="pho_name" style="width: 300px;" maxlength="50" value="'.$photo_album->getValue('pho_name').'" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>';

            //Unterordnung
            echo'
            <li>
                <dl>
                    <dt><label for="pho_pho_id_parent">'.$gL10n->get('PHO_PARENT_ALBUM').':</label></dt>
                    <dd>
                        <select size="1" id="pho_pho_id_parent" name="pho_pho_id_parent" style="max-width: 95%;">
                            <option value="0">'.$gL10n->get('PHO_PHOTO_ALBUMS').'</option>';
                                // die Albenstruktur darstellen und das aktuelle Album vorauswählen
                                subfolder($adm_photo_list['pho_id'], '', $photo_album, $getPhotoId);
                        echo '</select>
                    </dd>
                </dl>
            </li>';

            // Beginn / Ende
            echo '
            <li>
                <dl>
                    <dt><label for="pho_begin">'.$gL10n->get('SYS_START').':</label></dt>
                    <dd>
                        <input type="text" id="pho_begin" name="pho_begin" size="10" maxlength="10" value="'. $photo_album->getValue('pho_begin').'" />
                        <a class="iconLink" id="anchor_pho_begin" href="javascript:calPopup.select(document.getElementById(\'pho_begin\'),\'anchor_pho_begin\',\''.$gPreferences['system_date'].'\',\'pho_begin\',\'pho_end\');"><img 
                        	src="'.THEME_PATH.'/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>
                        <span id="calendardiv" style="position: absolute; visibility: hidden;"></span>
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="pho_end">'.$gL10n->get('SYS_END').':</label></dt>
                    <dd>
                        <input type="text" id="pho_end" name="pho_end" size="10" maxlength="10" value="'. $photo_album->getValue('pho_end').'">
                        <a class="iconLink" id="anchor_pho_end" href="javascript:calPopup.select(document.getElementById(\'pho_end\'),\'anchor_pho_end\',\''.$gPreferences['system_date'].'\',\'pho_begin\',\'pho_end\');"><img 
                        	src="'. THEME_PATH. '/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>
                    </dd>
                </dl>
            </li>';

            //Photographen
            echo'
            <li>
                <dl>
                    <dt><label for="pho_photographers">'.$gL10n->get('PHO_PHOTOGRAPHER').':</label></dt>
                    <dd>
                        <input type="text" id="pho_photographers" name="pho_photographers" style="width: 300px;" maxlength="100" value="'.$photo_album->getValue('pho_photographers').'" />
                    </dd>
                </dl>
            </li>';

            //Freigabe
            echo'
            <li>
                <dl>
                    <dt><label for="pho_locked">'.$gL10n->get('SYS_LOCK').':</label></dt>
                    <dd>';
                        echo '<input type="checkbox" id="pho_locked" name="pho_locked" value="1"';

                        if($photo_album->getValue('pho_locked') == 1)
                        {
                            echo 'checked = "checked" ';
                        }

                     echo' /></dd>
                </dl>
            </li>
        </ul>

        <hr />';

        if($photo_album->getValue('pho_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gProfileFields, $photo_album->getValue('pho_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $photo_album->getValue('pho_timestamp_create'));

                if($photo_album->getValue('pho_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gProfileFields, $photo_album->getValue('pho_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $photo_album->getValue('pho_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit" name="submit" value="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
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
    <li>
        <span class="iconTextLink">
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=PHO_ALBUM_HELP_DESC&amp;message_title=SYS_WHAT_TO_DO&amp;inline=true"><img 
            	src="'. THEME_PATH. '/icons/help.png" alt="Help" /></a>
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=PHO_ALBUM_HELP_DESC&amp;message_title=SYS_WHAT_TO_DO&amp;inline=true">'.$gL10n->get('SYS_HELP').'</a>
        </span>
    </li>
</ul>';

/***********************************Ende********************************************/
require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>