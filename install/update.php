<?php

use Admidio\Components\Entity\ComponentUpdate;
use Admidio\Infrastructure\ChangeNotification;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\InstallationUpdate\Service\CoreUpdateHandover;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\Service\Update;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Session\Entity\Session;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\UI\Presenter\InstallationPresenter;

/**
 ***********************************************************************************************
 * Handle update of an Admidio database to a new version
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode = dialog: (Default) Check update status and show dialog with status
 *        update: Perform update
 *        result: Show result of update
 *        core_update: Perform the update for the automatic core updater, which shows its own pages;
 *                     the answer is JSON
 ***********************************************************************************************
 */
const THEME_URL = 'layout';

try {
    // set once the database update has begun, so that a failure is recorded for the core update
    $databaseUpdateStarted = false;
    $isCoreUpdate = false;

    /**
     * Shows an error dialog with an error message to the user.
     * @param string $message Message that should be shown to the user.
     * @param bool $reloadPage If set to **true** than the user could reload the update page.
     * @return void
     */
    function showErrorMessage(string $message, bool $reloadPage = false): void
    {
        global $gL10n, $isCoreUpdate;

        // The core updater shows the message on its own page.
        if ($isCoreUpdate) {
            throw new RuntimeException($message);
        }

        if (!is_object($gL10n)) {
            $gL10n = new Language('en');
        }

        $page = new InstallationPresenter('adm_update_message', $gL10n->get('INS_UPDATE'));
        $page->setUpdateModus();
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $message,
            ($reloadPage) ? $gL10n->get('SYS_RELOAD') : $gL10n->get('SYS_OVERVIEW'),
            ($reloadPage) ? 'bi-arrow-clockwise' : 'bi-house-door-fill',
            ($reloadPage) ? ADMIDIO_URL . FOLDER_INSTALLATION . '/index.php' : ADMIDIO_URL . FOLDER_MODULES . '/overview.php'
        );
    }

    /**
     * The warnings and information about plugins that the update recorded.
     * @param array<string,bool> $flags The values the update stored in the cookie adm_update_plugins_warnings.
     * @return array{warning: array<int,array{id: string, alertWarning: string}>, info: array<int,array{id: string, alertInfo: string}>}
     */
    function getUpdateNotes(array $flags): array
    {
        global $gL10n;

        $notes = array('warning' => array(), 'info' => array());
        if (!empty($flags['warn_old_plugins'])) {
            $notes['warning'][] = array(
                'id' => 'warn_old_plugins',
                'alertWarning' => $gL10n->get('INS_WARNING_OLD_ADM_PLUGINS_COULD_NOT_BE_DELETED', array('adm_plugins', 'adm_plugins'))
            );
        }
        if (!empty($flags['warn_3rd_party_plugins'])) {
            $notes['warning'][] = array(
                'id' => 'warn_3rd_party_plugins',
                'alertWarning' => $gL10n->get('INS_WARNING_3RD_PARTY_PLUGINS_COULD_NOT_BE_MOVED', array('plugins', 'adm_plugins'))
            );
        }
        if (!empty($flags['warn_old_plugins_folder'])) {
            $notes['warning'][] = array(
                'id' => 'warn_old_plugins_folder',
                'alertWarning' => $gL10n->get('INS_WARNING_OLD_ADM_PLUGINS_FOLDER_COULD_NOT_BE_DELETED', array('adm_plugins', 'adm_plugins'))
            );
        }
        if (!empty($flags['info_3rd_party_plugins'])) {
            $notes['info'][] = array(
                'id' => 'info_3rd_party_plugins',
                'alertInfo' => $gL10n->get('INS_INFO_3RD_PARTY_PLUGINS_HAVE_BEEN_MOVED', array('plugins'))
            );
        }

        return $notes;
    }

    $rootPath = dirname(__DIR__);

    // embed a config file
    $g_organization = '';
    $configPath = $rootPath . '/adm_my_files/config.php';

    if (is_file($configPath)) {
        require_once($configPath);
    } elseif (is_file($rootPath . '/config.php')) {
        exit('<div style="color: #cc0000;">Old v1.x or v2.x Config-File detected! Please update first to the latest v3.3 Version!</div>');
    } else {
        // no config file exists -> go to installation
        header('Location: installation.php');
        exit();
    }

    require_once($rootPath . '/system/bootstrap/bootstrap.php');

    // check HTML string for invalid tags and scripts
    $gHtmlPurifierConfig = HTMLPurifier_Config::createDefault();
    $gHtmlPurifierConfig->set('HTML.Doctype', 'HTML 4.01 Transitional');
    $gHtmlPurifierConfig->set('Attr.AllowedFrameTargets', array('_blank', '_top', '_self', '_parent'));
    $gHtmlPurifierConfig->set('Cache.SerializerPath', ADMIDIO_PATH . FOLDER_DATA . '/templates');
    $gHtmlPurifierFilter = new HTMLPurifier($gHtmlPurifierConfig);


    // Initialize and check the parameters

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'dialog', 'validValues' => array('dialog', 'update', 'result', 'core_update')));

    /*
     * An automatic core update owns the maintenance mode from the moment it changes files. It is
     * looked at in every mode, so that no migration starts while it is still copying files. The
     * updater runs the migration itself with the mode core_update and shows the result on its own
     * pages.
     */
    $coreUpdate = CoreUpdateHandover::fromMaintenance();
    $isCoreUpdate = $getMode === 'core_update' && $coreUpdate !== null;

    // connect to database
    $gDb = Database::createDatabaseInstance();

    // The core update gets a separate, clean PHP session. This prevents the new database updater
    // from depending on serialized objects created by the previous source version.
    $updateSessionPrefix = COOKIE_PREFIX;
    if ($isCoreUpdate) {
        $updateSessionPrefix .= '_CORE_UPDATE_' . substr($coreUpdate->getUpdateId(), 0, 12);
    }

    // start PHP session
    try {
        Session::start(
            $updateSessionPrefix,
            0,
            $isCoreUpdate ? rtrim(ADMIDIO_URL_PATH, '/') . '/install/' : ''
        );
    } catch (RuntimeException $exception) {
        // TODO
    }

    // create a session object
    if ($isCoreUpdate) {
        $gCurrentSession = new Session($gDb, $updateSessionPrefix);
        $_SESSION['gCurrentSession'] = $gCurrentSession;
    } elseif (array_key_exists('gCurrentSession', $_SESSION)) {
        $gCurrentSession = $_SESSION['gCurrentSession'];
    } else {
        // create a new session object and store it in PHP session
        $gCurrentSession = new Session($gDb, COOKIE_PREFIX);
        $_SESSION['gCurrentSession'] = $gCurrentSession;
    }

    // now check if a valid installation exists.
    $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
    $pdoStatement = $gDb->queryPrepared($sql, array(), false);

    if (!$pdoStatement || $pdoStatement->rowCount() === 0) {
        // no valid installation exists -> show installation wizard
        admRedirect(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php');
        // => EXIT
    }

    // create an organization object of the current organization
    $gCurrentOrganization = Organization::createDefaultOrganizationObject($gDb, $g_organization);
    $gCurrentOrgId = $gCurrentOrganization->getValue('org_id');


    /* Disable logging changes to the database. This will not be reverted,
     *  i.e., during installation / setup no logs are written. The next user
     * call will use the default value of true and properly log changes...
     */
    Entity::setLoggingEnabled(false);
    Entity::setHooksEnabled(false);

    // get system user id
    $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = \'System\' ';
    $pdoStatement = $gDb->queryPrepared($sql);
    $gCurrentUserId = $pdoStatement->fetchColumn();

    if ($gCurrentOrgId === 0) {
        // Organization was not found
        exit('<div style="color: #cc0000;">Error: The organization of the config.php could not be found in the database!</div>');
    }

    // define global
    $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);

    // read organization specific parameters from adm_preferences
    $gSettingsManager =& $gCurrentOrganization->getSettingsManager();

    // create a language and language data object to handle translations
    if (!$gSettingsManager->has('system_language')) {
        $gSettingsManager->set('system_language', 'en');
    }
    $gL10n = new Language($gSettingsManager->getString('system_language'), true);
    $gChangeNotification = new ChangeNotification();

    // Only the page of the core updater may run its migration: the request carries the updater
    // cookie and the CSRF token of the update.
    if ($getMode === 'core_update' && (!$isCoreUpdate || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !$coreUpdate->isAuthorized($_COOKIE[$coreUpdate->getCookieName()] ?? '')
        || !$coreUpdate->isValidCsrfToken($_POST['csrf_token'] ?? ''))) {
        throw new \Admidio\Infrastructure\Exception('SYS_INVALID_PAGE_VIEW');
    }
    if ($isCoreUpdate) {
        // The updater cookie has proven the administrator, so Update::checkLogin() asks for no login.
        $gLoginForUpdate = false;
    }

    // The files of the core update that owns the maintenance mode are still a mix of two releases.
    if ($coreUpdate !== null && !$coreUpdate->isMigrationAllowed()) {
        throw new \Admidio\Infrastructure\Exception('SYS_CORE_UPDATE_IN_PROGRESS');
    }

    // check if adm_my_files has "write" privileges and check some subfolders of adm_my_files
    Installation::checkFolderPermissions();

    // config.php exists at wrong place
    if (is_file(ADMIDIO_PATH . '/config.php') && is_file(ADMIDIO_PATH . FOLDER_DATA . '/config.php')) {
        // try to delete the config file at the old place otherwise show notice to user
        try {
            FileSystemUtils::deleteFileIfExists(ADMIDIO_PATH . '/config.php');
        } catch (RuntimeException $exception) {
            throw new \Admidio\Infrastructure\Exception('INS_DELETE_CONFIG_FILE', array(ADMIDIO_URL));
        }
    }

    // check database version
    $message = Installation::checkDatabaseVersion($gDb);

    if ($message !== '') {
        showErrorMessage($message);
        // => EXIT
    }

    // read current version of Admidio database
    $installedDbVersion = '';
    $installedDbBetaVersion = '';
    $maxUpdateStep = 0;
    $currentUpdateStep = 0;

    $sql = 'SELECT 1 FROM ' . TBL_COMPONENTS;
    if (!$gDb->queryPrepared($sql, array(), false)) {
        // in Admidio version 2, the database version was stored in preference table
        if ($gSettingsManager->has('db_version')) {
            $installedDbVersion = $gSettingsManager->getString('db_version');
            $installedDbBetaVersion = $gSettingsManager->getInt('db_version_beta');
        }
    } else {
        // read a system component
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));

        if ($componentUpdateHandle->getValue('com_id') > 0) {
            $installedDbVersion = $componentUpdateHandle->getValue('com_version');
            $installedDbBetaVersion = (int)$componentUpdateHandle->getValue('com_beta');
            $currentUpdateStep = (int)$componentUpdateHandle->getValue('com_update_step');
            $maxUpdateStep = $componentUpdateHandle->getMaxUpdateStep();
        }
    }

    // if a beta was installed, then create the version string with a Beta version
    if ($installedDbBetaVersion > 0) {
        $installedDbVersion = $installedDbVersion . ' Beta ' . $installedDbBetaVersion;
    }

    if (version_compare($installedDbVersion, '3.1.0', '<')) {
        showErrorMessage($gL10n->get('SYS_VERSION_TO_OLD', array($installedDbVersion, ADMIDIO_VERSION_TEXT)));
        // => EXIT
    }

    // if a database version is not set, then show notice
    if ($installedDbVersion === '') {
        showErrorMessage($gL10n->get('INS_UPDATE_NOT_POSSIBLE') . '<p>' . $gL10n->get('INS_NO_INSTALLED_VERSION_FOUND', array(ADMIDIO_VERSION_TEXT)) . '</p>');
        // => EXIT
    }

    if ($getMode === 'dialog') {
        $gLogger->info('UPDATE: Show update start-view');

        // if a database version is smaller than source version -> update
        // if a database version is equal to source but beta has a difference -> update
        if (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '<')
            || (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep > $currentUpdateStep)) {
            // create a page with the notice that the installation must be configured on the next pages
            $page = new InstallationPresenter('adm_update', $gL10n->get('INS_UPDATE_VERSION', array(ADMIDIO_VERSION_TEXT)));
            $page->addTemplateFile('update.tpl');
            $page->setUpdateModus();
            $page->assignSmartyVariable('installedDbVersion', $installedDbVersion);

            // create form with login and update button
            $form = new FormPresenter(
                'adm_update_login_form',
                'update.tpl',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php', array('mode' => 'update')),
                $page
            );
            if (!isset($gLoginForUpdate) || $gLoginForUpdate) {
                $form->addInput(
                    'adm_login_name',
                    $gL10n->get('SYS_USERNAME'),
                    '',
                    array('maxLength' => 254, 'property' => FormPresenter::FIELD_REQUIRED, 'class' => 'form-control-small')
                );
                $form->addInput(
                    'adm_password',
                    $gL10n->get('SYS_PASSWORD'),
                    '',
                    array('type' => 'password', 'property' => FormPresenter::FIELD_REQUIRED, 'class' => 'form-control-small')
                );
                $form->addInput(
                    'adm_totp_code',
                    $gL10n->get('SYS_SECURITY_CODE'),
                    '',
                    array('maxLength' => 6, 'class' => 'form-control-small')
                );
            }
            $form->addSubmitButton(
                'adm_next_page',
                $gL10n->get('INS_UPDATE_DATABASE'),
                array('icon' => 'bi-arrow-repeat')
            );
            $page->assignSmartyVariable('settings', $gSettingsManager);
            $form->addToHtmlPage();
            $_SESSION['updateLoginForm'] = $form;
            $page->show();
        } // if versions are equal > no update
        elseif (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep === $currentUpdateStep) {
            // Only the updater finishes a core update, under the lock of its migration: the version
            // is recorded before the last steps of the migration, which may still be running.
            if ($coreUpdate !== null) {
                $page = new InstallationPresenter('adm_update_message', $gL10n->get('INS_UPDATE'));
                $page->setUpdateModus();
                $page->showMessage(
                    'warning',
                    $gL10n->get('SYS_NOTE'),
                    $gL10n->get('SYS_CORE_UPDATE_FINISH_IN_UPDATER'),
                    $gL10n->get('SYS_CORE_UPDATE_OPEN_UPDATER'),
                    'bi-arrow-repeat',
                    ADMIDIO_URL . FOLDER_INSTALLATION . '/core-update/index.php'
                );
                // => EXIT
            }

            $page = new InstallationPresenter('adm_update_message', $gL10n->get('INS_UPDATE'));
            $page->setUpdateModus();
            $page->showMessage(
                'success',
                $gL10n->get('SYS_NOTE'),
                $gL10n->get('SYS_DATABASE_IS_UP_TO_DATE') . '<br />' . $gL10n->get('SYS_DATABASE_DOESNOT_NEED_UPDATED'),
                $gL10n->get('SYS_OVERVIEW'),
                'bi-house-door-fill',
                ADMIDIO_URL . FOLDER_MODULES . '/overview.php'
            );
            // => EXIT
        } // if source version smaller than database -> show error
        else {
            $page = new InstallationPresenter('adm_update_message', $gL10n->get('INS_UPDATE'));
            $page->setUpdateModus();
            $page->showMessage(
                'error',
                $gL10n->get('SYS_NOTE'),
                $gL10n->get(
                    'SYS_FILESYSTEM_VERSION_INVALID',
                    array($installedDbVersion,
                        ADMIDIO_VERSION_TEXT, '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>')
                ),
                $gL10n->get('SYS_OVERVIEW'),
                'bi-house-door-fill',
                ADMIDIO_URL . FOLDER_MODULES . '/overview.php'
            );
            // => EXIT
        }
    } elseif ($getMode === 'update') {
        // check form field input and sanitized it from malicious content
        if (isset($_SESSION['updateLoginForm'])) {
            $_SESSION['updateLoginForm']->validate($_POST);
        } else {
            throw new \Admidio\Infrastructure\Exception('SYS_INVALID_PAGE_VIEW');
        }

        // start the update
        if ($coreUpdate !== null) {
            if (!$coreUpdate->lockDatabaseUpdate()) {
                throw new \Admidio\Infrastructure\Exception('SYS_CORE_UPDATE_DATABASE_RUNNING');
            }
            $coreUpdate->markDatabaseRunning();
        }
        $databaseUpdateStarted = true;

        $update = new Update();
        $update->doAdmidioUpdate($installedDbVersion);
        $databaseUpdateStarted = false;

        /*
         * The migration has succeeded, so the core update is finished here and maintenance mode goes
         * off in this request - whether or not the browser that started it is still waiting.
         */
        if ($coreUpdate !== null) {
            $coreUpdate->complete();
        }

        echo json_encode(array(
            'status' => 'success',
            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php', array('mode' => 'result'))
        ));
        exit();
    } elseif ($getMode === 'core_update') {
        // A proxy may cut off the answer of this long request: the migration still has to finish,
        // and the updater page then waits for it to end.
        ignore_user_abort(true);
        if (!$coreUpdate->lockDatabaseUpdate()) {
            echo json_encode(array('status' => 'running'));
            exit();
        }

        if (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '>')) {
            throw new RuntimeException($gL10n->get('SYS_FILESYSTEM_VERSION_INVALID', array($installedDbVersion, ADMIDIO_VERSION_TEXT, '', '')));
        }

        $coreUpdate->markDatabaseRunning();
        $databaseUpdateStarted = true;

        // Also with the version of the files already recorded: a migration that failed or was cut
        // off in its last steps has to run them again. The update steps that are done are skipped.
        $update = new Update();
        $update->doAdmidioUpdate($installedDbVersion);
        $databaseUpdateStarted = false;

        // The notes about plugins go to the updater, which shows them with the result. The update
        // has put them into a cookie for the result page of this script, which is not shown.
        $pluginWarnings = array();
        foreach (headers_list() as $header) {
            if (preg_match('/^Set-Cookie: adm_update_plugins_warnings=([^;]+)/i', $header, $matches) === 1) {
                $pluginWarnings = (array)json_decode(urldecode($matches[1]), true);
            }
        }
        setcookie('adm_update_plugins_warnings', '', time() - 3600, '/');
        $notes = array();
        foreach (getUpdateNotes($pluginWarnings) as $type => $messages) {
            foreach ($messages as $message) {
                $notes[] = array('type' => $type, 'text' => CoreUpdateHandover::toText($message['alertWarning'] ?? $message['alertInfo']));
            }
        }

        $coreUpdate->complete($notes);
        echo json_encode(array('status' => 'success'));
        exit();
    } elseif ($getMode === 'result') {
        // check if there are warnings about the plugins
        // the variables are stored in a cookie so that they survive the redirect after the update
        $notes = getUpdateNotes(isset($_COOKIE['adm_update_plugins_warnings']) ? (array)json_decode($_COOKIE['adm_update_plugins_warnings'], true) : array());
        $updateWarnings = $notes['warning'];
        $updateInfos = $notes['info'];

        // reset the cookie after reading the values so that the warnings are only shown once
        setcookie('adm_update_plugins_warnings', '', time() - 3600, '/');

        // show notice that update was successful
        $page = new InstallationPresenter('adm_update_successful', $gL10n->get('INS_UPDATE'));
        $page->addTemplateFile('update.successful.tpl');
        $page->assignSmartyVariable('updateWarnings', $updateWarnings);
        $page->assignSmartyVariable('updateInfos', $updateInfos);
        $page->setUpdateModus();
        $page->addJavascript('$("#buttonDonate").focus();', true);
        $page->show();
    }
} catch (Throwable $e) {
    // A failed migration leaves the maintenance mode on: the files are new, the database is not.
    if ($databaseUpdateStarted && $coreUpdate !== null) {
        try {
            $coreUpdate->markDatabaseFailed($e->getMessage());
        } catch (Throwable) {
            // The error of the migration is the one to report.
        }
    }

    if (($getMode ?? '') === 'core_update') {
        // The updater shows the error on its own page: after a failed migration the one recorded
        // in the state of the update, before that this message.
        if (isset($gDb) && $gDb instanceof Database) {
            $gDb->rollback();
        }
        echo json_encode(array('status' => 'error', 'message' => CoreUpdateHandover::toText($e->getMessage()), 'state' => $coreUpdate?->getState() ?? ''));
    } elseif ($getMode === 'update') {
        handleException($e, true);
    } else {
        showErrorMessage($e->getMessage(), true);
    }
}
