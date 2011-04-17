<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 * pho_id: id des Albums das bearbeitet werden soll
 * job:    - new    (neue eingaben speichern)
 *         - change (Aenderungen ausfuehren)
 *         - delete (Loeschen eines Albums)
 *		   - set_rights
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_photos.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show($g_l10n->get('PHO_NO_RIGHTS'));
}

// Uebergabevariablen pruefen

if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']) == false && $_GET['pho_id']!=NULL)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['job']) && $_GET['job'] != 'new' && $_GET['job'] != 'delete' && $_GET['job'] != 'change')
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Gepostete Variablen in Session speichern
$_SESSION['photo_album_request'] = $_REQUEST;

//Uebernahme Variablen
$pho_id  = $_GET['pho_id'];

// Fotoalbumobjekt anlegen
$photo_album = new TablePhotos($g_db);

if($_GET['job'] != 'new')
{
    $photo_album->readData($pho_id);
    
    // Pruefung, ob das Fotoalbum zur aktuellen Organisation gehoert
    if($photo_album->getValue('pho_org_shortname') != $g_organization)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
}

//Speicherort mit dem Pfad aus der Datenbank
$ordner = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d').'_'.$photo_album->getValue('pho_id');

/********************Aenderungen oder Neueintraege kontrollieren***********************************/
if(isset($_POST['submit']) && $_POST['submit'])
{
    //Gesendete Variablen Uebernehmen und kontollieren

    //Freigabe(muss zuerst gemacht werden da diese nicht gesetzt sein koennte)
    if(isset($_POST['pho_locked']) == false)
    {
        $_POST['pho_locked'] = 0;
    }
    //Album
    if(strlen($_POST['pho_name']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', 'Album'));
    }
	
    //Beginn
    if(strlen($_POST['pho_begin'] > 0))
    {
        $startDate = new DateTimeExtended($_POST['pho_begin'], $g_preferences['system_date'], 'date');
        
        if($startDate->valid())
        {
            $_POST['pho_begin'] = $startDate->format('Y-m-d');
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_DATE_INVALID', 'Beginn', $g_preferences['system_date']));
        }
    }
    else
    {
        $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('START')));
    }    
    
    //Ende
    if(strlen($_POST['pho_end']) > 0)
    {
        $endDate = new DateTimeExtended($_POST['pho_end'], $g_preferences['system_date'], 'date');

        if($endDate->valid())
        {
            $_POST['pho_end'] = $endDate->format('Y-m-d');
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_DATE_INVALID', $g_l10n->get('SYS_END'), $g_preferences['system_date']));
        }
    }
    else
    {
        $_POST['pho_end'] = $_POST['pho_begin'];
    }

    //Anfang muss vor oder gleich Ende sein
    if(strlen($_POST['pho_end']) > 0 && $_POST['pho_end'] < $_POST['pho_begin'])
    {
        $g_message->show($g_l10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    }

    //Photographen
    if(strlen($_POST['pho_photographers']) == 0)
    {
        $_POST['pho_photographers'] = $g_l10n->get('SYS_UNKNOWN');
    }

    // POST Variablen in das Role-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'pho_') === 0)
        {
            $photo_album->setValue($key, $value);
        }
    }
    
    /********************neuen Datensatz anlegen***********************************/
    if ($_GET['job']=='new')
    {
        // Album in Datenbank schreiben
        $photo_album->save();
        
        $error = $photo_album->createFolder();
        
        if(strlen($error['text']) > 0)
        {
            $photo_album->delete();
            
            // der entsprechende Ordner konnte nicht angelegt werden
            $g_message->setForwardUrl($g_root_path.'/adm_program/modules/photos/photos.php');
            $g_message->show($g_l10n->get($error['text'], $error['path'], '<a href="mailto:'.$g_preferences['email_administrator'].'">', '</a>'));
        }
		
		if(strlen($error['text']) == 0)
		{
			// Benachrichtigungs-Email für neue Einträge
			if($g_preferences['enable_email_notification'] == 1)
			{
				EmailNotification($g_preferences['email_administrator'], $g_current_organization->getValue('org_shortname'). ": ".$g_l10n->get('PHO_EMAIL_NOTIFICATION_TITLE'), str_replace("<br />","\n",$g_l10n->get('PHO_EMAIL_NOTIFICATION_MESSAGE', $g_current_organization->getValue('org_longname'), $_POST['pho_name'], $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME'), date("d.m.Y H:m", time()))), $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME'), $g_current_user->getValue('EMAIL'));
			}	
		}
        
        $pho_id = $photo_album->getValue('pho_id');

        // Anlegen des Albums war erfolgreich -> album_new aus der Historie entfernen
        $_SESSION['navigation']->deleteLastUrl();
    }//if

    /********************Aenderung des Ordners***********************************/
    // Bearbeiten Anfangsdatum und Ordner geaendert
    elseif ($_GET['job']=='change' && $ordner != SERVER_PATH. '/adm_my_files/photos/'.$_POST['pho_begin'].'_'.$pho_id)
    {
        $newFolder = SERVER_PATH. '/adm_my_files/photos/'.$_POST['pho_begin'].'_'.$photo_album->getValue('pho_id');
        
        // das komplette Album in den neuen Ordner kopieren
        $albumFolder = new Folder($ordner);
        $b_return = $albumFolder->move($newFolder);
        
        // Verschieben war nicht erfolgreich, Schreibrechte vorhanden ?
        if($b_return == false)
        {
            $g_message->setForwardUrl($g_root_path.'/adm_program/modules/photos/photos.php');
            $g_message->show($g_l10n->get('SYS_FOLDER_WRITE_ACCESS', $newFolder, '<a href="mailto:'.$g_preferences['email_administrator'].'">', '</a>'));
        }

        // Aendern des Albums war erfolgreich -> album_new aus der Historie entfernen
        $_SESSION['navigation']->deleteLastUrl();
    }//if

    /********************Aenderung der Datenbankeinträge***********************************/

    if($_GET['job']=='change')
    {
        // geaenderte Daten in der Datenbank akutalisieren
        $photo_album->save();
    }

    //Photomodulspezifische CSS laden
    $g_layout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';
    
    // HTML-Kopf
    $g_layout['title'] = $g_l10n->get('SYS_END');
    require(THEME_SERVER_PATH. '/overall_header.php');

    echo'
    <div class="formLayout" id="photo_report_form">
        <div class="formHead">'.$g_l10n->get('SYS_REPORT').'</div>
        <div class="formBody"> 
            <p>'.$g_l10n->get('PHO_ALBUM_WRITE_SUCCESS').'</p>  
            <ul class="formFieldList">
                <li><dl>
                    <dt>'.$g_l10n->get('SYS_REPORT').':</dt>
                    <dd>'.$photo_album->getValue('pho_name').'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$g_l10n->get('PHO_PARENT_ALBUM').':</dt>
                    <dd>';
                        if($photo_album->getValue('pho_pho_id_parent') > 0)
                        {
                            $photo_album_parent = new TablePhotos($g_db, $photo_album->getValue('pho_pho_id_parent'));
                            echo $photo_album_parent->getValue('pho_name');
                        }
                        else
                        {
                            echo $g_l10n->get('PHO_PHOTO_ALBUMS');
                        }
                    echo'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$g_l10n->get('SYS_START').':</dt>
                    <dd>'.$photo_album->getValue('pho_begin', $g_preferences['system_date']).'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$g_l10n->get('SYS_END').':</dt>
                    <dd>'.$photo_album->getValue('pho_end', $g_preferences['system_date']).'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$g_l10n->get('PHO_PHOTOGRAPHER').':</dt>
                    <dd>'.$photo_album->getValue('pho_photographers').'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$g_l10n->get('SYS_LOCKED').':</dt>
                    <dd>';
                        if($photo_album->getValue('pho_locked')==1)
                        {
                             echo $g_l10n->get('SYS_YES');
                        }
                        else
                        {
                             echo $g_l10n->get('SYS_NO');
                        }   
                    echo'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$g_l10n->get('PHO_NUMBER_OF_FOTOS').':</dt>
                    <dd>';
                        if($photo_album->getValue('pho_quantity')!=NULL)
                        {
                            echo $photo_album->getValue('pho_quantity');
                        }
                        else
                        {
                            echo'0';
                        }
                    echo'</dd>
                </dl></li>
            <ul>
        </div>
    </div>
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$pho_id.'">'.$g_l10n->get('SYS_NEXT').'&nbsp;</a>
                <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$pho_id.'"><img src="'. THEME_PATH. '/icons/forward.png" alt="'.$g_l10n->get('SYS_NEXT').'" /></a>
            </span>
        </li>
    </ul>';
}//submit


/***********************Album Loeschen*******************************************/

if(isset($_GET['job']) && $_GET['job']=='delete')
{
    if($photo_album->delete())
    {
        echo 'done'; 
    }
    exit();
}

require(THEME_SERVER_PATH. '/overall_footer.php');
?>