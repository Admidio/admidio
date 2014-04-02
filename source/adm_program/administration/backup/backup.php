<?php
/******************************************************************************
 * Show list with avaiable backup files and button to create a new backup
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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
require_once('../../system/login_valid.php');
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

$backupabsolutepath = $myFilesBackup->getFolder().'/'; // make sure to include trailing slash

$old_backup_files = array();
$old_backup_files_cp = array();

// Navigation faengt hier im Modul an
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL);

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

$gLayout['title']  = $gL10n->get('BAC_DATABASE_BACKUP');
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() {
            $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
        }); 
    //--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');
echo '<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>';

// create module menu
$backupMenu = new ModuleMenu('admMenuBackup');

// show link to create new backup
$backupMenu->addItem('admMenuItemNewBackup', $g_root_path.'/adm_program/administration/backup/backup_script.php', 
							$gL10n->get('BAC_START_BACKUP'), 'database_save.png');
$backupMenu->show();


//Define table
$table = new HtmlTableBasic('', 'tableList');
$table->addAttribute('cellspacing', '0');
$table->addRow();
$table->addColumn($gL10n->get('BAC_BACKUP_FILE'), '', '', 'th');
$table->addColumn($gL10n->get('BAC_CREATION_DATE'), '', '', 'th');
$table->addColumn($gL10n->get('SYS_SIZE'), '', '', 'th');
$table->addColumn($gL10n->get('SYS_DELETE'), 'style', 'text-align: center;', 'th');

flush();
	
if(count($old_backup_files) == 0)
{   
    $table->addRow();
    $table->addColumn($gL10n->get('BAC_NO_BACKUP_FILE_EXISTS'), 'colspan', '4');
}
	
$backup_size_sum = 0;
	
foreach($old_backup_files as $key => $old_backup_file)
{
    $table->addRow('', array('id' => 'row_file_'.$key));
    $table->addAttribute('class', 'tableMouseOver');
    $table->addColumn('<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/backup/backup_file_function.php?job=get_file&amp;filename='. $old_backup_file. '">
                        <img src="'. THEME_PATH. '/icons/page_white_compressed.png" alt="'. $old_backup_file. '" title="'. $old_backup_file. '" /></a>
                        <a href="'.$g_root_path.'/adm_program/administration/backup/backup_file_function.php?job=get_file&amp;filename='. $old_backup_file. '">'. $old_backup_file. '</a>');
    $table->addColumn(date ('d.m.Y H:i:s', filemtime($backupabsolutepath.$old_backup_file)));    
    $table->addColumn(round(filesize($backupabsolutepath.$old_backup_file)/1024). ' kB&nbsp;', 'style', 'text-align: right;');    
    $table->addColumn('<a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=bac&amp;element_id=row_file_'.
                        $key.'&amp;name='.urlencode($old_backup_file).'&amp;database_id='.$old_backup_file.'"><img
                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>', 'style', 'text-align: center;');    

	$backup_size_sum = $backup_size_sum + round(filesize($backupabsolutepath.$old_backup_file)/1024);
}

$table->addRow();
$table->addColumn('&nbsp;', '', '', 'th');
$table->addColumn($gL10n->get('BAC_SUM'), '', '', 'th');
$table->addColumn($backup_size_sum .' kB&nbsp;', 'style', 'text-align: right;', 'th');
$table->addColumn('&nbsp;', '', '', 'th');

echo $table->getHtmlTable();

require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>
