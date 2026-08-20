<?php
/**
 * Admidio Bootstrap for Regression Tests
 *
 * Initializes Admidio's core infrastructure including:
 * - Database connection and wrapper
 * - Logger initialization
 * - Session and user context
 * - Configuration loading
 *
 * This bootstrap enables full integration testing with Admidio's
 * Entity and Service classes.
 */

// Prevent multiple loads
if (defined('ADMIDIO_TEST_BOOTSTRAP_LOADED')) {
    return;
}
define('ADMIDIO_TEST_BOOTSTRAP_LOADED', true);

// Get the Admidio root path
$admidioRoot = dirname(__DIR__);

// Load environment configuration
$envFile = $admidioRoot . '/.env.test';
if (!file_exists($envFile)) {
    throw new RuntimeException(
        "Test environment not configured.\n"
        . "Copy .env.test.example to .env.test and run: php tests/bin/setup-test-env.php"
    );
}

// Load environment variables
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (empty($line) || strpos($line, '#') === 0) {
        continue;
    }
    if (strpos($line, '=') === false) {
        continue;
    }
    [$name, $value] = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value);
    putenv("$name=$value");
    $_ENV[$name] = $value;
}

// Verify test environment
if (!getenv('TEST_DATABASE_ENGINE')) {
    throw new RuntimeException('TEST_DATABASE_ENGINE not set in .env.test');
}

// Load Admidio's autoloader
require_once $admidioRoot . '/vendor/autoload.php';

// Define constants needed by Admidio infrastructure
// These are normally defined in system/bootstrap/constants.php
const MIN_PHP_VERSION = '8.2.0';
const ADMIDIO_VERSION_MAIN = 5;
const ADMIDIO_VERSION_MINOR = 1;
const ADMIDIO_VERSION_PATCH = 0;
const ADMIDIO_VERSION_BETA = 0;
const ADMIDIO_VERSION = ADMIDIO_VERSION_MAIN . '.' . ADMIDIO_VERSION_MINOR . '.' . ADMIDIO_VERSION_PATCH;
const ADMIDIO_HOMEPAGE = 'https://www.admidio.org/';
const HTTPS = false;
const FOLDER_DATA = '/adm_my_files';
const FOLDER_TEMP_DATA = '/adm_my_files/temp';
const FOLDER_SYSTEM = '/system';
const FOLDER_INSTALLATION = '/install';
const FOLDER_LIBS = '/libs';
const FOLDER_LANGUAGES = '/languages';
const FOLDER_THEMES = '/themes';
const FOLDER_MODULES = '/modules';
const FOLDER_PLUGINS = '/plugins';
const DATE_MAX = '9999-12-31';
const TABLE_PREFIX = 'adm';

// Define as PHP define() since they depend on runtime values
define('ADMIDIO_VERSION_TEXT', ADMIDIO_VERSION);
define('ADMIDIO_PATH', $admidioRoot);
define('DATE_NOW', date('Y-m-d'));
define('DATETIME_NOW', date('Y-m-d H:i:s'));
define('SCRIPT_START_TIME', microtime(true));
define('DOMAIN', 'admidio.test');
define('ADMIDIO_URL', 'http://admidio.test');
define('ADMIDIO_URL_PATH', '');
define('SCHEME', 'http');
define('HOST', 'admidio.test');

// Database table constants that entities require
const TBL_ANNOUNCEMENTS = TABLE_PREFIX . '_announcements';
const TBL_AUTO_LOGIN = TABLE_PREFIX . '_auto_login';
const TBL_CATEGORIES = TABLE_PREFIX . '_categories';
const TBL_CATEGORY_REPORT = TABLE_PREFIX . '_category_report';
const TBL_COMPONENTS = TABLE_PREFIX . '_components';
const TBL_EVENTS = TABLE_PREFIX . '_events';
const TBL_FILES = TABLE_PREFIX . '_files';
const TBL_FOLDERS = TABLE_PREFIX . '_folders';
const TBL_FORUM_TOPICS = TABLE_PREFIX . '_forum_topics';
const TBL_FORUM_POSTS = TABLE_PREFIX . '_forum_posts';
const TBL_GUESTBOOK = TABLE_PREFIX . '_guestbook';
const TBL_GUESTBOOK_COMMENTS = TABLE_PREFIX . '_guestbook_comments';
const TBL_IDS = TABLE_PREFIX . '_ids';
const TBL_LINKS = TABLE_PREFIX . '_links';
const TBL_LIST_COLUMNS = TABLE_PREFIX . '_list_columns';
const TBL_LISTS = TABLE_PREFIX . '_lists';
const TBL_LOG_CHANGES = TABLE_PREFIX . '_log_changes';
const TBL_MEMBERS = TABLE_PREFIX . '_members';
const TBL_MENU = TABLE_PREFIX . '_menu';
const TBL_MESSAGES = TABLE_PREFIX . '_messages';
const TBL_MESSAGES_ATTACHMENTS = TABLE_PREFIX . '_messages_attachments';
const TBL_MESSAGES_CONTENT = TABLE_PREFIX . '_messages_content';
const TBL_MESSAGES_RECIPIENTS = TABLE_PREFIX . '_messages_recipients';
const TBL_OIDC_CLIENTS = TABLE_PREFIX . '_oidc_clients';
const TBL_OIDC_ACCESS_TOKENS = TABLE_PREFIX . '_oidc_access_tokens';
const TBL_OIDC_REFRESH_TOKENS = TABLE_PREFIX . '_oidc_refresh_tokens';
const TBL_OIDC_AUTH_CODES = TABLE_PREFIX . '_oidc_auth_codes';
const TBL_ORGANIZATIONS = TABLE_PREFIX . '_organizations';
const TBL_PHOTOS = TABLE_PREFIX . '_photos';
const TBL_PREFERENCES = TABLE_PREFIX . '_preferences';
const TBL_REGISTRATIONS = TABLE_PREFIX . '_registrations';
const TBL_ROLE_DEPENDENCIES = TABLE_PREFIX . '_role_dependencies';
const TBL_ROLES = TABLE_PREFIX . '_roles';
const TBL_ROLES_RIGHTS = TABLE_PREFIX . '_roles_rights';
const TBL_ROLES_RIGHTS_DATA = TABLE_PREFIX . '_roles_rights_data';
const TBL_ROOMS = TABLE_PREFIX . '_rooms';
const TBL_SAML_CLIENTS = TABLE_PREFIX . '_saml_clients';
const TBL_SSO_KEYS = TABLE_PREFIX . '_sso_keys';
const TBL_USERS = TABLE_PREFIX . '_users';
const TBL_USER_DATA = TABLE_PREFIX . '_user_data';
const TBL_USER_LOG = TABLE_PREFIX . '_user_log';
const TBL_USER_FIELDS = TABLE_PREFIX . '_user_fields';
const TBL_USER_FIELD_OPTIONS = TABLE_PREFIX . '_user_field_select_options';
const TBL_USER_RELATIONS = TABLE_PREFIX . '_user_relations';
const TBL_USER_RELATION_TYPES = TABLE_PREFIX . '_user_relation_types';
const TBL_SESSIONS = TABLE_PREFIX . '_sessions';
const TBL_TEXTS = TABLE_PREFIX . '_texts';
const TBL_INVENTORY_ITEM_DATA = TABLE_PREFIX . '_inventory_item_data';
const TBL_INVENTORY_FIELDS = TABLE_PREFIX . '_inventory_fields';
const TBL_INVENTORY_FIELD_OPTIONS = TABLE_PREFIX . '_inventory_field_select_options';
const TBL_INVENTORY_ITEMS = TABLE_PREFIX . '_inventory_items';
const TBL_INVENTORY_ITEM_BORROW_DATA = TABLE_PREFIX . '_inventory_item_borrow_data';

// Password settings
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_GEN_LENGTH = 16;
const PASSWORD_GEN_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

// Load system bootstrap functions (getExecutionTime, etc)
require_once $admidioRoot . '/system/bootstrap/function.php';

// Initialize Logger FIRST (minimal mock for testing)
// Database constructor calls $gLogger->debug(), so it must exist before DB init
$gLogger = new TestLogger();
$GLOBALS['gLogger'] = $gLogger;

// Initialize empty session/user for now
// Full user context will be set in individual tests as needed
$gCurrentUser = null;
$gCurrentSession = null;
$GLOBALS['gCurrentUser'] = $gCurrentUser;
$GLOBALS['gCurrentSession'] = $gCurrentSession;

// Initialize Language for Installation service
$gL10n = new \Admidio\Infrastructure\Language('en');
$GLOBALS['gL10n'] = $gL10n;

// Initialize global password hash algorithm
$gPasswordHashAlgorithm = 'DEFAULT';
$GLOBALS['gPasswordHashAlgorithm'] = $gPasswordHashAlgorithm;

// Now get database configuration and initialize
$dbConfig = getTestDatabaseConfig();

// Map engine name to PDO_ENGINE constant for DB_TYPE
$engineMap = [
    'mariadb' => \Admidio\Infrastructure\Database::PDO_ENGINE_MYSQL,
    'mysql' => \Admidio\Infrastructure\Database::PDO_ENGINE_MYSQL,
    'postgres' => \Admidio\Infrastructure\Database::PDO_ENGINE_PGSQL,
];
define('DB_TYPE', $engineMap[$dbConfig['engine']] ?? \Admidio\Infrastructure\Database::PDO_ENGINE_MYSQL);

try {
    // Initialize Admidio Database class
    // Logger is now available globally for Database to use
    $gDb = createTestDatabase($dbConfig);
} catch (\Exception $e) {
    throw new RuntimeException(
        "Failed to initialize test database.\n"
        . "Ensure Docker services are running: docker-compose -f docker-compose.test.yml up -d\n"
        . "Error: " . $e->getMessage()
    );
}

// Make database available globally
$GLOBALS['gDb'] = $gDb;

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get test database configuration
 */
function getTestDatabaseConfig(): array
{
    $engine = getenv('TEST_DATABASE_ENGINE') ?: 'mariadb';
    $prefix = 'TEST_DB_' . strtoupper($engine);

    return [
        'engine' => $engine,
        'host' => getenv($prefix . '_HOST') ?: 'localhost',
        'port' => (int)(getenv($prefix . '_PORT') ?: ($engine === 'postgres' ? 5432 : 3306)),
        'user' => getenv($prefix . '_USER') ?: 'admidio',
        'password' => getenv($prefix . '_PASS') ?: '',
        'database' => getenv($prefix . '_NAME') ?: 'admidio_test',
    ];
}

/**
 * Create test database instance
 */
function createTestDatabase(array $config): \Admidio\Infrastructure\Database
{
    // Map config engine names to Database constants
    $engine = $config['engine'];
    if ($engine === 'mariadb') {
        $engine = \Admidio\Infrastructure\Database::DB_TYPE_MARIADB;
    } elseif ($engine === 'postgres') {
        $engine = \Admidio\Infrastructure\Database::DB_TYPE_PGSQL;
    } else {
        $engine = \Admidio\Infrastructure\Database::DB_TYPE_MYSQL;
    }

    return new \Admidio\Infrastructure\Database(
        $engine,
        $config['host'],
        $config['port'],
        $config['database'],
        $config['user'],
        $config['password']
    );
}

/**
 * Minimal logger for testing
 * Implements the methods Admidio Database needs
 */
class TestLogger
{
    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }

    /**
     * Log a warning
     */
    public function warning(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }

    /**
     * Log an error
     */
    public function error(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }

    /**
     * Log a notice message
     */
    public function notice(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }
}
