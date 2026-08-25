<?php
namespace Admidio\InstallationUpdate\Service;

use Admidio\Components\Entity\ComponentUpdate;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PasswordUtils;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Users\Entity\User;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use UnexpectedValueException;

/**
 * @brief Class to implement useful method for installation and update process.
 *
 * Beside the helper methods for the update the class implements the complete installation of a new
 * Admidio database. install() creates the schema, the default data, the first organization and its
 * administrator from an InstallationConfig, and generateConfigFileContent() and writeConfigFile()
 * create the configuration file. None of these methods reads a request, a session or a form, so the
 * web installation wizard and the command line share the same installation.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Installation
{
    /**
     * Checks whether the minimum requirements for PHP and MySQL have been met.
     * @param Database $database Object of the database that should be checked. A connection should be established.
     * @return string Returns an error text if the database doesn't meet the necessary requirements.
     * @throws Exception
     */
    public static function checkDatabaseVersion(Database $database): string
    {
        global $gL10n;

        // check database version
        if (!$database->checkMinimumRequiredVersion()) {
            return $gL10n->get('SYS_DATABASE_VERSION') . ': <strong>' . $database->getVersion() . '</strong><br /><br />' .
                $gL10n->get('INS_WRONG_MYSQL_VERSION', array(ADMIDIO_VERSION_TEXT, $database->getMinimumRequiredVersion(),
                    '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>'));
        }

        return '';
    }

    /**
     * Method will check if the folder adm_my_files is writable for the PHP user. The subfolders **ecard_templates**
     * **logs**, **mail_templates** and **temp** will be checked if they exist and created if they don't exist.
     * @return void
     * @throws Exception
     */
    public static function checkFolderPermissions()
    {
        // check if adm_my_files has write permissions
        if (!is_writable(ADMIDIO_PATH . FOLDER_DATA)) {
            // try to set write permissions otherwise throw an exception
            try {
                FileSystemUtils::chmodDirectory(ADMIDIO_PATH . FOLDER_DATA);
            } catch (RuntimeException | UnexpectedValueException $e) {
                throw new Exception('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA));
            }
        }

        // now check some sub folders and create them if necessary
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates');
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new Exception('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA . '/ecard_templates'));
        }
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/logs');
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new Exception('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA . '/logs'));
        }
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates');
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new Exception('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA . '/mail_templates'));
        }
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_TEMP_DATA);
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new Exception('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_TEMP_DATA));
        }
    }

    /**
     * @param Database $db
     * @throws Exception
     */
    public static function disableSoundexSearchIfPgSql(Database $db)
    {
        if ($db->getEngine() === Database::PDO_ENGINE_PGSQL) {
            // soundex is not a default function in PostgresSQL
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                   SET prf_value = false
                 WHERE prf_name = \'system_search_similar\'';
            $db->queryPrepared($sql);
        }
    }

    /**
     * Read data from sql file and execute all statements to the current database
     * @param Database $db
     * @param string $sqlFileName
     * @throws Exception
     */
    public static function querySqlFile(Database $db, string $sqlFileName)
    {
        $sqlPath = ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/';
        $sqlFilePath = $sqlPath . $sqlFileName;

        if (!is_file($sqlFilePath)) {
            throw new Exception('INS_DATABASE_FILE_NOT_FOUND', array($sqlFileName, $sqlPath));
        }

        try {
            $sqlStatements = Database::getSqlStatementsFromSqlFile($sqlFilePath);
        } catch (RuntimeException $exception) {
            throw new Exception('INS_ERROR_OPEN_FILE', array($sqlFilePath));
        }

        foreach ($sqlStatements as $sqlStatement) {
            $db->queryPrepared($sqlStatement);
        }
    }

    /**
     * Check the values that are necessary to connect to the database of the new installation. This is
     * the validation of the database step of the installation wizard.
     * @param string $dbType Database system, one of the Database::DB_TYPE_* values.
     * @param string $dbHost Host name or IP address of the database server.
     * @param int|null $dbPort Port of the database server or **null** for the default port.
     * @param string $dbName Name of the database.
     * @param string $dbUsername User of the database.
     * @param string $tablePrefix Prefix of all Admidio tables.
     * @return void
     * @throws Exception
     */
    public static function validateDatabaseInput(
        string $dbType,
        string $dbHost,
        ?int $dbPort,
        string $dbName,
        string $dbUsername,
        string $tablePrefix
    ): void {
        $sqlIdentifiersRegex = '/^[a-zA-Z0-9_$@-]+$/';

        if (!in_array($dbType, array(Database::DB_TYPE_MARIADB, Database::DB_TYPE_MYSQL, Database::DB_TYPE_PGSQL), true)) {
            throw new Exception('INS_DATABASE_TYPE_INVALID');
        }

        // TODO: unix_server is currently not supported
        if (filter_var($dbHost, FILTER_VALIDATE_DOMAIN) === false && filter_var($dbHost, FILTER_VALIDATE_IP) === false) {
            throw new Exception('INS_HOST_INVALID');
        }

        if ($dbPort !== null && ($dbPort < 1 || $dbPort > 65535)) {
            throw new Exception('INS_DATABASE_PORT_INVALID');
        }

        if (strlen($dbName) > 64 || preg_match($sqlIdentifiersRegex, $dbName) !== 1) {
            throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_DATABASE'));
        }

        if (strlen($dbUsername) > 64 || preg_match($sqlIdentifiersRegex, $dbUsername) !== 1) {
            throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_USERNAME'));
        }

        if (strlen($tablePrefix) > 10 || preg_match($sqlIdentifiersRegex, $tablePrefix) !== 1) {
            throw new Exception('SYS_FIELD_INVALID_INPUT', array('INS_TABLE_PREFIX'));
        }
    }

    /**
     * Check the values of the organization that will be created. This is the validation of the
     * organization step of the installation wizard.
     * @param string $shortName Short name of the organization.
     * @param string $longName Long name of the organization.
     * @param string $email Email address of the organization administrator.
     * @param string $timezone PHP time zone of the organization.
     * @return void
     * @throws Exception
     */
    public static function validateOrganizationInput(
        string $shortName,
        string $longName,
        string $email,
        string $timezone
    ): void {
        // allow only letters, numbers and special characters like .-_+@
        if (!StringUtils::strValidCharacters($shortName, 'noSpecialChar')) {
            throw new Exception('SYS_FIELD_INVALID_CHAR', array('SYS_NAME_ABBREVIATION'));
        }

        if ($longName === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_NAME'));
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new Exception('SYS_EMAIL_INVALID', array('SYS_EMAIL_ADMINISTRATOR'));
        }

        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new Exception('SYS_FIELD_INVALID_INPUT', array('ORG_TIMEZONE'));
        }
    }

    /**
     * Check the values of the administrator that will be created. This is the validation of the
     * administrator step of the installation wizard.
     * @param string $login Login name of the administrator.
     * @param string $firstName First name of the administrator.
     * @param string $lastName Last name of the administrator.
     * @param string $email Email address of the administrator.
     * @param string $password Password of the administrator.
     * @return void
     * @throws Exception
     */
    public static function validateAdministratorInput(
        string $login,
        string $firstName,
        string $lastName,
        string $email,
        string $password
    ): void {
        // username should only have valid chars
        if (!StringUtils::strValidCharacters($login, 'noSpecialChar')) {
            throw new Exception('SYS_FIELD_INVALID_CHAR', array('SYS_USERNAME'));
        }

        if ($firstName === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_FIRSTNAME'));
        }

        if ($lastName === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_LASTNAME'));
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new Exception('SYS_EMAIL_INVALID', array('SYS_EMAIL'));
        }

        // Password min length is 8 chars
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('SYS_PASSWORD_LENGTH');
        }

        // Admin Password should have a minimum strength of 1
        if (PasswordUtils::passwordStrength($password, array($lastName, $firstName, $email, $login)) < 1) {
            throw new Exception('SYS_PASSWORD_NOT_STRONG_ENOUGH');
        }
    }

    /**
     * Check all values of an installation. The web wizard validates the input of each step with the
     * methods above, a headless installation receives all values at once and validates them here.
     * @param InstallationConfig $config Input values of the new installation.
     * @return void
     * @throws Exception
     */
    public static function validateConfiguration(InstallationConfig $config): void
    {
        global $gL10n;

        self::validateDatabaseInput(
            $config->dbType,
            $config->dbHost,
            $config->dbPort,
            $config->dbName,
            $config->dbUsername,
            $config->tablePrefix
        );

        if ($config->rootUrl === '' || filter_var($config->rootUrl, FILTER_VALIDATE_URL) === false
            || !in_array(parse_url($config->rootUrl, PHP_URL_SCHEME), array('http', 'https'), true)) {
            throw new Exception('INS_ROOT_URL_INVALID');
        }

        if ($config->language === '') {
            throw new Exception('INS_LANGUAGE_NOT_CHOSEN');
        }

        if (!array_key_exists($config->language, $gL10n->getAvailableLanguages())) {
            throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_LANGUAGE'));
        }

        self::validateOrganizationInput(
            $config->organizationShortName,
            $config->organizationName,
            $config->organizationEmail,
            $config->timezone
        );
        self::validateAdministratorInput(
            $config->adminLogin,
            $config->adminFirstName,
            $config->adminLastName,
            $config->adminEmail,
            $config->adminPassword
        );
    }

    /**
     * Connect to the database of the new installation and check that Admidio can be installed there.
     * The connection needs write access, the database must fulfill the minimum version and it may
     * not contain an Admidio installation already.
     * @param InstallationConfig $config Input values of the new installation.
     * @return Database Returns the connection to the database of the new installation.
     * @throws Exception
     */
    public static function connectDatabase(InstallationConfig $config): Database
    {
        try {
            $db = new Database(
                $config->dbType,
                $config->dbHost,
                $config->dbPort,
                $config->dbName,
                $config->dbUsername,
                $config->dbPassword
            );
            $db->checkWriteAccess();
        } catch (Exception $e) {
            throw new Exception('SYS_DATABASE_NO_LOGIN', array($e->getMessage()));
        }

        $message = self::checkDatabaseVersion($db);
        if ($message !== '') {
            throw new Exception($message);
        }

        self::checkInstallationNotExists($db, $config->tablePrefix);

        return $db;
    }

    /**
     * Check that the database doesn't contain an Admidio installation already. A second installation
     * would overwrite the data of the existing one.
     * @param Database $db Connection to the database of the new installation.
     * @param string $tablePrefix Prefix of all Admidio tables.
     * @return void
     * @throws Exception
     */
    public static function checkInstallationNotExists(Database $db, string $tablePrefix): void
    {
        $sql = 'SELECT org_id FROM ' . $tablePrefix . '_organizations';
        $pdoStatement = $db->queryPrepared($sql, array(), false);

        // an error of the statement means that the table doesn't exist and therefore no installation exists
        if ($pdoStatement !== false && $pdoStatement->rowCount() > 0) {
            throw new Exception('INS_INSTALLATION_EXISTS');
        }
    }

    /**
     * Create the content of the configuration file config.php out of the template install/config.php
     * and the values of the installation.
     * @param InstallationConfig $config Input values of the new installation.
     * @return string Returns the content of the configuration file.
     * @throws Exception
     */
    public static function generateConfigFileContent(InstallationConfig $config): string
    {
        $templatePath = ADMIDIO_PATH . FOLDER_INSTALLATION . '/config.php';

        try {
            $configFileContent = FileSystemUtils::readFile($templatePath);
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new Exception('INS_ERROR_OPEN_FILE', array($templatePath));
        }

        /*
         * The values are written into single quoted PHP strings, so a backslash or an apostrophe of
         * a password would otherwise create a configuration file that cannot be parsed.
         */
        $escape = static function (string $value): string {
            return str_replace(array('\\', '\''), array('\\\\', '\\\''), $value);
        };

        // replace placeholders in configuration file structure with data of the installation
        $replaces = array(
            '%DB_TYPE%' => $escape($config->dbType),
            '%DB_HOST%' => $escape($config->dbHost),
            '\'%DB_PORT%\'' => $config->dbPort === null ? 'null' : (string) $config->dbPort, // String -> Int
            '%DB_NAME%' => $escape($config->dbName),
            '%DB_USERNAME%' => $escape($config->dbUsername),
            '%DB_PASSWORD%' => $escape($config->dbPassword),
            '%TABLE_PREFIX%' => $escape($config->tablePrefix),
            '%ROOT_PATH%' => $escape($config->rootUrl),
            '%TIMEZONE%' => $escape($config->timezone)
        );

        return StringUtils::strMultiReplace($configFileContent, $replaces);
    }

    /**
     * Write the configuration file of the new installation. The file is created in a temporary file
     * and moved to its destination afterwards, so an aborted write cannot leave a half written
     * configuration file behind, which the next request would load.
     * @param InstallationConfig $config Input values of the new installation.
     * @param string|null $configPath Path of the configuration file. Default is adm_my_files/config.php.
     * @return string Returns the path of the written configuration file.
     * @throws Exception
     * @throws RuntimeException Throws if the file could not be written.
     */
    public static function writeConfigFile(InstallationConfig $config, ?string $configPath = null): string
    {
        $configPath ??= ADMIDIO_PATH . FOLDER_DATA . '/config.php';
        $configFileContent = self::generateConfigFileContent($config);
        $configFolder = dirname($configPath);

        /*
         * tempnam() would fall back to the temp folder of the system, where the file with the
         * database password does not belong and from where it could not be moved on every system.
         */
        if (!is_writable($configFolder)) {
            throw new RuntimeException('The folder ' . $configFolder . ' is not writable.');
        }

        $temporaryFile = @tempnam($configFolder, 'config');
        if ($temporaryFile === false) {
            throw new RuntimeException('The configuration file ' . $configPath . ' could not be written.');
        }

        try {
            // tempnam() creates the file for the current user only, but the web server has to read it
            @chmod($temporaryFile, 0666 & ~umask());

            if (@file_put_contents($temporaryFile, $configFileContent) === false
                || !@rename($temporaryFile, $configPath)) {
                throw new RuntimeException('The configuration file ' . $configPath . ' could not be written.');
            }
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }

        return $configPath;
    }

    /**
     * Install a new Admidio database. The method creates the database schema, all default data, the
     * first organization with its preferences and basic data, the administrator of that organization
     * and installs the plugins that are delivered with Admidio.
     *
     * The database must be empty, use connectDatabase() to create a connection that was checked for
     * that. The configuration file must already match the given values, because the table prefix,
     * the time zone and the URL of the installation are read from the Admidio constants.
     *
     * @param Database $db Connection to the database of the new installation.
     * @param InstallationConfig $config Input values of the new installation.
     * @return array<string,int> Returns the id of the created organization and of its administrator.
     * @throws Exception
     */
    public static function install(Database $db, InstallationConfig $config): array
    {
        global $gDb, $gL10n, $gLogger, $gCurrentUser, $gCurrentUserId, $gCurrentOrganization, $gCurrentOrgId;
        global $gProfileFields, $gSettingsManager;

        self::validateConfiguration($config);

        /*
         * db.sql and the table constants are built from TABLE_PREFIX, which Admidio derives from the
         * configuration file while it is bootstrapping. The installation can therefore only create
         * the tables of the prefix that the process was started with.
         */
        if ($config->tablePrefix !== TABLE_PREFIX) {
            throw new Exception('INS_DATA_DO_NOT_MATCH', array('config.php'));
        }

        // the entities of the installation are created with the database of the new installation
        $gDb = $db;

        /*
         * The installation is not a change of an existing installation, so it doesn't belong into the
         * changelog. Beside that the tables of the changelog don't exist before db.sql was executed.
         */
        Entity::setLoggingEnabled(false);
        Entity::setHooksEnabled(false);

        $gL10n->setLanguage($config->language);

        // set execution time to 5 minutes because we have a lot to do
        PhpIniUtils::startNewExecutionTimeLimit(300);

        // read data from sql script db.sql and execute all statements to the current database
        self::querySqlFile($db, 'db.sql');

        // create default data
        self::installComponents($db);
        $gCurrentUserId = self::createSystemUser($db);
        self::createDefaultProfileFields($db, $gCurrentUserId);
        self::createRolesRights($db);
        self::createUserRelationTypes($db, $gCurrentUserId);

        // create new organization with its administrator
        $gCurrentOrganization = self::createOrganization($db, $config);
        $gCurrentOrgId = (int) $gCurrentOrganization->getValue('org_id');
        $gProfileFields = new ProfileFields($db, $gCurrentOrgId);
        $administratorId = self::createAdministrator($db, $config, $gCurrentUserId);

        /*
         * No reference assignment: within a function that would only rebind the local alias of the
         * global variable, so $gSettingsManager would stay empty for everything that reads it while
         * the installation is running.
         */
        $gSettingsManager = $gCurrentOrganization->getSettingsManager();
        $gSettingsManager->setMulti(self::defaultOrganizationPreferences($config), false);
        self::disableSoundexSearchIfPgSql($db);
        $gCurrentOrganization->createBasicData($administratorId);

        self::createDefaultRoom($db, $gCurrentUserId);
        self::completeUserData($db, $config, $administratorId, $gCurrentUserId);
        self::createDefaultMenu($db);
        self::installPlugins();

        $gLogger->info('INSTALLATION: Installation successfully complete');

        return array(
            'organizationId' => $gCurrentOrgId,
            'administratorId' => $administratorId
        );
    }

    /**
     * Add the system component and all module components to the database.
     * @param Database $db Connection to the database of the new installation.
     * @return void
     * @throws Exception
     */
    private static function installComponents(Database $db): void
    {
        // add system component to database
        $component = new ComponentUpdate($db);
        $component->setValue('com_type', 'SYSTEM');
        $component->setValue('com_name', 'Admidio Core');
        $component->setValue('com_name_intern', 'CORE');
        $component->setValue('com_version', ADMIDIO_VERSION);
        $component->setValue('com_beta', ADMIDIO_VERSION_BETA);
        $component->setValue('com_update_step', $component->getMaxUpdateStep());
        $component->save();

        // create all modules components
        $sql = 'INSERT INTO ' . TBL_COMPONENTS . '
                       (com_type, com_name, com_name_intern, com_version, com_beta)
                VALUES (\'MODULE\', \'SYS_ANNOUNCEMENTS\',   \'ANNOUNCEMENTS\',  \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_CATEGORIES\',      \'CATEGORIES\',     \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_CATEGORY_REPORT\', \'CATEGORY-REPORT\',\''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_CHANGE_HISTORY\',  \'CHANGELOG\',      \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_EVENTS\',          \'EVENTS\',         \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_DOCUMENTS_FILES\', \'DOCUMENTS-FILES\',\''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_INVENTORY\',        \'INVENTORY\',     \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_FORUM\',           \'FORUM\',          \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_WEBLINKS\',        \'LINKS\',          \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_GROUPS_ROLES\',    \'GROUPS-ROLES\',   \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_CONTACTS\',        \'CONTACTS\',       \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_MESSAGES\',        \'MESSAGES\',       \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_MENU\',            \'MENU\',           \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_ORGANIZATION\',    \'ORGANIZATIONS\',  \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_PHOTOS\',          \'PHOTOS\',         \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_SETTINGS\',        \'PREFERENCES\',    \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_PROFILE\',         \'PROFILE\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_REGISTRATION\',    \'REGISTRATION\',   \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_ROOM_MANAGEMENT\', \'ROOMS\',          \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
                     , (\'MODULE\', \'SYS_PLUGIN_MANAGER\',  \'PLUGINS\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')';
        $db->query($sql); // TODO add more params
    }

    /**
     * Create a hidden system user for internal use. All records that are created by the installation
     * get this user as their creating user.
     * @param Database $db Connection to the database of the new installation.
     * @return int Returns the id of the system user.
     * @throws Exception
     */
    private static function createSystemUser(Database $db): int
    {
        global $gL10n, $gCurrentUser;

        $gCurrentUser = new Entity($db, TBL_USERS, 'usr');
        $gCurrentUser->setValue('usr_login_name', $gL10n->get('SYS_SYSTEM'));
        $gCurrentUser->setValue('usr_valid', '0');
        $gCurrentUser->setValue('usr_timestamp_create', DATETIME_NOW);
        $gCurrentUser->save(false); // no registered user -> UserIdCreate couldn't be filled

        return (int) $gCurrentUser->getValue('usr_id');
    }

    /**
     * Create the categories and profile fields that every Admidio installation has.
     * @param Database $db Connection to the database of the new installation.
     * @param int $systemUserId Id of the system user that creates the records.
     * @return void
     * @throws Exception
     */
    private static function createDefaultProfileFields(Database $db, int $systemUserId): void
    {
        global $gL10n;

        // create organization independent categories
        $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                       (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (NULL, \'' . Uuid::uuid4() . '\', \'USF\', \'BASIC_DATA\', \'SYS_BASIC_DATA\', false, true, 1, ?, ?) -- $systemUserId, DATETIME_NOW';
        $db->queryPrepared($sql, array($systemUserId, DATETIME_NOW));
        $categoryIdMasterData = $db->lastInsertId();

        $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                       (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (NULL, \'' . Uuid::uuid4() . '\', \'USF\', \'SOCIAL_NETWORKS\', \'SYS_SOCIAL_NETWORKS\', false, false, 2, ?, ?) -- $systemUserId, DATETIME_NOW';
        $db->queryPrepared($sql, array($systemUserId, DATETIME_NOW));
        $categoryIdSocialNetworks = $db->lastInsertId();

        $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                       (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (NULL, \'' . Uuid::uuid4() . '\', \'USF\', \'ADDIDIONAL_DATA\', \'INS_ADDIDIONAL_DATA\', false, false, 3, ?, ?) -- $systemUserId, DATETIME_NOW';
        $db->queryPrepared($sql, array($systemUserId, DATETIME_NOW));
        $categoryIdAddidionalData = $db->lastInsertId();

        // create profile fields of category basic data
        $sql = 'INSERT INTO ' . TBL_USER_FIELDS . '
                       (usf_cat_id, usf_uuid, usf_type, usf_name_intern, usf_name, usf_description, usf_system, usf_disabled, usf_required_input, usf_registration, usf_sequence, usf_usr_id_create, usf_timestamp_create)
                VALUES (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'TEXT\',         \'LAST_NAME\',  \'SYS_LASTNAME\',  NULL, true, true, 1, true, 1,  ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'TEXT\',         \'FIRST_NAME\', \'SYS_FIRSTNAME\', NULL, true, true, 1, true, 2,  ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'TEXT\',         \'STREET\',     \'SYS_STREET\',    NULL, false, false, 0, false, 3,  ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'TEXT\',         \'POSTCODE\',   \'SYS_POSTCODE\',  NULL, false, false, 0, false, 4,  ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'TEXT\',         \'CITY\',       \'SYS_CITY\',      NULL, false, false, 0, false, 5,  ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'TEXT\',         \'COUNTRY\',    \'SYS_COUNTRY\',   NULL, false, false, 0, false, 6,  ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'PHONE\',        \'PHONE\',      \'SYS_PHONE\',     NULL, false, false, 0, false, 7,  ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'PHONE\',        \'MOBILE\',     \'SYS_MOBILE\',    NULL, false, false, 0, false, 8,  ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'DATE\',         \'BIRTHDAY\',   \'SYS_BIRTHDAY\',  NULL, false, false, 0, false, 10, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'RADIO_BUTTON\', \'GENDER\',     \'SYS_GENDER\',    NULL, false, false, 0, false, 11, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'EMAIL\',        \'EMAIL\',      \'SYS_EMAIL\',     NULL, true, false, 2, true, 12, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdMasterData . ', \'' . Uuid::uuid4() . '\', \'URL\',          \'WEBSITE\',    \'SYS_WEBSITE\',   NULL, false, false, 0, false, 13, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdAddidionalData . ', \'' . Uuid::uuid4() . '\', \'CHECKBOX\', \'DATA_PROTECTION_PERMISSION\', \'SYS_DATA_PROTECTION_PERMISSION\', \'' . $gL10n->get('SYS_DATA_PROTECTION_PERMISSION_DESC') . '\', false, false, 2, false, 14, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')';
        $db->query($sql); // TODO add more params

        // add gender options to database
        $sql = 'INSERT INTO ' . TBL_USER_FIELD_OPTIONS . '
                       (ufo_usf_id, ufo_value, ufo_sequence)
                VALUES ((SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_cat_id = ' . $categoryIdMasterData . ' AND usf_name_intern = \'GENDER\'), \'gender-male|SYS_MALE\', 1)
                     , ((SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_cat_id = ' . $categoryIdMasterData . ' AND usf_name_intern = \'GENDER\'), \'gender-female|SYS_FEMALE\', 2)
                     , ((SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_cat_id = ' . $categoryIdMasterData . ' AND usf_name_intern = \'GENDER\'), \'gender-trans|SYS_DIVERSE\', 3)';
        $db->query($sql);

        // create profile fields of category social networks
        $sql = 'INSERT INTO ' . TBL_USER_FIELDS . '
                       (usf_cat_id, usf_uuid, usf_type, usf_name_intern, usf_name, usf_description, usf_icon, usf_url, usf_system, usf_sequence, usf_usr_id_create, usf_timestamp_create)
                VALUES (' . $categoryIdSocialNetworks . ', \'' . Uuid::uuid4() . '\', \'TEXT\', \'BLUESKY\',   \'SYS_BLUESKY\',   \'' . $gL10n->get('SYS_SOCIAL_NETWORK_FIELD_URL_DESC') . '\', \'bluesky\',   \'https://bsky.app/profile/#user_content#\',     false, 1, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdSocialNetworks . ', \'' . Uuid::uuid4() . '\', \'TEXT\', \'FACEBOOK\',  \'SYS_FACEBOOK\',  \'' . $gL10n->get('SYS_SOCIAL_NETWORK_FIELD_URL_DESC') . '\', \'facebook\',  \'https://www.facebook.com/#user_content#\',     false, 2, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdSocialNetworks . ', \'' . Uuid::uuid4() . '\', \'TEXT\', \'INSTAGRAM\', \'SYS_INSTAGRAM\', \'' . $gL10n->get('SYS_SOCIAL_NETWORK_FIELD_URL_DESC') . '\', \'instagram\', \'https://www.instagram.com/#user_content#\',    false, 3, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdSocialNetworks . ', \'' . Uuid::uuid4() . '\', \'TEXT\', \'LINKEDIN\',  \'SYS_LINKEDIN\',  \'' . $gL10n->get('SYS_SOCIAL_NETWORK_FIELD_URL_DESC') . '\', \'linkedin\',  \'https://www.linkedin.com/in/#user_content#\',  false, 4, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdSocialNetworks . ', \'' . Uuid::uuid4() . '\', \'TEXT\', \'MASTODON\',  \'SYS_MASTODON\',  \'' . $gL10n->get('SYS_SOCIAL_NETWORK_FIELD_URL_DESC') . '\', \'mastodon\',  \'https://mastodon.social/#user_content#\',      false, 5, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (' . $categoryIdSocialNetworks . ', \'' . Uuid::uuid4() . '\', \'TEXT\', \'XING\',      \'SYS_XING\',      \'' . $gL10n->get('SYS_SOCIAL_NETWORK_FIELD_URL_DESC') . '\', null,          \'https://www.xing.com/profile/#user_content#\', false, 6, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')';
        $db->query($sql); // TODO add more params
    }

    /**
     * Create the roles rights that Admidio assigns to roles.
     * @param Database $db Connection to the database of the new installation.
     * @return void
     * @throws Exception
     */
    private static function createRolesRights(Database $db): void
    {
        $sql = 'INSERT INTO ' . TBL_ROLES_RIGHTS . '
                       (ror_name_intern, ror_table)
                VALUES (\'folder_view\',   \'' . TBL_FOLDERS . '\')
                     , (\'folder_upload\', \'' . TBL_FOLDERS . '\')
                     , (\'category_view\', \'' . TBL_CATEGORIES . '\')
                     , (\'event_participation\', \'' . TBL_CATEGORIES . '\')
                     , (\'menu_view\',     \'' . TBL_MENU . '\')
                     , (\'sso_saml_access\', \'' . TBL_SAML_CLIENTS . '\')
                     , (\'sso_oidc_access\', \'' . TBL_OIDC_CLIENTS . '\')
                     ';
        $db->queryPrepared($sql);

        // add edit categories right with reference to parent right
        $sql = 'INSERT INTO ' . TBL_ROLES_RIGHTS . '
                       (ror_name_intern, ror_table, ror_ror_id_parent)
                VALUES (\'category_edit\', \'' . TBL_CATEGORIES . '\', (SELECT rr.ror_id FROM ' . TBL_ROLES_RIGHTS . ' rr WHERE rr.ror_name_intern = \'category_view\'))';
        $db->queryPrepared($sql);
    }

    /**
     * Create the user relation types that Admidio delivers.
     * @param Database $db Connection to the database of the new installation.
     * @param int $systemUserId Id of the system user that creates the records.
     * @return void
     * @throws Exception
     */
    private static function createUserRelationTypes(Database $db, int $systemUserId): void
    {
        $sql = 'INSERT INTO ' . TBL_USER_RELATION_TYPES . '
                       (urt_id, urt_uuid, urt_name, urt_name_male, urt_name_female, urt_id_inverse, urt_usr_id_create, urt_timestamp_create)
                VALUES (1, \'' . Uuid::uuid4() . '\', \'SYS_PARENT\',      \'SYS_FATHER\',           \'SYS_MOTHER\',          null, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (2, \'' . Uuid::uuid4() . '\', \'SYS_CHILD\',       \'SYS_SON\',              \'SYS_DAUGHTER\',           1, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (3, \'' . Uuid::uuid4() . '\', \'SYS_SIBLING\',     \'SYS_BROTHER\',          \'SYS_SISTER\',             3, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (4, \'' . Uuid::uuid4() . '\', \'SYS_SPOUSE\',      \'SYS_HUSBAND\',          \'SYS_WIFE\',               4, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (5, \'' . Uuid::uuid4() . '\', \'SYS_COHABITANT\',  \'SYS_COHABITANT_MALE\',  \'SYS_COHABITANT_FEMALE\',  5, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (6, \'' . Uuid::uuid4() . '\', \'SYS_COMPANION\',   \'SYS_BOYFRIEND\',        \'SYS_GIRLFRIEND\',         6, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (7, \'' . Uuid::uuid4() . '\', \'SYS_SUPERIOR\',    \'SYS_SUPERIOR_MALE\',    \'SYS_SUPERIOR_FEMALE\', null, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')
                     , (8, \'' . Uuid::uuid4() . '\', \'SYS_SUBORDINATE\', \'SYS_SUBORDINATE_MALE\', \'SYS_SUBORDINATE_FEMALE\', 7, ' . $systemUserId . ', \'' . DATETIME_NOW . '\')';
        $db->query($sql); // TODO add more params

        $sql = 'UPDATE ' . TBL_USER_RELATION_TYPES . '
                   SET urt_id_inverse = 2
                 WHERE urt_id = 1';
        $db->queryPrepared($sql);

        $sql = 'UPDATE ' . TBL_USER_RELATION_TYPES . '
                   SET urt_id_inverse = 8
                 WHERE urt_id = 7';
        $db->queryPrepared($sql);
    }

    /**
     * Create the first organization of the installation.
     * @param Database $db Connection to the database of the new installation.
     * @param InstallationConfig $config Input values of the new installation.
     * @return Organization Returns the created organization.
     * @throws Exception
     */
    private static function createOrganization(Database $db, InstallationConfig $config): Organization
    {
        $organization = new Organization($db, $config->organizationShortName);
        $organization->setValue('org_longname', $config->organizationName);
        $organization->setValue('org_shortname', $config->organizationShortName);
        $organization->setValue('org_homepage', $config->rootUrl);
        $organization->setValue('org_email_administrator', $config->organizationEmail);
        $organization->save();

        return $organization;
    }

    /**
     * Create the administrator of the new organization. Only the login data is set here, the profile
     * fields of the user are filled after the basic data of the organization was created.
     * @param Database $db Connection to the database of the new installation.
     * @param InstallationConfig $config Input values of the new installation.
     * @param int $systemUserId Id of the system user that creates the records.
     * @return int Returns the id of the administrator.
     * @throws Exception
     */
    private static function createAdministrator(Database $db, InstallationConfig $config, int $systemUserId): int
    {
        global $gProfileFields;

        $administrator = new User($db, $gProfileFields);
        $administrator->setValue('usr_login_name', $config->adminLogin);
        $administrator->setPassword($config->adminPassword);
        $administrator->setValue('usr_usr_id_create', $systemUserId);
        $administrator->setValue('usr_timestamp_create', DATETIME_NOW);
        $administrator->save(false); // no registered user -> UserIdCreate couldn't be filled

        return (int) $administrator->getValue('usr_id');
    }

    /**
     * Read the default preferences of an organization and set the values that come from the input of
     * the installation.
     * @param InstallationConfig $config Input values of the new installation.
     * @return array<string,mixed> Returns the preferences of the new organization.
     * @throws Exception
     */
    private static function defaultOrganizationPreferences(InstallationConfig $config): array
    {
        global $gPasswordHashAlgorithm;

        // read all preferences from preferences.php
        require(ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/preferences.php');

        // set some specific preferences whose values came from the input of the installation
        $defaultOrgPreferences['system_language'] = $config->language;

        // calculate the best cost value for your server performance
        $benchmarkResults = PasswordUtils::costBenchmark($gPasswordHashAlgorithm);
        if (is_int($benchmarkResults['options']['cost'])) {
            $defaultOrgPreferences['system_hashing_cost'] = $benchmarkResults['options']['cost'];
        }

        return $defaultOrgPreferences;
    }

    /**
     * Create the default room of the room module.
     * @param Database $db Connection to the database of the new installation.
     * @param int $systemUserId Id of the system user that creates the records.
     * @return void
     * @throws Exception
     */
    private static function createDefaultRoom(Database $db, int $systemUserId): void
    {
        global $gL10n;

        $sql = 'INSERT INTO ' . TBL_ROOMS . '
                       (room_uuid, room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
                VALUES (\'' . Uuid::uuid4() . '\', ?, ?, 15, ?, ?) -- $gL10n->get(\'INS_CONFERENCE_ROOM\'), $gL10n->get(\'INS_DESCRIPTION_CONFERENCE_ROOM\'), $systemUserId, DATETIME_NOW';
        $params = array(
            $gL10n->get('INS_CONFERENCE_ROOM'),
            $gL10n->get('INS_DESCRIPTION_CONFERENCE_ROOM'),
            $systemUserId,
            DATETIME_NOW
        );
        $db->queryPrepared($sql, $params);
    }

    /**
     * Fill the profile fields of the administrator and of the system user. This is only possible
     * after the basic data of the organization exists, because the profile fields are checked
     * against the rights of the current user.
     * @param Database $db Connection to the database of the new installation.
     * @param InstallationConfig $config Input values of the new installation.
     * @param int $administratorId Id of the administrator of the new organization.
     * @param int $systemUserId Id of the system user that creates the records.
     * @return void
     * @throws Exception
     */
    private static function completeUserData(Database $db, InstallationConfig $config, int $administratorId, int $systemUserId): void
    {
        global $gL10n, $gProfileFields, $gCurrentUser;

        // first create a user object "current user" with administrator rights
        // because administrator is allowed to edit firstname and lastname
        $gCurrentUser = new User($db, $gProfileFields, $administratorId);
        $gCurrentUser->saveChangesWithoutRights();
        $gCurrentUser->setValue('LAST_NAME', $config->adminLastName);
        $gCurrentUser->setValue('FIRST_NAME', $config->adminFirstName);
        $gCurrentUser->setValue('EMAIL', $config->adminEmail);
        $gCurrentUser->save(false);

        // now create a full user object for system user
        $systemUser = new User($db, $gProfileFields, $systemUserId);
        $systemUser->saveChangesWithoutRights();
        $systemUser->setValue('LAST_NAME', $gL10n->get('SYS_SYSTEM'));
        $systemUser->save(false); // no registered user -> UserIdCreate couldn't be filled

        // now set current user to system user
        $gCurrentUser->readDataById($systemUserId);
    }

    /**
     * Create the menu entries of a standard installation.
     * @param Database $db Connection to the database of the new installation.
     * @return void
     * @throws Exception
     */
    private static function createDefaultMenu(Database $db): void
    {
        $sql = 'INSERT INTO ' . TBL_MENU . '
                       (men_com_id, men_men_id_parent, men_uuid, men_node, men_order, men_standard, men_name_intern, men_url, men_icon, men_name, men_description)
                VALUES (NULL, NULL, \'' . Uuid::uuid4() . '\', true, 1, true, \'modules\', NULL, \'\', \'SYS_MODULES\', \'\')
                     , (NULL, NULL, \'' . Uuid::uuid4() . '\', true, 2, true, \'administration\', NULL, \'\', \'SYS_ADMINISTRATION\', \'\')
                     , (NULL, NULL, \'' . Uuid::uuid4() . '\', true, 3, true, \'extensions\', NULL, \'\', \'SYS_EXTENSIONS\', \'\')
                     , (NULL, 1, \'' . Uuid::uuid4() . '\', false, 1, true, \'overview\', \''.FOLDER_MODULES.'/overview.php\', \'bi-house-door-fill\', \'SYS_OVERVIEW\', \'\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'ANNOUNCEMENTS\'), 1, \'' . Uuid::uuid4() . '\', false, 2, true, \'announcements\', \''.FOLDER_MODULES.'/announcements.php\', \'newspaper\', \'SYS_ANNOUNCEMENTS\', \'SYS_ANNOUNCEMENTS_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'EVENTS\'), 1, \'' . Uuid::uuid4() . '\', false, 3, true, \'events\', \''.FOLDER_MODULES.'/events/events.php\', \'calendar-week-fill\', \'SYS_EVENTS\', \'SYS_EVENTS_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'MESSAGES\'), 1, \'' . Uuid::uuid4() . '\', false, 4, true, \'messages\', \''.FOLDER_MODULES.'/messages/messages.php\', \'envelope-fill\', \'SYS_MESSAGES\', \'SYS_MESSAGES_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'GROUPS-ROLES\'), 1, \'' . Uuid::uuid4() . '\', false, 5, true, \'groups-roles\', \''.FOLDER_MODULES.'/groups-roles/groups_roles.php\', \'people-fill\', \'SYS_GROUPS_ROLES\', \'SYS_GROUPS_ROLES_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'CONTACTS\'), 1, \'' . Uuid::uuid4() . '\', false, 6, true, \'contacts\', \''.FOLDER_MODULES.'/contacts/contacts.php\', \'person-vcard-fill\', \'SYS_CONTACTS\', \'SYS_CONTACTS_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'DOCUMENTS-FILES\'), 1, \'' . Uuid::uuid4() . '\', false, 7, true, \'documents-files\', \''.FOLDER_MODULES.'/documents-files.php\', \'file-earmark-arrow-down-fill\', \'SYS_DOCUMENTS_FILES\', \'SYS_DOCUMENTS_FILES_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'INVENTORY\'), 1, \'' . Uuid::uuid4() . '\', false, 8, true, \'inventory\', \''.FOLDER_MODULES.'/inventory.php\', \'box-seam-fill\', \'SYS_INVENTORY\', \'SYS_INVENTORY_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'PHOTOS\'), 1, \'' . Uuid::uuid4() . '\', false, 9, true, \'photo\', \''.FOLDER_MODULES.'/photos/photos.php\', \'image-fill\', \'SYS_PHOTOS\', \'SYS_PHOTOS_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'CATEGORY-REPORT\'), 1, \'' . Uuid::uuid4() . '\', false, 10, true, \'category-report\', \''.FOLDER_MODULES.'/category-report/category_report.php\', \'list-stars\', \'SYS_CATEGORY_REPORT\', \'SYS_CATEGORY_REPORT_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'LINKS\'), 1, \'' . Uuid::uuid4() . '\', false, 11, true, \'weblinks\', \''.FOLDER_MODULES.'/links/links.php\', \'link-45deg\', \'SYS_WEBLINKS\', \'SYS_WEBLINKS_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'FORUM\'), 1, \'' . Uuid::uuid4() . '\', false, 12, true, \'forum\', \''.FOLDER_MODULES.'/forum.php\', \'chat-dots-fill\', \'SYS_FORUM\', \'SYS_FORUM_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'PREFERENCES\'), 2, \'' . Uuid::uuid4() . '\', false, 1, true, \'orgprop\', \''.FOLDER_MODULES.'/preferences.php\', \'gear-fill\', \'SYS_SETTINGS\', \'ORG_ORGANIZATION_PROPERTIES_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'REGISTRATION\'), 2, \'' . Uuid::uuid4() . '\', false, 2, true, \'registration\', \''.FOLDER_MODULES.'/registration.php\', \'card-checklist\', \'SYS_REGISTRATIONS\', \'SYS_MANAGE_NEW_REGISTRATIONS_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'MENU\'), 2, \'' . Uuid::uuid4() . '\', false, 3, true, \'menu\', \''.FOLDER_MODULES.'/menu.php\', \'menu-button-wide-fill\', \'SYS_MENU\', \'SYS_MENU_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'ORGANIZATIONS\'), 2, \'' . Uuid::uuid4() . '\', false, 4, true, \'organization\', \''.FOLDER_MODULES.'/organizations.php\', \'diagram-3-fill\', \'SYS_ORGANIZATION\', \'SYS_ORGANIZATION_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'PLUGINS\'), 2, \'' . Uuid::uuid4() . '\', false, 5, true, \'plugins\', \''.FOLDER_MODULES.'/plugins.php\', \'puzzle\', \'SYS_PLUGIN_MANAGER\', \'SYS_PLUGIN_MANAGER_DESC\')
                     , ((SELECT com_id FROM '.TBL_COMPONENTS.' WHERE com_name_intern = \'CHANGELOG\'), 2, \'' . Uuid::uuid4() . '\', false, 6, true, \'changelog\', \''.FOLDER_MODULES.'/changelog/changelog.php\', \'clock-history\', \'SYS_CHANGE_HISTORY\', \'SYS_CHANGE_HISTORY_DESC\')
                     ';
        $db->query($sql);
    }

    /**
     * Install all overview plugins that are delivered with Admidio.
     * @return void
     * @throws Exception
     */
    private static function installPlugins(): void
    {
        $pluginManager = new PluginManager();
        $plugins = $pluginManager->getAvailablePlugins();

        foreach ($plugins as $plugin) {
            // check, if the plugin has an interface, if not, scip it
            if (!isset($plugin['interface']) || $plugin['interface'] == null) {
                continue;
            }
            // check if the plugin is an overview plugin, if so, install it
            $instance = $plugin['interface']::getInstance();
            if ($instance->isAdmidioPlugin()) {
                // Install the overview plugin
                $instance->doInstall();
            }
        }
    }
}
