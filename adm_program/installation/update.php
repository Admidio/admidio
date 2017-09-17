<?php
/**
 ***********************************************************************************************
 * Handle update of Admidio database to a new version
 *
 * @copyright 2004-2017 The Admidio Team
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

// embed config and constants file
$configPath = '../../adm_my_files/config.php';
$configPathOld = '../../config.php';
if (is_file($configPath))
{
    require_once($configPath);
}
elseif (is_file($configPathOld))
{
    // config file at destination of version 2.0 exists -> copy config file to new destination
    if (!@copy($configPathOld, $configPath))
    {
        exit('<div style="color: #cc0000;">Error: The file <strong>config.php</strong> could not be copied to the folder <strong>adm_my_files</strong>.
            Please check if this folder has the necessary write rights. If it\'s not possible to set this right then copy the
            config.php from the Admidio main folder to adm_my_files with your FTP program.</div>');
    }
    require_once($configPath);
}
else
{
    // no config file exists -> go to installation
    header('Location: installation.php');
    exit();
}

$rootPath = substr(__FILE__, 0, strpos(__FILE__, DIRECTORY_SEPARATOR . 'adm_program'));
require_once($rootPath . '/adm_program/system/init_globals.php');
require_once($rootPath . '/adm_program/system/constants.php');

// check PHP version and show notice if version is too low
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
{
    exit('<div style="color: #cc0000;">Error: Your PHP version ' . PHP_VERSION . ' does not fulfill
        the minimum requirements for this Admidio version. You need at least PHP ' . MIN_PHP_VERSION . ' or higher.</div>');
}

require_once(ADMIDIO_PATH . '/adm_program/installation/install_functions.php');
require_once(ADMIDIO_PATH . '/adm_program/system/function.php');
require_once(ADMIDIO_PATH . '/adm_program/system/string.php');
require_once(ADMIDIO_PATH . '/adm_program/system/logging.php');

// Initialize and check the parameters

define('THEME_URL', 'layout');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'int', array('defaultValue' => 1));
$message = '';

// connect to database
try
{
    $gDb = new Database($gDbType, $g_adm_srv, $g_adm_port, $g_adm_db, $g_adm_usr, $g_adm_pw);
}
catch (AdmException $e)
{
    showNotice(
        $gL10n->get('SYS_DATABASE_NO_LOGIN', $e->getText()),
        'installation.php?mode=3',
        $gL10n->get('SYS_BACK'),
        'layout/back.png'
    );
    // => EXIT
}

// now check if a valid installation exists.
$sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
$pdoStatement = $gDb->query($sql, false);

if (!$pdoStatement || $pdoStatement->rowCount() === 0)
{
    // no valid installation exists -> show installation wizard
    admRedirect(ADMIDIO_URL . '/adm_program/installation/installation.php');
    // => EXIT
}

// create an organization object of the current organization
$gCurrentOrganization = new Organization($gDb, $g_organization);
$organizationId = (int) $gCurrentOrganization->getValue('org_id');

if ($organizationId === 0)
{
    // Organisation wurde nicht gefunden
    exit('<div style="color: #cc0000;">Error: The organization of the config.php could not be found in the database!</div>');
}

// organisationsspezifische Einstellungen aus adm_preferences auslesen
$gPreferences = $gCurrentOrganization->getPreferences();

$gProfileFields = new ProfileFields($gDb, $organizationId);

// create language and language data object to handle translations
if (!isset($gPreferences['system_language']))
{
    $gPreferences['system_language'] = 'de';
}
$gL10n = new Language();
$gLanguageData = new LanguageData($gPreferences['system_language']);
$gL10n->addLanguageData($gLanguageData);

// config.php exists at wrong place
if (is_file(ADMIDIO_PATH . '/config.php') && is_file(ADMIDIO_PATH . FOLDER_DATA . '/config.php'))
{
    // try to delete the config file at the old place otherwise show notice to user
    if (!@unlink(ADMIDIO_PATH . '/config.php'))
    {
        showNotice(
            $gL10n->get('INS_DELETE_CONFIG_FILE', ADMIDIO_URL),
            ADMIDIO_URL . '/adm_program/installation/index.php',
            $gL10n->get('SYS_OVERVIEW'),
            'layout/application_view_list.png'
        );
        // => EXIT
    }
}

// check database version
$message = checkDatabaseVersion($gDb);

if ($message !== '')
{
    showNotice(
        $message,
        ADMIDIO_URL . '/adm_program/index.php',
        $gL10n->get('SYS_OVERVIEW'),
        'layout/application_view_list.png'
    );
    // => EXIT
}

// read current version of Admidio database
$installedDbVersion     = '';
$installedDbBetaVersion = '';
$maxUpdateStep          = 0;
$currentUpdateStep      = 0;

if (!$gDb->query('SELECT 1 FROM ' . TBL_COMPONENTS, false))
{
    // in Admidio version 2 the database version was stored in preferences table
    if (isset($gPreferences['db_version']))
    {
        $installedDbVersion     = $gPreferences['db_version'];
        $installedDbBetaVersion = $gPreferences['db_version_beta'];
    }
}
else
{
    // read system component
    $componentUpdateHandle = new ComponentUpdate($gDb);
    $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));

    if ($componentUpdateHandle->getValue('com_id') > 0)
    {
        $installedDbVersion     = $componentUpdateHandle->getValue('com_version');
        $installedDbBetaVersion = (int) $componentUpdateHandle->getValue('com_beta');
        $currentUpdateStep      = (int) $componentUpdateHandle->getValue('com_update_step');
        $maxUpdateStep          = $componentUpdateHandle->getMaxUpdateStep();
    }
}

// if a beta was installed then create the version string with Beta version
if ($installedDbBetaVersion > 0)
{
    $installedDbVersion = $installedDbVersion . ' Beta ' . $installedDbBetaVersion;
}

// if database version is not set then show notice
if ($installedDbVersion === '')
{
    $message = '
        <div class="alert alert-danger alert-small" role="alert">
            <span class="glyphicon glyphicon-exclamation-sign"></span>
            <strong>' . $gL10n->get('INS_UPDATE_NOT_POSSIBLE') . '</strong>
        </div>
        <p>' . $gL10n->get('INS_NO_INSTALLED_VERSION_FOUND', ADMIDIO_VERSION_TEXT) . '</p>';

    showNotice(
        $message,
        ADMIDIO_URL . '/adm_program/index.php',
        $gL10n->get('SYS_OVERVIEW'),
        'layout/application_view_list.png',
        true
    );
    // => EXIT
}

if ($getMode === 1)
{
    $gLogger->info('UPDATE: Show update start-view');

    // if database version is smaller then source version -> update
    // if database version is equal to source but beta has a difference -> update
    if (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '<')
    || (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep > $currentUpdateStep))
    {
        // create a page with the notice that the installation must be configured on the next pages
        $form = new HtmlFormInstallation('update_login_form', 'update.php?mode=2');
        $form->setUpdateModus();
        $form->setFormDescription('<h3>' . $gL10n->get('INS_DATABASE_NEEDS_UPDATED_VERSION', $installedDbVersion, ADMIDIO_VERSION_TEXT) . '</h3>');

        if (!isset($gLoginForUpdate) || $gLoginForUpdate == 1)
        {
            $form->addDescription($gL10n->get('INS_ADMINISTRATOR_LOGIN_DESC'));
            $form->addInput(
                'login_name', $gL10n->get('SYS_USERNAME'), null,
                array('maxLength' => 35, 'property' => FIELD_REQUIRED, 'class' => 'form-control-small')
            );
            // TODO Future: 'minLength' => PASSWORD_MIN_LENGTH
            $form->addInput(
                'password', $gL10n->get('SYS_PASSWORD'), null,
                array('type' => 'password', 'property' => FIELD_REQUIRED, 'class' => 'form-control-small')
            );
        }

        // if this is a beta version then show a warning message
        if (ADMIDIO_VERSION_BETA > 0)
        {
            $gLogger->notice('UPDATE: This is a BETA release!');

            $form->addDescription('
                <div class="alert alert-warning alert-small" role="alert">
                    <span class="glyphicon glyphicon-warning-sign"></span>
                    ' . $gL10n->get('INS_WARNING_BETA_VERSION') . '
                </div>');
        }
        $form->addSubmitButton(
            'next_page', $gL10n->get('INS_UPDATE_DATABASE'),
            array('icon' => 'layout/database_in.png', 'onClickText' => $gL10n->get('INS_DATABASE_IS_UPDATED'))
        );
        echo $form->show();
    }
    // if versions are equal > no update
    elseif (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep === $currentUpdateStep)
    {
        $message = '
            <div class="alert alert-success form-alert">
                <span class="glyphicon glyphicon-ok"></span>
                <strong>' . $gL10n->get('INS_DATABASE_IS_UP_TO_DATE') . '</strong>
            </div>
            <p>' . $gL10n->get('INS_DATABASE_DOESNOT_NEED_UPDATED') . '</p>';

        showNotice(
            $message,
            ADMIDIO_URL . '/adm_program/index.php',
            $gL10n->get('SYS_OVERVIEW'),
            'layout/application_view_list.png',
            true
        );
        // => EXIT
    }
    // if source version smaller then database -> show error
    else
    {
        $message = '
            <div class="alert alert-danger form-alert">
                <span class="glyphicon glyphicon-exclamation-sign"></span>
                <strong>' . $gL10n->get('SYS_ERROR') . '</strong>
                <p>' .
                    $gL10n->get(
                        'SYS_FILESYSTEM_VERSION_INVALID', $installedDbVersion,
                        ADMIDIO_VERSION_TEXT, '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>'
                    ) . '
                </p>
            </div>';

        showNotice(
            $message,
            ADMIDIO_URL . '/adm_program/index.php',
            $gL10n->get('SYS_OVERVIEW'),
            'layout/application_view_list.png',
            true
        );
        // => EXIT
    }
}
elseif ($getMode === 2)
{
    $gLogger->info('UPDATE: Execute update');

    /**************************************/
    /* execute update script for database */
    /**************************************/

    if (!isset($gLoginForUpdate) || $gLoginForUpdate == 1)
    {
        // get username and password
        $loginName = admFuncVariableIsValid($_POST, 'login_name', 'string', array('requireValue' => true, 'directOutput' => true));
        $password  = admFuncVariableIsValid($_POST, 'password',   'string', array('requireValue' => true, 'directOutput' => true));

        // Search for username
        $sql = 'SELECT usr_id
                  FROM '.TBL_USERS.'
                 WHERE UPPER(usr_login_name) = UPPER(\''.$loginName.'\')';
        $userStatement = $gDb->query($sql);

        if ($userStatement->rowCount() === 0)
        {
            $message = '
                <div class="alert alert-danger alert-small" role="alert">
                    <span class="glyphicon glyphicon-exclamation-sign"></span>
                    <strong>' . $gL10n->get('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT') . '</strong>
                </div>';

            showNotice($message, 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
            // => EXIT
        }
        else
        {
            // create object with current user field structure und user object
            $gCurrentUser = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());

            // check login data. If login failed an exception will be thrown.
            // Don't update the current session with user id and don't do a rehash of the password
            // because in former versions the password field was to small for the current hashes
            // and the update of this field will be done after this check.
            $checkLoginReturn = $gCurrentUser->checkLogin($password, false, false, false, true);

            if (is_string($checkLoginReturn))
            {
                $message = '
                    <div class="alert alert-danger alert-small" role="alert">
                        <span class="glyphicon glyphicon-exclamation-sign"></span>
                        <strong>' . $checkLoginReturn . '</strong>
                    </div>';

                showNotice($message, 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
                // => EXIT
            }
        }
    }

    // setzt die Ausfuehrungszeit des Scripts auf 5 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(300);

    preg_match('/^(\d+)\.(\d+)\.(\d+)/', $installedDbVersion, $versionArray);
    $versionArray = array_map('intval', $versionArray);
    list(, $versionMain, $versionMinor, $versionPatch) = $versionArray;

    $flagNextVersion = true;
    ++$versionPatch;

    // erst einmal die evtl. neuen Orga-Einstellungen in DB schreiben
    require_once('db_scripts/preferences.php');

    // calculate the best cost value for your server performance
    $benchmarkResults = PasswordHashing::costBenchmark(0.35, 'password', $gPasswordHashAlgorithm);
    $updateOrgPreferences = array('system_hashing_cost' => $benchmarkResults['cost']);

    $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
    $orgaStatement = $gDb->query($sql);

    while($orgId = $orgaStatement->fetchColumn())
    {
        $organization = new Organization($gDb, $orgId);
        $organization->setPreferences($defaultOrgPreferences, false);
        $organization->setPreferences($updateOrgPreferences, true);
    }

    if ($gDbType === 'mysql')
    {
        // disable foreign key checks for mysql, so tables can easily deleted
        $sql = 'SET foreign_key_checks = 0';
        $gDb->query($sql);
    }

    // in version 2 we had an other update mechanism which will be handled here
    if ($versionMain === 2)
    {
        // nun in einer Schleife die Update-Scripte fuer alle Versionen zwischen der Alten und Neuen einspielen
        while($flagNextVersion)
        {
            $flagNextVersion = false;

            if ($versionMain === 2)
            {
                // version 2 Admidio had sql and php files where the update statements where stored
                // these files must be executed

                // in der Schleife wird geschaut ob es Scripte fuer eine Microversion (3.Versionsstelle) gibt
                // Microversion 0 sollte immer vorhanden sein, die anderen in den meisten Faellen nicht
                for ($versionPatch; $versionPatch < 15; ++$versionPatch)
                {
                    $version = $versionMain . '_' . $versionMinor . '_' . $versionPatch;

                    // output of the version number for better debugging
                    $gLogger->info('Update to version ' . $version);

                    $dbScriptsPath = ADMIDIO_PATH . '/adm_program/installation/db_scripts/';
                    $sqlFileName = 'upd_' . $version . '_db.sql';
                    $phpFileName = 'upd_' . $version . '_conv.php';

                    if (is_file($dbScriptsPath . $sqlFileName))
                    {
                        $sqlQueryResult = querySqlFile($gDb, $sqlFileName);

                        if ($sqlQueryResult === true)
                        {
                            $flagNextVersion = true;
                        }
                        else
                        {
                            showNotice($sqlQueryResult, 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
                            // => EXIT
                        }
                    }

                    $phpUpdateFile = $dbScriptsPath . $phpFileName;
                    // check if an php update file exists and then execute the script
                    if (is_file($phpUpdateFile))
                    {
                        include($phpUpdateFile);
                        $flagNextVersion = true;
                    }
                }

                // keine Datei mit der Microversion gefunden, dann die Main- oder Subversion hochsetzen,
                // solange bis die aktuelle Versionsnummer erreicht wurde
                if (!$flagNextVersion && version_compare($versionMain . '.' . $versionMinor . '.' . $versionPatch, ADMIDIO_VERSION, '<'))
                {
                    if ($versionMinor === 4) // we do not have more then 4 subversions with old updater
                    {
                        ++$versionMain;
                        $versionMinor = 0;
                    }
                    else
                    {
                        ++$versionMinor;
                    }

                    $versionPatch = 0;
                    $flagNextVersion = true;
                }
            }
        }
    }

    disableSoundexSearchIfPgsql($gDb);

    // since version 3 we do the update with xml files and a new class model
    if ($versionMain >= 3)
    {
        // set system user as current user, but this user only exists since version 3
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = \'' . $gL10n->get('SYS_SYSTEM') . '\'';
        $systemUserStatement = $gDb->query($sql);

        $gCurrentUser = new User($gDb, $gProfileFields, (int) $systemUserStatement->fetchColumn());

        // reread component because in version 3.0 the component will be created within the update
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
        $componentUpdateHandle->setTargetVersion(ADMIDIO_VERSION);
        $componentUpdateHandle->update();
    }

    if ($gDbType === 'mysql')
    {
        // activate foreign key checks, so database is consistent
        $sql = 'SET foreign_key_checks = 1';
        $gDb->query($sql);
    }


    // create ".htaccess" file for folder "adm_my_files"
    if (!is_file(ADMIDIO_PATH . FOLDER_DATA.'/.htaccess'))
    {
        $protection = new Htaccess(ADMIDIO_PATH . FOLDER_DATA);

        if (!$protection->protectFolder())
        {
            $gLogger->warning('htaccess file could not be created!');
        }
    }

    // nach dem Update erst einmal bei Sessions das neue Einlesen des Organisations- und Userobjekts erzwingen
    $sql = 'UPDATE ' . TBL_SESSIONS . ' SET ses_renew = 1';
    $gDb->query($sql);

    // create an installation unique cookie prefix and remove special characters
    $gCookiePraefix = 'ADMIDIO_' . $g_organization . '_' . $g_adm_db . '_' . $g_tbl_praefix;
    $gCookiePraefix = str_replace(array(' ', '.', ',', ';', ':', '[', ']'), '_', $gCookiePraefix);

    // start php session and remove session object with all data, so that
    // all data will be read after the update
    Session::start($gCookiePraefix);
    unset($_SESSION['gCurrentSession']);

    // show notice that update was successful
    $form = new HtmlFormInstallation('installation-form', ADMIDIO_HOMEPAGE . 'donate.php');
    $form->setUpdateModus();
    $form->setFormDescription(
        $gL10n->get('INS_UPDATE_TO_VERSION_SUCCESSFUL', ADMIDIO_VERSION_TEXT) . '<br /><br />' . $gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT'),
        '<div class="alert alert-success form-alert">
            <span class="glyphicon glyphicon-ok"></span>
            <strong>'.$gL10n->get('INS_UPDATING_WAS_SUCCESSFUL').'</strong>
        </div>'
    );
    $form->openButtonGroup();
    $form->addSubmitButton('next_page', $gL10n->get('SYS_DONATE'), array('icon' => 'layout/money.png'));
    $form->addButton('main_page', $gL10n->get('SYS_LATER'), array('icon' => 'layout/application_view_list.png', 'link' => '../index.php'));
    $form->closeButtonGroup();
    echo $form->show();
}
