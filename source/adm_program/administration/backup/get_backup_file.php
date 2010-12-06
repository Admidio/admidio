<?php
/******************************************************************************
 * Download Script
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * filename      :  Der Name der Datei, welche heruntergeladen werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur Webmaster duerfen ein Backup runterladen
if($g_current_user->isWebmaster() == false)
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

$backupabsolutepath = SERVER_PATH. '/adm_my_files/backup/'; // make sure to include trailing slash
$filename = '';

//pruefen ob ein Dateiname übergeben wurde
if (array_key_exists('filename', $_GET))
{
    $returnCode = isValidFileName($_GET['filename']);
    
    if($returnCode < 0)
    {
        if($returnCode == -2)
        {
            $g_message->show($g_l10n->get('BAC_FILE_NAME_INVALID'));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
        }
    }
	
	$filename = $_GET['filename'];	

}
else
{
    // ohne Dateiname gehts auch nicht weiter
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

//kompletten Pfad der Datei holen
$completePath = $backupabsolutepath.$filename;


//pruefen ob File ueberhaupt physikalisch existiert
if (!file_exists($completePath))
{
    $g_message->show($g_l10n->get('SYS_FILE_NOT_EXIST'));
}

//Dateigroese ermitteln
$fileSize   = filesize($completePath);

// Passenden Datentyp erzeugen.
header('Content-Type: application/octet-stream');
header('Content-Length: '.$fileSize);
header('Content-Disposition: attachment; filename="'. $_GET['filename']. '"');
// noetig fuer IE6, da sonst pdf und doc nicht direkt geoeffnet werden kann
header('Cache-Control: private');
header('Pragma: public');

// Datei ausgeben.
readfile($completePath);
?>