<?php
/**
 ***********************************************************************************************
 * Create and edit announcements
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * ann_uuid  : UUID of the announcement that should be edited
 * copy = true : The announcement of the ann_id will be copied and the base for this new announcement
 ***********************************************************************************************
 */

use Admidio\Announcements\Entity\Announcement;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\Form;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('announcements_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Initialize and check the parameters
    $getAnnUuid = admFuncVariableIsValid($_GET, 'ann_uuid', 'uuid');
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'bool');

    // set headline of the script
    if ($getCopy) {
        $headline = $gL10n->get('SYS_COPY_ENTRY');
    } elseif ($getAnnUuid !== '') {
        $headline = $gL10n->get('SYS_EDIT_ENTRY');
    } else {
        $headline = $gL10n->get('SYS_CREATE_ENTRY');
    }

    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // Create announcements object
    $announcement = new Announcement($gDb);

    if ($getAnnUuid !== '') {
        $announcement->readDataByUuid($getAnnUuid);

        if ($getCopy === true) {
            $getAnnUuid = '';
        }

        // check if the current user could edit this announcement
        if (!$announcement->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('ANN')) === 0) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    // create html page object
    $page = new HtmlPage('admidio-announcements-edit', $gL10n->get('SYS_ANNOUNCEMENTS') . ' - ' . $headline);

    if ($gSettingsManager->getBool('profile_log_edit_fields') && !empty($getAnnUuid)) { // TODO_RK: More fine-grained logging settings
        // show link to view change history
        $page->addPageFunctionsMenuItem(
            'menu_item_announcement_change_history',
            $gL10n->get('SYS_CHANGE_HISTORY'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/changelog.php', array('table' => 'announcements', 'uuid' => $getAnnUuid)),
            'bi-clock-history'
        );
    }

    // show form
    $form = new Form(
        'adm_announcements_edit_form',
        'modules/announcements.edit.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_function.php', array('ann_uuid' => $getAnnUuid, 'mode' => 'edit')),
        $page
    );
    $form->addInput(
        'ann_headline',
        $gL10n->get('SYS_TITLE'),
        $announcement->getValue('ann_headline'),
        array('maxLength' => 100, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addSelectBoxForCategories(
        'ann_cat_id',
        $gL10n->get('SYS_CATEGORY'),
        $gDb,
        'ANN',
        Form::SELECT_BOX_MODUS_EDIT,
        array('property' => Form::FIELD_REQUIRED, 'defaultValue' => $announcement->getValue('cat_uuid'))
    );
    $form->addEditor(
        'ann_description',
        $gL10n->get('SYS_TEXT'),
        $announcement->getValue('ann_description'),
        array('property' => Form::FIELD_REQUIRED)
    );
    $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

    $page->assignSmartyVariable('nameUserCreated', $announcement->getNameOfCreatingUser());
    $page->assignSmartyVariable('timestampUserCreated', $announcement->getValue('ann_timestamp_create'));
    $page->assignSmartyVariable('nameLastUserEdited', $announcement->getNameOfLastEditingUser());
    $page->assignSmartyVariable('timestampLastUserEdited', $announcement->getValue('ann_timestamp_change'));
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
