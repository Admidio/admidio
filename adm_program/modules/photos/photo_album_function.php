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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'int');
$getMode    = admFuncVariableIsValid($_GET, 'mode',   'string', array('requireValue' => true, 'validValues' => array('new', 'change', 'delete')));

// check if the module is enabled and disallow access if it's disabled
if ($gPreferences['enable_photo_module'] == 0)
{
    // check if the module is activated
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// check if current user has right to upload photos
if (!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

// Gepostete Variablen in Session speichern
$_SESSION['photo_album_request'] = $_POST;

// Fotoalbumobjekt anlegen
$photoAlbum = new TablePhotos($gDb);

if ($getMode !== 'new' && $getPhotoId > 0)
{
    $photoAlbum->readDataById($getPhotoId);

    // check whether album belongs to the current organization
    if ((int) $photoAlbum->getValue('pho_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

$phoId = (int) $photoAlbum->getValue('pho_id');

// Speicherort mit dem Pfad aus der Datenbank
$albumPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $phoId;

$phoBegin = $_POST['pho_begin'];
$phoEnd   = $_POST['pho_end'];

/********************Aenderungen oder Neueintraege kontrollieren***********************************/
if ($getMode === 'new' || $getMode === 'change')
{
    // Gesendete Variablen Uebernehmen und kontollieren

    // Freigabe(muss zuerst gemacht werden da diese nicht gesetzt sein koennte)
    if (!isset($_POST['pho_locked']))
    {
        $_POST['pho_locked'] = 0;
    }
    // Album
    if (strlen($_POST['pho_name']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('PHO_ALBUM')));
        // => EXIT
    }

    // Beginn
    if (strlen($phoBegin) > 0)
    {
        $startDate = DateTime::createFromFormat($gPreferences['system_date'], $phoBegin);
        if ($startDate === false)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_START'), $gPreferences['system_date']));
            // => EXIT
        }
        else
        {
            $phoBegin = $startDate->format('Y-m-d');
        }
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_START')));
        // => EXIT
    }

    // Ende
    if (strlen($phoEnd) > 0)
    {
        $endDate = DateTime::createFromFormat($gPreferences['system_date'], $phoEnd);
        if ($endDate === false)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_END'), $gPreferences['system_date']));
            // => EXIT
        }
        else
        {
            $phoEnd = $endDate->format('Y-m-d');
        }
    }
    else
    {
        $phoEnd = $phoBegin;
    }

    // Anfang muss vor oder gleich Ende sein
    if (strlen($phoEnd) > 0 && $phoEnd < $phoBegin)
    {
        $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
        // => EXIT
    }

    // Photographen
    if (strlen($_POST['pho_photographers']) === 0)
    {
        $_POST['pho_photographers'] = $gL10n->get('SYS_UNKNOWN');
    }

    // POST Variablen in das Role-Objekt schreiben
    foreach ($_POST as $key => $value)
    {
        if (strpos($key, 'pho_') === 0)
        {
            $photoAlbum->setValue($key, $value);
        }
    }

    if ($getMode === 'new')
    {
        // write recordset with new album into database
        $photoAlbum->save();

        $error = $photoAlbum->createFolder();

        if (is_array($error))
        {
            $photoAlbum->delete();

            // der entsprechende Ordner konnte nicht angelegt werden
            $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php');
            $gMessage->show($gL10n->get($error['text'], $error['path'], '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
            // => EXIT
        }

        if ($error === null)
        {
            // Benachrichtigungs-Email für neue Einträge
            $notification = new Email();
            try
            {
                $message = $gL10n->get('PHO_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $_POST['pho_name'], $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gPreferences['system_date'], time()));
                $notification->adminNotification($gL10n->get('PHO_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
            }
            catch (AdmException $e)
            {
                $e->showHtml();
            }
        }

        $getPhotoId = $phoId;
    }
    else
    {
        // if begin date changed than the folder must also be changed
        if ($albumPath !== ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $phoBegin . '_' . $getPhotoId)
        {
            $newFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $phoBegin . '_' . $phoId;

            // das komplette Album in den neuen Ordner kopieren
            $albumFolder = new Folder($albumPath);
            $returnValue = $albumFolder->move($newFolder);

            // Verschieben war nicht erfolgreich, Schreibrechte vorhanden ?
            if (!$returnValue)
            {
                $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php');
                $gMessage->show($gL10n->get('SYS_FOLDER_WRITE_ACCESS', $newFolder, '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
                // => EXIT
            }
        }

        // now save changes to database
        $photoAlbum->save();
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

elseif ($getMode === 'delete')
{
    // Album loeschen
    if ($photoAlbum->delete())
    {
        echo 'done';
    }
    exit();
}
