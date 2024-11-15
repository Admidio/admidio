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

use Admidio\Exception;
use Admidio\UserInterface\Preferences;

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
            $headline = $gL10n->get('SYS_SETTINGS');

            if ($getPanel === '') {
                $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-gear-fill');
            }
            // create html page object
            $page = new Preferences('adm_preferences', $headline);

            if ($getPanel !== '') {
                $page->setPanelToShow($getPanel);
                // add current url to navigation stack
                $gNavigation->addUrl(CURRENT_URL, $headline);
            }

            $page->show();
            break;
        case 'save':
            $preferences = new Admidio\Modules\Preferences();
            $preferences->save($getPanel, $_POST);

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_SAVE_DATA')));
            break;

        // Returns the html of the requested form
        case 'html_form':
            $preferencesUI = new Preferences('adm_preferences_form');
            $methodName = 'create' . str_replace('_', '', ucwords($getPanel, '_')) . 'Form';
            echo $preferencesUI->{$methodName}();
            break;

        // set directory protection, write htaccess
        case 'htaccess':
            $preferences = new Admidio\Modules\Preferences();
            if ($preferences->setHtaccessProtection()) {
                echo $gL10n->get('SYS_ON');
            } else {
                echo $gL10n->get('SYS_OFF');
            }
            break;

        // send test email
        case 'test_email':
            $debugOutput = '';
            $preferences = new Admidio\Modules\Preferences();
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
            $preferences = new Admidio\Modules\Preferences();
            echo $preferences->showUpdateInfo();
            break;
    }
} catch (Throwable $exception) {
    if (in_array($getMode, array('save', 'new_org_create'))) {
        echo json_encode(array('status' => 'error', 'message' => $exception->getMessage()));
    } elseif ($getMode === 'html_form') {
        echo $exception->getMessage();
    } else {
        $gMessage->show($exception->getMessage());
    }
}
