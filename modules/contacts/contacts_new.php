<?php
/**
 ***********************************************************************************************
 * Enter firstname and surname and checks if member already exists
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // only legitimate users are allowed to call the user management
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $form = new FormPresenter(
        'adm_contacts_new_form',
        'modules/contacts.new.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_assign.php',
        null,
        array('showRequiredFields' => false)
    );
    $form->addInput(
        'lastname',
        $gL10n->get('SYS_LASTNAME'),
        '',
        array('maxLength' => 100, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addInput(
        'firstname',
        $gL10n->get('SYS_FIRSTNAME'),
        '',
        array('maxLength' => 100, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addSubmitButton(
        'btn_add',
        $gL10n->get('SYS_CREATE_CONTACT'),
        array('icon' => 'bi-plus-circle-fill')
    );

    $smarty = PagePresenter::createSmartyObject();
    $form->addToSmarty($smarty);
    $gCurrentSession->addFormObject($form);
    echo $smarty->fetch('modules/contacts.new.tpl');
} catch (Throwable $e) {
    handleException($e);
}
