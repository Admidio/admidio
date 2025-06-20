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
use Admidio\SSO\Entity\SSOClient;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Service\SAMLService;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Service\OIDCService;
use Admidio\UI\Presenter\SSOClientPresenter;

require_once(__DIR__ . '/../../system/common.php');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => array('list', 'edit_saml', 'save_saml', 'delete_saml', 'edit_oidc', 'save_oidc', 'delete_oidc', 'sequence', 'enable')));

try {
    
    // Only administrators are allowed to manage SSO clients (both SAML 2.0 and OIDC)
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Initialize and check the parameters
    $getClientUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');


    switch ($getMode) {
        case 'list':
            // create html page object
            $page = new SSOClientPresenter();
            $page->createList();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-key');
            $page->setContentFullWidth();
            $page->show();
            break;

        case 'edit_saml':
            // create html page object
            $page = new SSOClientPresenter($getClientUUID);
            $page->createSAMLEditForm();
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'save_saml':
            $samlService = new SAMLService($gDb, $gCurrentUser);
            $samlService->save($getClientUUID);

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete_saml':
            // delete menu entry

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $client = new SAMLClient($gDb);
            $client->readDataByUuid($getClientUUID);
            $client->delete();
            echo json_encode(array('status' => 'success'));
            break;

        case 'edit_oidc':
            // create html page object
            $page = new SSOClientPresenter($getClientUUID);
            $page->createOIDCEditForm();
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'save_oidc':
            $oidcService = new OIDCService($gDb, $gCurrentUser);
            $oidcService->save($getClientUUID);

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete_oidc':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $oidcService = new OIDCService($gDb, $gCurrentUser);
            $client = $oidcService->getClientFromUUID($getClientUUID);
            $client->delete();
            echo json_encode(array('status' => 'success'));
            break;
        case 'enable':
            $enabled = admFuncVariableIsValid($_GET, 'enabled', 'boolean');
            $client = new SAMLClient($gDb);
            $client->readDataByUuid($getClientUUID);
            if ($client->isNewRecord()) {
                // Not a SAML record, so try OIDC:
                $client = new OIDCClient($gDb);
                $client->readDataByUuid($getClientUUID);
            }
            if ($client->isNewRecord()) {
                throw new Exception('SYS_SSO_INVALID_CLIENT');
            }
            $client->enable($enabled);
            $client->save();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Throwable $e) {
    if (in_array($getMode, array('save', 'delete', 'save_saml', 'delete_saml', 'save_oidc', 'delete_oidc', 'enable'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
