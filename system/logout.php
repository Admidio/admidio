<?php
/**
 ***********************************************************************************************
 * Logout current user and delete cookie
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Hooks\Hooks;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\SSO\Service\SAMLService;

try {
    require_once(__DIR__ . '/common.php');

    $externalSessionId = (string) $gCurrentSession->getValue('ses_external_session_id');

    /*
     * Notify every single sign-on client of this session before the session is gone. The
     * OIDC back-channel clients are contacted right away, the OIDC front-channel clients
     * are loaded in one page of iframes, and the SAML service providers are then visited
     * one after another through the browser. The chain ends at the logout homepage.
     */
    $ssoLogoutService = null;

    if ($externalSessionId !== ''
        && ($gSettingsManager->get('sso_saml_enabled') === '1' || $gSettingsManager->get('sso_oidc_enabled') === '1')
    ) {
        $ssoLogoutService = new SAMLService($gDb, $gCurrentUser);
    }

    $gValidLogin = false;

    // the user is still known here, which is what a listener needs; afterwards the object is cleared
    Hooks::doAction('logout', $gCurrentUser);

    // remove user from session
    $gCurrentSession->logout();

    // if login organization is different to organization of config file then create new session variables
    if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) !== 0 && $g_organization !== '') {
        // read organization of config file with their preferences
        $gCurrentOrganization->readDataByColumns(array('org_shortname' => $g_organization));

        // read new profile field structure for this organization
        $gProfileFields->readProfileFields($gCurrentOrgId);

        // save new organization id to session
        $gCurrentSession->setValue('ses_org_id', $gCurrentOrgId);
        $gCurrentSession->save();

        // read all settings from the new organization
        $gSettingsManager = new SettingsManager($gDb, $gCurrentOrgId);
    }

    // clear data from global objects
    $gCurrentUser->clear();
    $gMenu->initialize();

    // set homepage to logout page
    $gHomepage = ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_logout');

    if ($ssoLogoutService !== null) {
        $response = $ssoLogoutService->startSessionLogout($externalSessionId, $gHomepage);

        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        echo (string) $response->getBody();
        exit;
    }

    // message logout successful and go to homepage
    $gMessage->setForwardUrl($gHomepage, 2000);
    $gMessage->show($gL10n->get('SYS_LOGOUT_SUCCESSFUL'));
    // => EXIT
} catch (Throwable $e) {
    handleException($e);
}
