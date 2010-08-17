<?php
/******************************************************************************
 * Downloadfunktionen
 *
 * Copyright    : (c) 2004 - 2010 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode   :  1 - Datei hochladen
 *           2 - Datei loeschen
 *           3 - Ordner erstellen
 *           4 - Datei / Ordner umbenennen
 *           5 - Ordner loeschen
 *           6 - Datei / Ordner zur DB hinzufuegen
 *           7 - Berechtigungen ffür Ordner speichern
 * folder_id :  OrdnerId in der DB
 * file_id   :  FileId in der DB
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/my_files.php');
require_once('../../system/classes/table_folder.php');
require_once('../../system/classes/table_file.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editDownloadRight())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// Uebergabevariablen pruefen
if (isset($_GET['mode']))
{
    if (is_numeric($_GET['mode']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $req_mode = $_GET['mode'];

}
else
{
    //ohne mode geht es nicht weiter
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if (isset($_GET['folder_id']))
{
    if (is_numeric($_GET['mode']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $folder_id = $_GET['folder_id'];
}
else
{
    $folder_id = 0;
}

if (isset($_GET['file_id']))
{
    if (is_numeric($_GET['mode']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $file_id = $_GET['file_id'];
}
else
{
    $file_id = 0;
}

$_SESSION['download_request'] = $_REQUEST;

// Pfad in adm_my_files pruefen und ggf. anlegen
$myFilesDownload = new MyFiles('DOWNLOAD');
if($myFilesDownload->checkSettings() == false)
{
    $g_message->show($g_l10n->get($myFilesDownload->errorText, $myFilesDownload->errorPath, '<a href="mailto:'.$g_preferences['email_administrator'].'">', '</a>'));
}

// Dateien hochladen
if ($req_mode == 1)
{
    if ($folder_id == 0) 
    {
        //FolderId ist zum hochladen erforderlich
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //Informationen zum Zielordner aus der DB holen
    $targetFolder = new TableFolder($g_db);
    $targetFolder->getFolderForDownload($folder_id);

    //pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
    if (!$targetFolder->getValue('fol_id'))
    {
        //Datensatz konnte nicht in DB gefunden werden...
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if (empty($_POST))
    {
        $g_message->show($g_l10n->get('DOW_PHR_UPLOAD_POST_EMPTY',ini_get('upload_max_filesize')));
    }

    $local_file = $_FILES['userfile']['name'];

    //Dateigroesse ueberpruefen Servereinstellungen
    if ($_FILES['userfile']['error']==1)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FILE_TO_LARGE_SERVER',ini_get('upload_max_filesize')));
    }

    //Dateigroesse ueberpruefen Administratoreinstellungen
    if ($_FILES['userfile']['size']>($g_preferences['max_file_upload_size'])*1024)
    {
        $g_message->show($g_l10n->get('DOW_PHR_FILE_TO_LARGE', $g_preferences['max_file_upload_size']));
    }

    // Dateinamen ermitteln
    $file_name = $local_file;

    // wenn neuer Name uebergeben wurde, dann diesen nehmen
    if(strlen($_POST['new_name']) > 0)
    {
        $file_name = $_POST['new_name'];
    }

    // pruefen, ob der Dateiname gueltig ist
    $ret_code = isValidFileName($file_name, true);

    if($ret_code != 0)
    {
        if($ret_code == -1)
        {
            $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('DOW_CHOOSE_FILE')));
        }
        elseif($ret_code == -2)
        {
            $g_message->show($g_l10n->get('DOW_PHR_FILE_NAME_INVALID'));
        }
        elseif($ret_code == -3)
        {
            $g_message->show($g_l10n->get('DOW_PHR_FILE_EXTENSION_INVALID'));
        }
    }

    if (file_exists($targetFolder->getCompletePathOfFolder(). '/'.$file_name))
    {
        $g_message->show($g_l10n->get('DOW_PHR_FILE_EXIST', $newFile));
    }

    $file_description = $_POST['new_description'];

    // Datei hochladen
    if(move_uploaded_file($_FILES['userfile']['tmp_name'], $targetFolder->getCompletePathOfFolder(). '/'.$file_name))
    {
        //Neue Datei noch in der DB eintragen
        $newFile = new TableFile($g_db);
        $newFile->setValue('fil_fol_id',$targetFolder->getValue('fol_id'));
        $newFile->setValue('fil_name',$file_name);
        $newFile->setValue('fil_description',$file_description);
        $newFile->setValue('fil_locked',$targetFolder->getValue('fol_locked'));
        $newFile->setValue('fil_counter','0');
        $newFile->save();
		
		// Benachrichtigungs-Email für neue Einträge
		if($g_preferences['enable_email_notification'] == 1)
		{
			$sender_name = $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME');
			if(!isValidEmailAddress($g_current_user->getValue('EMAIL')))
			{
				$sender_email = $g_preferences['email_administrator'];
				$sender_name = 'Administrator '.$g_current_organization->getValue('org_homepage');
			}
			EmailNotification($g_preferences['email_administrator'], $g_current_organization->getValue('org_shortname'). ": ".$g_l10n->get('DOW_EMAIL_NOTIFICATION_TITLE'), str_replace("<br />","\n",$g_l10n->get('DOW_EMAIL_NOTIFICATION_MESSAGE', $g_current_organization->getValue('org_longname'), $file_name. ' ('.$file_description.')', $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME'), date("d.m.Y H:m", time()))), $sender_name, $sender_email);
		}

        $g_message->setForwardUrl($g_root_path.'/adm_program/system/back.php');
        $g_message->show($g_l10n->get('DOW_PHR_FILE_UPLOADED', $file_name));
    }
    else
    {
        $g_message->show($g_l10n->get('DOW_PHR_FILE_UPLOAD_ERROR',$file_name));
    }
}


//Datei loeschen
elseif ($req_mode == 2)
{
    if (!$file_id)
    {
        //Es muss eine FileID uebergeben werden
        //beides ist auch nicht erlaubt
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if($file_id > 0)
    {
        $file = new TableFile($g_db);
        $file->getFileForDownload($file_id);

        //Pruefen ob Datensatz gefunden
        if ($file->getValue('fil_id'))
        {
            if ($file->delete())
            {
                // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
                echo 'done';
            }
        }
    }

    unset($_SESSION['download_request']);
}


// Ordner erstellen
elseif ($req_mode == 3)
{

    if ($folder_id == 0) {
        //FolderId ist zum Anlegen eines Unterordners erforderlich
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //Informationen zum Zielordner aus der DB holen
    $targetFolder = new TableFolder($g_db);
    $targetFolder->getFolderForDownload($folder_id);

    //pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
    if (!$targetFolder->getValue('fol_id'))
    {
        //Datensatz konnte nicht in DB gefunden werden...
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $newFolderName = null;

    if (strlen($_POST['new_folder']) > 0)
    {
        $ret_code = isValidFileName($_POST['new_folder']);

        if ($ret_code == 0)
        {
            $newFolderName = $_POST['new_folder'];
        }
        else
        {
            if ($ret_code == -1)
            {
                $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_FILE')));
            }
            elseif ($ret_code == -2)
            {
                $g_message->show($g_l10n->get('DOW_PHR_FOLDER_NAME_INVALID'));
            }
        }
    }
    else
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_FILE')));
    }

    $newFolderDescription = $_POST['new_description'];


    //Test ob der Ordner schon existiert im Filesystem
    if (file_exists($targetFolder->getCompletePathOfFolder(). '/'.$newFolderName)) 
    {
        $g_message->show($g_l10n->get('DOW_PHR_FOLDER_EXISTS', $newFolder));
    }
    else
    {
        // Ordner erstellen
        $b_return = $targetFolder->createFolder($newFolderName);

        if(strlen($b_return['text']) == 0)
        {
            //Jetzt noch den Ordner der DB hinzufuegen...
            $newFolder = new TableFolder($g_db);

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
            $g_message->setForwardUrl($g_root_path.'/adm_program/modules/downloads/downloads.php');
            $g_message->show($g_l10n->get($b_return['text'], $b_return['path'], '<a href="mailto:'.$g_preferences['email_administrator'].'">', '</a>'));
        }

        $g_message->setForwardUrl($g_root_path.'/adm_program/system/back.php');
        $g_message->show($g_l10n->get('DOW_PHR_FOLDER_CREATED', $newFolderName));
    }
}


//Datei / Ordner umbenennen
elseif ($req_mode == 4)
{
    if ( (!$file_id && !$folder_id) OR ($file_id && $folder_id) )
    {
        //Es muss entweder eine FileID ODER eine FolderId uebergeben werden
        //beides ist auch nicht erlaubt
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if($file_id > 0)
    {
        $file = new TableFile($g_db);
        $file->getFileForDownload($file_id);

        //Pruefen ob Datensatz gefunden
        if ($file->getValue('fil_id')) {
            $oldFile = $file->getCompletePathOfFile();
        }
        else {
            $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
        }

        $newFile = null;

        if (strlen($_POST['new_name']) > 0)
        {
            $ret_code = isValidFileName($_POST['new_name'], true);

            if($ret_code != 0)
            {
                if($ret_code == -1)
                {
                    $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('DOW_NEW_NAME')));
                }
                elseif($ret_code == -2)
                {
                    $g_message->show($g_l10n->get('DOW_PHR_FILE_NAME_INVALID'));
                }
                elseif($ret_code == -3)
                {
                    $g_message->show($g_l10n->get('DOW_PHR_FILE_EXTENSION_INVALID'));
                }
            }
            else {
                $newFile = $_POST['new_name'];
            }
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('DOW_NEW_NAME')));
        }

        $newDescription = $_POST['new_description'];

        //Test ob die Datei schon existiert im Filesystem
        if ($newFile != $file->getValue('fil_name')
         && file_exists(SERVER_PATH. $file->getValue('fol_path'). '/'. $file->getValue('fol_name'). '/'.$newFile))
        {
            $g_message->show($g_l10n->get('DOW_PHR_FILE_EXIST', $newFile));
        }
        else
        {
            $oldName = $file->getValue('fil_name');

            // Datei umbenennen im Filesystem und in der Datenbank
            if (rename($oldFile,SERVER_PATH. $file->getValue('fol_path'). '/'. $file->getValue('fol_name'). '/'.$newFile))
            {
                $file->setValue('fil_name', $newFile);
                $file->setValue('fil_description', $newDescription);
                $file->save();

                $g_message->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                $g_message->show($g_l10n->get('DOW_PHR_FILE_RENAME',$oldName));
            }
            else {
                $g_message->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                $g_message->show($g_l10n->get('DOW_PHR_FILE_RENAME_ERROR',$oldName));
            }
        }

    }
    else if ($folder_id > 0)
    {
        $folder = new TableFolder($g_db);
        $folder->getFolderForDownload($folder_id);

        //Pruefen ob Datensatz gefunden
        if ($folder->getValue('fol_id')) {
            $oldFolder = $folder->getCompletePathOfFolder();
        }
        else {
            $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
        }

        $newFolder = null;

        if (strlen($_POST['new_name']) > 0)
        {
            $ret_code = isValidFileName($_POST['new_name']);

            if ($ret_code == 0)
            {
                $newFolder = $_POST['new_name'];
            }
            else
            {
                if ($ret_code == -1)
                {
                    $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('DOW_NEW_NAME')));
                }
                elseif ($ret_code == -2)
                {
                    $g_message->show($g_l10n->get('DOW_PHR_FOLDER_NAME_INVALID'));
                }
            }
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('DOW_NEW_NAME')));
        }

        $newDescription = $_POST['new_description'];

        //Test ob der Ordner schon existiert im Filesystem
        if ($newFolder != $folder->getValue('fol_name')
         && file_exists(SERVER_PATH. $folder->getValue('fol_path'). '/'.$newFolder))
        {
            $g_message->show($g_l10n->get('DOW_PHR_FOLDER_EXISTS', $newFolder));
        }
        else
        {
            $oldName = $folder->getValue('fol_name');

            // Ordner umbenennen im Filesystem und in der Datenbank
            if (rename($oldFolder,SERVER_PATH. $folder->getValue('fol_path'). '/'.$newFolder))
            {
                $folder->setValue('fol_description', $newDescription);
                $folder->rename($newFolder, $folder->getValue('fol_path'));

                $g_message->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                $g_message->show($g_l10n->get('DOW_PHR_FOLDER_RENAME',$oldName));
            }
            else {
                $g_message->setForwardUrl($g_root_path.'/adm_program/system/back.php');
                $g_message->show($g_l10n->get('DOW_PHR_FOLDER_RENAME_ERROR',$oldName));
            }
        }

    }
}


//Folder loeschen
elseif ($req_mode == 5)
{
    if (!$folder_id)
    {
        //Es muss eine FolderId uebergeben werden
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    else if ($folder_id > 0)
    {
        $folder = new TableFolder($g_db);
        $folder->getFolderForDownload($folder_id);

        //Pruefen ob Datensatz gefunden
        if ($folder->getValue('fol_id'))
        {
            if ($folder->delete())
            {
                // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
                echo 'done';
            }
        }
    }

    unset($_SESSION['download_request']);
}


//Datei / Ordner zur DB hinzufeuegen
elseif ($req_mode == 6)
{
    if ($folder_id == 0) {
        //FolderId ist zum hinzufuegen erforderlich
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if (isset($_GET['name']))
    {
        $ret_code = isValidFileName(urldecode($_GET['name']), true);
        if($ret_code == 0)
        {
            $name = urldecode($_GET['name']);
        }
        else
        {
            if($ret_code == -2)
            {
                $g_message->show($g_l10n->get('DOW_PHR_FILE_NAME_INVALID'));
            }
            elseif($ret_code == -3)
            {
                $g_message->show($g_l10n->get('DOW_PHR_FILE_EXTENSION_INVALID'));
            }
        }
    }
    else
    {
        //name ist zum hinzufuegen erforderlich
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //Informationen zum Zielordner aus der DB holen
    $targetFolder = new TableFolder($g_db);
    $targetFolder->getFolderForDownload($folder_id);

    //pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
    if (!$targetFolder->getValue('fol_id'))
    {
        //Datensatz konnte nicht in DB gefunden werden...
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //Pruefen ob das neue Element eine Datei order ein Ordner ist.
    if (is_file($targetFolder->getCompletePathOfFolder(). '/'. $name)) {
        //Datei hinzufuegen
        $newFile = new TableFile($g_db);
        $newFile->setValue('fil_fol_id',$targetFolder->getValue('fol_id'));
        $newFile->setValue('fil_name',$name);
        $newFile->setValue('fil_locked',$targetFolder->getValue('fol_locked'));
        $newFile->setValue('fil_counter','0');
        $newFile->save();

        //Zurueck zur letzten Seite
        $_SESSION['navigation']->addUrl(CURRENT_URL);
        $location = 'Location: '.$g_root_path.'/adm_program/system/back.php';
        header($location);
        exit();
    }
    else if (is_dir($targetFolder->getCompletePathOfFolder(). '/'. $name)) {

        //Ordner der DB hinzufuegen
        $newFolder = new TableFolder($g_db);
        $newFolder->setValue('fol_fol_id_parent', $targetFolder->getValue('fol_id'));
        $newFolder->setValue('fol_type', 'DOWNLOAD');
        $newFolder->setValue('fol_name', $name);
        $newFolder->setValue('fol_path', $targetFolder->getValue('fol_path'). '/'.$targetFolder->getValue('fol_name'));
        $newFolder->setValue('fol_locked', $targetFolder->getValue('fol_locked'));
        $newFolder->setValue('fol_public', $targetFolder->getValue('fol_public'));
        $newFolder->save();

        //Ordnerberechtigungen des ParentOrdners uebernehmen
        $newFolder->setRolesOnFolder($targetFolder->getRoleArrayOfFolder());

        //Zurueck zur letzten Seite
        $_SESSION['navigation']->addUrl(CURRENT_URL);
        $location = 'Location: '.$g_root_path.'/adm_program/system/back.php';
        header($location);
        exit();
   }

}

//Berechtigungen fuer einen Ordner speichern
elseif ($req_mode == 7)
{
    if ($folder_id == 0) {
        //FolderId ist zum hinzufuegen erforderlich
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //Informationen zum Zielordner aus der DB holen
    $targetFolder = new TableFolder($g_db);
    $targetFolder->getFolderForDownload($folder_id);

    //pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
    if (!$targetFolder->getValue('fol_id'))
    {
        //Datensatz konnte nicht in DB gefunden werden...
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //Formularinhalt aufbereiten
    if(isset($_POST['fol_public']) == false || $_POST['fol_public'] != 0)
    {
        $publicFlag = 1;
    }
    else {
        $publicFlag = 0;
    }

    //setze schon einmal das Public_Flag
    $targetFolder->editPublicFlagOnFolder($publicFlag);

    $rolesArray = null;

    //Nur wenn der Ordner oeffentlich nicht zugaenglich ist
    //werden die Rollenbrechtigungen gespeichert.
    //Ansonsten wird ein leeres Rollenset gespeichert...
    if ($publicFlag == 0) {

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

    $g_message->setForwardUrl($g_root_path.'/adm_program/system/back.php');
    $g_message->show($g_l10n->get('SYS_PHR_SAVE'));
}


?>