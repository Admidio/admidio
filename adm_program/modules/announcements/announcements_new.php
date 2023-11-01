<?php
/**
 ***********************************************************************************************
 * Create and edit announcements
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * ann_uuid  : UUID of the announcement that should be edited
 * headline  : Title of the announcement module. This will be shown in the whole module.
 *             (Default) SYS_ANNOUNCEMENTS
 * copy = true : The announcement of the ann_id will be copied and the base for this new announcement
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_announcements_module') === 0) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize and check the parameters
$getAnnUuid  = admFuncVariableIsValid($_GET, 'ann_uuid', 'string');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_ANNOUNCEMENTS')));
$getCopy     = admFuncVariableIsValid($_GET, 'copy', 'bool');

// set headline of the script
if ($getCopy) {
    $headline = $gL10n->get('SYS_COPY_ENTRY');
} elseif ($getAnnUuid !== '') {
    $headline = $gL10n->get('SYS_EDIT_ENTRY');
} else {
    $headline = $gL10n->get('SYS_CREATE_ENTRY');
}

try {
    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);
} catch (AdmException $e) {
    $e->showHtml();
}

// Create announcements object
$announcement = new TableAnnouncement($gDb);

if ($getAnnUuid !== '') {
    $announcement->readDataByUuid($getAnnUuid);

    if ($getCopy === true) {
        $getAnnUuid = '';
    }

    // check if the current user could edit this announcement
    if (!$announcement->isEditable()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
} else {
    // check if the user has the right to edit at least one category
    if (count($gCurrentUser->getAllEditableCategories('ANN')) === 0) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if (isset($_SESSION['announcements_request'])) {
    // due to incorrect input the user has returned to this form
    // now write the previously entered contents into the object
    $announcementDescription = admFuncVariableIsValid($_SESSION['announcements_request'], 'ann_description', 'html');
    $announcement->setArray(SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['announcements_request'])));
    $announcement->setValue('ann_description', $announcementDescription);
    unset($_SESSION['announcements_request']);
}

// create html page object
$page = new HtmlPage('admidio-announcements-edit', $getHeadline . ' - ' . $headline);

// show form
$form = new HtmlForm('announcements_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_function.php', array('ann_uuid' => $getAnnUuid, 'headline' => $getHeadline, 'mode' => '1')), $page);
$form->addInput(
    'ann_headline',
    $gL10n->get('SYS_TITLE'),
    $announcement->getValue('ann_headline'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addSelectBoxForCategories(
    'ann_cat_id',
    $gL10n->get('SYS_CATEGORY'),
    $gDb,
    'ANN',
    HtmlForm::SELECT_BOX_MODUS_EDIT,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $announcement->getValue('cat_uuid'))
);
$form->addEditor(
    'ann_description',
    $gL10n->get('SYS_TEXT'),
    $announcement->getValue('ann_description'),
    array('property' => HtmlForm::FIELD_REQUIRED, 'height' => '400')
);
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $announcement->getValue('ann_usr_id_create'),
    $announcement->getValue('ann_timestamp_create'),
    (int) $announcement->getValue('ann_usr_id_change'),
    $announcement->getValue('ann_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
