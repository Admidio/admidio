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
use Psr\Http\Message\ResponseInterface;

try {
    require_once(__DIR__ . '/common.php');

    // A listener may take over the logout instead of ending the session: a plugin that lets an
    // administrator act as another user returns to the administrator here. It is asked before
    // anything else happens, so that the single sign-on clients of the session stay logged in.
    $logoutTarget = Hooks::resolve('logout_target', null, $gCurrentUser);
    if (is_string($logoutTarget) && $logoutTarget !== '') {
        admRedirect($logoutTarget);
        // => EXIT
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

    // set homepage to logout page; a listener may send the visitor somewhere else after the logout
    $gHomepage = Hooks::applyTypedFilters('logout_homepage', ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_logout'));

    // A listener may finish the logout with a response of its own, e.g. the single sign-on module
    // with the logout chain of the clients of the session, which ends at the logout homepage
    $response = Hooks::resolve('logout_response', null, $gHomepage);
    if ($response instanceof ResponseInterface) {
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
