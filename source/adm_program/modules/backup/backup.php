<?php
/******************************************************************************
 * Show list with avaiable backup files and button to create a new backup
 *
 * Copyright    : (c) 2004 - 2014 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * 
 * Based uppon backupDB Version 1.2.7-201104261502
 * by James Heinrich <info@silisoftware.com>  
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('backup.functions.php');


// only webmaster are allowed to start backup
if($gCurrentUser->isWebmaster() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// check backup path in adm_my_files and create it if neccessary
$myFilesBackup = new MyFiles('BACKUP');
if($myFilesBackup->checkSettings() == false)
{
    $gMessage->show($gL10n->get($myFilesBackup->errorText, $myFilesBackup->errorPath, '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
}

$headline = $gL10n->get('BAC_DATABASE_BACKUP');

$backupabsolutepath = $myFilesBackup->getFolder().'/'; // make sure to include trailing slash

$old_backup_files = array();
$old_backup_files_cp = array();

// Navigation faengt hier im Modul an
$gNavigation->addStartUrl(CURRENT_URL, $headline);

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

// create html page object
$page = new HtmlPage();

$page->addHeadline($headline);

$page->addJavascript('$(".icon-link-popup").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});', true);

// create module menu
$backupMenu = new HtmlNavbar('admMenuBackup', $headline, $page);

// show link to create new backup
$backupMenu->addItem('admMenuItemNewBackup', $g_root_path.'/adm_program/modules/backup/backup_script.php', 
							$gL10n->get('BAC_START_BACKUP'), 'database_save.png');

$page->addHtml($backupMenu->show(false));

//Define table
$table = new HtmlTable('tableList', $page, true);
$table->setMessageIfNoRowsFound('BAC_NO_BACKUP_FILE_EXISTS');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('BAC_BACKUP_FILE'),
    $gL10n->get('BAC_CREATION_DATE'),
    $gL10n->get('SYS_SIZE'),
    $gL10n->get('SYS_DELETE'));
$table->setColumnAlignByArray(array('left', 'left', 'right', 'center'));
$table->addRowHeadingByArray($columnHeading);
	
$backup_size_sum = 0;
	
foreach($old_backup_files as $key => $old_backup_file)
{
    // create array with all column values
    $columnValues = array(
        '<a class="icon-text-link" href="'.$g_root_path.'/adm_program/modules/backup/backup_file_function.php?job=get_file&amp;filename='. $old_backup_file. '"><img 
            src="'. THEME_PATH. '/icons/page_white_compressed.png" alt="'. $old_backup_file. '" title="'. $old_backup_file. '" />'. $old_backup_file. '</a>',
        date ('d.m.Y H:i:s', filemtime($backupabsolutepath.$old_backup_file)),
        round(filesize($backupabsolutepath.$old_backup_file)/1024). ' kB',
        '<a class="icon-link icon-link-popup" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=bac&amp;element_id=row_file_'.
            $key.'&amp;name='.urlencode($old_backup_file).'&amp;database_id='.$old_backup_file.'"><img
            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');
    $table->addRowByArray($columnValues, 'row_file_'.$key);

	$backup_size_sum = $backup_size_sum + round(filesize($backupabsolutepath.$old_backup_file)/1024);
}

if(count($old_backup_files) > 0)
{   
    $columnValues = array('&nbsp;', $gL10n->get('BAC_SUM'), $backup_size_sum .' kB', '&nbsp;');
    $table->addRowByArray($columnValues);
}

$page->addHtml($table->show(false));

// show html of complete page
$page->show();

?>
