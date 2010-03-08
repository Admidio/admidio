<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 * pho_id: id des Albums das bearbeitet werden soll
 * job:    - new (neues Formular)
 *         - change (Formular fuer Aenderunmgen)
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_photos.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show($g_l10n->get('PHO_PHR_NO_RIGHTS'));
}

// Uebergabevariablen pruefen
//Albumsuebergabe Numerisch und != Null?
if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']) == false && $_GET['pho_id']!=NULL)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Aufgabe gesetzt, welche Aufgabe
if(isset($_GET['job']) && $_GET['job'] != 'new' && $_GET['job'] != 'change')
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Variablen initialisieren
$pho_id = $_GET['pho_id'];
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoalbumobjekt anlegen
$photo_album = new TablePhotos($g_db);

// nur Daten holen, wenn Album editiert werden soll
if ($_GET['job'] == 'change')
{
    $photo_album->readData($pho_id);

    // Pruefung, ob das Fotoalbum zur aktuellen Organisation gehoert
    if($photo_album->getValue('pho_org_shortname') != $g_organization)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
}

if(isset($_SESSION['photo_album_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['photo_album_request'] as $key => $value)
    {
        if(strpos($key, 'pho_') == 0)
        {
            $photo_album->setValue($key, stripslashes($value));
        }
    }
    unset($_SESSION['photo_album_request']);
}


// die Albenstruktur fuer eine Auswahlbox darstellen und das aktuelle Album vorauswählen
function subfolder($parent_id, $vorschub, $photo_album, $pho_id)
{
    global $g_db;
    $vorschub = $vorschub.'&nbsp;&nbsp;&nbsp;';
    $pho_id_condition = '';
    $parentPhotoAlbum = new TablePhotos($g_db);

    //Erfassen des auszugebenden Albums
    if($parent_id > 0)
    {
        $pho_id_condition .= ' AND pho_pho_id_parent = "'.$parent_id.'" ';
    }
    else
    {
        $pho_id_condition .= ' AND pho_pho_id_parent IS NULL ';
    }

    $sql = 'SELECT *
              FROM '. TBL_PHOTOS. '
             WHERE pho_id <> '. $photo_album->getValue('pho_id').
                   $pho_id_condition;
    $result_child = $g_db->query($sql);

    while($adm_photo_child = $g_db->fetch_array($result_child))
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

if($_GET['job']=='new')
{
    $g_layout['title'] = 'Neues Album anlegen';
}
elseif($_GET['job']=='change')
{
    $g_layout['title'] = 'Album bearbeiten';
}
$g_layout['header'] = '
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
require(THEME_SERVER_PATH. "/overall_header.php");


/****************************Formular***********************************************/

echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/photos/photo_album_function.php?pho_id='. $_GET['pho_id']. '&amp;job='. $_GET['job']. '">
<div class="formLayout" id="photo_album_new_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">';
        //Album
        echo'
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="pho_name">Album:</label></dt>
                    <dd>
                        <input type="text" id="pho_name" name="pho_name" style="width: 300px;" maxlength="50" tabindex="1" value="'.$photo_album->getValue('pho_name').'" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>';

            //Unterordnung
            echo'
            <li>
                <dl>
                    <dt><label for="pho_pho_id_parent">im Album:</label></dt>
                    <dd>
                        <select size="1" id="pho_pho_id_parent" name="pho_pho_id_parent" style="max-width: 95%;" tabindex="2">
                            <option value="0">Fotogalerien(Hauptordner)</option>';
                                // die Albenstruktur darstellen und das aktuelle Album vorauswählen
                                subfolder($adm_photo_list['pho_id'], '', $photo_album, $pho_id);
                        echo '</select>
                    </dd>
                </dl>
            </li>';

            // Beginn / Ende
            echo '
            <li>
                <dl>
                    <dt><label for="pho_begin">Beginn:</label></dt>
                    <dd>
                        <input type="text" id="pho_begin" name="pho_begin" size="10" tabindex="3" maxlength="10" value="'. $photo_album->getValue('pho_begin').'" />
                        <a class="iconLink" id="anchor_pho_begin" href="javascript:calPopup.select(document.getElementById(\'pho_begin\'),\'anchor_pho_begin\',\''.$g_preferences['system_date'].'\',\'pho_begin\',\'pho_end\');"><img 
                        	src="'.THEME_PATH.'/icons/calendar.png" alt="Kalender anzeigen" title="Kalender anzeigen" /></a>
                        <span id="calendardiv" style="position: absolute; visibility: hidden;"></span>
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="pho_end">Ende:</label></dt>
                    <dd>
                        <input type="text" id="pho_end" name="pho_end" size="10" tabindex="4" maxlength="10" value="'. $photo_album->getValue('pho_end').'">
                        <a class="iconLink" id="anchor_pho_end" href="javascript:calPopup.select(document.getElementById(\'pho_end\'),\'anchor_pho_end\',\''.$g_preferences['system_date'].'\',\'pho_begin\',\'pho_end\');"><img 
                        	src="'. THEME_PATH. '/icons/calendar.png" alt="Kalender anzeigen" title="Kalender anzeigen" /></a>
                    </dd>
                </dl>
            </li>';

            //Photographen
            echo'
            <li>
                <dl>
                    <dt><label for="pho_photographers">Fotografen:</label></dt>
                    <dd>
                        <input type="text" id="pho_photographers" name="pho_photographers" style="width: 300px;" tabindex="5" maxlength="100" value="'.$photo_album->getValue('pho_photographers').'" />
                    </dd>
                </dl>
            </li>';

            //Freigabe
            echo'
            <li>
                <dl>
                    <dt><label for="pho_locked">Sperren:</label></dt>
                    <dd>';
                        echo '<input type="checkbox" id="pho_locked" name="pho_locked" tabindex="6" value="1"';

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
                $user_create = new User($g_db, $photo_album->getValue('pho_usr_id_create'));
                echo $g_l10n->get('SYS_PHR_CREATED_BY', $user_create->getValue('Vorname'). ' '. $user_create->getValue('Nachname'), $photo_album->getValue('pho_timestamp_create'));

                if($photo_album->getValue('pho_usr_id_change') > 0)
                {
                    $user_change = new User($g_db, $photo_album->getValue('pho_usr_id_change'));
                    echo '<br />'.$g_l10n->get('SYS_PHR_LAST_EDITED_BY', $user_change->getValue('Vorname'). ' '. $user_change->getValue('Nachname'), $photo_album->getValue('pho_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button name="submit" type="submit" tabindex="7" value="speichern"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
        </div>

    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="Zurück" tabindex="8" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=album_help&amp;inline=true"><img 
            	src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" /></a>
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=album_help&amp;inline=true">Hilfe</a>
        </span>
    </li>
</ul>';

/***********************************Ende********************************************/
require(THEME_SERVER_PATH. '/overall_footer.php');

?>