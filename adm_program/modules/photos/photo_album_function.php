<?php
/**
 ***********************************************************************************************
 * Various functions for photo albums
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * pho_id        : Id of photo album that should be edited
 * mode - new    : create a new photo album
 *      - change : edit a photo album
 *      - delete : delete a photo album
 *      - lock   : lock a photo album
 *      - unlock : unlock a photo album
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'int');
$getMode    = admFuncVariableIsValid($_GET, 'mode',   'string', array('requireValue' => true, 'validValues' => array('new', 'change', 'delete', 'lock', 'unlock')));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0)
{
    // check if the module is activated
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Gepostete Variablen in Session speichern
$_SESSION['photo_album_request'] = $_POST;

// create photo album object
$photoAlbum = new TablePhotos($gDb);

if ($getMode !== 'new' && $getPhotoId > 0)
{
    $photoAlbum->readDataById($getPhotoId);
}

// check if the user is allowed to edit this photo album
if (!$photoAlbum->isEditable())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

$phoId = (int) $photoAlbum->getValue('pho_id');

// Speicherort mit dem Pfad aus der Datenbank
$albumPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $phoId;

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
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('PHO_ALBUM'))));
        // => EXIT
    }

    // Beginn
    if (strlen($_POST['pho_begin']) > 0)
    {
        $startDate = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $_POST['pho_begin']);
        if ($startDate === false)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_START'), $gSettingsManager->getString('system_date'))));
            // => EXIT
        }
        else
        {
            $_POST['pho_begin'] = $startDate->format('Y-m-d');
        }
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_START'))));
        // => EXIT
    }

    // Ende
    if (strlen($_POST['pho_end']) > 0)
    {
        $endDate = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $_POST['pho_end']);
        if ($endDate === false)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_END'), $gSettingsManager->getString('system_date'))));
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
    if (strlen($_POST['pho_end']) > 0 && $_POST['pho_end'] < $_POST['pho_begin'])
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
    foreach ($_POST as $key => $value) // TODO possible security issue
    {
        if (StringUtils::strStartsWith($key, 'pho_'))
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
            $gMessage->show($gL10n->get($error['text'], array($error['path'], '<a href="mailto:'.$gSettingsManager->getString('email_administrator').'">', '</a>')));
            // => EXIT
        }

        if ($error === null)
        {
            // Benachrichtigungs-Email für neue Einträge
            $notification = new Email();
            try
            {
                $message = $gL10n->get('PHO_EMAIL_NOTIFICATION_MESSAGE', array($gCurrentOrganization->getValue('org_longname'), $_POST['pho_name'], $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gSettingsManager->getString('system_date'))));
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
        if ($albumPath !== ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $_POST['pho_begin'] . '_' . $getPhotoId)
        {
            $newFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $_POST['pho_begin'] . '_' . $phoId;

            // das komplette Album in den neuen Ordner verschieben
            try
            {
                FileSystemUtils::moveDirectory($albumPath, $newFolder);
            }
            catch (\RuntimeException $exception)
            {
                $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php');
                $gMessage->show($gL10n->get('SYS_FOLDER_WRITE_ACCESS', array($newFolder, '<a href="mailto:'.$gSettingsManager->getString('email_administrator').'">', '</a>')));
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
        admRedirect(safeUrl(ADMIDIO_URL . FOLDER_MODULES.'/photos/photos.php', array('pho_id' => $getPhotoId)));
        // => EXIT
    }
    else
    {
        admRedirect($gNavigation->getUrl());
        // => EXIT
    }
}

/**************************************************************************/

// delete photo album
elseif ($getMode === 'delete')
{
    if ($photoAlbum->delete())
    {
        echo 'done';
    }
    exit();
}

// lock photo album
elseif ($getMode === 'lock')
{
    $photoAlbum->setValue('pho_locked', 1);
    $photoAlbum->save();

    admRedirect($gNavigation->getUrl());
    // => EXIT
}

// unlock photo album
elseif ($getMode === 'unlock')
{
    $photoAlbum->setValue('pho_locked', 0);
    $photoAlbum->save();

    admRedirect($gNavigation->getUrl());
    // => EXIT
}
