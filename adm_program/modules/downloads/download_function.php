<?php
/**
 ***********************************************************************************************
 * Various functions for download module
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Parameters:
 *
 * mode   :  2 - Delete file
 *           3 - Create folder
 *           4 - Rename file/folder
 *           5 - Delete folder
 *           6 - Add file/folder to database
 *           7 - Save access to folder
 * folder_id : Id of the folder in the database
 * file_id   : Id of the file in the database
 * name      : Name of the file/folder that should be added to the database
 ***********************************************************************************************/
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('enable_download_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize and check the parameters
$getMode     = admFuncVariableIsValid($_GET, 'mode',      'int', array('requireValue' => true));
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'int');
$getFileId   = admFuncVariableIsValid($_GET, 'file_id',   'int');
$getName     = admFuncVariableIsValid($_GET, 'name',      'file');

$_SESSION['download_request'] = $_POST;

// Pfad in adm_my_files pruefen und ggf. anlegen
try
{
    FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/' . TableFolder::getRootFolderName());
}
catch (\RuntimeException $exception)
{
    $gMessage->show($exception->getMessage());
    // => EXIT
}

// check the rights of the current folder
// user must be administrator or must have the right to upload files
$folder = new TableFolder($gDb, $getFolderId);

if (!$folder->hasUploadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Delete file
if ($getMode === 2)
{
    if($getFileId > 0)
    {
        try
        {
            // get recordset of current file from database
            $file = new TableFile($gDb);
            $file->getFileForDownload($getFileId);
        }
        catch(AdmException $e)
        {
            $e->showText();
            // => EXIT
        }

        if ($file->delete())
        {
            // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
            echo 'done';
        }
    }
    else
    {
        // if no file id was set then show error
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    unset($_SESSION['download_request']);
}

// create folder
elseif ($getMode === 3)
{
    if ($getFolderId === 0)
    {
        // FolderId ist zum Anlegen eines Unterordners erforderlich
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    try
    {
        $newFolderName = admFuncVariableIsValid($_POST, 'new_folder', 'file', array('requireValue' => true));
        $newFolderDescription = admFuncVariableIsValid($_POST, 'new_description', 'string');

        // get recordset of current folder from database
        $folder->getFolderForDownload($getFolderId);

        // Test ob der Ordner schon existiert im Filesystem
        if (is_dir($folder->getFullFolderPath() . '/' . $newFolderName))
        {
            $gMessage->show($gL10n->get('DOW_FOLDER_EXISTS', array($newFolderName)));
            // => EXIT
        }
        else
        {
            // Ordner erstellen
            $error = $folder->createFolder($newFolderName);

            if($error === null)
            {
                $folId = (int) $folder->getValue('fol_id');

                // Jetzt noch den Ordner der DB hinzufuegen...
                $newFolder = new TableFolder($gDb);

                $newFolder->setValue('fol_fol_id_parent', $folId);
                $newFolder->setValue('fol_type', 'DOWNLOAD');
                $newFolder->setValue('fol_name', $newFolderName);
                $newFolder->setValue('fol_description', $newFolderDescription);
                $newFolder->setValue('fol_path', $folder->getFolderPath());
                $newFolder->setValue('fol_locked', $folder->getValue('fol_locked'));
                $newFolder->setValue('fol_public', $folder->getValue('fol_public'));
                $newFolder->save();

                // get roles rights of parent folder
                $rightParentFolderView = new RolesRights($gDb, 'folder_view', $folId);
                $newFolder->addRolesOnFolder('folder_view', $rightParentFolderView->getRolesIds());
                $rightParentFolderUpload = new RolesRights($gDb, 'folder_upload', $folId);
                $newFolder->addRolesOnFolder('folder_upload', $rightParentFolderUpload->getRolesIds());
            }
            else
            {
                // der entsprechende Ordner konnte nicht angelegt werden
                $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/downloads.php');
                $gMessage->show($gL10n->get($error['text'], array($error['path'], '<a href="mailto:'.$gSettingsManager->getString('email_administrator').'">', '</a>')));
                // => EXIT
            }

            $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/system/back.php');
            $gMessage->show($gL10n->get('DOW_FOLDER_CREATED', array($newFolderName)));
            // => EXIT
        }
    }
    catch(AdmException $e)
    {
        if($e->getMessage() === 'SYS_FILENAME_EMPTY')
        {
            $e->setNewMessage('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME'));
        }
        if($e->getMessage() === 'SYS_FILENAME_INVALID')
        {
            $e->setNewMessage('DOW_FOLDER_NAME_INVALID');
        }
        $e->showHtml();
        // => EXIT
    }
}

// Datei / Ordner umbenennen
elseif ($getMode === 4)
{
    if (!$getFileId && !$getFolderId)
    {
        // fileid and/or folderid must be set
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    try
    {
        $newName = admFuncVariableIsValid($_POST, 'new_name', 'file', array('requireValue' => true));
        $newDescription = admFuncVariableIsValid($_POST, 'new_description', 'string');

        if($getFileId > 0)
        {
            // get recordset of current file from database and throw exception if necessary
            $file = new TableFile($gDb);
            $file->getFileForDownload($getFileId);

            $oldFile = $file->getFullFilePath();
            $newPath = $file->getFullFolderPath() . '/';
            $newFile = $newName . '.' . pathinfo($oldFile, PATHINFO_EXTENSION);

            // check if file already exists in filesystem
            if ($newFile !== $file->getValue('fil_name') && is_file($newPath . $newFile))
            {
                $gMessage->show($gL10n->get('DOW_FILE_EXIST', array($newFile)));
                // => EXIT
            }
            else
            {
                $oldName = $file->getValue('fil_name');

                if($newFile !== $file->getValue('fil_name'))
                {    
                    // rename file in filesystem and database
                    try
                    {
                        FileSystemUtils::moveFile($oldFile, $newPath . $newFile);
                    }
                    catch (\RuntimeException $exception)
                    {
                        $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/system/back.php');
                        $gMessage->show($gL10n->get('DOW_FILE_RENAME_ERROR', array($oldName)));
                        // => EXIT
                    }
                }

                $file->setValue('fil_name', $newFile);
                $file->setValue('fil_description', $newDescription);
                $file->save();

                $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/system/back.php');
                $gMessage->show($gL10n->get('DOW_FILE_RENAME', array($oldName)));
                // => EXIT
            }
        }
        elseif($getFolderId > 0)
        {
            // get recordset of current folder from database and throw exception if necessary
            $folder->getFolderForDownload($getFolderId);

            $oldFolder = $folder->getFullFolderPath();
            $newFolder = $newName;

            // check if folder already exists in filesystem
            if ($newFolder !== $folder->getValue('fol_name')
            && is_dir(ADMIDIO_PATH. $folder->getValue('fol_path'). '/'.$newFolder))
            {
                $gMessage->show($gL10n->get('DOW_FOLDER_EXISTS', array($newFolder)));
                // => EXIT
            }
            else
            {
                $oldName = $folder->getValue('fol_name');

                if($newFolder !== $folder->getValue('fol_name'))
                {    
                    // rename folder in filesystem and database
                    try
                    {
                        FileSystemUtils::moveDirectory($oldFolder, ADMIDIO_PATH. $folder->getValue('fol_path'). '/'.$newFolder);

                        $folder->rename($newFolder, $folder->getValue('fol_path'));
                    }
                    catch (\RuntimeException $exception)
                    {
                        $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/system/back.php');
                        $gMessage->show($gL10n->get('DOW_FOLDER_RENAME_ERROR', array($oldName)));
                        // => EXIT
                    }
                }

                $folder->setValue('fol_description', $newDescription);
                $folder->save();

                $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/system/back.php');
                $gMessage->show($gL10n->get('DOW_FOLDER_RENAME', array($oldName)));
                // => EXIT
            }
        }
    }
    // exception handling; replace some exception strings with better descriptions
    catch(AdmException $e)
    {
        if($e->getMessage() === 'SYS_FILENAME_EMPTY')
        {
            $e->setNewMessage('SYS_FIELD_EMPTY', $gL10n->get('DOW_NEW_NAME'));
        }
        if($e->getMessage() === 'SYS_FILENAME_INVALID' && $getFolderId > 0)
        {
            $e->setNewMessage('DOW_FOLDER_NAME_INVALID');
        }
        $e->showHtml();
        // => EXIT
    }
}

// Folder loeschen
elseif ($getMode === 5)
{
    if ($getFolderId === 0)
    {
        // Es muss eine FolderId uebergeben werden
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }
    elseif ($getFolderId > 0)
    {
        try
        {
            // get recordset of current folder from database
            $folder->getFolderForDownload($getFolderId);
        }
        catch(AdmException $e)
        {
            $e->showText();
            // => EXIT
        }

        if ($folder->delete())
        {
            // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
            echo 'done';
        }
    }

    unset($_SESSION['download_request']);
}

// Datei / Ordner zur DB hinzufeuegen
elseif ($getMode === 6)
{
    if ($getFolderId === 0)
    {
        // FolderId ist zum hinzufuegen erforderlich
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // only users with download administration rights should set new roles rights
    if(!$gCurrentUser->editDownloadRight())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    try
    {
        $getName = urldecode($getName);

        // get recordset of current folder from database
        $folder->getFolderForDownload($getFolderId);
    }
    catch(AdmException $e)
    {
        $e->showHtml();
        // => EXIT
    }

    $newObjectPath = $folder->getFullFolderPath() . '/' . $getName;

    $folId = (int) $folder->getValue('fol_id');

    // Pruefen ob das neue Element eine Datei order ein Ordner ist.
    if (is_file($newObjectPath))
    {
        // Datei hinzufuegen
        $newFile = new TableFile($gDb);
        $newFile->setValue('fil_fol_id', $folId);
        $newFile->setValue('fil_name', $getName);
        $newFile->setValue('fil_locked', $folder->getValue('fol_locked'));
        $newFile->setValue('fil_counter', 0);
        $newFile->save();

        // Zurueck zur letzten Seite
        $gNavigation->addUrl(CURRENT_URL);

        admRedirect(ADMIDIO_URL . '/adm_program/system/back.php');
        // => EXIT
    }
    elseif (is_dir($newObjectPath))
    {

        // Ordner der DB hinzufuegen
        $newFolder = new TableFolder($gDb);
        $newFolder->setValue('fol_fol_id_parent', $folId);
        $newFolder->setValue('fol_type', 'DOWNLOAD');
        $newFolder->setValue('fol_name', $getName);
        $newFolder->setValue('fol_path', $folder->getFolderPath());
        $newFolder->setValue('fol_locked', $folder->getValue('fol_locked'));
        $newFolder->setValue('fol_public', $folder->getValue('fol_public'));
        $newFolder->save();

        // get roles rights of parent folder
        $rightParentFolderView = new RolesRights($gDb, 'folder_view', $folId);
        $newFolder->addRolesOnFolder('folder_view', $rightParentFolderView->getRolesIds());
        $rightParentFolderUpload = new RolesRights($gDb, 'folder_upload', $folId);
        $newFolder->addRolesOnFolder('folder_upload', $rightParentFolderUpload->getRolesIds());

        // Zurueck zur letzten Seite
        $gNavigation->addUrl(CURRENT_URL);

        admRedirect(ADMIDIO_URL . '/adm_program/system/back.php');
        // => EXIT
    }
}

// save view or upload rights for a folder
elseif ($getMode === 7)
{
    if(!isset($_POST['adm_roles_view_right']))
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_VISIBLE_FOR'))));
        // => EXIT
    }
    if(!isset($_POST['adm_roles_upload_right']))
    {
        // upload right need not to be set because download module administrators still
        // have the right, so initialize the parameter
        $_POST['adm_roles_upload_right'] = array();
    }

    if ($getFolderId === 0 || !is_array($_POST['adm_roles_view_right']) || !is_array($_POST['adm_roles_upload_right']))
    {
        // FolderId ist zum hinzufuegen erforderlich
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // only users with download administration rights should set new roles rights
    if(!$gCurrentUser->editDownloadRight())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    try
    {
        $postIntRolesViewRight   = array_map('intval', $_POST['adm_roles_view_right']);
        $postIntRolesUploadRight = array_map('intval', $_POST['adm_roles_upload_right']);

        // get recordset of current folder from database
        $folder->getFolderForDownload($getFolderId);

        if ($folder->getValue('fol_fol_id_parent'))
        {
            // get recordset of parent folder from database
            $parentFolder = new TableFolder($gDb);
            $parentFolder->getFolderForDownload((int) $folder->getValue('fol_fol_id_parent'));
        }

        // Read current view roles rights of the folder
        $rightFolderView = new RolesRights($gDb, 'folder_view', $getFolderId);
        $rolesFolderView = $rightFolderView->getRolesIds();

        // Read current upload roles rights of the folder
        $rightFolderUpload = new RolesRights($gDb, 'folder_upload', $getFolderId);
        $rolesFolderUpload = $rightFolderUpload->getRolesIds();

        // get new roles and removed roles
        $addUploadRoles = array_diff($postIntRolesUploadRight, $rolesFolderUpload);
        $removeUploadRoles = array_diff($rolesFolderUpload, $postIntRolesUploadRight);

        if(in_array(0, $postIntRolesViewRight, true))
        {
            // set flag public for this folder and all child folders
            $folder->editPublicFlagOnFolder(true);
            // if all users have access then delete all existing roles
            $folder->removeRolesOnFolder('folder_view', $rolesFolderView);
        }
        else
        {
            // set flag public for this folder and all child folders
            $folder->editPublicFlagOnFolder(false);

            // get new roles and removed roles
            $addViewRoles = array_unique(array_merge(array_diff($postIntRolesViewRight, $rolesFolderView), $postIntRolesUploadRight, $rolesFolderUpload));
            $removeViewRoles = array_diff($rolesFolderView, $postIntRolesViewRight);

            $folder->addRolesOnFolder('folder_view', $addViewRoles);
            $folder->removeRolesOnFolder('folder_view', $removeViewRoles);

            // upload right should not contain removed view roles
            $removeUploadRoles = array_merge($removeUploadRoles, $removeViewRoles);
        }

        $folder->addRolesOnFolder('folder_upload', $addUploadRoles);
        $folder->removeRolesOnFolder('folder_upload', $removeUploadRoles);

        $folder->save();

        $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/system/back.php');
        $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
        // => EXIT
    }
    catch(AdmException $e)
    {
        $e->showHtml();
        // => EXIT
    }
}
