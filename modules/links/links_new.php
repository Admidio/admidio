<?php
/**
 ***********************************************************************************************
 * Create and edit weblinks
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * link_uuid - UUID of the weblink that should be edited
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Weblinks\Entity\Weblink;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'uuid');

    // check if the module is enabled for use
    if ((int)$gSettingsManager->get('enable_weblinks_module') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // create weblink object
    $link = new Weblink($gDb);

    if ($getLinkUuid !== '') {
        $link->readDataByUuid($getLinkUuid);

        // check if the current user could edit this weblink
        if (!$link->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('LNK')) === 0) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    if ($getLinkUuid !== '') {
        $headline = $gL10n->get('SYS_EDIT_WEBLINK');
    } else {
        $headline = $gL10n->get('SYS_CREATE_WEBLINK');
    }

    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-weblinks-edit', $headline);

    ChangelogService::displayHistoryButton($page, 'weblinks', 'links', !empty($getLinkUuid), array('uuid' => $getLinkUuid));

    // show form
    $form = new FormPresenter(
        'adm_weblinks_edit_form',
        'modules/links.edit.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/links/links_function.php', array('link_uuid' => $getLinkUuid, 'mode' => 'create')),
        $page
    );
    $form->addInput(
        'lnk_name',
        $gL10n->get('SYS_LINK_NAME'),
        $link->getValue('lnk_name'),
        array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addInput(
        'lnk_url',
        $gL10n->get('SYS_LINK_ADDRESS'),
        $link->getValue('lnk_url'),
        array('type' => 'url', 'maxLength' => 2000, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addSelectBoxForCategories(
        'lnk_cat_id',
        $gL10n->get('SYS_CATEGORY'),
        $gDb,
        'LNK',
        FormPresenter::SELECT_BOX_MODUS_EDIT,
        array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $link->getValue('cat_uuid'))
    );
    $form->addEditor(
        'lnk_description',
        $gL10n->get('SYS_DESCRIPTION'),
        $link->getValue('lnk_description')
    );
    $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

    $page->assignSmartyVariable('userCreatedName', $link->getNameOfCreatingUser());
    $page->assignSmartyVariable('userCreatedTimestamp', $link->getValue('lnk_timestamp_create'));
    $page->assignSmartyVariable('lastUserEditedName', $link->getNameOfLastEditingUser());
    $page->assignSmartyVariable('lastUserEditedTimestamp', $link->getValue('lnk_timestamp_change'));
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
