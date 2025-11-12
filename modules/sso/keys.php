<?php
/**
 ***********************************************************************************************
 * Show and manage all SSO clients (SAML 2.0 and OIDC)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Service\KeyService;
use Admidio\UI\Presenter\SSOKeyPresenter;

require_once(__DIR__ . '/../../system/common.php');
$validModes = array('list', 'edit', 'save', 'delete', 'import', 'export', 'export_password', 'certificate', 'regenerate');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => $validModes));

try {

    // Only administrators are allowed to manage SSO keys (both SAML 2.0 and OIDC)
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Initialize and check the parameters
    $getKeyUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');

    switch ($getMode) {
        case 'list':
            // create html page object
            $page = new SSOKeyPresenter();
            $page->createList();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-key');
            $page->setContentFullWidth();
            $page->show();
            break;

        case 'edit':
            // create html page object
            $page = new SSOKeyPresenter($getKeyUUID);
            $page->createEditForm();
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'save':
            $keyService = new KeyService($gDb);
            $keyService->save($getKeyUUID);

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            // delete cryptographic key

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $key = new Key($gDb);
            $key->readDataByUuid($getKeyUUID);
            $keyId = $key->getValue('key_id');
            // Check if key is set as this IdP's signing or encryption key
            if ($gSettingsManager->get('sso_saml_signing_key') == $keyId ||
                $gSettingsManager->get('sso_saml_encryption_key') == $keyId) {
                    echo json_encode(array('status' => 'error',
                            'message' => $gL10n->get('SYS_SSO_KEY_IN_USE')));
            } else {
                $key->delete();
                echo json_encode(array('status' => 'success'));
            }
            break;

        case 'import':
            // TODO_RK

            break;

        case 'export_password':
            $page = new SSOKeyPresenter($getKeyUUID);
            $page->createExportPasswordForm();
            // $page->show();
            break;

        case 'export':
            // SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
            $keyService = new KeyService($gDb);
            $password = admFuncVariableIsValid($_POST, 'key_password', 'string');
            $keyService->exportToPkcs12($getKeyUUID, $password);
            break;

        case 'certificate':
            $keyService = new KeyService($gDb);
            $keyService->exportCertificate($getKeyUUID);
            break;
    }
} catch (Throwable $e) {
    handleException($e, in_array($getMode, array('save', 'delete', 'export')));
}
