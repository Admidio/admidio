<?php
/**
 ***********************************************************************************************
 * Page to check the two factor authentication (TFA) of a user
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode     : dialog - (Default) show the login dialog
 *            check  - Check the data to the login dialog
 *
 * **********************************************************************************************
 */
use Admidio\Infrastructure\Exception;

try {
    require_once(__DIR__ . '/common.php');

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'dialog', 'validValues' => array('dialog', 'check')));

    if ($getMode === 'dialog') {
        $headline = $gL10n->get('SYS_TFA');

        // remember url (will be removed in login_check)
        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = new HtmlPage('admidio-login-tfa', $headline);
        $loginModule = new ModuleLogin();
        $loginModule->addHtmlTfaCheck($page);
        $page->show();
    } elseif ($getMode === 'check') {
        // check the data of the login dialog
        $loginModule = new ModuleLogin();
        $loginModule->checkTotp();

        // remove login page from navigation stack
        try {
            if (str_ends_with($gNavigation->getUrl(), '/login_tfa.php')) {
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

        echo json_encode(array(
            'status' => 'success',
            'url' => $forwardUrl
        ));
        exit();
    }
} catch (Exception $e) {
    if($getMode === 'check') {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
