<?php
/******************************************************************************
 * Various functions for download module
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode   :  1 - Upload files
 *           2 - Datei loeschen
 *           3 - Ordner erstellen
 *           4 - Datei / Ordner umbenennen
 *           5 - Ordner loeschen
 *           6 - Datei / Ordner zur DB hinzufuegen
 *           7 - Berechtigungen für Ordner speichern
 * folder_id : Id of the folder in the database
 * file_id   : Id of the file in the database
 * name      : Name of the file/folder that should be added to the database
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('requireValue' => true));
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric');
$getFileId   = admFuncVariableIsValid($_GET, 'file_id', 'numeric');
$getName     = admFuncVariableIsValid($_GET, 'name', 'string');

$_SESSION['download_request'] = $_POST;

// Pfad in adm_my_files pruefen und ggf. anlegen
$myFilesDownload = new MyFiles('DOWNLOAD');
if($myFilesDownload->checkSettings() == false)
{
    $gMessage->show($gL10n->get($myFilesDownload->errorText, $myFilesDownload->errorPath, '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
}

// upload files
if ($getMode == 1)
{
    if ($getFolderId == 0)
    {
        //FolderId ist zum hochladen erforderlich
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    try
    {
        // get recordset of current folder from database and throw exception if necessary
        $targetFolder = new TableFolder($gDb);
        $targetFolder->getFolderForDownload($getFolderId);

        if(strlen($_FILES['userfile']['name'][0]) == 0)
        {
            $gMessage->show($gL10n->get('DOW_UPLOAD_POST_EMPTY', ini_get('upload_max_filesize')));
        }


        $fileSize = 0;
        $fileName = '';
        $countUploadedFiles = 0;

        // now check every file
        for($currentFileNo = 0; isset($_FILES['userfile']['name'][$currentFileNo]) == true; $currentFileNo++)
        {
            //Dateigroesse ueberpruefen Servereinstellungen
            if ($_FILES['userfile']['error'][$currentFileNo] == 1)
            {
                $gMessage->show($gL10n->get('SYS_FILE_TO_LARGE_SERVER', ini_get('upload_max_filesize')));
            }

            //Dateigroesse ueberpruefen Administratoreinstellungen
            if ($_FILES['userfile']['size'][$currentFileNo] > ($gPreferences['max_file_upload_size']) * 1024 * 1024)
            {
                $gMessage->show($gL10n->get('DOW_FILE_TO_LARGE', $gPreferences['max_file_upload_size']));
            }

            // Wenn eine Datei vorliegt diese in den Ordner hochlagen
            if ($_FILES['userfile']['error'][$currentFileNo] == 0)
            {
                // pruefen, ob die Anhanggroesse groesser als die zulaessige Groesse ist
                $fileSize = $fileSize + $_FILES['userfile']['size'][$currentFileNo];

                //Falls der Dateityp nicht bestimmt ist auf Standard setzen
                if (strlen($_FILES['userfile']['type'][$currentFileNo]) <= 0)
                {
                    $_FILES['userfile']['type'][$currentFileNo] = 'application/octet-stream';
                }

                // Dateinamen ermitteln
                $fileName = $_FILES['userfile']['name'][$currentFileNo];

                // check filename and throw exception if something is wrong
                if(admStrIsValidFileName($fileName, true))
                {
                    if (file_exists($targetFolder->getCompletePathOfFolder(). '/'.$fileName))
                    {
                        $gMessage->show($gL10n->get('DOW_FILE_EXIST', $fileName));
                    }

                    // Datei hochladen
                    if(move_uploaded_file($_FILES['userfile']['tmp_name'][$currentFileNo], $targetFolder->getCompletePathOfFolder(). '/'.$fileName))
                    {
                        //Neue Datei noch in der DB eintragen
                        $newFile = new TableFile($gDb);
                        $newFile->setValue('fil_fol_id', $targetFolder->getValue('fol_id'));
                        $newFile->setValue('fil_name', $fileName);
                        $newFile->setValue('fil_locked', $targetFolder->getValue('fol_locked'));
                        $newFile->setValue('fil_counter', '0');
                        $newFile->save();

                        // Benachrichtigungs-Email für neue Einträge
                        $message = $gL10n->get('DOW_EMAIL_NOTIFICATION_MESSAGE', $gCurrentOrganization->getValue('org_longname'), $fileName, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gPreferences['system_date'], time()));
                        $notification = new Email();
                        $notification->adminNotfication($gL10n->get('DOW_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));

                        $countUploadedFiles++;
                    }
                    else
                    {
                        $gMessage->show($gL10n->get('DOW_FILE_UPLOAD_ERROR', $fileName));
                    }
                }
            }
        }

        if($currentFileNo == 1)
        {
            $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
            $gMessage->show($gL10n->get('DOW_FILE_UPLOADED', $fileName));
        }
        else
        {
            $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
            $gMessage->show($gL10n->get('DOW_FILES_UPLOADED', $countUploadedFiles));
        }
    }
    catch(AdmException $e)
    {
        if($e->getMessage() == 'SYS_FILENAME_EMPTY')
        {
            $e->setNewMessage('SYS_FIELD_EMPTY', $gL10n->get('DOW_CHOOSE_FILE'));
        }

        $e->showHtml();
    }
}


//Datei loeschen
elseif ($getMode == 2)
{
    if (!$getFileId)
    {
        //Es muss eine FileID uebergeben werden
        //beides ist auch nicht erlaubt
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if($getFileId > 0)
    {
        try
        {
            // get recordset of current file from databse
            $file = new TableFile($gDb);
            $file->getFileForDownload($getFileId);
        }
        catch(AdmException $e)
        {
            $e->showText();
        }

        if ($file->delete())
        {
            // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
            echo 'done';
        }
    }

    unset($_SESSION['download_request']);
}


// Ordner erstellen
elseif ($getMode == 3)
{
    if ($getFolderId == 0) {
        //FolderId ist zum Anlegen eines Unterordners erforderlich
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    try
    {
        // get recordset of current folder from databse
        $targetFolder = new TableFolder($gDb);
        $targetFolder->getFolderForDownload($getFolderId);

        $newFolderName = null;

        // check filename and throw exception if something is wrong
        if(admStrIsValidFileName($_POST['new_folder']))
        {
            $newFolderName        = $_POST['new_folder'];
            $newFolderDescription = $_POST['new_description'];

            //Test ob der Ordner schon existiert im Filesystem
            if (file_exists($targetFolder->getCompletePathOfFolder(). '/'.$newFolderName))
            {
                $gMessage->show($gL10n->get('DOW_FOLDER_EXISTS', $newFolderName));
            }
            else
            {
                // Ordner erstellen
                $b_return = $targetFolder->createFolder($newFolderName);

                if(strlen($b_return['text']) == 0)
                {
                    //Jetzt noch den Ordner der DB hinzufuegen...
                    $newFolder = new TableFolder($gDb);

                    $newFolder->setValue('fol_fol_id_parent', $targetFolder->getValue('fol_id'));
                    $newFolder->setValue('fol_type', 'DOWNLOAD');
                    $newFolder->setValue('fol_name', $newFolderName);
                    $newFolder->setValue('fol_description', $newFolderDescription);
                    $newFolder->setValue('fol_path', $targetFolder->getValue('fol_path'). '/'.$targetFolder->getValue('fol_name'));
                    $newFolder->setValue('fol_locked', $targetFolder->getValue('fol_locked'));
                    $newFolder->setValue('fol_public', $targetFolder->getValue('fol_public'));
                    $newFolder->save();

                    //Ordnerberechtigungen des ParentOrdners uebernehmen
                    $newFolder->setRolesOnFolder($targetFolder->getRoleArrayOfFolder());
                }
                else
                {
                    // der entsprechende Ordner konnte nicht angelegt werden
                    $gMessage->setForwardUrl($g_root_path.'/adm_program/modules/downloads/downloads.php');
                    $gMessage->show($gL10n->get($b_return['text'], $b_return['path'], '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
                }

                $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                $gMessage->show($gL10n->get('DOW_FOLDER_CREATED', $newFolderName));
            }
        }
    }
    catch(AdmException $e)
    {
        if($e->getMessage() == 'SYS_FILENAME_EMPTY')
        {
            $e->setNewMessage('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME'));
        }
        if($e->getMessage() == 'BAC_FILE_NAME_INVALID')
        {
            $e->setNewMessage('DOW_FOLDER_NAME_INVALID');
        }
        $e->showHtml();
    }
}


//Datei / Ordner umbenennen
elseif ($getMode == 4)
{
    if ((!$getFileId && !$getFolderId) or ($getFileId && $getFolderId))
    {
        //Es muss entweder eine FileID ODER eine FolderId uebergeben werden
        //beides ist auch nicht erlaubt
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    try
    {
        if($getFileId > 0)
        {
            // get recordset of current file from database and throw exception if necessary
            $file = new TableFile($gDb);
            $file->getFileForDownload($getFileId);

            $oldFile = $file->getCompletePathOfFile();
            $newFile = null;

            // check filename and throw exception if something is wrong
            if(admStrIsValidFileName($_POST['new_name'], true))
            {
                $newFile        = $_POST['new_name'].admFuncGetFilenameExtension($oldFile);
                $newDescription = $_POST['new_description'];

                //Test ob die Datei schon existiert im Filesystem
                if ($newFile != $file->getValue('fil_name')
                 && file_exists(SERVER_PATH. $file->getValue('fol_path'). '/'. $file->getValue('fol_name'). '/'.$newFile))
                {
                    $gMessage->show($gL10n->get('DOW_FILE_EXIST', $newFile));
                }
                else
                {
                    $oldName = $file->getValue('fil_name');

                    // Datei umbenennen im Filesystem und in der Datenbank
                    if (rename($oldFile, SERVER_PATH. $file->getValue('fol_path'). '/'. $file->getValue('fol_name'). '/'.$newFile))
                    {
                        $file->setValue('fil_name', $newFile);
                        $file->setValue('fil_description', $newDescription);
                        $file->save();

                        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                        $gMessage->show($gL10n->get('DOW_FILE_RENAME', $oldName));
                    }
                    else {
                        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                        $gMessage->show($gL10n->get('DOW_FILE_RENAME_ERROR', $oldName));
                    }
                }
            }
        }
        elseif($getFolderId > 0)
        {
            // get recordset of current folder from database and throw exception if necessary
            $folder = new TableFolder($gDb);
            $folder->getFolderForDownload($getFolderId);

            $oldFolder = $folder->getCompletePathOfFolder();
            $newFolder = null;

            // check foldername and throw exception if something is wrong
            if(admStrIsValidFileName($_POST['new_name']))
            {
                $newFolder      = $_POST['new_name'];
                $newDescription = $_POST['new_description'];

                //Test ob der Ordner schon existiert im Filesystem
                if ($newFolder != $folder->getValue('fol_name')
                && file_exists(SERVER_PATH. $folder->getValue('fol_path'). '/'.$newFolder))
                {
                    $gMessage->show($gL10n->get('DOW_FOLDER_EXISTS', $newFolder));
                }
                else
                {
                    $oldName = $folder->getValue('fol_name');

                    // Ordner umbenennen im Filesystem und in der Datenbank
                    if (rename($oldFolder, SERVER_PATH. $folder->getValue('fol_path'). '/'.$newFolder))
                    {
                        $folder->setValue('fol_description', $newDescription);
                        $folder->rename($newFolder, $folder->getValue('fol_path'));

                        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                        $gMessage->show($gL10n->get('DOW_FOLDER_RENAME', $oldName));
                    }
                    else {
                        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                        $gMessage->show($gL10n->get('DOW_FOLDER_RENAME_ERROR', $oldName));
                    }
                }
            }
        }
    }
    // exception handling; replace some exception strings with better descriptions
    catch(AdmException $e)
    {
        if($e->getMessage() == 'SYS_FILENAME_EMPTY')
        {
            $e->setNewMessage('SYS_FIELD_EMPTY', $gL10n->get('DOW_NEW_NAME'));
        }
        if($e->getMessage() == 'BAC_FILE_NAME_INVALID' && $getFolderId > 0)
        {
            $e->setNewMessage('DOW_FOLDER_NAME_INVALID');
        }
        $e->showHtml();
    }
}


//Folder loeschen
elseif ($getMode == 5)
{
    if (!$getFolderId)
    {
        //Es muss eine FolderId uebergeben werden
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    elseif ($getFolderId > 0)
    {
        try
        {
            // get recordset of current folder from databse
            $folder = new TableFolder($gDb);
            $folder->getFolderForDownload($getFolderId);
        }
        catch(AdmException $e)
        {
            $e->showText();
        }

        if ($folder->delete())
        {
            // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
            echo 'done';
        }
    }

    unset($_SESSION['download_request']);
}


//Datei / Ordner zur DB hinzufeuegen
elseif ($getMode == 6)
{
    if ($getFolderId == 0)
    {
        //FolderId ist zum hinzufuegen erforderlich
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    try
    {
        // check filename and throw exception if something is wrong
        if(admStrIsValidFileName(urldecode($getName), true))
        {
            $getName = urldecode($getName);

            // get recordset of current folder from databse
            $targetFolder = new TableFolder($gDb);
            $targetFolder->getFolderForDownload($getFolderId);
        }
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }

    //Pruefen ob das neue Element eine Datei order ein Ordner ist.
    if (is_file($targetFolder->getCompletePathOfFolder(). '/'. $getName)) {
        //Datei hinzufuegen
        $newFile = new TableFile($gDb);
        $newFile->setValue('fil_fol_id', $targetFolder->getValue('fol_id'));
        $newFile->setValue('fil_name', $getName);
        $newFile->setValue('fil_locked', $targetFolder->getValue('fol_locked'));
        $newFile->setValue('fil_counter', '0');
        $newFile->save();

        //Zurueck zur letzten Seite
        $gNavigation->addUrl(CURRENT_URL);
        $location = 'Location: '.$g_root_path.'/adm_program/system/back.php';
        header($location);
        exit();
    }
    elseif (is_dir($targetFolder->getCompletePathOfFolder(). '/'. $getName)) {

        //Ordner der DB hinzufuegen
        $newFolder = new TableFolder($gDb);
        $newFolder->setValue('fol_fol_id_parent', $targetFolder->getValue('fol_id'));
        $newFolder->setValue('fol_type', 'DOWNLOAD');
        $newFolder->setValue('fol_name', $getName);
        $newFolder->setValue('fol_path', $targetFolder->getValue('fol_path'). '/'.$targetFolder->getValue('fol_name'));
        $newFolder->setValue('fol_locked', $targetFolder->getValue('fol_locked'));
        $newFolder->setValue('fol_public', $targetFolder->getValue('fol_public'));
        $newFolder->save();

        //Ordnerberechtigungen des ParentOrdners uebernehmen
        $newFolder->setRolesOnFolder($targetFolder->getRoleArrayOfFolder());

        //Zurueck zur letzten Seite
        $gNavigation->addUrl(CURRENT_URL);
        $location = 'Location: '.$g_root_path.'/adm_program/system/back.php';
        header($location);
        exit();
   }

}

//Berechtigungen fuer einen Ordner speichern
elseif ($getMode == 7)
{
    if ($getFolderId == 0) {
        //FolderId ist zum hinzufuegen erforderlich
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    try
    {
        // get recordset of current folder from databse
        $targetFolder = new TableFolder($gDb);
        $targetFolder->getFolderForDownload($getFolderId);

        if ($targetFolder->getValue('fol_fol_id_parent'))
        {
            // get recordset of parent folder from databse
            $parentFolder = new TableFolder($gDb);
            $parentFolder->getFolderForDownload($targetFolder->getValue('fol_fol_id_parent'));
        }

        //Formularinhalt aufbereiten
        if($targetFolder->getValue('fol_fol_id_parent') && $parentFolder->getValue('fol_public') == 0)
        {
            $publicFlag = $targetFolder->getValue('fol_public');
        }
        else
        {
            if(isset($_POST['fol_public']) == false || $_POST['fol_public'] == 0)
            {
                $publicFlag = 1;
            }
            else
            {
                $publicFlag = 0;
            }
        }

        //setze schon einmal das Public_Flag
        $targetFolder->editPublicFlagOnFolder($publicFlag);

        $rolesArray = null;
        //Nur wenn der Ordner oeffentlich nicht zugaenglich ist
        //werden die Rollenbrechtigungen gespeichert.
        //Ansonsten wird ein leeres Rollenset gespeichert...
        if ($publicFlag == 0)
        {
            //Rollenberechtigungen aufbereiten
            if(array_key_exists('AllowedRoles', $_POST))
            {
                $sentAllowedRoles = $_POST['AllowedRoles'];

                //fuege alle neuen Rollen hinzu
                foreach ($sentAllowedRoles as $newRole)
                {
                    $rolesArray[] = array('rol_id'        => $newRole,
                                          'rol_name'      => '');
                }
            }
        }

        //jetzt noch die Rollenberechtigungen in die DB schreiben
        $targetFolder->setRolesOnFolder($rolesArray);

        $targetFolder->save();

        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
        $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }
}


?>
