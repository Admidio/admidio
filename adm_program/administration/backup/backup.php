<?php
/******************************************************************************
 * Backup
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * 
 * Based uppon backupDB Version 1.2.5a-200806190803 
 * by James Heinrich <info@silisoftware.com>  
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/htaccess.php');
require_once('backup.functions.php');


// nur Webmaster duerfen ein Backup starten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show('norights');
}

$backupabsolutepath = SERVER_PATH. '/adm_my_files/backup/'; // make sure to include trailing slash

//ggf. Ordner für Backups anlegen
if(!file_exists($backupabsolutepath))
{
    mkdir($backupabsolutepath, 0777);
    chmod($backupabsolutepath, 0777);
}

$protection = new Htaccess(SERVER_PATH. '/adm_my_files');
$protection->protectFolder();


$old_backup_files = array();
$old_backup_files_cp = array();

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

//Zeitpunkt des letzten Backups bestimmen
if ($handle = opendir($backupabsolutepath)) 
{
    while (false !== ($file = readdir($handle))) 
	{
        if ($file != '.' && $file != '..' && $file !='.htaccess') 
		{
            $old_backup_files[] = $file;
			$old_backup_files_cp[] = $backupabsolutepath.$file;
        }
    }
    closedir($handle);	
}

$g_layout['title']  = 'Datenbank Backup';
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>';

require(THEME_SERVER_PATH. '/overall_header.php');
echo '<h1 class="moduleHeadline">Datenbank Backup</h1>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/backup/backup_function.php"><img
            src="'. THEME_PATH. '/icons/database_save.png" alt="Backup starten" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/backup/backup_function.php">Backup starten</a>
        </span>
    </li>
</ul>';

//Anlegen der Tabelle
echo '
<table class="tableList" cellspacing="0">
    <tr>
        <th>Backupdatei</th>
        <th>Erstellungsdatum</th>
        <th>Größe</th>
        <th style="text-align: center;">Löschen</th>
	</tr>';

    flush();

    foreach($old_backup_files as $key => $old_backup_file)
    {
        echo '
        <tr class="tableMouseOver" id="row_file_'.$key.'">
            <td>
                <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/backup/get_backup_file.php?filename='. $old_backup_file. '">
                <img src="'. THEME_PATH. '/icons/page_white_compressed.png" alt="Datei" title="Datei" /></a>
                <a href="'.$g_root_path.'/adm_program/administration/backup/get_backup_file.php?filename='. $old_backup_file. '">'. $old_backup_file. '</a></td>
            <td>'. date ("d.m.Y H:i:s", filemtime($old_backup_files_cp[$key])). '</td>
            <td>'. round(filesize($old_backup_files_cp[$key])/1024). ' KB&nbsp;</td>
            <td style="text-align: center;">
                <a class="iconLink" href="javascript:deleteObject(\'bck\', \'row_file_'.$key.'\',0,\''.$old_backup_file.'\')">
                <img src="'. THEME_PATH. '/icons/delete.png" alt="Löschen" title="Löschen" /></a>
            </td>
        </tr>';
    }
echo '</table>';

require(THEME_SERVER_PATH. '/overall_footer.php');
?>
