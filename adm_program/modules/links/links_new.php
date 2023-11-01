<?php
/**
 ***********************************************************************************************
 * Create and edit weblinks
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * link_uuid - UUID of the weblink that should be edited
 * headline  - Title of the weblink module. This will be shown in the whole module.
 *             (Default) SYS_WEBLINKS
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'string');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_WEBLINKS')));

// check if the module is enabled for use
if ((int) $gSettingsManager->get('enable_weblinks_module') === 0) {
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create weblink object
$link = new TableWeblink($gDb);

if ($getLinkUuid !== '') {
    $link->readDataByUuid($getLinkUuid);

    // check if the current user could edit this weblink
    if (!$link->isEditable()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
} else {
    // check if the user has the right to edit at least one category
    if (count($gCurrentUser->getAllEditableCategories('LNK')) === 0) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if (isset($_SESSION['links_request'])) {
    // due to incorrect input the user has returned to this form
    // now write the previously entered contents into the object
    $linkDescription = admFuncVariableIsValid($_SESSION['links_request'], 'lnk_description', 'html');
    $link->setArray(SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['links_request'])));
    $link->setValue('lnk_description', $linkDescription);
    unset($_SESSION['links_request']);
}

if ($getLinkUuid !== '') {
    $headline = $gL10n->get('SYS_EDIT_VAR', array($getHeadline));
} else {
    $headline = $gL10n->get('SYS_CREATE_VAR', array($getHeadline));
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage('admidio-weblinks-edit', $headline);

// show form
$form = new HtmlForm('weblinks_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/links/links_function.php', array('link_uuid' => $getLinkUuid, 'headline' => $getHeadline, 'mode' => 1)), $page);
$form->addInput(
    'lnk_name',
    $gL10n->get('SYS_LINK_NAME'),
    $link->getValue('lnk_name'),
    array('maxLength' => 250, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'lnk_url',
    $gL10n->get('SYS_LINK_ADDRESS'),
    $link->getValue('lnk_url'),
    array('maxLength' => 2000, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addSelectBoxForCategories(
    'lnk_cat_id',
    $gL10n->get('SYS_CATEGORY'),
    $gDb,
    'LNK',
    HtmlForm::SELECT_BOX_MODUS_EDIT,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $link->getValue('cat_uuid'))
);
$form->addEditor(
    'lnk_description',
    $gL10n->get('SYS_DESCRIPTION'),
    $link->getValue('lnk_description'),
    array('height' => '150px')
);
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));

$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $link->getValue('lnk_usr_id_create'),
    $link->getValue('lnk_timestamp_create'),
    (int) $link->getValue('lnk_usr_id_change'),
    $link->getValue('lnk_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
