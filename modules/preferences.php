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
 *            html_form      - Returns the HTML of the requested form
 *            save           - Save organization preferences
 *            htaccess       - set directory protection, write htaccess
 *            test_email     - send test email
 *            backup         - back up the database into adm_my_files/backups/
 *            backup_download - send a backup of adm_my_files/backups/
 *            backup_delete  - remove a backup of adm_my_files/backups/
 *            update_check   - Check for a new version of Admidio
 * panel    : The name of the preferences panel that should be shown or saved.
 * name     : The name of the backup to send or remove.
 ***********************************************************************************************
 */

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Backups;
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
            'validValues' => array('html', 'html_form', 'save', 'htaccess', 'test_email', 'backup', 'backup_download', 'backup_delete',
                'update_check', 'changelog_purge')
        ));
    $getPanel = admFuncVariableIsValid($_GET, 'panel', 'string', array('defaultValue' => 'system_information'));
    $getName = admFuncVariableIsValid($_GET, 'name', 'string');

    // only administrators are allowed to view, edit organization preferences or create new organizations
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'html':
            // create HTML page object
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

        // Returns the HTML of the requested form
        case 'html_form':
            $preferencesUI = new PreferencesPresenter('adm_preferences_form');
            echo $preferencesUI->createPanel($getPanel);
            break;

        // delete the entries of the change history that are older than the retention period
        case 'changelog_purge':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $deletedEntries = ChangelogService::purgeOldEntries();
            echo $gL10n->get('SYS_CHANGELOG_PURGE_RESULT', array($deletedEntries));
            break;

        // set directory protection, write htaccess
        case 'htaccess':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $preferences = new PreferencesService();
            if ($preferences->setHtaccessProtection()) {
                echo $gL10n->get('SYS_ON');
            } else {
                echo $gL10n->get('SYS_OFF');
            }
            break;

        // send test email
        case 'test_email':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $debugOutput = '';
            $preferences = new PreferencesService();
            $sendResult = $preferences->sendTestEmail();

            if (isset($GLOBALS['phpmailer_output_debug'])) {
                $debugOutput .= '<br /><br /><h3>' . $gL10n->get('SYS_DEBUG_OUTPUT') . '</h3>' . $GLOBALS['phpmailer_output_debug'];
            }

            // message if send/save is OK
            if ($sendResult === true) { // don't remove check === true. ($sendResult) won't work
                $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('panel' => 'email_dispatch')));
                $gMessage->show($gL10n->get('SYS_EMAIL_SEND') . $debugOutput);
                // => EXIT
            } else {
                $gMessage->show($gL10n->get('SYS_EMAIL_NOT_SEND', array($gL10n->get('SYS_RECIPIENT'), $sendResult)) . $debugOutput);
                // => EXIT
            }
            break;

        // back up the database into adm_my_files/backups/, then show the list of the backups
        case 'backup':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            Backups::createDatabaseBackup();
            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('panel' => 'update_backup')));
            break;

        // a backup contains every data of the installation: only an administrator gets it, and only from the list
        case 'backup_download':
            $file = Backups::getFile($getName);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $getName . '"');
            header('Content-Length: ' . filesize($file));
            header('Cache-Control: private, no-store');
            readfile($file);
            exit();

        case 'backup_delete':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            Backups::delete($getName);
            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('panel' => 'update_backup')));
            break;

        case 'update_check':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $preferences = new PreferencesService();
            echo $preferences->showUpdateInfo();
            break;
    }
} catch (Throwable $e) {
    handleException($e, in_array($getMode, array('save', 'update_check')));
}
