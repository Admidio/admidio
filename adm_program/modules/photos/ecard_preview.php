<?php
/**
 ***********************************************************************************************
 * Preview of eCard
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// preview will be called before form is send, so there are now POST parameters available
// then show nothing. Second call is with POST parameters then show preview
use Admidio\Exception;

require_once(__DIR__ . '/../../system/common.php');

try {
    // check if the photo module is enabled and eCard is enabled
    if (!$gSettingsManager->getBool('photo_ecard_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 2) {
        // only logged-in users can access the module
        require(__DIR__ . '/../../system/login_valid.php');
    }

    $gMessage->showInModalWindow();

    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

    if (strlen($_POST['ecard_template']) === 0) {
        throw new Exception('SYS_FIELD_EMPTY', array('SYS_TEMPLATE'));
    }

    // Initialize and check the parameters
    $ecardMessage = '';
    $postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', array('requireValue' => true));
    $postPhotoUuid = admFuncVariableIsValid($_POST, 'photo_uuid', 'uuid', array('requireValue' => true));
    $postPhotoNr = admFuncVariableIsValid($_POST, 'photo_nr', 'int', array('requireValue' => true));
    $nameRecipient = admFuncVariableIsValid($_POST, 'name_recipient', 'string');
    $emailRecipient = admFuncVariableIsValid($_POST, 'email_recipient', 'string');
    $ecardMessage = admFuncVariableIsValid($_POST, 'ecard_message', 'html');

    $imageUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $postPhotoUuid, 'photo_nr' => $postPhotoNr, 'max_width' => 350, 'max_height' => $gSettingsManager->getInt('photo_ecard_scale')));

    $funcClass = new ECard($gL10n);

    // read content of template file
    $ecardDataToParse = $funcClass->getEcardTemplate($postTemplateName);

    if ($ecardDataToParse === null) {
        throw new Exception('SYS_ERROR_PAGE_NOT_FOUND');
    }

    echo '
    <div class="modal-header">
        <h3 class="modal-title">' . $gL10n->get('SYS_PREVIEW') . '</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body" id="preview_content">';

    // show output of parsed template
    echo $funcClass->parseEcardTemplate($imageUrl, $ecardMessage, $ecardDataToParse, $nameRecipient, $emailRecipient);

    echo '</div>';
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
