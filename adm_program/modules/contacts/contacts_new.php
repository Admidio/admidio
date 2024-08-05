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
try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // only legitimate users are allowed to call the user management
    if (!$gCurrentUser->editUsers()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    echo '
    <script type="text/javascript">
        $("body").on("shown.bs.modal", ".modal", function() {
            $("#lastname").trigger("focus")
        });

        $("#form_contacts_new").submit(formSubmit);
    </script>

    <div class="modal-header">
        <h3 class="modal-title">' . $gL10n->get('SYS_CREATE_CONTACT') . '</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <p class="lead">' . $gL10n->get('SYS_INPUT_FIRSTNAME_LASTNAME') . '</p>';
        $form = new HtmlForm(
            'form_contacts_new',
            ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_assign.php',
            null,
            array('showRequiredFields' => false)
        );
        $form->addInput(
            'lastname',
            $gL10n->get('SYS_LASTNAME'),
            '',
            array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
        );
        $form->addInput(
            'firstname',
            $gL10n->get('SYS_FIRSTNAME'),
            '',
            array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
        );
        $form->addSubmitButton(
            'btn_add',
            $gL10n->get('SYS_CREATE_CONTACT'),
            array('icon' => 'bi-plus-circle-fill')
        );
        echo $form->show();
    echo '</div>';
} catch (AdmException|Exception $e) {
    $gMessage->show($e->getMessage());
}
