<?php
/**
 ***********************************************************************************************
 * Organization preferences
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode     : html           - (default) Show page with all preferences panels
 *            html_form      - Returns the html of the requested form
 *            save           - Save organization preferences
 *            htaccess       - set directory protection, write htaccess
 *            test_email     - send test email
 *            backup         - create backup of Admidio database
 *            update_check   - Check for a new version of Admidio
 * panel    : The name of the preferences panel that should be shown or saved.
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\DatabaseDump;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\UI\Presenter\PreferencesPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string',
        array(
            'defaultValue' => 'html',
            'validValues' => array('html', 'html_form', 'save', 'htaccess', 'test_email', 'backup', 'update_check')
        ));
    $getPanel = admFuncVariableIsValid($_GET, 'panel', 'string');

    // only administrators are allowed to view, edit organization preferences or create new organizations
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'html':
            // create html page object
            $page = new PreferencesPresenter($getPanel);

            if ($getPanel === '') {
                $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-gear-fill');
            } else {
                $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            }

            $page->show();
            break;
        case 'save':
            $preferences = new PreferencesService();
            $preferences->save($getPanel, $_POST);

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_SAVE_DATA'), 'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('panel' => strtolower($getPanel)))));
            break;

        // Returns the html of the requested form
        case 'html_form':
            $preferencesUI = new PreferencesPresenter('adm_preferences_form');
            $methodName = 'create' . str_replace('_', '', ucwords($getPanel, '_')) . 'Form';
            echo $preferencesUI->{$methodName}();
            break;

        // set directory protection, write htaccess
        case 'htaccess':
            $preferences = new PreferencesService();
            if ($preferences->setHtaccessProtection()) {
                echo $gL10n->get('SYS_ON');
            } else {
                echo $gL10n->get('SYS_OFF');
            }
            break;

        // send test email
        case 'test_email':
            $debugOutput = '';
            $preferences = new PreferencesService();
            $sendResult = $preferences->sendTestEmail();

            if (isset($GLOBALS['phpmailer_output_debug'])) {
                $debugOutput .= '<br /><br /><h3>' . $gL10n->get('SYS_DEBUG_OUTPUT') . '</h3>' . $GLOBALS['phpmailer_output_debug'];
            }

            // message if send/save is OK
            if ($sendResult === true) { // don't remove check === true. ($sendResult) won't work
                $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('show_option' => 'email_dispatch')));
                $gMessage->show($gL10n->get('SYS_EMAIL_SEND') . $debugOutput);
                // => EXIT
            } else {
                $gMessage->show($gL10n->get('SYS_EMAIL_NOT_SEND', array($gL10n->get('SYS_RECIPIENT'), $sendResult)) . $debugOutput);
                // => EXIT
            }
            break;

        // create backup of Admidio database
        case 'backup':
            // function not available for other databases except MySQL
            if (DB_ENGINE !== Database::PDO_ENGINE_MYSQL) {
                throw new Exception('SYS_MODULE_DISABLED');
            }

            $dump = new DatabaseDump($gDb);
            $dump->create('admidio_dump_' . $g_adm_db . '.sql.gzip');
            $dump->export();
            $dump->deleteDumpFile();
            break;

        case 'update_check':
            $preferences = new PreferencesService();
            echo $preferences->showUpdateInfo();
            break;
    }
} catch (Throwable $e) {
    handleException($e, $getMode == 'save');
}
