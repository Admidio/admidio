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
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Photos\ValueObject\ECard;

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

    // check form field input and sanitized it from malicious content
    $categoryEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $categoryEditForm->validate($_POST);

    $imageUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php',
        array(
            'photo_uuid' => $formValues['photo_uuid'],
            'photo_nr' => $formValues['photo_nr'],
            'max_width' => 350,
            'max_height' => $gSettingsManager->getInt('photo_ecard_scale')
        )
    );

    $funcClass = new ECard($gL10n);

    // read content of template file
    $ecardDataToParse = $funcClass->getEcardTemplate($formValues['ecard_template']);

    if ($ecardDataToParse === null) {
        throw new Exception('SYS_ERROR_PAGE_NOT_FOUND');
    }

    $smarty = HtmlPage::createSmartyObject();
    $smarty->assign('l10n', $gL10n);
    $smarty->assign('ecardContent', $funcClass->parseEcardTemplate($imageUrl, $formValues['ecard_message'], $ecardDataToParse, '', ''));
    echo $smarty->fetch('modules/photos.ecard.preview.tpl');
} catch (Throwable $e) {
    $gMessage->showInModalWindow();
    $gMessage->show($e->getMessage());
}
