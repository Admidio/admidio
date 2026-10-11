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
 ***********************************************************************************************
 */
const THEME_URL = 'layout';

try {
    // set once the database update has begun, so that a failure is recorded for the core update
    $databaseUpdateStarted = false;

    /**
     * Shows an error dialog with an error message to the user.
     * @param string $message Message that should be shown to the user.
     * @param bool $reloadPage If set to **true** than the user could reload the update page.
     * @return void
     */
    function showErrorMessage(string $message, bool $reloadPage = false): void
    {
        global $gL10n;

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

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'dialog', 'validValues' => array('dialog', 'update', 'result')));
    $getCoreUpdate = admFuncVariableIsValid($_GET, 'core_update', 'string', array('defaultValue' => ''));

    /*
     * An automatic core update hands over to this script once it has installed the files. The update
     * that owns the maintenance mode is looked at in every case, also without the parameter, so that
     * no migration starts while that update is still copying files.
     */
    $coreUpdate = CoreUpdateHandover::fromMaintenance();
    if ($getCoreUpdate !== '') {
        if ($coreUpdate !== null && $coreUpdate->getUpdateId() !== $getCoreUpdate) {
            throw new \Admidio\Infrastructure\Exception('SYS_INVALID_PAGE_VIEW');
        }
        $coreUpdate ??= CoreUpdateHandover::load($getCoreUpdate);

        // The administrator who started the update is known by the updater cookie. Anybody else
        // proves who they are with the normal login of this page.
        if ($coreUpdate->isAuthorized($_COOKIE[$coreUpdate->getCookieName()] ?? '')) {
            $gLoginForUpdate = false;
        }
    }

    // connect to database
    $gDb = Database::createDatabaseInstance();

    // Start a separate, clean PHP session for automatic core updates. This prevents the new
    // database updater from depending on serialized objects created by the previous source version.
    $updateSessionPrefix = COOKIE_PREFIX;
    if ($getCoreUpdate !== '') {
        $updateSessionPrefix .= '_CORE_UPDATE_' . substr($getCoreUpdate, 0, 12);
    }

    // start PHP session
    try {
        Session::start(
            $updateSessionPrefix,
            0,
            $getCoreUpdate !== '' ? rtrim(ADMIDIO_URL_PATH, '/') . '/install/' : ''
        );
    } catch (RuntimeException $exception) {
        // TODO
    }

    // create a session object
    if ($getCoreUpdate !== '') {
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

            // After a failed database update of a core update this page retries it, and the updater
            // can restore the previous version instead.
            $coreUpdateFailed = $coreUpdate !== null && $coreUpdate->isDatabaseFailed();
            $page->assignSmartyVariable('coreUpdateFailed', $coreUpdateFailed);
            $page->assignSmartyVariable('coreUpdateFailedText', $coreUpdateFailed ? $gL10n->get(
                $coreUpdate->hasDatabaseBackup() ? 'SYS_CORE_UPDATE_DATABASE_FAILED' : 'SYS_CORE_UPDATE_DATABASE_FAILED_OWN',
                array(ADMIDIO_VERSION_TEXT, $coreUpdate->getPreviousVersion())
            ) : '');
            $page->assignSmartyVariable('coreUpdateRestoreUrl', $coreUpdateFailed ? $coreUpdate->getUpdaterUrl() : '');
            $page->assignSmartyVariable('coreUpdateRestoreText', $coreUpdateFailed
                ? $gL10n->get('SYS_CORE_UPDATE_RESTORE_VERSION', array($coreUpdate->getPreviousVersion())) : '');

            // The update request must name the core update as well, or nothing would finish it.
            $updateParameters = array('mode' => 'update');
            if ($getCoreUpdate !== '') {
                $updateParameters['core_update'] = $getCoreUpdate;
            }

            // create form with login and update button
            $form = new FormPresenter(
                'adm_update_login_form',
                'update.tpl',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php', $updateParameters),
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
                $gL10n->get($coreUpdateFailed ? 'SYS_CORE_UPDATE_DATABASE_RETRY' : 'INS_UPDATE_DATABASE'),
                array('icon' => 'bi-arrow-repeat')
            );
            $page->assignSmartyVariable('settings', $gSettingsManager);
            $form->addToHtmlPage();
            $_SESSION['updateLoginForm'] = $form;
            $page->show();
        } // if versions are equal > no update
        elseif (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep === $currentUpdateStep) {
            // There is nothing to migrate, so this request finishes the core update.
            if ($coreUpdate !== null) {
                $coreUpdate->complete();
                if ($getCoreUpdate !== '') {
                    admRedirect($coreUpdate->getResultUrl());
                    // => EXIT
                }
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

        if ($getCoreUpdate !== '') {
            $resultUrl = $coreUpdate->getResultUrl();
        } else {
            $resultUrl = SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php',
                array('mode' => 'result')
            );
        }

        echo json_encode(array(
            'status' => 'success',
            'url' => $resultUrl
        ));
        exit();
    } elseif ($getMode === 'result') {
        // check if there are warnings about the plugins
        $updateWarnings = array();
        $updateInfos = array();
        // the variables are stored in a cookie so that they survive the redirect after the update
        $cookie = isset($_COOKIE['adm_update_plugins_warnings']) ? json_decode($_COOKIE['adm_update_plugins_warnings'], true) : [];
        $gWarnOldPlugins = false;
        $gWarn3rdPartyPlugins = false;
        $gWarnOldPluginsFolder = false;
        $gInfo3rdPartyPlugins = false;

        if (!empty($cookie)) {
            if (isset($cookie['warn_old_plugins'])) {
                $gWarnOldPlugins = $cookie['warn_old_plugins'];
            }
            if (isset($cookie['warn_3rd_party_plugins'])) {
                $gWarn3rdPartyPlugins = $cookie['warn_3rd_party_plugins'];
            }
            if (isset($cookie['warn_old_plugins_folder'])) {
                $gWarnOldPluginsFolder = $cookie['warn_old_plugins_folder'];
            }
            if (isset($cookie['info_3rd_party_plugins'])) {
                $gInfo3rdPartyPlugins = $cookie['info_3rd_party_plugins'];
            }
        }

        // reset the cookie after reading the values so that the warnings are only shown once
        setcookie('adm_update_plugins_warnings', '', time() - 3600, '/');

        if ($gWarnOldPlugins) {
            $updateWarnings[] = array(
                'id' => 'warn_old_plugins',
                'alertWarning' => $gL10n->get('INS_WARNING_OLD_ADM_PLUGINS_COULD_NOT_BE_DELETED', array('adm_plugins', 'adm_plugins'))
            );
        }
        if ($gWarn3rdPartyPlugins) {
            $updateWarnings[] = array(
                'id' => 'warn_3rd_party_plugins',
                'alertWarning' => $gL10n->get('INS_WARNING_3RD_PARTY_PLUGINS_COULD_NOT_BE_MOVED', array('plugins', 'adm_plugins'))
            );
        }
        if ($gWarnOldPluginsFolder) {
            $updateWarnings[] = array(
                'id' => 'warn_old_plugins_folder',
                'alertWarning' => $gL10n->get('INS_WARNING_OLD_ADM_PLUGINS_FOLDER_COULD_NOT_BE_DELETED', array('adm_plugins', 'adm_plugins'))
            );
        }
        if ($gInfo3rdPartyPlugins) {
            $updateInfos[] = array(
                'id' => 'info_3rd_party_plugins',
                'alertInfo' => $gL10n->get('INS_INFO_3RD_PARTY_PLUGINS_HAVE_BEEN_MOVED', array('plugins'))
            );
        }

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
            if ($getMode === 'update') {
                // The updater shows what failed and offers to retry the update or to restore the
                // previous version, which no page of Admidio can do while maintenance mode is on.
                echo json_encode(array('status' => 'success', 'url' => $coreUpdate->getUpdaterUrl()));
                exit();
            }
        } catch (Throwable) {
            // The error of the migration is the one to report.
        }
    }

    if ($getMode === 'update') {
        handleException($e, true);
    } else {
        showErrorMessage($e->getMessage(), true);
    }
}
