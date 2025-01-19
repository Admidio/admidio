<?php
/**
 ***********************************************************************************************
 * Change two factor authentication settings of a user
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid        : Uuid of the user whose two factor authentication settings should be changed
 * mode    - html   : Default mode to show a html form to change the two factor authentication settings
 *           setup  : Setup two factor authentication settings in database
 *           reset  : Reset two factor authentication settings in database
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Users\Entity\User;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\QRServerProvider;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    header('Content-type: text/html; charset=utf-8');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('requireValue' => true));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'setup', 'reset')));

    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);
    $userId = $user->getValue('usr_id');

    // only the own 2fa settings could be individual set.
    // Administrators could only delete 2fa settings if a loginname is set
    if (
        $gCurrentUserId !== $userId
        && (!isMember($userId)
            || (!$gCurrentUser->isAdministrator() && $gCurrentUserId !== $userId)
            || ($gCurrentUser->isAdministrator() && $user->getValue('usr_login_name') === '')
        )
    ) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $orgName = $gCurrentOrganization->getValue('org_longname');
    $tfa = new TwoFactorAuth(issuer: $orgName, qrcodeprovider: new QRServerProvider());

    if ($getMode === 'setup') {
        // check form field input and sanitized it from malicious content
        $profilePasswordEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $profilePasswordEditForm->validate($_POST);

        if ($gCurrentUserId !== $userId) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $otpCode = $_POST['otp_code'];
        $secret = $gCurrentUser->getValue('usr_tfa_secret');

        // Handle form input

        if ($tfa->verifyCode($secret, $otpCode)) {
            $user->setSecondFactorSecret($secret);
            $user->save();

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_TFA_SETUP_SUCCESSFUL')));
            exit();
        } else {
            throw new Exception('SYS_TFA_TOTP_CODE_INCORRECT');
        }

    } elseif ($getMode === 'reset') {
        // check form field input and sanitized it from malicious content
        $profilePasswordEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $profilePasswordEditForm->validate($_POST);

        if (!($gCurrentUser->isAdministrator() || $gCurrentUserId !== $userId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // Reset two factor authentication settings
        $user->setSecondFactorSecret(null);
        $user->save();

        echo json_encode(array('status' => 'success', $gL10n->get('SYS_TFA_RESET_SUCCESSFUL')));
        exit();

    } elseif ($getMode === 'html') {
        // Show two factor authentication setup form if user does not have two factor authentication enabled
        if (!$user->getValue('usr_tfa_secret')) {

            // Admins can only set up two factor authentication for themselves
            if ($gCurrentUserId !== $userId) {
                throw new Exception($gL10n->get('SYS_TFA_NOT_SETUP_FOR_USER'));
            }
            $template = 'modules/profile.two-factor-authentication.setup.tpl';
            $form = new FormPresenter(
                'adm_tfa_setup_form',
                $template,
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/two_factor_authentication.php', array('user_uuid' => $getUserUuid, 'mode' => 'setup')),
                options: array('type' => 'vertical')
            );

            // Save secret in current session
            $secret = $tfa->createSecret();
            $gCurrentUser->setValue('usr_tfa_secret', $secret);

            // Prepare setup form
            $qrImageUri = $tfa->getQRCodeImageAsDataUri($orgName, $secret, 200);
            $html = '<img id="qr_code" src="' . $qrImageUri . '" alt="Secret: ' . $secret . '" />';
            $form->addCustomContent('qr_code', $gL10n->get('SYS_TFA_SETUP_SCAN_QR'), $html);
            $form->addInput(
                'otp_code', 
                $gL10n->get('SYS_TFA_TOTP_CODE'), 
                '', 
                array(
                    'type' => 'text', 
                    'required' => true, 
                    'maxLength' => 6,
                    'class' => 'w-50'
                    )
            );
            $form->addSubmitButton(
                'adm_button_save',
                $gL10n->get('SYS_SAVE'),
                array('icon' => 'bi-check-lg')
            );

            // Show two factor authentication reset form if user has two factor authentication enabled
        } else {
            $template = 'modules/profile.two-factor-authentication.reset.tpl';
            $form = new FormPresenter(
                'adm_tfa_reset_form',
                $template,
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/two_factor_authentication.php', array('user_uuid' => $getUserUuid, 'mode' => 'reset'))
            );
            $form->addButton('tfa_reset_button', $gL10n->get('SYS_REMOVE'), array('icon' => 'bi bi-trash', 'class' => 'btn-danger', 'type' => 'submit'));
        }

        $smarty = HtmlPage::createSmartyObject();
        $form->addToSmarty($smarty);
        $gCurrentSession->addFormObject($form);
        echo $smarty->fetch($template);
    }

} catch (Throwable $e) {
    if ($getMode === 'setup') {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->showInModalWindow();
        $gMessage->show($e->getMessage());
    }
}
