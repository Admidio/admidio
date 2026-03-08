<?php
/**
 ***********************************************************************************************
 * Organization settings
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode     : html           - (default) Show page with all settings panels
 *            html_form      - Returns the HTML of the requested form
 *            save           - Save organization settings
 *            htaccess       - set directory protection, write htaccess
 *            test_email     - send test email
 *            backup         - create backup of Admidio database
 *            update_check   - Check for a new version of Admidio
 * panel    : The name of the settings panel that should be shown or saved.
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\DatabaseDump;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Settings\Service\SettingsService;
use Admidio\UI\Presenter\SettingsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string',
        array(
            'defaultValue' => 'html',
            'validValues' => array('html', 'html_form', 'save', 'htaccess', 'test_email', 'backup', 'update_check')
        ));
    $getPanel = admFuncVariableIsValid($_GET, 'panel', 'string', array('defaultValue' => 'system_information'));

    // only administrators are allowed to view, edit organization settings or create new organizations
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'html':
            // create HTML page object
            $page = new SettingsPresenter($getPanel);

            if ($getPanel === '') {
                $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-gear-fill');
            } else {
                $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            }

            $page->show();
            break;
        case 'save':
            $settings = new SettingsService();
            $settings->save($getPanel, $_POST);

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_SAVE_DATA'), 'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/settings.php', array('panel' => strtolower($getPanel)))));
            break;

        // Returns the HTML of the requested form
        case 'html_form':
            $settingsUI = new SettingsPresenter('adm_settings_form');
            $methodName = 'create' . str_replace('_', '', ucwords($getPanel, '_')) . 'Form';
            echo $settingsUI->{$methodName}();
            break;

        // set directory protection, write htaccess
        case 'htaccess':
            $settings = new SettingsService();
            if ($settings->setHtaccessProtection()) {
                echo $gL10n->get('SYS_ON');
            } else {
                echo $gL10n->get('SYS_OFF');
            }
            break;

        // send test email
        case 'test_email':
            $debugOutput = '';
            $settings = new SettingsService();
            $sendResult = $settings->sendTestEmail();

            if (isset($GLOBALS['phpmailer_output_debug'])) {
                $debugOutput .= '<br /><br /><h3>' . $gL10n->get('SYS_DEBUG_OUTPUT') . '</h3>' . $GLOBALS['phpmailer_output_debug'];
            }

            // message if send/save is OK
            if ($sendResult === true) { // don't remove check === true. ($sendResult) won't work
                $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/settings.php', array('show_option' => 'email_dispatch')));
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
            if (DB_TYPE === Database::PDO_ENGINE_PGSQL) {
                throw new Exception('SYS_MODULE_DISABLED');
            }

            $dump = new DatabaseDump($gDb);
            $dump->create('admidio_dump_' . $g_adm_db . '.sql.gz');
            $dump->export();
            $dump->deleteDumpFile();
            break;

        case 'update_check':
            $settings = new SettingsService();
            echo $settings->showUpdateInfo();
            break;
    }
} catch (Throwable $e) {
    handleException($e, $getMode == 'save');
}
