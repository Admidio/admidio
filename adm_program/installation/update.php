<?php
/**
 ***********************************************************************************************
 * Handle update of Admidio database to a new version
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode = 1 : (Default) Check update status and show dialog with status
 *        2 : Perform update
 *        3 : Show result of update
 ***********************************************************************************************
 */
$rootPath = dirname(__DIR__, 2);

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
require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/install_functions.php');
require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/update_functions.php');

// Initialize and check the parameters

const THEME_URL = 'layout';
$getMode = admFuncVariableIsValid($_GET, 'mode', 'int', array('defaultValue' => 1));

// connect to database
try {
    $gDb = Database::createDatabaseInstance();
} catch (AdmException $e) {
    $gLanguageData = new LanguageData('en');
    $gL10n = new Language($gLanguageData);

    showErrorMessage($gL10n->get('SYS_DATABASE_NO_LOGIN', array($e->getText())), true);
    // => EXIT
}

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

// check if adm_my_files has "write" privileges
if (!is_writable(ADMIDIO_PATH . FOLDER_DATA)) {
    echo $gL10n->get('INS_FOLDER_NOT_WRITABLE', array('adm_my_files'));
    exit();
}

// now check if a valid installation exists.
$sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
$pdoStatement = $gDb->queryPrepared($sql, array(), false);

if (!$pdoStatement || $pdoStatement->rowCount() === 0) {
    // no valid installation exists -> show installation wizard
    admRedirect(ADMIDIO_URL . '/adm_program/installation/installation.php');
    // => EXIT
}

// create an organization object of the current organization
$gCurrentOrganization = Organization::createDefaultOrganizationObject($gDb, $g_organization);
$gCurrentOrgId  = $gCurrentOrganization->getValue('org_id');

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
$gLanguageData = new LanguageData($gSettingsManager->getString('system_language'));
$gL10n = new Language($gLanguageData);
$gChangeNotification = new ChangeNotification();

if (FileSystemUtils::isUnixWithPosix() && (!is_executable(ADMIDIO_PATH . FOLDER_DATA) || !is_writable(ADMIDIO_PATH . FOLDER_DATA))) {
    try {
        FileSystemUtils::chmodDirectory(ADMIDIO_PATH . FOLDER_DATA);
    } catch (RuntimeException $exception) {
        try {
            $pathPermissions = FileSystemUtils::getPathPermissions(ADMIDIO_PATH . FOLDER_DATA);
        } catch (RuntimeException $exception) {
            $pathPermissions = array('exception' => $exception->getMessage());
        }
        $pathPermissions['path'] = ADMIDIO_PATH . FOLDER_DATA;

        $gLogger->error('FILESYSTEM: Could not set the necessary directory mode!', $pathPermissions);

        showErrorMessage($gL10n->get('INS_DATA_DIR_RIGHTS'), true);
        // => EXIT
    }
}

// config.php exists at wrong place
if (is_file(ADMIDIO_PATH . '/config.php') && is_file(ADMIDIO_PATH . FOLDER_DATA . '/config.php')) {
    // try to delete the config file at the old place otherwise show notice to user
    try {
        FileSystemUtils::deleteFileIfExists(ADMIDIO_PATH . '/config.php');
    } catch (RuntimeException $exception) {
        showErrorMessage($gL10n->get('INS_DELETE_CONFIG_FILE', array(ADMIDIO_URL)), true);
        // => EXIT
    }
}

// check database version
$message = checkDatabaseVersion($gDb);

if ($message !== '') {
    showErrorMessage($message);
    // => EXIT
}

// read current version of Admidio database
$installedDbVersion     = '';
$installedDbBetaVersion = '';
$maxUpdateStep          = 0;
$currentUpdateStep      = 0;

$sql = 'SELECT 1 FROM ' . TBL_COMPONENTS;
if (!$gDb->queryPrepared($sql, array(), false)) {
    // in Admidio version 2 the database version was stored in preferences table
    if ($gSettingsManager->has('db_version')) {
        $installedDbVersion     = $gSettingsManager->getString('db_version');
        $installedDbBetaVersion = $gSettingsManager->getInt('db_version_beta');
    }
} else {
    // read system component
    $componentUpdateHandle = new ComponentUpdate($gDb);
    $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));

    if ($componentUpdateHandle->getValue('com_id') > 0) {
        $installedDbVersion     = $componentUpdateHandle->getValue('com_version');
        $installedDbBetaVersion = (int) $componentUpdateHandle->getValue('com_beta');
        $currentUpdateStep      = (int) $componentUpdateHandle->getValue('com_update_step');
        $maxUpdateStep          = $componentUpdateHandle->getMaxUpdateStep();
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

if ($getMode === 1) {
    $gLogger->info('UPDATE: Show update start-view');

    // if database version is smaller than source version -> update
    // if database version is equal to source but beta has a difference -> update
    if (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '<')
    || (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep > $currentUpdateStep)) {
        // create a page with the notice that the installation must be configured on the next pages
        $page = new HtmlPageInstallation('admidio-update');
        $page->addTemplateFile('update.tpl');
        $page->setUpdateModus();
        $page->addJavascript('
            $("#next_page").on("click", function() {
                var showProgress = true;
                var requiredInput = $("input[required]");

                // check if all required fields have values
                for(var i = 0; i < requiredInput.length; i++)
                {
                    if(requiredInput[i].value == "")
                    {
                        showProgress = false;
                    }
                }

                if(showProgress == true)
                {
                    $(this).prop("disabled", true);
                    $(this).html("<i class=\"fas fa-sync fa-spin\"></i> ' . $gL10n->get('INS_DATABASE_IS_UPDATED') . '");
                    $("#update_login_form").submit();
                }
            });', true);
        $page->assign('installedDbVersion', $installedDbVersion);

        // create form with login and update button
        $form = new HtmlForm('update_login_form', SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/installation/update.php', array('mode' => 2)));

        if (!isset($gLoginForUpdate) || $gLoginForUpdate) {
            $form->addDescription($gL10n->get('INS_ADMINISTRATOR_LOGIN_DESC'));
            $form->addInput(
                'login_name',
                $gL10n->get('SYS_USERNAME'),
                '',
                array('maxLength' => 254, 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
            );
            // TODO Future: 'minLength' => PASSWORD_MIN_LENGTH
            $form->addInput(
                'password',
                $gL10n->get('SYS_PASSWORD'),
                '',
                array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
            );
        }

        $form->addButton(
            'next_page',
            $gL10n->get('INS_UPDATE_DATABASE'),
            array('icon' => 'fa-sync', 'class' => 'btn-primary')
        );

        $page->addHtml($form->show());
        $page->show();
    }
    // if versions are equal > no update
    elseif (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep === $currentUpdateStep) {
        $page = new HtmlPageInstallation('admidio-update-message');
        $page->setUpdateModus();
        $page->showMessage(
            'success',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('SYS_DATABASE_IS_UP_TO_DATE') . '<br />' . $gL10n->get('SYS_DATABASE_DOESNOT_NEED_UPDATED'),
            $gL10n->get('SYS_OVERVIEW'),
            'fa-home',
            ADMIDIO_URL . '/adm_program/overview.php'
        );
    // => EXIT
    }
    // if source version smaller than database -> show error
    else {
        $page = new HtmlPageInstallation('admidio-update-message');
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
            'fa-home',
            ADMIDIO_URL . '/adm_program/overview.php'
        );
        // => EXIT
    }
} elseif ($getMode === 2) {
    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $page = new HtmlPageInstallation('admidio-update-message');
        $page->setUpdateModus();
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $exception->getText(),
            $gL10n->get('SYS_OVERVIEW'),
            'fa-home',
            ADMIDIO_URL . '/adm_program/overview.php'
        );
    }

    doAdmidioUpdate($installedDbVersion);

    // remove session object with all data, so that
    // all data will be read after the update
    session_unset();
    session_destroy();

    // show notice that update was successful
    $page = new HtmlPageInstallation('admidio-update-successful');
    $page->addTemplateFile('update_successful.tpl');
    $page->setUpdateModus();
    $page->addJavascript('$("#next_page").focus();', true);

    $form = new HtmlForm('update-successful-form', ADMIDIO_HOMEPAGE . 'donate.php', null, array('setFocus' => false));
    $form->addButton(
        'main_page',
        $gL10n->get('SYS_LATER'),
        array('icon' => 'fa-home', 'link' => ADMIDIO_URL . '/adm_program/overview.php')
    );
    $form->addSubmitButton('next_page', $gL10n->get('SYS_DONATE'), array('icon' => 'fa-heart'));

    $page->addHtml($form->show());
    $page->show();
}
