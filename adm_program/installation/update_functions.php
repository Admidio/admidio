<?php
/**
 ***********************************************************************************************
 * Functions for update
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * checks if login is required and if so, if it is valid
 */
function checkLogin()
{
    global $gDb, $gL10n, $gLoginForUpdate, $gProfileFields, $gCurrentUser;

    if (isset($gLoginForUpdate) && !$gLoginForUpdate) {
        return;
    }

    // get username and password
    $loginName = admFuncVariableIsValid($_POST, 'login_name', 'string', array('requireValue' => true, 'directOutput' => true));
    $password  = $_POST['password']; // password could contain special chars, so no conversation should be done

    // Search for username
    $sql = 'SELECT usr_id
              FROM '.TBL_USERS.'
             WHERE UPPER(usr_login_name) = UPPER(?)';
    $userStatement = $gDb->queryPrepared($sql, array($loginName));

    if ($userStatement->rowCount() === 0) {
        // show message that username or password is incorrect
        $page = new HtmlPageInstallation('admidio-update-message');
        $page->setUpdateModus();
        $page->showMessage('error', $gL10n->get('SYS_NOTE'), $gL10n->get('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT'), $gL10n->get('SYS_BACK'), 'fa-arrow-circle-left', 'update.php');
    // => EXIT
    } else {
        // create object with current user field structure und user object
        $gCurrentUser = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());

        try {
            // check login data. If login failed an exception will be thrown.
            // Don't update the current session with user id and don't do a rehash of the password
            // because in former versions the password field was to small for the current hashes
            // and the update of this field will be done after this check.
            $gCurrentUser->checkLogin($password, false, false, false, true);
        } catch (AdmException $e) {
            // show message with the error of the exception
            $page = new HtmlPageInstallation('admidio-update-message');
            $page->setUpdateModus();
            $page->showMessage('error', $gL10n->get('SYS_NOTE'), $e->getText(), $gL10n->get('SYS_BACK'), 'fa-arrow-circle-left', 'update.php');
            // => EXIT
        }
    }
}

function updateOrgPreferences()
{
    global $gDb, $gPasswordHashAlgorithm;

    // set execution time to 2 minutes because we have a lot to do
    PhpIniUtils::startNewExecutionTimeLimit(120);

    // erst einmal die evtl. neuen Orga-Einstellungen in DB schreiben
    require_once(__DIR__ . '/db_scripts/preferences.php');

    // calculate the best cost value for your server performance
    $benchmarkResults = PasswordUtils::costBenchmark($gPasswordHashAlgorithm);
    $updateOrgPreferences = array();

    if (is_int($benchmarkResults['options']['cost'])) {
        $updateOrgPreferences = array('system_hashing_cost' => $benchmarkResults['options']['cost']);
    }

    $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
    $orgaStatement = $gDb->queryPrepared($sql);

    while ($orgId = $orgaStatement->fetchColumn()) {
        $organization = new Organization($gDb, $orgId);
        $settingsManager =& $organization->getSettingsManager();
        $settingsManager->setMulti($defaultOrgPreferences, false);
        $settingsManager->setMulti($updateOrgPreferences);
    }
}

/**
 * @param bool $enable
 */
function toggleForeignKeyChecks($enable)
{
    global $gDb;

    if (DB_ENGINE === Database::PDO_ENGINE_MYSQL) {
        // disable foreign key checks for mysql, so tables can easily deleted
        $sql = 'SET foreign_key_checks = ' . (int) $enable;
        $gDb->queryPrepared($sql);
    }
}

/**
 * @param string $installedDbVersion
 */
function doAdmidioUpdate($installedDbVersion)
{
    global $gLogger, $gDb, $gL10n, $gProfileFields, $gCurrentUser;

    $gLogger->info('UPDATE: Execute update');

    checkLogin();

    updateOrgPreferences();

    // disable foreign key checks for mysql, so tables can easily deleted
    toggleForeignKeyChecks(false);

    disableSoundexSearchIfPgSql($gDb);

    preg_match('/^(\d+)\.(\d+)\.(\d+)/', $installedDbVersion, $versionArray);
    $versionArray = array_map('intval', $versionArray);

    // set execution time to 5 minutes because we have a lot to do
    PhpIniUtils::startNewExecutionTimeLimit(300);

    // set system user as current user, but this user only exists since version 3
    $sql = 'SELECT usr_id
              FROM ' . TBL_USERS . '
             WHERE usr_login_name = ?';
    $systemUserStatement = $gDb->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));

    $gCurrentUser = new User($gDb, $gProfileFields, (int) $systemUserStatement->fetchColumn());

    $componentUpdateHandle = new ComponentUpdate($gDb);
    $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $componentUpdateHandle->update(ADMIDIO_VERSION);

    // activate foreign key checks, so database is consistent
    toggleForeignKeyChecks(true);

    // create ".htaccess" file for folder "adm_my_files"
    $htaccess = new Htaccess(ADMIDIO_PATH . FOLDER_DATA);

    try {
        // remove compiled templates so new ones will be created with the new version
        FileSystemUtils::deleteDirectoryIfExists(ADMIDIO_PATH . FOLDER_DATA . '/templates', true);
    } catch (UnexpectedValueException|RuntimeException $e) {
        $gLogger->warning('Folder adm_my_files/templates could not be deleted!');
    }

    if (!$htaccess->protectFolder()) {
        $gLogger->warning('.htaccess file could not be created!');
    }

    // after the update first force the reload of the cache for all active sessions
    $sql = 'UPDATE ' . TBL_SESSIONS . ' SET ses_reload = \'true\'';
    $gDb->queryPrepared($sql);
}

/**
 * Shows an error dialog with a error message to the user.
 * @param string $message Message that should be shown to the user.
 * @param bool $reloadPage If set to **true** than the user could reload the update page.
 * @return void
 */
function showErrorMessage(string $message, bool $reloadPage = false)
{
    global $gL10n;

    $page = new HtmlPageInstallation('admidio-update-message');
    $page->setUpdateModus();
    $page->showMessage(
        'error',
        $gL10n->get('SYS_NOTE'),
        $message,
        ($reloadPage) ? $gL10n->get('SYS_RELOAD') : $gL10n->get('SYS_OVERVIEW'),
        ($reloadPage) ? 'fa-redo-alt' : 'fa-home',
        ($reloadPage) ? ADMIDIO_URL . FOLDER_INSTALLATION . '/index.php' : ADMIDIO_URL . '/adm_program/overview.php'
    );
}
