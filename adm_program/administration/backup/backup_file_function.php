<?php
/******************************************************************************
 * Backup
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * job      - get_file : die uebergebene Backupdatei wird heruntergeladen
 *          - delete   : die uebergebene Backupdatei wird geloescht
 * filename : Der Name der Datei, welche heruntergeladen werden soll
 *
 *****************************************************************************/
require('../../system/common.php');
require('../../system/login_valid.php');

// Initialize and check the parameters
$getJob      = admFuncVariableIsValid($_GET, 'job', 'string', null, true, array('delete', 'get_file'));
$getFilename = admFuncVariableIsValid($_GET, 'filename', 'file', null, true);

// nur Webmaster duerfen ein Backup starten
if($gCurrentUser->isWebmaster() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$backupAbsolutePath = SERVER_PATH. '/adm_my_files/backup/'; // make sure to include trailing slash

//kompletten Pfad der Datei holen
$completePath = $backupAbsolutePath.$getFilename;

//pruefen ob File ueberhaupt physikalisch existiert
if(!file_exists($completePath))
{
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
}

switch($getJob)
{
	case 'get_file':
		//Dateigroese ermitteln
		$fileSize   = filesize($completePath);

		// Passenden Datentyp erzeugen.
		header('Content-Type: application/octet-stream');
		header('Content-Length: '.$fileSize);
		header('Content-Disposition: attachment; filename="'.urlencode($getFilename).'"');
		// noetig fuer IE, da ansonsten der Download mit SSL nicht funktioniert
		header('Cache-Control: private');
		header('Pragma: public');

		// Datei ausgeben.
		readfile($completePath);
		break;
	
	case 'delete':
		// Backupdatei loeschen
		if(unlink($completePath))
		{
			echo 'done'; 
		}
		exit();
		break;
}
