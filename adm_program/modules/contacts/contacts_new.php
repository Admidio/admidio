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
use Admidio\Exception;
use Admidio\UserInterface\Form;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // only legitimate users are allowed to call the user management
    if (!$gCurrentUser->editUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $form = new Form(
        'contacts_new_form',
        'modules/contacts.new.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_assign.php',
        null,
        array('showRequiredFields' => false)
    );
    $form->addInput(
        'lastname',
        $gL10n->get('SYS_LASTNAME'),
        '',
        array('maxLength' => 100, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addInput(
        'firstname',
        $gL10n->get('SYS_FIRSTNAME'),
        '',
        array('maxLength' => 100, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addSubmitButton(
        'btn_add',
        $gL10n->get('SYS_CREATE_CONTACT'),
        array('icon' => 'bi-plus-circle-fill')
    );

    $smarty = HtmlPage::createSmartyObject();
    $form->addToSmarty($smarty);
    $gCurrentSession->addFormObject($form);
    echo $smarty->fetch('modules/contacts.new.tpl');
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
