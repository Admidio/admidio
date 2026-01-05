<?php
/**
 ***********************************************************************************************
 * Login page
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode: dialog - (Default) show the login dialog
 *       check - Check the data to the login dialog
 * organization_short_name: short name of the organization that should be preselected at the select box
 *
 * **********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/common.php');

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'dialog', 'validValues' => array('dialog', 'check')));
    $getOrganizationShortName = admFuncVariableIsValid($_GET, 'organization_short_name', 'string');

    if ($getMode === 'dialog') {
        $headline = $gL10n->get('SYS_LOGIN');

        // remember url (will be removed in login_check)
        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create an HTML page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-login', $headline);
        $loginModule = new ModuleLogin();
        $loginModule->addHtmlLogin($page, $getOrganizationShortName);
        $page->show();
    } elseif ($getMode === 'check') {
        // check the data of the login dialog
        $loginModule = new ModuleLogin();
        $loginModule->checkLogin();

        // check if browser can set cookies and throw error if not
        if (!array_key_exists(COOKIE_PREFIX . '_SESSION_ID', $_COOKIE)) {
            throw new Exception('SYS_COOKIE_NOT_SET', array(DOMAIN));
        }

        // remove login page from navigation stack
        try {
            if (str_ends_with($gNavigation->getUrl(), '/login.php')) {
                $gNavigation->deleteLastUrl();
            }
        } catch (Exception $e) {
            $gNavigation->clear();
        }

        // If no forward url has been set, then refer to the start page after login
        if (array_key_exists('login_forward_url', $_SESSION)) {
            $forwardUrl = $_SESSION['login_forward_url'];
        } else {
            $forwardUrl = ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_login');
        }

        unset($_SESSION['login_forward_url']);

        $payload = array(
            'status' => 'success',
            'url' => $forwardUrl
        );
        if (array_key_exists('login_forward_url_post', $_SESSION)) {
            $payload['url_post'] = $_SESSION['login_forward_url_post'];
            unset($_SESSION['login_forward_url_post']);
        }

        echo json_encode($payload);
        exit();
    }
} catch (Throwable $e) {
    handleException($e, $getMode == 'check');
}
