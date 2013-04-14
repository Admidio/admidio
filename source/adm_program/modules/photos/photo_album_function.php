<?php
/******************************************************************************
 * Various functions for photo albums
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id       : Id of photo album that should be edited
 * job - new    : create a new photo album
 *     - change : edit a photo album
 *     - delete : delete a photo album
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_photos.php');
require_once(SERVER_PATH. '/adm_program/system/classes/email.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', 0);
$getJob     = admFuncVariableIsValid($_GET, 'job', 'string', null, true, array('new', 'change', 'delete'));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//nur von eigentlicher OragHompage erreichbar
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) != 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $gHomepage));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
}

//Gepostete Variablen in Session speichern
$_SESSION['photo_album_request'] = $_POST;

// Fotoalbumobjekt anlegen
$photo_album = new TablePhotos($gDb);

if($getJob != 'new')
{
    $photo_album->readDataById($getPhotoId);
    
    // Pruefung, ob das Fotoalbum zur aktuellen Organisation gehoert
    if($photo_album->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

//Speicherort mit dem Pfad aus der Datenbank
$ordner = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d').'_'.$photo_album->getValue('pho_id');

/********************Aenderungen oder Neueintraege kontrollieren***********************************/
if($getJob == 'new' || $getJob == 'change')
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
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('PHO_ALBUM')));
    }
	
    //Beginn
    if(strlen($_POST['pho_begin']) > 0)
    {
        $startDate = new DateTimeExtended($_POST['pho_begin'], $gPreferences['system_date'], 'date');
        
        if($startDate->valid())
        {
            $_POST['pho_begin'] = $startDate->format('Y-m-d');
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_START'), $gPreferences['system_date']));
        }
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_START')));
    }    
    
    //Ende
    if(strlen($_POST['pho_end']) > 0)
    {
        $endDate = new DateTimeExtended($_POST['pho_end'], $gPreferences['system_date'], 'date');

        if($endDate->valid())
        {
            $_POST['pho_end'] = $endDate->format('Y-m-d');
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_END'), $gPreferences['system_date']));
        }
    }
    else
    {
        $_POST['pho_end'] = $_POST['pho_begin'];
    }

    //Anfang muss vor oder gleich Ende sein
    if(strlen($_POST['pho_end']) > 0 && $_POST['pho_end'] < $_POST['pho_begin'])
    {
        $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    }

    //Photographen
    if(strlen($_POST['pho_photographers']) == 0)
    {
        $_POST['pho_photographers'] = $gL10n->get('SYS_UNKNOWN');
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
    if ($getJob == 'new')
    {
        // Album in Datenbank schreiben
        $photo_album->save();
        
        $error = $photo_album->createFolder();
        
        if(strlen($error['text']) > 0)
        {
            $photo_album->delete();
            
            // der entsprechende Ordner konnte nicht angelegt werden
            $gMessage->setForwardUrl($g_root_path.'/adm_program/modules/photos/photos.php');
            $gMessage->show($gL10n->get($error['text'], $error['path'], '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
        }
		
		if(strlen($error['text']) == 0)
		{ 
			// Benachrichtigungs-Email für neue Einträge
		    $notification = new Email();
            $message = $gL10n->get('PHO_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $_POST['pho_name'], $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gPreferences['system_date'], time()));
            $notification->adminNotfication($gL10n->get('PHO_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));		
		}
        
        $getPhotoId = $photo_album->getValue('pho_id');

        // Anlegen des Albums war erfolgreich -> album_new aus der Historie entfernen
        $gNavigation->deleteLastUrl();
    }//if

    /********************Aenderung des Ordners***********************************/
    // Wurde das Anfangsdatum bearbeitet, muss sich der Ordner aendern
    elseif ($getJob=='change' && $ordner != SERVER_PATH. '/adm_my_files/photos/'.$_POST['pho_begin'].'_'.$getPhotoId)
    {
        $newFolder = SERVER_PATH. '/adm_my_files/photos/'.$_POST['pho_begin'].'_'.$photo_album->getValue('pho_id');
        
        // das komplette Album in den neuen Ordner kopieren
        $albumFolder = new Folder($ordner);
        $b_return = $albumFolder->move($newFolder);
        
        // Verschieben war nicht erfolgreich, Schreibrechte vorhanden ?
        if($b_return == false)
        {
            $gMessage->setForwardUrl($g_root_path.'/adm_program/modules/photos/photos.php');
            $gMessage->show($gL10n->get('SYS_FOLDER_WRITE_ACCESS', $newFolder, '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
        }

        // Aendern des Albums war erfolgreich -> album_new aus der Historie entfernen
        $gNavigation->deleteLastUrl();
    }//if

    /********************Aenderung der Datenbankeinträge***********************************/

    if($getJob == 'change')
    {
        // geaenderte Daten in der Datenbank akutalisieren
        $photo_album->save();
    }

    //Photomodulspezifische CSS laden
    $gLayout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';
    
    // HTML-Kopf
    $gLayout['title'] = $gL10n->get('SYS_END');
    require(SERVER_PATH. '/adm_program/system/overall_header.php');

    echo'
    <div class="formLayout" id="photo_report_form">
        <div class="formHead">'.$gL10n->get('SYS_REPORT').'</div>
        <div class="formBody"> 
            <p>'.$gL10n->get('PHO_ALBUM_WRITE_SUCCESS').'</p>  
            <ul class="formFieldList">
                <li><dl>
                    <dt>'.$gL10n->get('SYS_REPORT').':</dt>
                    <dd>'.$photo_album->getValue('pho_name').'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$gL10n->get('PHO_PARENT_ALBUM').':</dt>
                    <dd>';
                        if($photo_album->getValue('pho_pho_id_parent') > 0)
                        {
                            $photo_album_parent = new TablePhotos($gDb, $photo_album->getValue('pho_pho_id_parent'));
                            echo $photo_album_parent->getValue('pho_name');
                        }
                        else
                        {
                            echo $gL10n->get('PHO_PHOTO_ALBUMS');
                        }
                    echo'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$gL10n->get('SYS_START').':</dt>
                    <dd>'.$photo_album->getValue('pho_begin', $gPreferences['system_date']).'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$gL10n->get('SYS_END').':</dt>
                    <dd>'.$photo_album->getValue('pho_end', $gPreferences['system_date']).'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$gL10n->get('PHO_PHOTOGRAPHER').':</dt>
                    <dd>'.$photo_album->getValue('pho_photographers').'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$gL10n->get('SYS_LOCKED').':</dt>
                    <dd>';
                        if($photo_album->getValue('pho_locked')==1)
                        {
                             echo $gL10n->get('SYS_YES');
                        }
                        else
                        {
                             echo $gL10n->get('SYS_NO');
                        }   
                    echo'</dd>
                </dl></li>

                <li><dl>
                    <dt>'.$gL10n->get('PHO_NUMBER_OF_FOTOS').':</dt>
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
                <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId.'">'.$gL10n->get('SYS_NEXT').'&nbsp;</a>
                <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId.'"><img src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('SYS_NEXT').'" /></a>
            </span>
        </li>
    </ul>';
}

/**************************************************************************/

elseif($getJob == 'delete')
{
	// Album loeschen
    if($photo_album->delete())
    {
        echo 'done'; 
    }
    exit();
}

require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>