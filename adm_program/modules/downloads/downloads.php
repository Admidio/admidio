<?php
/**
 ***********************************************************************************************
 * Show a list of all downloads
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_id : Id of the current folder that should be shown
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/../../system/file_extension_icons.php');

unset($_SESSION['download_request']);

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'int');

// Check if module is activated
if (!$gSettingsManager->getBool('enable_download_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

try
{
    // get recordset of current folder from database
    $currentFolder = new TableFolder($gDb);
    $currentFolder->getFolderForDownload($getFolderId);
}
catch(AdmException $e)
{
    $e->showHtml();
    // => EXIT
}

// set headline of the script
if($currentFolder->getValue('fol_fol_id_parent') == null)
{
    $headline = $gL10n->get('DOW_DOWNLOADS');
}
else
{
    $headline = $gL10n->get('DOW_DOWNLOADS').' - '.$currentFolder->getValue('fol_name');
}

// Navigation of the module starts here
$gNavigation->addFirst(CURRENT_URL);

$getFolderId = (int) $currentFolder->getValue('fol_id');

// Get folder content for style
$folderContent = $currentFolder->getFolderContentsForDownload();

// Keep navigation link
$navigationBar = $currentFolder->getNavigationForDownload();

// create html page object
$page = new HtmlPage($headline);

$page->enableModal();
$page->addJavascript('
    $("body").on("hidden.bs.modal", ".modal", function() {
        $(this).removeData("bs.modal");
        location.reload();
    });
    $("#menu_item_upload_files").attr("data-toggle", "modal");
    $("#menu_item_upload_files").attr("data-target", "#admidio_modal");
    ',
    true
);

// get module menu
$downloadsMenu = $page->getMenu();

if ($currentFolder->hasUploadRight())
{
    // upload only possible if upload filesize > 0
    if ($gSettingsManager->getInt('max_file_upload_size') > 0)
    {
        // show links for upload, create folder and folder configuration
        $downloadsMenu->addItem(
            'menu_item_create_folder', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/folder_new.php', array('folder_id' => $getFolderId)),
            $gL10n->get('DOW_CREATE_FOLDER'), 'fa-plus-circle'
        );

        $downloadsMenu->addItem(
            'menu_item_upload_files', safeUrl(ADMIDIO_URL.'/adm_program/system/file_upload.php', array('module' => 'downloads', 'id' => $getFolderId)),
            $gL10n->get('DOW_UPLOAD_FILES'), 'fa-upload'
        );
    }

    if($gCurrentUser->editDownloadRight())
    {
        $downloadsMenu->addItem(
            'menu_item_config_folder', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/folder_config.php', array('folder_id' => $getFolderId)),
            $gL10n->get('SYS_PERMISSIONS'), 'fa-lock'
        );
    }
}

if($gCurrentUser->isAdministrator())
{
    // show link to system preferences of weblinks
    $downloadsMenu->addItem(
        'admMenuItemPreferencesLinks', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', array('show_option' => 'downloads')),
        $gL10n->get('SYS_MODULE_PREFERENCES'), 'fa-cog', 'right'
    );
}

// Create table object
$downloadOverview = new HtmlTable('tbl_downloads', $page, true, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TYPE'),
    '<i class="fas fa-fw fa-folder-open" data-toggle="tooltip" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'"></i>',
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_DATE_MODIFIED'),
    $gL10n->get('SYS_SIZE'),
    $gL10n->get('DOW_COUNTER')
);

if ($currentFolder->hasUploadRight())
{
    $columnHeading[] = '&nbsp;';
    $downloadOverview->disableDatatablesColumnsSort(array(7));
}

$downloadOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right', 'right', 'right'));
$downloadOverview->addRowHeadingByArray($columnHeading);
$downloadOverview->setMessageIfNoRowsFound('DOW_FOLDER_NO_FILES', 'warning');

// Get folder content
if (isset($folderContent['folders']))
{
    // First get possible sub folders
    foreach ($folderContent['folders'] as $nextFolder)
    {
        $folderDescription = '';
        if($nextFolder['fol_description'] !== null)
        {
            $folderDescription = '<i class="fas fa-info-circle admidio-info-icon" data-toggle="popover" data-trigger="hover"
                data-placement="right" title="'.$gL10n->get('SYS_DESCRIPTION').'" data-content="'.$nextFolder['fol_description'].'"></i>';
        }

        // create array with all column values
        $columnValues = array(
            1, // Type folder
            '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/downloads.php', array('folder_id' => $nextFolder['fol_id'])). '">
                <i class="fas fa-fw fa-folder" data-toggle="tooltip" title="'.$gL10n->get('SYS_FOLDER').'"></i></a>',
            '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/downloads.php', array('folder_id' => $nextFolder['fol_id'])). '">'. $nextFolder['fol_name']. '</a>'.$folderDescription,
            '',
            '',
            ''
        );

        if ($currentFolder->hasUploadRight())
        {
            // Links for change and delete
            $additionalFolderFunctions = '';

            if($nextFolder['fol_exists'] === true)
            {
                $additionalFolderFunctions = '
                <a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/rename.php', array('folder_id' => $nextFolder['fol_id'])). '">
                    <i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
            }
            elseif($gCurrentUser->editDownloadRight())
            {
                $additionalFolderFunctions = '
                <i class="fas fa-exclamation-triangle" data-toggle="popover" data-trigger="hover" data-placement="left"
                    title="'.$gL10n->get('SYS_WARNING').'" data-content="'.$gL10n->get('DOW_FOLDER_NOT_EXISTS').'"></i>';
            }

            $columnValues[] = $additionalFolderFunctions.'
                                <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                    href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'fol', 'element_id' => 'row_folder_'.$nextFolder['fol_id'],
                                    'name' => $nextFolder['fol_name'], 'database_id' => $nextFolder['fol_id'])).'">
                                    <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
        }
        $downloadOverview->addRowByArray($columnValues, 'row_folder_'.$nextFolder['fol_id']);
    }
}

// Get contained files
if (isset($folderContent['files']))
{
    foreach ($folderContent['files'] as $nextFile)
    {
        // Check filetyp
        $fileExtension = strtolower(pathinfo($nextFile['fil_name'], PATHINFO_EXTENSION));

        // Choose icon for the file
        $iconFile = 'fa-file';
        if(array_key_exists($fileExtension, $iconFileExtension))
        {
            $iconFile = $iconFileExtension[$fileExtension];
        }

        // Format timestamp
        $timestamp = \DateTime::createFromFormat('Y-m-d H:i:s', $nextFile['fil_timestamp']);

        $fileDescription = '';
        if($nextFile['fil_description'] !== null)
        {
            $fileDescription = '<i class="fas fa-info-circle admidio-info-icon" data-toggle="popover" data-trigger="hover"
                data-placement="right" title="'.$gL10n->get('SYS_DESCRIPTION').'" data-content="'.$nextFile['fil_description'].'"></i>';
        }

        // create array with all column values
        $columnValues = array(
            2, // Type file
            '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/get_file.php', array('file_id' => $nextFile['fil_id'])). '">
                <i class="fas fa-fw ' . $iconFile . '" data-toggle="tooltip" title="'.$gL10n->get('SYS_FILE').'"></i></a>',
            '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/get_file.php', array('file_id' => $nextFile['fil_id'])). '">'. $nextFile['fil_name']. '</a>'.$fileDescription,
            $timestamp->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
            round($nextFile['fil_size'] / 1024). ' kB&nbsp;',
            ($nextFile['fil_counter'] !== '') ? $nextFile['fil_counter'] : 0
        );

        if ($currentFolder->hasUploadRight())
        {
            // Links for change and delete
            $additionalFileFunctions = '';

            if($nextFile['fil_exists'] === true)
            {
                $additionalFileFunctions = '
                <a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/rename.php', array('folder_id' => $getFolderId, 'file_id' => $nextFile['fil_id'])). '">
                    <i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
            }
            elseif($gCurrentUser->editDownloadRight())
            {
                $additionalFileFunctions = '
                <i class="fas fa-exclamation-triangle" data-toggle="popover" data-trigger="hover" data-placement="left"
                    title="'.$gL10n->get('SYS_WARNING').'" data-content="'.$gL10n->get('DOW_FILE_NOT_EXISTS').'"></i>';
            }
            $columnValues[] = $additionalFileFunctions.'
            <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'fil', 'element_id' => 'row_file_'.$nextFile['fil_id'],
                'name' => $nextFile['fil_name'], 'database_id' => $nextFile['fil_id'], 'database_id_2' => $getFolderId)).'">
                <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
        }
        $downloadOverview->addRowByArray($columnValues, 'row_file_'.$nextFile['fil_id']);
    }
}

// Create download table
$downloadOverview->setDatatablesColumnsHide(array(1));
$downloadOverview->setDatatablesOrderColumns(array(1, 3));
$htmlDownloadOverview = $downloadOverview->show();

/**************************************************************************/
// Add Admin table to html page
/**************************************************************************/

// If user is download Admin show further files contained in this folder.
if ($gCurrentUser->editDownloadRight())
{
    // Check whether additional content was found in the folder
    if (isset($folderContent['additionalFolders']) || isset($folderContent['additionalFiles']))
    {
        $htmlAdminTableHeadline = '<h2>'.$gL10n->get('DOW_UNMANAGED_FILES').HtmlForm::getHelpTextIcon('DOW_ADDITIONAL_FILES').'</h2>';

        // Create table object
        $adminTable = new HtmlTable('tbl_downloads', $page, true);
        $adminTable->setColumnAlignByArray(array('left', 'left', 'left', 'right'));

        // create array with all column heading values
        $columnHeading = array(
            '<i class="fas fa-fw fa-folder-open" data-toggle="tooltip" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'"></i>',
            $gL10n->get('SYS_NAME'),
            $gL10n->get('SYS_SIZE'),
            '&nbsp;'
        );
        $adminTable->addRowHeadingByArray($columnHeading);

        // Get folders
        if (isset($folderContent['additionalFolders']))
        {
            foreach ($folderContent['additionalFolders'] as $nextFolder)
            {
                $columnValues = array(
                    '<i class="fas fa-fw fa-folder" data-toggle="tooltip" title="'.$gL10n->get('SYS_FOLDER').'"></i>',
                    $nextFolder['fol_name'],
                    '',
                    '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/download_function.php', array('mode' => '6', 'folder_id' => $getFolderId, 'name' => $nextFolder['fol_name'])). '">
                        <i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('DOW_ADD_TO_DATABASE').'"></i>
                    </a>'
                );
                $adminTable->addRowByArray($columnValues);
            }
        }

        // Get files
        if (isset($folderContent['additionalFiles']))
        {
            foreach ($folderContent['additionalFiles'] as $nextFile)
            {
                // Get filetyp
                $fileExtension = strtolower(pathinfo($nextFile['fil_name'], PATHINFO_EXTENSION));

                // Choose icon for the file
                $iconFile = 'fa-file';
                if(array_key_exists($fileExtension, $iconFileExtension))
                {
                    $iconFile = $iconFileExtension[$fileExtension];
                }

                $columnValues = array(
                    '<i class="fas fa-fw ' . $iconFile . '" data-toggle="tooltip" title="'.$gL10n->get('SYS_FILE').'"></i>',
                    $nextFile['fil_name'],
                    round($nextFile['fil_size'] / 1024). ' kB&nbsp;',
                    '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/download_function.php', array('mode' => '6', 'folder_id' => $getFolderId, 'name' => $nextFile['fil_name'])). '">
                        <i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('DOW_ADD_TO_DATABASE').'"></i>
                    </a>'
                );
                $adminTable->addRowByArray($columnValues);
            }
        }
        $htmlAdminTable = $adminTable->show();
    }
}

// Output module html to client

$page->addHtml($navigationBar);

$page->addHtml($htmlDownloadOverview);

// if user has admin download rights, then show admin table for undefined files in folders
if(isset($htmlAdminTable))
{
    $page->addHtml($htmlAdminTableHeadline);
    $page->addHtml($htmlAdminTable);
}

// show html of complete page
$page->show();
