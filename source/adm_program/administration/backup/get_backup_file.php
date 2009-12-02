<?php
/******************************************************************************
 * Download Script
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * filename      :  Der Name der Datei, welche heruntergeladen werden soll
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');

// nur Webmaster duerfen ein Backup runterladen
if($g_current_user->isWebmaster() == false)
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

$backupabsolutepath = SERVER_PATH. '/adm_my_files/backup/'; // make sure to include trailing slash
$filename = '';

//pruefen ob eine Dateiname übergeben wurde
if (array_key_exists('filename', $_GET))
{
    if (is_string($_GET['filename']) == false)
    {
        //FileId ist nicht string
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
	
	$filename = $_GET['filename'];	

}
else
{
    // ohne File gehts auch nicht weiter
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Pruefung, ob nur gute Dateinamen uebergeben werden
if( -2 == isValidFileName($filename) )
{
	$g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

//kompletten Pfad der Datei holen
$completePath = $backupabsolutepath.$filename;


//pruefen ob File ueberhaupt physikalisch existiert
if (!file_exists($completePath))
{
    $g_message->show($g_l10n->get('SYS_PHR_FILE_NOT_EXIST'));
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