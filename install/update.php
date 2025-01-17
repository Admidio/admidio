<?php


use Admidio\Components\Entity\ComponentUpdate;
use Admidio\Infrastructure\ChangeNotification;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\InstallationUpdate\Service\Update;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Session\Entity\Session;
use Admidio\UI\Component\Form;
use Admidio\UI\View\Installation;

/**
 ***********************************************************************************************
 * Handle update of Admidio database to a new version
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode = dialog : (Default) Check update status and show dialog with status
 *        update : Perform update
 *        result : Show result of update
 ***********************************************************************************************
 */
const THEME_URL = 'layout';

try {
    /**
     * Shows an error dialog with an error message to the user.
     * @param string $message Message that should be shown to the user.
     * @param bool $reloadPage If set to **true** than the user could reload the update page.
     * @return void
     */
    function showErrorMessage(string $message, bool $reloadPage = false)
    {
        global $gL10n;

        if (!is_object($gL10n)) {
            $gL10n = new Language('en');
        }

        $page = new Installation('admidio-update-message', $gL10n->get('INS_UPDATE'));
        $page->setUpdateModus();
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $message,
            ($reloadPage) ? $gL10n->get('SYS_RELOAD') : $gL10n->get('SYS_OVERVIEW'),
            ($reloadPage) ? 'bi-arrow-clockwise' : 'bi-house-door-fill',
            ($reloadPage) ? ADMIDIO_URL . FOLDER_INSTALLATION . '/index.php' : ADMIDIO_URL . '/adm_program/overview.php'
        );
    }

    $rootPath = dirname(__DIR__);

    // embed config file
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

    require_once($rootPath . '/adm_program/system/bootstrap/bootstrap.php');

    // Initialize and check the parameters

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'dialog', 'validValues' => array('dialog', 'update', 'result')));

    // connect to database
    $gDb = Database::createDatabaseInstance();

    // start PHP session
    try {
        Session::start(COOKIE_PREFIX);
    } catch (RuntimeException $exception) {
        // TODO
    }

    // create session object
    if (array_key_exists('gCurrentSession', $_SESSION)) {
        $gCurrentSession = $_SESSION['gCurrentSession'];
    } else {
        // create new session object and store it in PHP session
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
     *  i.e. during installation / setup no logs are written. The next user 
     * call will use the default value of true and properly log changes...
     */
    Entity::setLoggingEnabled(false);

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

    // create language and language data object to handle translations
    if (!$gSettingsManager->has('system_language')) {
        $gSettingsManager->set('system_language', 'de');
    }
    $gL10n = new Language($gSettingsManager->getString('system_language'));
    $gChangeNotification = new ChangeNotification();

    // check if adm_my_files has "write" privileges and check some sub folders of adm_my_files
    \Admidio\InstallationUpdate\Service\Installation::checkFolderPermissions();

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
    $message = \Admidio\InstallationUpdate\Service\Installation::checkDatabaseVersion($gDb);

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
        // in Admidio version 2 the database version was stored in preferences table
        if ($gSettingsManager->has('db_version')) {
            $installedDbVersion = $gSettingsManager->getString('db_version');
            $installedDbBetaVersion = $gSettingsManager->getInt('db_version_beta');
        }
    } else {
        // read system component
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));

        if ($componentUpdateHandle->getValue('com_id') > 0) {
            $installedDbVersion = $componentUpdateHandle->getValue('com_version');
            $installedDbBetaVersion = (int)$componentUpdateHandle->getValue('com_beta');
            $currentUpdateStep = (int)$componentUpdateHandle->getValue('com_update_step');
            $maxUpdateStep = $componentUpdateHandle->getMaxUpdateStep();
        }
    }

    // if a beta was installed then create the version string with Beta version
    if ($installedDbBetaVersion > 0) {
        $installedDbVersion = $installedDbVersion . ' Beta ' . $installedDbBetaVersion;
    }

    if (version_compare($installedDbVersion, '3.1.0', '<')) {
        showErrorMessage($gL10n->get('SYS_VERSION_TO_OLD', array($installedDbVersion, ADMIDIO_VERSION_TEXT)));
        // => EXIT
    }

    // if database version is not set then show notice
    if ($installedDbVersion === '') {
        showErrorMessage($gL10n->get('INS_UPDATE_NOT_POSSIBLE') . '<p>' . $gL10n->get('INS_NO_INSTALLED_VERSION_FOUND', array(ADMIDIO_VERSION_TEXT)) . '</p>');
        // => EXIT
    }

    if ($getMode === 'dialog') {
        $gLogger->info('UPDATE: Show update start-view');

        // if database version is smaller than source version -> update
        // if database version is equal to source but beta has a difference -> update
        if (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '<')
            || (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep > $currentUpdateStep)) {
            // create a page with the notice that the installation must be configured on the next pages
            $page = new Installation('admidio-update', $gL10n->get('INS_UPDATE'));
            $page->addTemplateFile('update.tpl');
            $page->setUpdateModus();
            $page->assignSmartyVariable('installedDbVersion', $installedDbVersion);

            // create form with login and update button
            $form = new Form(
                'adm_update_login_form',
                'update.tpl',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php', array('mode' => 'update')),
                $page
            );
            if ($gLoginForUpdate) {
                $form->addInput(
                    'adm_login_name',
                    $gL10n->get('SYS_USERNAME'),
                    '',
                    array('maxLength' => 254, 'property' => Form::FIELD_REQUIRED, 'class' => 'form-control-small')
                );
                $form->addInput(
                    'adm_password',
                    $gL10n->get('SYS_PASSWORD'),
                    '',
                    array('type' => 'password', 'property' => Form::FIELD_REQUIRED, 'class' => 'form-control-small')
                );
            }
            $form->addSubmitButton(
                'adm_next_page',
                $gL10n->get('INS_UPDATE_DATABASE'),
                array('icon' => 'bi-arrow-repeat')
            );
            $form->addToHtmlPage();
            $_SESSION['updateLoginForm'] = $form;
            $page->show();
        } // if versions are equal > no update
        elseif (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep === $currentUpdateStep) {
            $page = new Installation('admidio-update-message', $gL10n->get('INS_UPDATE'));
            $page->setUpdateModus();
            $page->showMessage(
                'success',
                $gL10n->get('SYS_NOTE'),
                $gL10n->get('SYS_DATABASE_IS_UP_TO_DATE') . '<br />' . $gL10n->get('SYS_DATABASE_DOESNOT_NEED_UPDATED'),
                $gL10n->get('SYS_OVERVIEW'),
                'bi-house-door-fill',
                ADMIDIO_URL . '/adm_program/overview.php'
            );
            // => EXIT
        } // if source version smaller than database -> show error
        else {
            $page = new Installation('admidio-update-message', $gL10n->get('INS_UPDATE'));
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
                ADMIDIO_URL . '/adm_program/overview.php'
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
        $update = new Update();
        $update->doAdmidioUpdate($installedDbVersion);

        echo json_encode(array(
            'status' => 'success',
            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php', array('mode' => 'result'))
        ));
        exit();
    } elseif ($getMode === 'result') {
        // show notice that update was successful
        $page = new Installation('admidio-update-successful', $gL10n->get('INS_UPDATE'));
        $page->addTemplateFile('update.successful.tpl');
        $page->setUpdateModus();
        $page->addJavascript('$("#buttonDonate").focus();', true);
        $page->show();
    }
} catch (Throwable $e) {
    if ($getMode === 'update') {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        showErrorMessage($e->getMessage(), true);
    }
}
