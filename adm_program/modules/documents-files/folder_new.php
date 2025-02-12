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

use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid', array('requireValue' => true));

    $headline = $gL10n->get('SYS_CREATE_FOLDER');

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    $folder = new Folder($gDb);
    $folder->readDataByUuid($getFolderUuid);

    // erst prüfen, ob der User auch die entsprechenden Rechte hat
    if (!$folder->hasUploadRight()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // get recordset of current folder from database
    $folder->getFolderForDownload($getFolderUuid);

    $parentFolderName = $folder->getValue('fol_name');

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-documents-files-new-folder', $headline);
    $page->assignSmartyVariable('parentFolderName', $parentFolderName);

    // show form
    $form = new FormPresenter(
        'adm_new_folder_form',
        'modules/documents-files.folder.new.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/documents_files_function.php', array('mode' => 'create_folder', 'folder_uuid' => $getFolderUuid)),
        $page
    );
    $form->addInput(
        'new_folder',
        $gL10n->get('SYS_NAME'),
        '',
        array('maxLength' => 255, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addMultilineTextInput(
        'new_description',
        $gL10n->get('SYS_DESCRIPTION'),
        '',
        4,
        array('maxLength' => 4000)
    );
    $form->addSubmitButton(
        'btn_create',
        $gL10n->get('SYS_CREATE_FOLDER'),
        array('icon' => 'bi-plus-circle-fill', 'class' => 'offset-sm-3')
    );

    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
