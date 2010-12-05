<?php
/******************************************************************************
 * Backup
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
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
require_once('../../system/classes/my_files.php');
require_once('backup.functions.php');


// nur Webmaster duerfen ein Backup starten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// Pfad in adm_my_files pruefen und ggf. anlegen
$myFilesBackup = new MyFiles('BACKUP');
if($myFilesBackup->checkSettings() == false)
{
    $g_message->show($g_l10n->get($myFilesBackup->errorText, $myFilesBackup->errorPath, '<a href="mailto:'.$g_preferences['email_administrator'].'">', '</a>'));
}

$backupabsolutepath = $myFilesBackup->getFolder().'/'; // make sure to include trailing slash

$old_backup_files = array();
$old_backup_files_cp = array();

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

//Liste der alten Backup Dateien bestimmen
if ($handle = opendir($backupabsolutepath)) 
{
    while (false !== ($file = readdir($handle))) 
	{
        if ($file != '.' && $file != '..' && $file !='.htaccess') 
		{
            $old_backup_files[] = $file;
        }
    }
    closedir($handle);	
}

// Sortiert die Backupfiles nach Dateiname / Datum
sort($old_backup_files);

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
        <th style="text-align: center;">'.$g_l10n->get('SYS_DELETE').'</th>
	</tr>';

    flush();
	
	if(count($old_backup_files) == 0)
	{
		echo'<tr><td colspan="4">Keine Backupdatei vorhanden!</td></tr>';
	}
	
	$backup_size_sum = 0;
	
    foreach($old_backup_files as $key => $old_backup_file)
    {
        echo '
        <tr class="tableMouseOver" id="row_file_'.$key.'">
            <td>
                <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/backup/get_backup_file.php?filename='. $old_backup_file. '">
                <img src="'. THEME_PATH. '/icons/page_white_compressed.png" alt="Datei" title="Datei" /></a>
                <a href="'.$g_root_path.'/adm_program/administration/backup/get_backup_file.php?filename='. $old_backup_file. '">'. $old_backup_file. '</a></td>
            <td>'. date ("d.m.Y H:i:s", filemtime($backupabsolutepath.$old_backup_file)). '</td>
            <td style="text-align: right;">'. round(filesize($backupabsolutepath.$old_backup_file)/1024). ' KB&nbsp;</td>
            <td style="text-align: center;">
                <a class="iconLink" href="javascript:deleteObject(\'bck\', \'row_file_'.$key.'\',0,\''.$old_backup_file.'\')">
                <img src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>
            </td>
        </tr>';
		$backup_size_sum = $backup_size_sum + round(filesize($backupabsolutepath.$old_backup_file)/1024);
    }
echo '<tr>
		<th>&nbsp</th>
		<th>Summe</th>
		<th style="text-align: right;">'. $backup_size_sum .' KB&nbsp</th>
		<th>&nbsp</th>
</tr>
		</table>';

require(THEME_SERVER_PATH. '/overall_footer.php');
?>
