<?php
/**
 ***********************************************************************************************
 * Various functions for photo albums
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * pho_id        : Id of photo album that should be edited
 * mode - new    : create a new photo album
 *      - change : edit a photo album
 *      - delete : delete a photo album
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'int');
$getMode    = admFuncVariableIsValid($_GET, 'mode',   'string', array('requireValue' => true, 'validValues' => array('new', 'change', 'delete')));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

// Gepostete Variablen in Session speichern
$_SESSION['photo_album_request'] = $_POST;

// Fotoalbumobjekt anlegen
$photo_album = new TablePhotos($gDb);

if($getMode !== 'new' && $getPhotoId > 0)
{
    $photo_album->readDataById($getPhotoId);

    // Pruefung, ob das Fotoalbum zur aktuellen Organisation gehoert
    if((int) $photo_album->getValue('pho_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

// Speicherort mit dem Pfad aus der Datenbank
$albumPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photo_album->getValue('pho_begin', 'Y-m-d') . '_' . $photo_album->getValue('pho_id');

/********************Aenderungen oder Neueintraege kontrollieren***********************************/
if($getMode === 'new' || $getMode === 'change')
{
    // Gesendete Variablen Uebernehmen und kontollieren

    // Freigabe(muss zuerst gemacht werden da diese nicht gesetzt sein koennte)
    if(!isset($_POST['pho_locked']))
    {
        $_POST['pho_locked'] = 0;
    }
    // Album
    if(strlen($_POST['pho_name']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('PHO_ALBUM')));
        // => EXIT
    }

    // Beginn
    if(strlen($_POST['pho_begin']) > 0)
    {
        $startDate = DateTime::createFromFormat($gPreferences['system_date'], $_POST['pho_begin']);
        if($startDate === false)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_START'), $gPreferences['system_date']));
            // => EXIT
        }
        else
        {
            $_POST['pho_begin'] = $startDate->format('Y-m-d');
        }
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_START')));
        // => EXIT
    }

    // Ende
    if(strlen($_POST['pho_end']) > 0)
    {
        $endDate = DateTime::createFromFormat($gPreferences['system_date'], $_POST['pho_end']);
        if($endDate === false)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_END'), $gPreferences['system_date']));
            // => EXIT
        }
        else
        {
            $_POST['pho_end'] = $endDate->format('Y-m-d');
        }
    }
    else
    {
        $_POST['pho_end'] = $_POST['pho_begin'];
    }

    // Anfang muss vor oder gleich Ende sein
    if(strlen($_POST['pho_end']) > 0 && $_POST['pho_end'] < $_POST['pho_begin'])
    {
        $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
        // => EXIT
    }

    // Photographen
    if(strlen($_POST['pho_photographers']) === 0)
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

    if ($getMode === 'new')
    {
        // write recordset with new album into database
        $photo_album->save();

        $error = $photo_album->createFolder();

        if(is_array($error))
        {
            $photo_album->delete();

            // der entsprechende Ordner konnte nicht angelegt werden
            $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php');
            $gMessage->show($gL10n->get($error['text'], $error['path'], '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
            // => EXIT
        }

        if($error === null)
        {
            // Benachrichtigungs-Email für neue Einträge
            $notification = new Email();
            try
            {
                $message = $gL10n->get('PHO_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $_POST['pho_name'], $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gPreferences['system_date'], time()));
                $notification->adminNotfication($gL10n->get('PHO_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
            }
            catch(AdmException $e)
            {
                $e->showHtml();
            }
        }

        $getPhotoId = $photo_album->getValue('pho_id');
    }
    else
    {
        // if begin date changed than the folder must also be changed
        if($albumPath !== ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $_POST['pho_begin'] . '_' . $getPhotoId)
        {
            $newFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $_POST['pho_begin'] . '_' . $photo_album->getValue('pho_id');

            // das komplette Album in den neuen Ordner kopieren
            $albumFolder = new Folder($albumPath);
            $b_return = $albumFolder->move($newFolder);

            // Verschieben war nicht erfolgreich, Schreibrechte vorhanden ?
            if(!$b_return)
            {
                $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php');
                $gMessage->show($gL10n->get('SYS_FOLDER_WRITE_ACCESS', $newFolder, '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
                // => EXIT
            }
        }

        // now save changes to database
        $photo_album->save();
    }

    unset($_SESSION['photo_album_request']);
    $gNavigation->deleteLastUrl();

    if ($getMode === 'new')
    {
        admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/photos/photos.php?pho_id=' . $getPhotoId);
        // => EXIT
    }
    else
    {
        admRedirect($gNavigation->getUrl());
        // => EXIT
    }
}

/**************************************************************************/

elseif($getMode === 'delete')
{
    // Album loeschen
    if($photo_album->delete())
    {
        echo 'done';
    }
    exit();
}
