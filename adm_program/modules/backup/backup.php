<?php
/**
 ***********************************************************************************************
 * Show list with available backup files and button to create a new backup
 *
 * @copyright 2004-2023 The Admidio Team
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

require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/backup.functions.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'show_list', 'validValues' => array('show_list', 'create_backup')));

// only administrators are allowed to create backups
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// module not available for other databases except MySQL
if (DB_ENGINE !== Database::PDO_ENGINE_MYSQL) {
    $gMessage->show($gL10n->get('SYS_BACKUP_ONLY_MYSQL'));
    // => EXIT
}

// check backup path in adm_my_files and create it if necessary
try {
    FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/backup');
} catch (\RuntimeException $exception) {
    $gMessage->show($exception->getMessage());
    // => EXIT
}

$backupAbsolutePath = ADMIDIO_PATH . FOLDER_DATA . '/backup/'; // make sure to include trailing slash

if ($getMode === 'show_list') {
    $existingBackupFiles = array();

    // start navigation of this module here
    $headline = $gL10n->get('SYS_DATABASE_BACKUP');
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-database');
    $page = new HtmlPage('admidio-backup', $headline);

    // create a list with all valid files in the backup folder
    $dirHandle = @opendir($backupAbsolutePath);
    if ($dirHandle) {
        while (($entry = readdir($dirHandle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            try {
                StringUtils::strIsValidFileName($entry);

                // replace invalid characters in filename
                $entry = FileSystemUtils::removeInvalidCharsInFilename($entry);

                $existingBackupFiles[] = $entry;
            } catch (AdmException $e) {
                $temp = 1;
            }
        }
        closedir($dirHandle);
    }

    // sort files (filename/date)
    sort($existingBackupFiles);

    // show link to create new backup
    $page->addPageFunctionsMenuItem(
        'menu_item_backup_start',
        $gL10n->get('SYS_START_BACKUP'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/backup/backup.php', array('mode' => 'create_backup')),
        'fa-database'
    );

    // Define table
    $table = new HtmlTable('tableList', $page, true, true);
    $table->setMessageIfNoRowsFound('SYS_NO_BACKUP_FILE_EXISTS');

    // create array with all column heading values
    $columnHeading = array(
        $gL10n->get('SYS_BACKUP_FILE'),
        $gL10n->get('SYS_CREATED_AT'),
        $gL10n->get('SYS_SIZE'),
        '&nbsp;'
    );
    $table->setColumnAlignByArray(array('left', 'left', 'right', 'center'));
    $table->addRowHeadingByArray($columnHeading);
    $table->setDatatablesOrderColumns(array(array(2, 'desc')));
    $table->disableDatatablesColumnsSort(array(4));
    $table->setDatatablesColumnsNotHideResponsive(array(4));

    $backupSizeSum = 0;

    foreach ($existingBackupFiles as $key => $oldBackupFile) {
        $fileSize = filesize($backupAbsolutePath.$oldBackupFile);
        $backupSizeSum += $fileSize;

        // create array with all column values
        $columnValues = array(
            '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/backup/backup_file_function.php', array('job' => 'get_file', 'filename' => $oldBackupFile)). '">
                <i class="fas fa-file-archive"></i>'. $oldBackupFile. '</a>',
            date($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'), filemtime($backupAbsolutePath.$oldBackupFile)),
            round($fileSize / 1024). ' kB',
            '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'bac', 'element_id' => 'row_file_'.$key, 'name' => $oldBackupFile, 'database_id' => $oldBackupFile)).'">
                <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE_FILE').'"></i></a>');
        $table->addRowByArray($columnValues, 'row_file_'.$key);
    }

    if (count($existingBackupFiles) > 0) {
        $columnValues = array('&nbsp;', $gL10n->get('SYS_TOTAL'), round($backupSizeSum / 1024) .' kB', '&nbsp;');
        $table->addRowFooterByArray($columnValues);
    }

    $page->addHtml($table->show());
} elseif ($getMode === 'create_backup') {
    ob_start();
    require_once(__DIR__ . '/backup_script.php');
    $fileContent = ob_get_contents();
    ob_end_clean();

    $headline = $gL10n->get('SYS_EXECUTE_BACKUP');
    $gNavigation->addUrl(CURRENT_URL, $headline);
    $page = new HtmlPage('admidio-backup', $headline);
    $page->addHtml($fileContent);

    // show button with link of backup list
    $form = new HtmlForm('show_backup_list_form', ADMIDIO_URL.FOLDER_MODULES.'/backup/backup.php', $page);
    $form->addSubmitButton(
        'btn_update_overview',
        $gL10n->get('SYS_BACK_TO_BACKUP_PAGE'),
        array('icon' => 'fa-arrow-circle-left')
    );
    $page->addHtml($form->show());
}

// show html of complete page
$page->show();
