<?php
/**
 ***********************************************************************************************
 * Preview of ecard
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// preview will be called before form is send, so there are now POST parameters available
// then show nothing. Second call is with POST parameters then show preview
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/ecard_function.php');

$gMessage->showThemeBody(false);
$gMessage->showInModalWindow();

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showHtml();
    // => EXIT
}

if (strlen($_POST['ecard_template']) === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TEMPLATE'))));
    // => EXIT
}

// Initialize and check the parameters
$ecardMessage     = '';
$postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', array('requireValue' => true));
$postPhotoUuid    = admFuncVariableIsValid($_POST, 'photo_uuid', 'string', array('requireValue' => true));
$postPhotoNr      = admFuncVariableIsValid($_POST, 'photo_nr', 'int', array('requireValue' => true));
$nameRecipient    = admFuncVariableIsValid($_POST, 'name_recipient', 'string');
$emailRecipient   = admFuncVariableIsValid($_POST, 'email_recipient', 'string');
$ecardMessage     = admFuncVariableIsValid($_POST, 'ecard_message', 'html');

$imageUrl = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('photo_uuid' => $postPhotoUuid, 'photo_nr' => $postPhotoNr, 'max_width' => $gSettingsManager->getInt('ecard_thumbs_scale'), 'max_height' => $gSettingsManager->getInt('ecard_thumbs_scale')));

$funcClass = new FunctionClass($gL10n);

// read content of template file
$ecardDataToParse = $funcClass->getEcardTemplate($postTemplateName);

if ($ecardDataToParse === null) {
    $gMessage->show($gL10n->get('SYS_ERROR_PAGE_NOT_FOUND'));
    // => EXIT
}

echo '
<div class="modal-header">
    <h3 class="modal-title">'.$gL10n->get('SYS_PREVIEW').'</h3>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
</div>
<div class="modal-body" id="preview_content">';

// show output of parsed template
echo $funcClass->parseEcardTemplate($imageUrl, $ecardMessage, $ecardDataToParse, $nameRecipient, $emailRecipient);

echo '</div>';
