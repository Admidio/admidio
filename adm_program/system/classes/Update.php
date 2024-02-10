<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class to implement useful method for installation and update process.
 */
class Update
{
    /**
     * checks if login is required and if so, if it is valid
     * @throws AdmException
     * @throws Exception
     */
    public function checkLogin()
    {
        global $gDb, $gLoginForUpdate, $gProfileFields, $gCurrentUser;

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
            throw new AdmException('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT');
            // => EXIT
        } else {
            // create object with current user field structure und user object
            $gCurrentUser = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());

            // check login data. If login failed an exception will be thrown.
            // Don't update the current session with user id and don't do a rehash of the password
            // because in former versions the password field was too small for the current hashes
            // and the update of this field will be done after this check.
            $gCurrentUser->checkLogin($password, false, false, false, true);
        }
    }

    /**
     * @param string $installedDbVersion
     * @throws AdmException
     * @throws Exception
     */
    public function doAdmidioUpdate(string $installedDbVersion)
    {
        global $gLogger, $gDb, $gL10n, $gProfileFields, $gCurrentUser;

        $gLogger->info('UPDATE: Execute update');

        $this->checkLogin();

        $this->updateOrgPreferences();

        // disable foreign key checks for mysql, so tables can easily be deleted
        $this->toggleForeignKeyChecks(false);

        InstallationUtils::disableSoundexSearchIfPgSql($gDb);

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
        $this->toggleForeignKeyChecks(true);

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

        // remove session object with all data, so that
        // all data will be read after the update
        session_unset();
        session_destroy();
    }

    /**
     * @throws AdmException
     * @throws Exception
     */
    public function updateOrgPreferences()
    {
        global $gDb, $gPasswordHashAlgorithm;

        // set execution time to 2 minutes because we have a lot to do
        PhpIniUtils::startNewExecutionTimeLimit(120);

        // first write the possible new Orga settings in DB
        require_once(__DIR__ . '/../../installation/db_scripts/preferences.php');

        // calculate the best cost value for your server performance
        $benchmarkResults = PasswordUtils::costBenchmark($gPasswordHashAlgorithm);
        $updateOrgPreferences = array();

        if (is_int($benchmarkResults['options']['cost'])) {
            $updateOrgPreferences = array('system_hashing_cost' => $benchmarkResults['options']['cost']);
        }

        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = $gDb->queryPrepared($sql);

        while ($orgId = $organizationStatement->fetchColumn()) {
            $organization = new Organization($gDb, $orgId);
            $settingsManager =& $organization->getSettingsManager();
            $settingsManager->setMulti($defaultOrgPreferences, false);
            $settingsManager->setMulti($updateOrgPreferences);
        }
    }

    /**
     * Method will activate / deactivate the foreign key check in the database. This
     * will only work for a MySQL database.
     * @param bool $enable If set to **true** the foreign key check will be activated.
     * @throws Exception
     */
    public function toggleForeignKeyChecks(bool $enable)
    {
        global $gDb;

        if (DB_ENGINE === Database::PDO_ENGINE_MYSQL) {
            // disable foreign key checks for mysql, so tables can easily be deleted
            $sql = 'SET foreign_key_checks = ' . (int) $enable;
            $gDb->queryPrepared($sql);
        }
    }
}
