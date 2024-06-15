<?php
/**
 ***********************************************************************************************
 * Create new folder
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_uuid : Folder UUID of the parent folder
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid', array('requireValue' => true));

    $headline = $gL10n->get('SYS_CREATE_FOLDER');

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        throw new AdmException('SYS_MODULE_DISABLED');
    }

    $folder = new TableFolder($gDb);
    $folder->readDataByUuid($getFolderUuid);

    // erst prüfen, ob der User auch die entsprechenden Rechte hat
    if (!$folder->hasUploadRight()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    if (isset($_SESSION['documents_files_request'])) {
        $formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['documents_files_request']));
        unset($_SESSION['documents_files_request']);
    } else {
        $formValues['new_folder'] = '';
        $formValues['new_description'] = '';
    }

    // get recordset of current folder from database
    $folder->getFolderForDownload($getFolderUuid);

    $parentFolderName = $folder->getValue('fol_name');

    // create html page object
    $page = new HtmlPage('admidio-documents-files-new-folder', $headline);

    $page->addHtml('<p class="lead">' . $gL10n->get('SYS_CREATE_FOLDER_DESC', array($parentFolderName)) . '</p>');

    // show form
    $form = new HtmlForm('new_folder_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/documents_files_function.php', array('mode' => 'create_folder', 'folder_uuid' => $getFolderUuid)), $page);
    $form->addInput(
        'new_folder',
        $gL10n->get('SYS_NAME'),
        $formValues['new_folder'],
        array('maxLength' => 255, 'property' => HtmlForm::FIELD_REQUIRED)
    );
    $form->addMultilineTextInput(
        'new_description',
        $gL10n->get('SYS_DESCRIPTION'),
        $formValues['new_description'],
        4,
        array('maxLength' => 4000)
    );
    $form->addSubmitButton(
        'btn_create',
        $gL10n->get('SYS_CREATE_FOLDER'),
        array('icon' => 'bi-plus-circle-fill')
    );

    // add form to html page and show page
    $page->addHtml($form->show());
    $page->show();
} catch (Exception|AdmException|\Smarty\Exception $e) {
    $gMessage->show($e->getMessage());
}
