<?php
/**
 ***********************************************************************************************
 * Show a list of all files
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_uuid : UUID of the current folder that should be shown
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['documents_files_request']);

// Initialize and check the parameters
$getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'string');

// Check if module is activated
if (!$gSettingsManager->getBool('documents_files_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

try {
    // get recordset of current folder from database
    $currentFolder = new TableFolder($gDb);
    $currentFolder->getFolderForDownload($getFolderUuid);
} catch (AdmException $e) {
    $e->showHtml();
    // => EXIT
}

// set headline of the script
if ($currentFolder->getValue('fol_fol_id_parent') == null) {
    $headline = $gL10n->get('SYS_DOCUMENTS_FILES');
} else {
    $headline = $gL10n->get('SYS_DOCUMENTS_FILES').' - '.$currentFolder->getValue('fol_name');
}

if ($getFolderUuid !== '') {
    // add URL to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $currentFolder->getValue('fol_name'));
} else {
    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $gL10n->get('SYS_DOCUMENTS_FILES'), 'fa-file-download');
}

$getFolderUuid = $currentFolder->getValue('fol_uuid');

// Get folder content for style
$folderContent = $currentFolder->getFolderContentsForDownload();

// create html page object
$page = new HtmlPage('admidio-documents-files', $headline);

if ($currentFolder->hasUploadRight()) {
    // upload only possible if upload filesize > 0
    if ($gSettingsManager->getInt('max_file_upload_size') > 0) {
        // show links for upload, create folder and folder configuration
        $page->addPageFunctionsMenuItem(
            'menu_item_documents_upload_files',
            $gL10n->get('SYS_UPLOAD_FILES'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/file_upload.php', array('module' => 'documents_files', 'uuid' => $getFolderUuid)),
            'fa-upload'
        );

        $page->addPageFunctionsMenuItem(
            'menu_item_documents_create_folder',
            $gL10n->get('SYS_CREATE_FOLDER'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/folder_new.php', array('folder_uuid' => $getFolderUuid)),
            'fa-plus-circle'
        );

        if ($currentFolder->getValue('fol_fol_id_parent') > 0) {
            $page->addPageFunctionsMenuItem(
                'menu_item_documents_edit_folder',
                $gL10n->get('SYS_EDIT_FOLDER'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/rename.php', array('folder_uuid' => $getFolderUuid)),
                'fa-edit'
            );
        }
    }

    if ($gCurrentUser->adminDocumentsFiles()) {
        $page->addPageFunctionsMenuItem(
            'menu_item_documents_permissions',
            $gL10n->get('SYS_PERMISSIONS'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/folder_config.php', array('folder_uuid' => $getFolderUuid)),
            'fa-lock'
        );
    }
}

// Create table object
$documentsFilesOverview = new HtmlTable('tbl_documents_files', $page, true, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TYPE'),
    '<i class="fas fa-fw fa-folder-open" data-toggle="tooltip" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('SYS_FILE_TYPE').'"></i>',
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_DATE_MODIFIED'),
    $gL10n->get('SYS_SIZE'),
    $gL10n->get('SYS_COUNTER'),
    '&nbsp;'
);

$documentsFilesOverview->disableDatatablesColumnsSort(array(7));
$documentsFilesOverview->setDatatablesColumnsNotHideResponsive(array(7));
$documentsFilesOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right', 'right', 'right'));
$documentsFilesOverview->addRowHeadingByArray($columnHeading);
$documentsFilesOverview->setMessageIfNoRowsFound('SYS_FOLDER_NO_FILES', 'warning');

// Get folder content
if (isset($folderContent['folders'])) {
    // First get possible sub folders
    foreach ($folderContent['folders'] as $nextFolder) {
        // create array with all column values
        $columnValues = array(
            1, // Type folder
            '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files.php', array('folder_uuid' => $nextFolder['fol_uuid'])). '">
                <i class="fas fa-fw fa-folder" data-toggle="tooltip" title="'.$gL10n->get('SYS_FOLDER').'"></i></a>',
            '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files.php', array('folder_uuid' => $nextFolder['fol_uuid'])). '">'. $nextFolder['fol_name']. '</a>'.HtmlForm::getHelpTextIcon((string) $nextFolder['fol_description'], 'SYS_DESCRIPTION'),
            '',
            '',
            ''
        );

        if ($currentFolder->hasUploadRight()) {
            // Links for change and delete
            $additionalFolderFunctions = '';

            if ($gCurrentUser->adminDocumentsFiles()) {
                $additionalFolderFunctions .= '
                <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/folder_config.php', array('folder_uuid' => $nextFolder['fol_uuid'])) . '">
                    <i class="fas fa-lock" data-toggle="tooltip" title="'.$gL10n->get('SYS_PERMISSIONS').'"></i></a>';
            }

            if ($nextFolder['fol_exists'] === true) {
                $additionalFolderFunctions .= '
                <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/rename.php', array('folder_uuid' => $nextFolder['fol_uuid'])) . '">
                    <i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT_FOLDER').'"></i></a>';
            } elseif ($gCurrentUser->adminDocumentsFiles()) {
                $additionalFolderFunctions .= '
                <i class="fas fa-exclamation-triangle" data-toggle="popover" data-trigger="hover click" data-placement="left"
                    title="'.$gL10n->get('SYS_WARNING').'" data-content="'.$gL10n->get('SYS_FOLDER_NOT_EXISTS').'"></i>';
            }

            $columnValues[] = $additionalFolderFunctions . '
                                <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                    data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'fol', 'element_id' => 'row_folder_'.$nextFolder['fol_uuid'],
                                    'name' => $nextFolder['fol_name'], 'database_id' => $nextFolder['fol_uuid'])).'">
                                    <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE_FOLDER').'"></i></a>';
        } else {
            $columnValues[] = '&nbsp;';
        }

        $documentsFilesOverview->addRowByArray($columnValues, 'row_folder_'.$nextFolder['fol_uuid']);
    }
}

// Get contained files
if (isset($folderContent['files'])) {
    $file = new TableFile($gDb);

    foreach ($folderContent['files'] as $nextFile) {
        $file->clear();
        $file->setArray($nextFile);

        $fileUuid   = $file->getValue('fil_uuid');
        $fileLink = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/get_file.php', array('file_uuid' => $fileUuid, 'view' => 1));
        $target   = '';

        if ($file->isViewableInBrowser()) {
            $target = ' target="_blank"';
        }

        // Format timestamp
        $timestamp = \DateTime::createFromFormat('Y-m-d H:i:s', $nextFile['fil_timestamp']);

        // create array with all column values
        $columnValues = array(
            2, // Type file
            '<a class="admidio-icon-link" href="' . $fileLink . '"' . $target . '>
                <i class="fas fa-fw ' . $file->getFontAwesomeIcon() . '" data-toggle="tooltip" title="'.$gL10n->get('SYS_FILE').'"></i></a>',
            '<a href="' . $fileLink . '"' . $target . '>'. $file->getValue('fil_name'). '</a>'.HtmlForm::getHelpTextIcon((string) $file->getValue('fil_description'), 'SYS_DESCRIPTION'),
            $timestamp->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
            round($file->getValue('fil_size') / 1024). ' kB&nbsp;',
            ($file->getValue('fil_counter') !== '') ? $file->getValue('fil_counter') : 0
        );

        $additionalFileFunctions = '';

        // add download link
        $additionalFileFunctions .= '
        <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/get_file.php', array('file_uuid' => $fileUuid)). '">
            <i class="fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('SYS_DOWNLOAD_FILE').'"></i></a>';

        if ($currentFolder->hasUploadRight()) {
            // Links for change and delete
            if ($file->getValue('fil_exists') === true) {
                $additionalFileFunctions .= '
                <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/rename.php', array('folder_uuid' => $getFolderUuid, 'file_uuid' => $fileUuid)). '">
                    <i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
            } elseif ($gCurrentUser->adminDocumentsFiles()) {
                $additionalFileFunctions .= '
                <i class="fas fa-exclamation-triangle" data-toggle="popover" data-trigger="hover click" data-placement="left"
                    title="'.$gL10n->get('SYS_WARNING').'" data-content="'.$gL10n->get('SYS_FILE_NOT_EXIST_DELETE_FROM_DB').'"></i>';
            }
            $additionalFileFunctions .= '
            <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'fil', 'element_id' => 'row_file_'.$fileUuid,
                'name' => $file->getValue('fil_name'), 'database_id' => $fileUuid, 'database_id_2' => $getFolderUuid)).'">
                <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE_FILE').'"></i></a>';
        }
        $columnValues[] = $additionalFileFunctions;
        $documentsFilesOverview->addRowByArray($columnValues, 'row_file_'.$fileUuid);
    }
}

// Create download table
$documentsFilesOverview->setDatatablesColumnsHide(array(1));
$documentsFilesOverview->setDatatablesOrderColumns(array(1, 3));
$htmlDocumentsFilesOverview = $documentsFilesOverview->show();


// Add Admin table to html page


// If user is download Admin show further files contained in this folder.
if ($gCurrentUser->adminDocumentsFiles()) {
    // Check whether additional content was found in the folder
    if (isset($folderContent['additionalFolders']) || isset($folderContent['additionalFiles'])) {
        $htmlAdminTableHeadline = '<h2>'.$gL10n->get('SYS_UNMANAGED_FILES').HtmlForm::getHelpTextIcon('SYS_ADDITIONAL_FILES').'</h2>';

        // Create table object
        $adminTable = new HtmlTable('tbl_documents_files', $page, true);
        $adminTable->setColumnAlignByArray(array('left', 'left', 'left', 'right'));

        // create array with all column heading values
        $columnHeading = array(
            '<i class="fas fa-fw fa-folder-open" data-toggle="tooltip" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('SYS_FILE_TYPE').'"></i>',
            $gL10n->get('SYS_NAME'),
            $gL10n->get('SYS_SIZE'),
            '&nbsp;'
        );
        $adminTable->addRowHeadingByArray($columnHeading);

        // Get folders
        if (isset($folderContent['additionalFolders'])) {
            foreach ($folderContent['additionalFolders'] as $nextFolder) {
                $columnValues = array(
                    '<i class="fas fa-fw fa-folder" data-toggle="tooltip" title="'.$gL10n->get('SYS_FOLDER').'"></i>',
                    $nextFolder['fol_name'],
                    '',
                    '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files_function.php', array('mode' => '6', 'folder_uuid' => $getFolderUuid, 'name' => $nextFolder['fol_name'])). '">
                        <i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_ADD_TO_DATABASE').'"></i>
                    </a>'
                );
                $adminTable->addRowByArray($columnValues);
            }
        }

        // Get files
        if (isset($folderContent['additionalFiles'])) {
            $file = new TableFile($gDb);

            foreach ($folderContent['additionalFiles'] as $nextFile) {
                $file->clear();
                $file->setArray($nextFile);

                $columnValues = array(
                    '<i class="fas fa-fw ' . $file->getFontAwesomeIcon() . '" data-toggle="tooltip" title="'.$gL10n->get('SYS_FILE').'"></i>',
                    $file->getValue('fil_name'),
                    round($file->getValue('fil_size') / 1024). ' kB&nbsp;',
                    '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files_function.php', array('mode' => '6', 'folder_uuid' => $getFolderUuid, 'name' => $file->getValue('fil_name'))). '">
                        <i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_ADD_TO_DATABASE').'"></i>
                    </a>'
                );
                $adminTable->addRowByArray($columnValues);
            }
        }
        $htmlAdminTable = $adminTable->show();
    }
}

// Output module html to client

$page->addHtml($htmlDocumentsFilesOverview);

// if user has admin download rights, then show admin table for undefined files in folders
if (isset($htmlAdminTable)) {
    $page->addHtml($htmlAdminTableHeadline);
    $page->addHtml($htmlAdminTable);
}

// show html of complete page
$page->show();
