<?php
/**
 * What every part of the updater needs: the configuration of the installation, the texts, the
 * state of an update with its lock, the updater cookie and the CSRF tokens, the pages, and versions.
 *
 * The updater replaces src/ and vendor/, so it uses no Admidio class: only adm_my_files/config.php,
 * the database and PHP itself. Its functions live in the namespace AdmidioCoreUpdater, so that they
 * never collide with Admidio's global functions in a request that loaded both. Everything here must
 * stay compatible with the lowest PHP version of any supported source release (8.2 for all 5.x).
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** The schema of state.json this updater writes; it reads every version up to this one. */
const SCHEMA_VERSION = 1;

/** The states before maintenance mode: an update in them has changed nothing yet. */
const STATES_BEFORE_CHANGES = array(
    'new', 'checking', 'checked', 'downloading', 'downloaded', 'prepared', 'extracting', 'planning', 'review', 'ready'
);

/** The states in which an update is over. */
const STATES_FINISHED = array('completed', 'rolled_back', 'cancelled');

/** An abandoned update that never changed anything is removed after this many seconds. */
const ABANDONED_AFTER = 86400;

/** An updater cookie expires this many seconds after its last use. */
const CREDENTIAL_LIFETIME = 86400;

/** Where the releases are announced. */
const UPDATE_INFO_URL = 'https://www.admidio.org/update.txt';

/**
 * An error the updater reports to the administrator.
 *
 * The message is either a text key (SYS_CORE_UPDATE_*) with its parameters, which the page
 * translates, or an English diagnostic for a situation in which no texts can be read yet. Why
 * exactly something failed - which archive entry, which field - is the English **detail**, which
 * goes into the log and the command line output.
 */
final class UpdaterException extends RuntimeException
{
    /**
     * @param string $message A text key or an English diagnostic.
     * @param array<int,string> $params
     * @param Throwable|null $previous
     * @param string $detail
     */
    public function __construct(
        string $message,
        private readonly array $params = array(),
        ?Throwable $previous = null,
        private readonly string $detail = ''
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * A failure with a text key for the administrator and an English diagnostic for the log.
     * @param array<int,string> $params
     */
    public static function withDetail(string $key, string $detail, array $params = array()): self
    {
        return new self($key, $params, null, $detail);
    }

    /** @return array<int,string> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    /**
     * The message for the administrator, translated if it is a text key.
     * @param array{language: string, texts: array<string,string>}|null $texts
     */
    public function getText(?array $texts): string
    {
        return ($texts !== null && isTextKey($this->getMessage())) ? text($texts, $this->getMessage(), $this->params) : $this->getMessage();
    }
}

// ------------------------------------------------------------------------------------------------
// Configuration
// ------------------------------------------------------------------------------------------------

/**
 * Read adm_my_files/config.php of an installation, in a scope of its own.
 * @return array<string,mixed> See configFromValues().
 * @throws UpdaterException Admidio is not installed there.
 */
function loadConfig(string $rootPath): array
{
    $rootPath = rtrim(str_replace('\\', '/', $rootPath), '/');
    $file = $rootPath . '/adm_my_files/config.php';
    if (!is_file($file)) {
        throw new UpdaterException('Admidio is not installed here: adm_my_files/config.php is missing.');
    }

    $values = (static function (string $configFile): array {
        include $configFile;

        return get_defined_vars();
    })($file);

    return configFromValues($rootPath, $values);
}

/**
 * The configuration from the variables of a config.php, with the defaults of init_globals.php.
 *
 * The two settings that change where updates come from and which key checks them are development
 * aids and are ignored unless $gDebug is set, so that a production installation cannot be pointed
 * elsewhere by a single line.
 * @param array<string,mixed> $values
 * @return array{rootPath: string, dbType: string, dbHost: string, dbPort: int|null, dbName: string, dbUser: string,
 *               dbPassword: string, tablePrefix: string, baseUrl: string, debug: bool, serverUrl: string,
 *               publicKeyFile: string, timezone: string}
 */
function configFromValues(string $rootPath, array $values): array
{
    $debug = !empty($values['gDebug']);
    $port = $values['g_adm_port'] ?? null;

    return array(
        'rootPath' => rtrim(str_replace('\\', '/', $rootPath), '/'),
        'dbType' => (string)($values['gDbType'] ?? 'mysql'),
        'dbHost' => (string)($values['g_adm_srv'] ?? ''),
        'dbPort' => ($port === null || $port === '') ? null : (int)$port,
        'dbName' => (string)($values['g_adm_db'] ?? ''),
        'dbUser' => (string)($values['g_adm_usr'] ?? ''),
        'dbPassword' => (string)($values['g_adm_pw'] ?? ''),
        'tablePrefix' => (string)($values['g_tbl_praefix'] ?? 'adm'),
        'baseUrl' => rtrim((string)($values['g_root_path'] ?? ''), '/'),
        'debug' => $debug,
        'serverUrl' => $debug ? (string)($values['gCoreUpdateServerUrl'] ?? '') : '',
        'publicKeyFile' => $debug ? (string)($values['gCoreUpdatePublicKeyFile'] ?? '') : '',
        'timezone' => (string)($values['gTimezone'] ?? 'Europe/Berlin')
    );
}

/**
 * The cookie prefix of the installation, the same as COOKIE_PREFIX. constants.php decides between
 * two forms by isset($gDebug), but init_globals.php always sets $gDebug first.
 * @param array<string,mixed> $config
 */
function cookiePrefix(array $config): string
{
    return (string)preg_replace('/\W/', '_', 'ADMIDIO_' . $config['dbName'] . '_' . $config['tablePrefix']);
}

/**
 * The path part of the installation URL, for example "/admidio", or an empty string.
 * @param array<string,mixed> $config
 */
function basePath(array $config): string
{
    return rtrim((string)parse_url($config['baseUrl'], PHP_URL_PATH), '/');
}

/** @param array<string,mixed> $config */
function isHttps(array $config): bool
{
    return strtolower((string)parse_url($config['baseUrl'], PHP_URL_SCHEME)) === 'https';
}

/**
 * Where the releases are announced: the official update.txt, or in debug mode the one of the
 * test server of $gCoreUpdateServerUrl.
 * @param array<string,mixed> $config
 */
function updateInfoUrl(array $config): string
{
    return $config['serverUrl'] !== '' ? rtrim($config['serverUrl'], '/') . '/update.txt' : UPDATE_INFO_URL;
}

/**
 * The file with the public key that signs update.txt: the one the updater ships with, or in debug
 * mode the test key of $gCoreUpdatePublicKeyFile, relative to the installation if not absolute.
 * @param array<string,mixed> $config
 */
function publicKeyFile(array $config, string $updaterPath): string
{
    $file = $config['publicKeyFile'];
    if ($file === '') {
        return $updaterPath . '/release-key.pem';
    }

    return preg_match('#^(/|[A-Za-z]:[/\\\\])#', $file) === 1 ? $file : $config['rootPath'] . '/' . $file;
}

// ------------------------------------------------------------------------------------------------
// Database
// ------------------------------------------------------------------------------------------------

/**
 * Connect to the database of the installation with plain PDO, the way Admidio's Database does.
 * @param array<string,mixed> $config
 * @throws UpdaterException
 */
function connectDatabase(array $config): PDO
{
    $port = $config['dbPort'] !== null ? ';port=' . $config['dbPort'] : '';
    $dsn = match ($config['dbType']) {
        'mysql' => 'mysql:host=' . $config['dbHost'] . $port . ';dbname=' . $config['dbName'] . ';charset=utf8',
        'pgsql', 'postgresql' => 'pgsql:host=' . $config['dbHost'] . $port . ';dbname=' . $config['dbName'],
        default => throw new UpdaterException('The database type "' . $config['dbType'] . '" is not supported.')
    };

    try {
        return new PDO($dsn, $config['dbUser'], $config['dbPassword'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ));
    } catch (PDOException $exception) {
        // The message of PDO can contain the host and the user name; it belongs in no page.
        throw new UpdaterException('The database of the installation could not be reached.', array(), $exception);
    }
}

/**
 * The first row of a query, or null.
 * @param array<int,mixed> $params
 * @return array<string,mixed>|null
 */
function dbRow(PDO $pdo, string $sql, array $params = array()): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

// ------------------------------------------------------------------------------------------------
// Texts
// ------------------------------------------------------------------------------------------------

/**
 * The texts of the updater in a language, with the gaps filled from its base language (de for
 * de-DE) and then from English.
 *
 * They are the SYS_CORE_UPDATE_* keys of Admidio's language files. The release builder copies them
 * into texts/<language>.json next to the updater, because an older installation's language files
 * lack them; a development tree has no such files and reads the language files directly.
 * @return array{language: string, texts: array<string,string>}
 */
function loadTexts(string $updaterPath, string $rootPath, string $language): array
{
    if (preg_match('/^[a-z]{2,3}(?:-[A-Za-z]{2,4})?$/', $language) !== 1) {
        $language = 'en';
    }

    $texts = array();
    foreach (array_unique(array('en', explode('-', $language)[0], $language)) as $code) {
        $file = $updaterPath . '/texts/' . $code . '.json';
        if (is_file($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            $texts = array_merge($texts, is_array($decoded) ? array_filter($decoded, 'is_string') : array());
        } elseif (is_file($rootPath . '/languages/' . $code . '.xml')) {
            $texts = array_merge($texts, readLanguageFile($rootPath . '/languages/' . $code . '.xml'));
        }
    }

    return array('language' => $language, 'texts' => $texts);
}

/**
 * The updater texts of an Admidio language file, as plain text: the Android string format escapes
 * apostrophes and quotation marks and writes line breaks as \n. The builder uses this as well.
 * @return array<string,string>
 */
function readLanguageFile(string $file): array
{
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string((string)file_get_contents($file), 'SimpleXMLElement', LIBXML_NONET);
    } finally {
        libxml_use_internal_errors($previous);
    }

    $texts = array();
    foreach ($xml === false ? array() : $xml->string as $node) {
        $key = (string)$node['name'];
        if (str_starts_with($key, 'SYS_CORE_UPDATE_')) {
            $texts[$key] = strtr((string)$node, array('\\n' => "\n", '\\\'' => '\'', '\\"' => '"'));
        }
    }

    return $texts;
}

/**
 * One text with #VAR1#, #VAR2#, ... replaced. A missing text shows its key.
 * @param array{language: string, texts: array<string,string>} $texts
 * @param array<int,string> $params
 */
function text(array $texts, string $key, array $params = array()): string
{
    $text = $texts['texts'][$key] ?? $key;
    foreach (array_values($params) as $index => $param) {
        $text = str_replace('#VAR' . ($index + 1) . '#', $param, $text);
    }

    return $text;
}

function isTextKey(string $value): bool
{
    return preg_match('/^SYS_CORE_UPDATE_[A-Z0-9_]+$/', $value) === 1;
}

/**
 * The language of the installation: the setting of its first organization, the one Admidio falls
 * back to. English if it cannot be read.
 */
function installationLanguage(PDO $pdo, string $tablePrefix): string
{
    try {
        $row = dbRow($pdo, 'SELECT prf_value FROM ' . $tablePrefix . '_preferences WHERE prf_name = ?
            AND prf_org_id = (SELECT MIN(org_id) FROM ' . $tablePrefix . '_organizations)', array('system_language'));
    } catch (Throwable) {
        return 'en';
    }

    return is_string($row['prf_value'] ?? null) && $row['prf_value'] !== '' ? $row['prf_value'] : 'en';
}

// ------------------------------------------------------------------------------------------------
// State of an update
// ------------------------------------------------------------------------------------------------

/**
 * The directory of the updates: adm_my_files/core-update. Every update has a workspace
 * <id>/ there with its state.json; active.json names the unfinished update; update.lock
 * serialises the requests.
 */
function updatesDirectory(string $rootPath): string
{
    return rtrim(str_replace('\\', '/', $rootPath), '/') . '/adm_my_files/core-update';
}

function isValidUpdateId(string $updateId): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $updateId) === 1;
}

function workspace(string $rootPath, string $updateId): string
{
    if (!isValidUpdateId($updateId)) {
        throw new UpdaterException('Invalid update ID.');
    }

    return updatesDirectory($rootPath) . '/' . $updateId;
}

/**
 * The lock of the updater while this request holds it.
 * @return resource|null
 */
function &heldLock()
{
    static $lock = null;

    return $lock;
}

/**
 * Take the lock of the updater for a step that spans several functions; releaseLock() follows in
 * a finally block. Prefer withLock().
 */
function acquireLock(string $rootPath, bool $wait = true): void
{
    $lock = &heldLock();
    if ($lock !== null) {
        throw new UpdaterException('The lock of the updater is already held by this request.');
    }

    ensureDirectory(updatesDirectory($rootPath));
    $handle = @fopen(updatesDirectory($rootPath) . '/update.lock', 'c+');
    if ($handle === false) {
        throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
    }
    if (!flock($handle, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
        fclose($handle);
        throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_RUNNING');
    }
    $lock = $handle;
}

function releaseLock(): void
{
    $lock = &heldLock();
    if ($lock !== null) {
        flock($lock, LOCK_UN);
        fclose($lock);
        $lock = null;
    }
}

/**
 * Run a function under the lock of the updater. A request that does not want to wait is refused
 * with "another request is running" - the browser may still be sending the previous step. Inside a
 * locked section the lock is simply kept.
 * @template T
 * @param callable(): T $function
 * @return T
 */
function withLock(string $rootPath, callable $function, bool $wait = true): mixed
{
    if (heldLock() !== null) {
        return $function();
    }

    acquireLock($rootPath, $wait);
    try {
        return $function();
    } finally {
        releaseLock();
    }
}

/**
 * Start a new update and make it the active one. Only one update can be unfinished.
 * @param array<string,mixed> $fields
 * @return array<string,mixed> The state of the new update.
 */
function createUpdate(string $rootPath, array $fields = array()): array
{
    return withLock($rootPath, static function () use ($rootPath, $fields): array {
        removeAbandonedUpdate($rootPath, time());
        if (activeUpdateId($rootPath) !== null) {
            throw new UpdaterException('SYS_CORE_UPDATE_ALREADY_RUNNING');
        }

        $updateId = bin2hex(random_bytes(16));
        ensureDirectory(workspace($rootPath, $updateId));

        $state = array_merge(array(
            'schemaVersion' => SCHEMA_VERSION,
            'updateId' => $updateId,
            'state' => 'new',
            'channel' => 'stable',
            'installedVersion' => '',
            'targetVersion' => '',
            'credentials' => array(),
            'csrfToken' => bin2hex(random_bytes(32)),
            'createdAt' => time(),
            'lastError' => ''
        ), $fields, array('updateId' => $updateId, 'schemaVersion' => SCHEMA_VERSION));

        writeState($rootPath, $state);
        writeJsonFile(updatesDirectory($rootPath) . '/active.json', array('updateId' => $updateId));

        return $state;
    });
}

/**
 * The state of an update. A state that cannot be read is an error and is never replaced by an
 * empty one: it may be all that is left to roll an update back with.
 * @return array<string,mixed>
 */
function readState(string $rootPath, string $updateId): array
{
    $file = workspace($rootPath, $updateId) . '/state.json';
    if (!is_file($file)) {
        throw new UpdaterException('SYS_CORE_UPDATE_NOT_FOUND');
    }

    $state = json_decode((string)file_get_contents($file), true);
    if (!is_array($state) || ($state['updateId'] ?? '') !== $updateId) {
        throw new UpdaterException('The state of update ' . $updateId . ' cannot be read. It was left in place; '
            . 'state.json.previous in its workspace holds the version before.');
    }

    $schemaVersion = (int)($state['schemaVersion'] ?? 0);
    if ($schemaVersion < 1 || $schemaVersion > SCHEMA_VERSION) {
        throw new UpdaterException('The state of update ' . $updateId . ' has the schema version '
            . $schemaVersion . ', which this updater does not know.');
    }

    return $state;
}

function updateExists(string $rootPath, string $updateId): bool
{
    return isValidUpdateId($updateId) && is_file(workspace($rootPath, $updateId) . '/state.json');
}

/**
 * Write the state atomically, keeping the version before as state.json.previous. The caller holds
 * the lock, or the update is new.
 * @param array<string,mixed> $state
 */
function writeState(string $rootPath, array $state): void
{
    $state['updatedAt'] = time();
    $file = workspace($rootPath, (string)($state['updateId'] ?? '')) . '/state.json';
    if (is_file($file) && !@copy($file, $file . '.previous')) {
        throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
    }

    writeJsonFile($file, $state);
}

/**
 * Change the state of an update under the lock.
 * @param callable(array<string,mixed>): array<string,mixed> $change
 * @return array<string,mixed>
 */
function updateState(string $rootPath, string $updateId, callable $change): array
{
    return withLock($rootPath, static function () use ($rootPath, $updateId, $change): array {
        $state = $change(readState($rootPath, $updateId));
        writeState($rootPath, $state);

        return $state;
    });
}

/**
 * The ID of the unfinished update, or null. An update whose state cannot be read counts as
 * unfinished: it blocks a new update until somebody looks at it.
 */
function activeUpdateId(string $rootPath): ?string
{
    $file = updatesDirectory($rootPath) . '/active.json';
    $active = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $updateId = is_array($active) ? (string)($active['updateId'] ?? '') : '';
    if (!updateExists($rootPath, $updateId)) {
        return null;
    }

    try {
        return in_array((string)(readState($rootPath, $updateId)['state'] ?? ''), STATES_FINISHED, true) ? null : $updateId;
    } catch (UpdaterException) {
        return $updateId;
    }
}

/**
 * Whether Admidio itself is still intact: no update is unfinished, or it has not started to change
 * files. Then the Admidio login decides who may use the updater; from maintenance mode on only the
 * updater cookie or the updater's own login can.
 */
function isAdmidioIntact(string $rootPath): bool
{
    $updateId = activeUpdateId($rootPath);
    if ($updateId === null) {
        return true;
    }

    try {
        return in_array((string)readState($rootPath, $updateId)['state'], STATES_BEFORE_CHANGES, true);
    } catch (UpdaterException) {
        return false;
    }
}

/** Forget the active update, because it is over. */
function clearActiveUpdate(string $rootPath, string $updateId): void
{
    withLock($rootPath, static function () use ($rootPath, $updateId): void {
        $file = updatesDirectory($rootPath) . '/active.json';
        $active = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        if (is_array($active) && ($active['updateId'] ?? '') === $updateId) {
            @unlink($file);
        }
    });
}

/**
 * Remove the active update if it was abandoned before it changed anything: in a state before
 * maintenance mode and without a request for ABANDONED_AFTER seconds. An update that got further
 * is never removed, because its workspace holds the rollback copy.
 */
function removeAbandonedUpdate(string $rootPath, int $now): bool
{
    return withLock($rootPath, static function () use ($rootPath, $now): bool {
        $file = updatesDirectory($rootPath) . '/active.json';
        $active = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        $updateId = is_array($active) ? (string)($active['updateId'] ?? '') : '';
        if (!updateExists($rootPath, $updateId)) {
            return false;
        }

        try {
            $state = readState($rootPath, $updateId);
        } catch (UpdaterException) {
            return false;
        }

        if (!in_array((string)$state['state'], STATES_BEFORE_CHANGES, true)
            || (int)($state['updatedAt'] ?? $state['createdAt'] ?? 0) + ABANDONED_AFTER > $now) {
            return false;
        }

        deleteDirectory(workspace($rootPath, $updateId));
        @unlink($file);

        return true;
    });
}

// ------------------------------------------------------------------------------------------------
// Files
// ------------------------------------------------------------------------------------------------

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $directory . ' cannot be created.');
    }
}

/** Write a file and check that every byte arrived. */
function writeFile(string $file, string $content): void
{
    if (@file_put_contents($file, $content, LOCK_EX) !== strlen($content)) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $file . ' cannot be written.');
    }
}

/**
 * Write a JSON file atomically: a temporary file in the same directory, then a rename.
 * @param array<string,mixed> $data
 */
function writeJsonFile(string $file, array $data): void
{
    ensureDirectory(dirname($file));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.new';

    if (@file_put_contents($temporary, $json, LOCK_EX) !== strlen($json) || !@rename($temporary, $file)) {
        @unlink($temporary);
        throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
    }
}

function deleteDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $entries = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($entries as $entry) {
        ($entry->isDir() && !$entry->isLink()) ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
    @rmdir($directory);
}

/**
 * Whether a path from a package or a manifest stays inside the installation: relative, forward
 * slashes, no empty, "." or ".." segment, no drive prefix, no NUL byte.
 */
function isSafeRelativePath(string $path): bool
{
    if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')
        || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
        return false;
    }

    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}

// ------------------------------------------------------------------------------------------------
// Updater cookie and CSRF
// ------------------------------------------------------------------------------------------------

/**
 * The name of the updater cookie. Its value is <update ID>.<secret>; the state keeps one credential
 * per browser with only the SHA-256 of the secret, each expiring CREDENTIAL_LIFETIME seconds after
 * its last use.
 */
function cookieName(string $cookiePrefix): string
{
    return $cookiePrefix . '_CORE_UPDATE';
}

/**
 * Add a credential to the state of an update.
 * @param array<string,mixed> $state
 * @return string The value of the cookie.
 */
function issueCredential(array &$state, int $now): string
{
    $secret = bin2hex(random_bytes(32));
    $credentials = liveCredentials((array)($state['credentials'] ?? array()), $now);
    $credentials[] = array('hash' => hash('sha256', $secret), 'createdAt' => $now, 'expiresAt' => $now + CREDENTIAL_LIFETIME);
    $state['credentials'] = $credentials;

    return $state['updateId'] . '.' . $secret;
}

/**
 * The update ID and the secret of a cookie value, or null.
 * @return array{0: string, 1: string}|null
 */
function parseCredential(mixed $value): ?array
{
    if (!is_string($value) || preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $value, $matches) !== 1) {
        return null;
    }

    return array($matches[1], $matches[2]);
}

/**
 * Whether a cookie value is a valid credential of the update; when it is, its expiry moves on.
 * Expired credentials are dropped.
 * @param array<string,mixed> $state
 */
function refreshCredential(array &$state, mixed $value, int $now): bool
{
    $parsed = parseCredential($value);
    if ($parsed === null || $parsed[0] !== ($state['updateId'] ?? '')) {
        return false;
    }

    $hash = hash('sha256', $parsed[1]);
    $credentials = liveCredentials((array)($state['credentials'] ?? array()), $now);
    $found = false;
    foreach ($credentials as &$credential) {
        if (hash_equals((string)$credential['hash'], $hash)) {
            $credential['expiresAt'] = $now + CREDENTIAL_LIFETIME;
            $found = true;
        }
    }
    unset($credential);
    $state['credentials'] = $credentials;

    return $found;
}

/**
 * Whether a cookie value is a valid credential of the update, without changing anything.
 * @param array<string,mixed> $state
 */
function isValidCredential(array $state, mixed $value, int $now): bool
{
    return refreshCredential($state, $value, $now);
}

/** @param array<string,mixed> $state */
function revokeCredentials(array &$state): void
{
    $state['credentials'] = array();
}

/**
 * @param array<int,mixed> $credentials
 * @return array<int,array{hash: string, createdAt: int, expiresAt: int}>
 */
function liveCredentials(array $credentials, int $now): array
{
    return array_values(array_filter($credentials, static fn(mixed $credential): bool =>
        is_array($credential) && isset($credential['hash']) && (int)($credential['expiresAt'] ?? 0) >= $now));
}

/**
 * Send or remove a cookie of the updater: HttpOnly, Secure under HTTPS, limited to install/.
 * The command line has no browser to send it to.
 * @param array<string,mixed> $config
 */
function sendCookie(array $config, string $name, string $value, int $expires, string $sameSite = 'Lax'): void
{
    if (headers_sent()) {
        return;
    }
    setcookie($name, $value, array(
        'expires' => $expires,
        'path' => basePath($config) . '/install/',
        'secure' => isHttps($config),
        'httponly' => true,
        'samesite' => $sameSite
    ));
}

/** @param array<string,mixed> $config */
function sendCredentialCookie(array $config, string $value): void
{
    sendCookie($config, cookieName(cookiePrefix($config)), $value, time() + CREDENTIAL_LIFETIME);
}

/** @param array<string,mixed> $config */
function clearCredentialCookie(array $config): void
{
    sendCookie($config, cookieName(cookiePrefix($config)), '', time() - 3600);
}

/**
 * Whether a request carries the CSRF token of the update.
 * @param array<string,mixed> $state
 */
function isValidCsrfToken(array $state, mixed $token): bool
{
    return is_string($token) && $token !== '' && isset($state['csrfToken']) && hash_equals((string)$state['csrfToken'], $token);
}

/**
 * The token of the login form, which comes before any update is known: a cookie and a form field
 * with the same value. Another site can submit the form, but cannot read the cookie.
 * @param array<string,mixed> $config
 */
function loginCsrfToken(array $config, mixed $cookieValue): string
{
    if (is_string($cookieValue) && preg_match('/^[a-f0-9]{64}$/', $cookieValue) === 1) {
        return $cookieValue;
    }

    $token = bin2hex(random_bytes(32));
    sendCookie($config, cookiePrefix($config) . '_CORE_UPDATE_LOGIN', $token, 0, 'Strict');

    return $token;
}

function isValidLoginCsrfToken(mixed $cookieValue, mixed $formValue): bool
{
    return is_string($cookieValue) && is_string($formValue)
        && preg_match('/^[a-f0-9]{64}$/', $cookieValue) === 1 && hash_equals($cookieValue, $formValue);
}

// ------------------------------------------------------------------------------------------------
// Versions
// ------------------------------------------------------------------------------------------------

/** Whether a version is written X.Y.Z or X.Y.Z-beta.N, which version_compare() orders correctly. */
function isCanonicalVersion(string $version): bool
{
    return preg_match('/^\d+\.\d+\.\d+(?:-beta\.[1-9]\d*)?$/', $version) === 1;
}

function versionFromParts(int $main, int $minor, int $patch, int $beta): string
{
    return $main . '.' . $minor . '.' . $patch . ($beta > 0 ? '-beta.' . $beta : '');
}

/**
 * The version of the installed files: from the manifest of the installed release, or else from the
 * version constants, read as text and never included.
 */
function filesVersion(string $rootPath): string
{
    $manifest = $rootPath . '/install/update-manifest.json';
    if (is_file($manifest)) {
        $version = json_decode((string)file_get_contents($manifest), true)['version'] ?? null;
        if (is_string($version) && isCanonicalVersion($version)) {
            return $version;
        }
    }

    $constants = (string)@file_get_contents($rootPath . '/system/bootstrap/constants.php');
    $parts = array();
    foreach (array('MAIN', 'MINOR', 'PATCH', 'BETA') as $part) {
        if (preg_match('/const\s+ADMIDIO_VERSION_' . $part . '\s*=\s*(\d+)\s*;/', $constants, $matches) !== 1) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_NOT_AVAILABLE', 'The installed version cannot be read from system/bootstrap/constants.php.');
        }
        $parts[] = (int)$matches[1];
    }

    return versionFromParts(...$parts);
}

/** The version of the database (component CORE), or null if it cannot be read. */
function databaseVersion(PDO $pdo, string $tablePrefix): ?string
{
    try {
        $row = dbRow($pdo, 'SELECT com_version, com_beta FROM ' . $tablePrefix . '_components WHERE com_type = ? AND com_name_intern = ?',
            array('SYSTEM', 'CORE'));
    } catch (Throwable) {
        return null;
    }

    if ($row === null || preg_match('/^(\d+)\.(\d+)\.(\d+)/', (string)$row['com_version'], $matches) !== 1) {
        return null;
    }

    return versionFromParts((int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$row['com_beta']);
}

/**
 * The version rule: the target must be the same as or newer than the files and the database. The
 * same version is a reinstall; an older one is refused, because the database may already be ahead.
 * @return string "update" or "reinstall"
 */
function checkVersionRule(string $target, string $installed, ?string $database): string
{
    if (version_compare($target, $installed) < 0) {
        throw new UpdaterException('SYS_CORE_UPDATE_VERSION_OLDER', array($target, $installed));
    }
    if ($database !== null && version_compare($target, $database) < 0) {
        throw new UpdaterException('SYS_CORE_UPDATE_DATABASE_NEWER', array($database, $target));
    }

    return version_compare($target, $installed) === 0 ? 'reinstall' : 'update';
}

// ------------------------------------------------------------------------------------------------
// Pages
// ------------------------------------------------------------------------------------------------

function sendSecurityHeaders(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

/** @param array<string,mixed> $data */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** Escape a value for HTML. */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Encode a value for a script block. */
function js(mixed $value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}

/**
 * Send a whole page: plain HTML with inline CSS, because the updater must not depend on the theme
 * or the libraries of the release it replaces.
 * @param string $title Plain text.
 * @param string $content HTML.
 * @param string $actions HTML.
 * @param int|null $progress Percentage of the progress bar, or null for none.
 * @param string $messageClass "error", "success" or "".
 */
function showPage(string $title, string $content, string $actions = '', ?int $progress = null, string $messageClass = '',
    string $languageCode = 'en', string $progressLabel = 'Update progress'): void
{
    $progressBar = $progress === null ? '' : '
    <div class="progress" role="progressbar" aria-label="' . h($progressLabel) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $progress . '">
        <div id="progress-bar" class="progress-bar" style="width:' . $progress . '%"></div>
    </div>';

    echo '<!doctype html>
<html lang="' . h($languageCode) . '">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>' . h($title) . '</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f3f5f7; color: #20252a; }
        main { max-width: 760px; margin: 5vh auto; padding: 2rem; background: #fff; border-radius: .5rem; box-shadow: 0 .25rem 1rem rgba(0,0,0,.12); }
        h1 { margin-top: 0; }
        dl { display: grid; grid-template-columns: 12rem 1fr; gap: .4rem 1rem; }
        dt { font-weight: 700; }
        dd { margin: 0; overflow-wrap: anywhere; }
        label { display: block; margin: 0 0 1rem; font-weight: 700; }
        input[type=text], input[type=password] { display: block; box-sizing: border-box; width: 100%; max-width: 24rem; margin-top: .3rem;
            padding: .5rem; font-size: 1rem; border: 1px solid #adb5bd; border-radius: .3rem; }
        .progress { height: 1rem; background: #dfe4e8; border-radius: .5rem; overflow: hidden; margin: 1.5rem 0; }
        .progress-bar { height: 100%; background: #2176bd; transition: width .2s ease; }
        .message, .warning { margin: 1rem 0; padding: 1rem; border-radius: .3rem; background: #e8f2fb; }
        .warning { background: #fff3cd; color: #5f4b00; }
        .error { background: #f8d7da; color: #721c24; }
        .success { background: #d1e7dd; color: #0f5132; }
        .button { display: inline-block; margin: .5rem .5rem 0 0; border: 0; border-radius: .3rem; padding: .7rem 1rem; background: #2176bd;
            color: #fff; text-decoration: none; cursor: pointer; font-size: 1rem; }
        .button.secondary { background: #6c757d; }
        .button:disabled { opacity: .65; cursor: wait; }
        [hidden] { display: none !important; }
        form.inline { display: inline; }
        fieldset { border: 0; margin: 0 0 1rem; padding: 0; }
        legend { font-weight: 700; margin-bottom: .3rem; }
        label.choice { display: block; font-weight: 400; margin: 0 0 .3rem; }
        ul.checks { list-style: none; padding: 0; }
        ul.checks li { padding: .3rem 0 .3rem 1.6rem; position: relative; }
        ul.checks li::before { position: absolute; left: 0; font-weight: 700; }
        .check-ok::before { content: "\2713"; color: #198754; }
        .check-warning::before { content: "!"; color: #b58100; }
        .check-error::before { content: "\2717"; color: #dc3545; }
        @media (prefers-color-scheme: dark) {
            body { background: #181a1b; color: #e8e6e3; }
            main { background: #242729; }
            .progress { background: #3b4044; }
            input[type=text], input[type=password] { background: #181a1b; color: #e8e6e3; }
        }
    </style>
</head>
<body>
<main>
    <h1>' . h($title) . '</h1>' . $progressBar . '
    <div' . ($messageClass !== '' ? ' class="' . h($messageClass) . '"' : '') . '>' . $content . '</div>
    <div>' . $actions . '</div>
</main>
</body>
</html>';
}
