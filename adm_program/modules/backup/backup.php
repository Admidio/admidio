<?php
/**
 ***********************************************************************************************
 * Show list with avaiable backup files and button to create a new backup
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * mode:   show_list     - show list with all created backup files
 *         create_backup - create a new backup from database
 *
 * Based uppon backupDB Version 1.2.7-201104261502
 * by James Heinrich <info@silisoftware.com>
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('backup.functions.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'show_list', 'validValues' => array('show_list', 'create_backup')));

// only administrators are allowed to create backups
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// module not available for other databases except MySQL
if($gDbType !== 'mysql')
{
    $gMessage->show($gL10n->get('BAC_ONLY_MYSQL'));
    // => EXIT
}

// check backup path in adm_my_files and create it if necessary
$myFilesBackup = new MyFiles('BACKUP');
if(!$myFilesBackup->checkSettings())
{
    $gMessage->show($gL10n->get($myFilesBackup->errorText, $myFilesBackup->errorPath, '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
    // => EXIT
}

$headline = $gL10n->get('BAC_DATABASE_BACKUP');

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$backupAbsolutePath = $myFilesBackup->getFolder().'/'; // make sure to include trailing slash

if($getMode === 'show_list')
{
    $existingBackupFiles = array();

    // start navigation of this module here
    $gNavigation->addStartUrl(CURRENT_URL, $headline);

    // create a list with all valid files in the backup folder
    $dirHandle = @opendir($backupAbsolutePath);
    if ($dirHandle)
    {
        while (($entry = readdir($dirHandle)) !== false)
        {
            if($entry === '.' || $entry === '..')
            {
                continue;
            }

            try
            {
                admStrIsValidFileName($entry, true);
                $existingBackupFiles[] = $entry;
            }
            catch(AdmException $e)
            {
                $temp = 1;
            }
        }
        closedir($dirHandle);
    }

    // sort files (filename/date)
    sort($existingBackupFiles);

    // get module menu
    $backupMenu = $page->getMenu();

    // show link to create new backup
    $backupMenu->addItem('admMenuItemNewBackup', ADMIDIO_URL.FOLDER_MODULES.'/backup/backup.php?mode=create_backup',
                         $gL10n->get('BAC_START_BACKUP'), 'database_save.png');

    // Define table
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

    foreach($existingBackupFiles as $key => $old_backup_file)
    {
        // create array with all column values
        $columnValues = array(
            '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/backup/backup_file_function.php?job=get_file&amp;filename='. $old_backup_file. '"><img
                src="'. THEME_URL. '/icons/page_white_compressed.png" alt="'. $old_backup_file. '" title="'. $old_backup_file. '" />'. $old_backup_file. '</a>',
            date($gPreferences['system_date'].' '.$gPreferences['system_time'], filemtime($backupAbsolutePath.$old_backup_file)),
            round(filesize($backupAbsolutePath.$old_backup_file) / 1024). ' kB',
            '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=bac&amp;element_id=row_file_'.
                $key.'&amp;name='.urlencode($old_backup_file).'&amp;database_id='.$old_backup_file.'"><img
                src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');
        $table->addRowByArray($columnValues, 'row_file_'.$key);

        $backup_size_sum += round(filesize($backupAbsolutePath.$old_backup_file) / 1024);
    }

    if(count($existingBackupFiles) > 0)
    {
        $columnValues = array('&nbsp;', $gL10n->get('BAC_SUM'), $backup_size_sum .' kB', '&nbsp;');
        $table->addRowByArray($columnValues);
    }

    $page->addHtml($table->show());
}
elseif($getMode === 'create_backup')
{
    ob_start();
    include(ADMIDIO_PATH. FOLDER_MODULES.'/backup/backup_script.php');
    $page->addHtml(ob_get_contents());
    ob_end_clean();

    // show button with link to backup list
    $form = new HtmlForm('show_backup_list_form', ADMIDIO_URL.FOLDER_MODULES.'/backup/backup.php', $page);
    $form->addSubmitButton('btn_update_overview', $gL10n->get('BAC_BACK_TO_BACKUP_PAGE'), array('icon' => THEME_URL.'/icons/back.png'));
    $page->addHtml($form->show(false));

}

// show html of complete page
$page->show();
