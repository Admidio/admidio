<?php
/**
 * What every part of the updater needs (install/core-update/lib/common.php): the configuration,
 * the state of an update with its lock, the updater cookie, the CSRF tokens and the versions.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;
use AdmidioCoreUpdater\UpdaterException;

final class CommonTest extends AdmidioTestCase
{
    private const UPDATE_ID = '0123456789abcdef0123456789abcdef';
    private const NOW = 1791500000;

    private string $rootPath = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = sys_get_temp_dir() . '/adm_updater_common_' . uniqid();
        mkdir($this->rootPath . '/adm_my_files', 0777, true);
    }

    protected function tearDown(): void
    {
        U\releaseLock();
        U\deleteDirectory($this->rootPath);

        parent::tearDown();
    }

    /**
     * Assert that a call fails with the given text key or diagnostic.
     */
    private function assertFailsWith(string $expected, callable $call): void
    {
        try {
            $call();
            $this->fail('no exception, expected ' . $expected);
        } catch (UpdaterException $exception) {
            $this->assertStringContainsString($expected, $exception->getMessage());
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function credentialState(): array
    {
        return array('updateId' => self::UPDATE_ID, 'credentials' => array(), 'csrfToken' => str_repeat('c', 64));
    }

    // --------------------------------------------------------------------------------------------
    // Configuration
    // --------------------------------------------------------------------------------------------

    /**
     * @testdox A configuration without optional settings gets the defaults of init_globals.php
     */
    public function testDefaults(): void
    {
        $config = U\configFromValues('C:\\admidio\\', array('g_adm_db' => 'club'));

        $this->assertSame('C:/admidio', $config['rootPath']);
        $this->assertSame('mysql', $config['dbType']);
        $this->assertNull($config['dbPort']);
        $this->assertSame('adm', $config['tablePrefix']);
        $this->assertSame('Europe/Berlin', $config['timezone']);
        $this->assertFalse($config['debug']);
    }

    /**
     * @testdox MariaDB is reached with the driver of MySQL, as Admidio does
     */
    public function testMariaDbIsMySql(): void
    {
        $this->assertSame('mysql', U\configFromValues('/srv/admidio', array('gDbType' => 'mariadb'))['dbType']);
        $this->assertSame('pgsql', U\configFromValues('/srv/admidio', array('gDbType' => 'pgsql'))['dbType']);
    }

    /**
     * @testdox The test server and the test key are ignored unless debug mode is on
     */
    public function testDebugSettingsNeedDebugMode(): void
    {
        $values = array(
            'gCoreUpdateServerUrl' => 'http://localhost:8090/',
            'gCoreUpdatePublicKeyFile' => 'keys/test.pem'
        );

        $production = U\configFromValues('/srv/admidio', $values);
        $this->assertSame(U\UPDATE_INFO_URL, U\updateInfoUrl($production));
        $this->assertSame('/srv/admidio/install/core-update/release-key.pem', U\publicKeyFile($production, '/srv/admidio/install/core-update'));

        $development = U\configFromValues('/srv/admidio', $values + array('gDebug' => true));
        $this->assertSame('http://localhost:8090/update.txt', U\updateInfoUrl($development));
        $this->assertSame('/srv/admidio/keys/test.pem', U\publicKeyFile($development, '/srv/admidio/install/core-update'),
            'a relative path is resolved from the installation');

        $absolute = U\configFromValues('/srv/admidio', array('gDebug' => true, 'gCoreUpdatePublicKeyFile' => 'D:\\keys\\test.pem'));
        $this->assertSame('D:\\keys\\test.pem', U\publicKeyFile($absolute, '/srv/admidio/install/core-update'));
    }

    /**
     * @testdox The cookie prefix is the one constants.php derives
     *
     * init_globals.php always sets $gDebug before constants.php asks isset($gDebug), so every
     * installation uses the database name and the table prefix; characters that are no word
     * characters become underscores.
     */
    public function testCookiePrefix(): void
    {
        $config = U\configFromValues('/srv/admidio', array('g_adm_db' => 'my-club.db', 'g_tbl_praefix' => 'xy'));

        $this->assertSame('ADMIDIO_my_club_db_xy', U\cookiePrefix($config));
        $this->assertSame('ADMIDIO_my_club_db_xy_CORE_UPDATE', U\cookieName(U\cookiePrefix($config)));
    }

    /**
     * @testdox The base path and the scheme come from the URL of the installation
     */
    public function testUrl(): void
    {
        $config = U\configFromValues('/srv/admidio', array('g_root_path' => 'https://example.org/club/'));
        $this->assertSame('https://example.org/club', $config['baseUrl']);
        $this->assertSame('/club', U\basePath($config));
        $this->assertTrue(U\isHttps($config));

        $root = U\configFromValues('/srv/admidio', array('g_root_path' => 'http://example.org'));
        $this->assertSame('', U\basePath($root));
        $this->assertFalse(U\isHttps($root));
    }

    /**
     * @testdox The configuration file is read in a scope of its own
     */
    public function testLoadConfig(): void
    {
        file_put_contents($this->rootPath . '/adm_my_files/config.php', '<?php
            $gDbType = "pgsql";
            $g_adm_srv = "db.local";
            $g_adm_port = "5433";
            $g_adm_db = "club";
            $g_adm_usr = "admidio";
            $g_adm_pw = "secret";
            $g_tbl_praefix = "club";
            $g_root_path = "https://club.example.org";
            $configFile = "must not leak into the loader";
        ');

        $config = U\loadConfig($this->rootPath);

        $this->assertSame('pgsql', $config['dbType']);
        $this->assertSame('db.local', $config['dbHost']);
        $this->assertSame(5433, $config['dbPort']);
        $this->assertSame('club', $config['dbName']);
        $this->assertSame('admidio', $config['dbUser']);
        $this->assertSame('secret', $config['dbPassword']);
        $this->assertSame('club', $config['tablePrefix']);
        $this->assertSame('https://club.example.org', $config['baseUrl']);
    }

    /**
     * @testdox Without a configuration there is no installation to update
     */
    public function testMissingConfiguration(): void
    {
        $this->expectException(UpdaterException::class);
        U\loadConfig($this->rootPath . '/nothing');
    }

    // --------------------------------------------------------------------------------------------
    // State of an update
    // --------------------------------------------------------------------------------------------

    /**
     * @testdox A new update gets a workspace, the current schema and becomes the active one
     */
    public function testCreateUpdate(): void
    {
        $state = U\createUpdate($this->rootPath, array('channel' => 'beta', 'installedVersion' => '5.0.14', 'updateId' => 'forged'));

        $this->assertTrue(U\isValidUpdateId($state['updateId']), 'the ID cannot be given from outside');
        $this->assertSame(U\SCHEMA_VERSION, $state['schemaVersion']);
        $this->assertSame('new', $state['state']);
        $this->assertSame('beta', $state['channel']);
        $this->assertSame('5.0.14', $state['installedVersion']);
        $this->assertSame(array(), $state['credentials']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $state['csrfToken']);
        $this->assertSame($state['updateId'], U\activeUpdateId($this->rootPath));
        $stored = U\readState($this->rootPath, $state['updateId']);
        unset($stored['updatedAt']);
        $this->assertSame($state, $stored, 'what createUpdate() returns is what was stored');
    }

    /**
     * @testdox Only one update can be unfinished
     */
    public function testOneUpdateAtATime(): void
    {
        U\createUpdate($this->rootPath);

        $this->assertFailsWith('SYS_CORE_UPDATE_ALREADY_RUNNING', fn() => U\createUpdate($this->rootPath));
    }

    /**
     * @testdox A finished update no longer blocks a new one
     */
    public function testFinishedUpdateIsNotActive(): void
    {
        $state = U\createUpdate($this->rootPath);
        U\updateState($this->rootPath, $state['updateId'], static fn(array $s): array => array_merge($s, array('state' => 'completed')));

        $this->assertNull(U\activeUpdateId($this->rootPath));
        $this->assertNotSame($state['updateId'], U\createUpdate($this->rootPath)['updateId']);
    }

    /**
     * @testdox A state is written atomically, and the version before is kept
     */
    public function testWriteKeepsThePreviousVersion(): void
    {
        $state = U\createUpdate($this->rootPath);
        $workspace = U\workspace($this->rootPath, $state['updateId']);

        U\updateState($this->rootPath, $state['updateId'], static fn(array $s): array => array_merge($s, array('state' => 'prepared')));

        $this->assertSame('prepared', U\readState($this->rootPath, $state['updateId'])['state']);
        $this->assertSame('new', json_decode((string)file_get_contents($workspace . '/state.json.previous'), true)['state']);
        $this->assertSame(array(), glob($workspace . '/*.new'), 'no temporary file is left');
    }

    /**
     * @testdox A state that cannot be read is refused and left as it is
     */
    public function testUnreadableStateIsNeverReplaced(): void
    {
        $state = U\createUpdate($this->rootPath);
        $file = U\workspace($this->rootPath, $state['updateId']) . '/state.json';
        file_put_contents($file, '{"updateId": "' . $state['updateId'] . '", "state": "inst');

        $this->assertFailsWith('cannot be read', fn() => U\readState($this->rootPath, $state['updateId']));
        $this->assertFailsWith('cannot be read', fn() => U\updateState($this->rootPath, $state['updateId'], static fn(array $s): array => $s));
        $this->assertStringEndsWith('"state": "inst', (string)file_get_contents($file));
        $this->assertSame($state['updateId'], U\activeUpdateId($this->rootPath), 'an unreadable update still blocks a new one');
    }

    /**
     * @testdox A state of a newer schema than this updater knows is refused
     */
    public function testUnknownSchemaIsRefused(): void
    {
        $state = U\createUpdate($this->rootPath);
        U\writeState($this->rootPath, array_merge($state, array('schemaVersion' => U\SCHEMA_VERSION + 1)));

        $this->assertFailsWith('schema version', fn() => U\readState($this->rootPath, $state['updateId']));
    }

    /**
     * @testdox An update that does not exist is reported as such
     */
    public function testMissingUpdate(): void
    {
        $this->assertFailsWith('SYS_CORE_UPDATE_NOT_FOUND', fn() => U\readState($this->rootPath, str_repeat('a', 32)));
        $this->assertFailsWith('Invalid update ID', fn() => U\readState($this->rootPath, '../../etc'));
        $this->assertFalse(U\updateExists($this->rootPath, '../../etc'));
    }

    /**
     * @testdox An update abandoned before it changed anything is removed after a day
     */
    public function testAbandonedUpdateIsRemoved(): void
    {
        $state = U\createUpdate($this->rootPath, array('state' => 'prepared'));
        $workspace = U\workspace($this->rootPath, $state['updateId']);
        $lastRequest = (int)U\readState($this->rootPath, $state['updateId'])['updatedAt'];

        $this->assertFalse(U\removeAbandonedUpdate($this->rootPath, $lastRequest + U\ABANDONED_AFTER - 1), 'not yet');
        $this->assertDirectoryExists($workspace);

        $this->assertTrue(U\removeAbandonedUpdate($this->rootPath, $lastRequest + U\ABANDONED_AFTER));
        $this->assertDirectoryDoesNotExist($workspace);
        $this->assertNull(U\activeUpdateId($this->rootPath));
    }

    /**
     * @testdox An update that changed files is never removed, however old it is
     */
    public function testUpdateBeyondReadyIsKept(): void
    {
        $state = U\createUpdate($this->rootPath, array('state' => 'installing'));

        $this->assertFalse(U\removeAbandonedUpdate($this->rootPath, time() + 10 * U\ABANDONED_AFTER));
        $this->assertSame($state['updateId'], U\activeUpdateId($this->rootPath));
    }

    /**
     * @testdox The Admidio login applies until an update starts to change files
     *
     * Before maintenance mode the updater runs inside Admidio and uses its login; from then on only
     * the updater cookie and the updater's own login can.
     */
    public function testAdmidioIsIntactUntilFilesChange(): void
    {
        $this->assertTrue(U\isAdmidioIntact($this->rootPath), 'no update');

        $state = U\createUpdate($this->rootPath);
        foreach (U\STATES_BEFORE_CHANGES as $before) {
            U\updateState($this->rootPath, $state['updateId'], static fn(array $s): array => array_merge($s, array('state' => $before)));
            $this->assertTrue(U\isAdmidioIntact($this->rootPath), $before);
        }

        foreach (array('dumping', 'installing', 'verifying', 'files_installed', 'database_running', 'database_failed', 'rollback_required') as $after) {
            U\updateState($this->rootPath, $state['updateId'], static fn(array $s): array => array_merge($s, array('state' => $after)));
            $this->assertFalse(U\isAdmidioIntact($this->rootPath), $after);
        }

        file_put_contents(U\workspace($this->rootPath, $state['updateId']) . '/state.json', 'broken');
        $this->assertFalse(U\isAdmidioIntact($this->rootPath), 'an unreadable state counts as started');

        U\writeState($this->rootPath, array_merge($state, array('state' => 'completed')));
        $this->assertTrue(U\isAdmidioIntact($this->rootPath), 'a finished update');
    }

    /**
     * @testdox A request that does not want to wait is refused while another one holds the lock
     */
    public function testLockConflict(): void
    {
        // Another request: its own handle on the lock file.
        U\ensureDirectory(U\updatesDirectory($this->rootPath));
        $other = fopen(U\updatesDirectory($this->rootPath) . '/update.lock', 'c+');
        flock($other, LOCK_EX);

        try {
            $this->assertFailsWith('SYS_CORE_UPDATE_REQUEST_RUNNING', fn() => U\withLock($this->rootPath, static fn() => null, false));
        } finally {
            flock($other, LOCK_UN);
            fclose($other);
        }

        $this->assertSame('free', U\withLock($this->rootPath, static fn(): string => 'free', false));
        $this->assertNull(U\heldLock(), 'the lock is released again');
    }

    /**
     * @testdox The lock can be taken again inside a locked section
     */
    public function testLockIsReentrant(): void
    {
        $state = U\createUpdate($this->rootPath);

        U\withLock($this->rootPath, function () use ($state): void {
            U\updateState($this->rootPath, $state['updateId'], static fn(array $s): array => array_merge($s, array('state' => 'ready')));
            U\clearActiveUpdate($this->rootPath, $state['updateId']);
        });

        $this->assertSame('ready', U\readState($this->rootPath, $state['updateId'])['state']);
        $this->assertNull(U\activeUpdateId($this->rootPath));
    }

    // --------------------------------------------------------------------------------------------
    // Updater cookie and CSRF
    // --------------------------------------------------------------------------------------------

    /**
     * @testdox The cookie carries the update and a secret, the state only the hash of the secret
     */
    public function testIssueCredential(): void
    {
        $state = self::credentialState();
        $cookie = U\issueCredential($state, self::NOW);

        $this->assertMatchesRegularExpression('/^' . self::UPDATE_ID . '\.[a-f0-9]{64}$/', $cookie);
        $this->assertCount(1, $state['credentials']);
        $this->assertSame(hash('sha256', substr($cookie, 33)), $state['credentials'][0]['hash']);
        $this->assertStringNotContainsString(substr($cookie, 33), json_encode($state));
        $this->assertSame(self::NOW + U\CREDENTIAL_LIFETIME, $state['credentials'][0]['expiresAt']);
    }

    /**
     * @testdox Every use keeps a credential alive for another day
     */
    public function testSlidingExpiry(): void
    {
        $state = self::credentialState();
        $cookie = U\issueCredential($state, self::NOW);

        $later = self::NOW + U\CREDENTIAL_LIFETIME - 1;
        $this->assertTrue(U\refreshCredential($state, $cookie, $later));
        $this->assertSame($later + U\CREDENTIAL_LIFETIME, $state['credentials'][0]['expiresAt']);

        $this->assertTrue(U\isValidCredential($state, $cookie, $later + U\CREDENTIAL_LIFETIME));
        $this->assertFalse(U\isValidCredential($state, $cookie, $later + U\CREDENTIAL_LIFETIME + 1), 'a day without a request');
    }

    /**
     * @testdox An expired credential is dropped from the state
     */
    public function testExpiredCredentialIsDropped(): void
    {
        $state = self::credentialState();
        $old = U\issueCredential($state, self::NOW);
        $new = U\issueCredential($state, self::NOW + U\CREDENTIAL_LIFETIME);

        $this->assertFalse(U\refreshCredential($state, $old, self::NOW + U\CREDENTIAL_LIFETIME + 1));
        $this->assertCount(1, $state['credentials']);
        $this->assertTrue(U\isValidCredential($state, $new, self::NOW + U\CREDENTIAL_LIFETIME + 1));
    }

    /**
     * @testdox Each browser has a credential of its own
     */
    public function testSeveralBrowsers(): void
    {
        $state = self::credentialState();
        $first = U\issueCredential($state, self::NOW);
        $second = U\issueCredential($state, self::NOW + 10);

        $this->assertNotSame($first, $second);
        $this->assertTrue(U\isValidCredential($state, $first, self::NOW + 20));
        $this->assertTrue(U\isValidCredential($state, $second, self::NOW + 20));
    }

    /**
     * @testdox A cookie of another update, a forged and a malformed cookie are refused
     */
    public function testInvalidCookies(): void
    {
        $state = self::credentialState();
        $cookie = U\issueCredential($state, self::NOW);
        $secret = substr($cookie, 33);

        foreach (array(
            'another update' => str_repeat('f', 32) . '.' . $secret,
            'another secret' => self::UPDATE_ID . '.' . str_repeat('0', 64),
            'no separator' => self::UPDATE_ID . $secret,
            'upper case' => strtoupper($cookie),
            'empty' => '',
            'array' => array($cookie),
            'null' => null
        ) as $case => $value) {
            $this->assertFalse(U\isValidCredential($state, $value, self::NOW), $case);
        }
    }

    /**
     * @testdox When the update is over every credential is revoked
     */
    public function testRevokeCredentials(): void
    {
        $state = self::credentialState();
        $cookie = U\issueCredential($state, self::NOW);

        U\revokeCredentials($state);

        $this->assertFalse(U\isValidCredential($state, $cookie, self::NOW));
    }

    /**
     * @testdox A request is only accepted with the CSRF token of its update
     */
    public function testCsrf(): void
    {
        $state = self::credentialState();

        $this->assertTrue(U\isValidCsrfToken($state, str_repeat('c', 64)));
        $this->assertFalse(U\isValidCsrfToken($state, str_repeat('d', 64)));
        $this->assertFalse(U\isValidCsrfToken($state, ''));
        $this->assertFalse(U\isValidCsrfToken($state, null));
        $this->assertFalse(U\isValidCsrfToken(array('updateId' => self::UPDATE_ID), str_repeat('c', 64)), 'a state without a token');
    }

    /**
     * @testdox The login form is only accepted with the token of its cookie
     */
    public function testLoginCsrf(): void
    {
        $token = str_repeat('a1', 32);

        $this->assertTrue(U\isValidLoginCsrfToken($token, $token));
        $this->assertFalse(U\isValidLoginCsrfToken($token, str_repeat('b2', 32)));
        $this->assertFalse(U\isValidLoginCsrfToken(null, $token), 'no cookie, as on a request from another site');
        $this->assertFalse(U\isValidLoginCsrfToken('', ''));
        $this->assertFalse(U\isValidLoginCsrfToken('short', 'short'));
        $this->assertSame($token, U\loginCsrfToken(array(), $token), 'the token of the cookie is kept');
    }

    // --------------------------------------------------------------------------------------------
    // Versions
    // --------------------------------------------------------------------------------------------

    /**
     * @testdox A beta comes before its release, and betas are ordered by number
     */
    public function testVersionOrder(): void
    {
        $ordered = array('5.0.14', '6.0.0-beta.1', '6.0.0-beta.2', '6.0.0-beta.10', '6.0.0', '6.0.1', '6.1.0', '10.0.0');

        for ($i = 1, $count = count($ordered); $i < $count; $i++) {
            $this->assertSame(-1, version_compare($ordered[$i - 1], $ordered[$i]), $ordered[$i - 1] . ' < ' . $ordered[$i]);
        }
    }

    /**
     * @testdox Versions are written X.Y.Z or X.Y.Z-beta.N
     */
    public function testCanonicalVersion(): void
    {
        $this->assertSame('6.0.0-beta.1', U\versionFromParts(6, 0, 0, 1));
        $this->assertSame('5.0.14', U\versionFromParts(5, 0, 14, 0));

        foreach (array('5.0.14', '6.0.0-beta.1', '10.20.30') as $version) {
            $this->assertTrue(U\isCanonicalVersion($version), $version);
        }
        foreach (array('5.0', '6.0.0 Beta 1', '6.0.0-beta.0', '6.0.0-rc.1', 'v5.0.14', '') as $version) {
            $this->assertFalse(U\isCanonicalVersion($version), $version);
        }
    }

    /**
     * @testdox A newer version is an update, the same version a reinstall
     */
    public function testVersionRule(): void
    {
        $this->assertSame('update', U\checkVersionRule('5.1.0', '5.0.14', '5.0.14'));
        $this->assertSame('update', U\checkVersionRule('6.0.0', '6.0.0-beta.2', null), 'from a beta to its release');
        $this->assertSame('reinstall', U\checkVersionRule('5.0.14', '5.0.14', '5.0.14'));
    }

    /**
     * @testdox An older version is refused, also when only the database is newer
     */
    public function testOlderVersionIsRefused(): void
    {
        foreach (array(
            'older than the files' => array('5.0.13', '5.0.14', '5.0.14', 'SYS_CORE_UPDATE_VERSION_OLDER'),
            'stable after a newer beta' => array('5.1.0', '6.0.0-beta.1', null, 'SYS_CORE_UPDATE_VERSION_OLDER'),
            'database ahead of the files' => array('5.0.14', '5.0.14', '5.1.0', 'SYS_CORE_UPDATE_DATABASE_NEWER')
        ) as $case => [$target, $installed, $database, $key]) {
            try {
                U\checkVersionRule($target, $installed, $database);
                $this->fail('accepted: ' . $case);
            } catch (UpdaterException $exception) {
                $this->assertSame($key, $exception->getMessage(), $case);
            }
        }
    }

    /**
     * @testdox The installed version comes from the manifest of the release, or else from the constants
     */
    public function testFilesVersion(): void
    {
        mkdir($this->rootPath . '/system/bootstrap', 0777, true);
        mkdir($this->rootPath . '/install', 0777, true);

        file_put_contents($this->rootPath . '/system/bootstrap/constants.php', "<?php\nconst ADMIDIO_VERSION_MAIN = 6;\n"
            . "const ADMIDIO_VERSION_MINOR = 0;\nconst ADMIDIO_VERSION_PATCH = 0;\nconst ADMIDIO_VERSION_BETA = 2;\n"
            . "exit('never included');\n");
        $this->assertSame('6.0.0-beta.2', U\filesVersion($this->rootPath));

        file_put_contents($this->rootPath . '/install/update-manifest.json', json_encode(array('version' => '6.0.1')));
        $this->assertSame('6.0.1', U\filesVersion($this->rootPath), 'the manifest of the installed release wins');

        // The real constants file of this checkout has the form the pattern expects.
        $this->assertTrue(U\isCanonicalVersion(U\filesVersion(dirname(__DIR__, 3))));

        $this->expectException(UpdaterException::class);
        U\filesVersion($this->rootPath . '/nothing');
    }

    /**
     * @testdox The sidebar follows the update from step to step
     */
    public function testUpdateSteps(): void
    {
        $this->assertSame(array('check' => 'current', 'download' => 'pending', 'prepare' => 'pending', 'review' => 'pending',
            'backup' => 'pending', 'install' => 'pending', 'database' => 'pending', 'finish' => 'pending'),
            U\updateSteps(array('state' => 'new')));

        $installing = U\updateSteps(array('state' => 'archiving', 'backup' => 'dump'));
        $this->assertSame(array('done', 'done', 'done', 'done', 'done', 'current', 'pending', 'pending'), array_values($installing));

        $this->assertSame('skipped', U\updateSteps(array('state' => 'installing', 'backup' => 'own'))['backup'],
            'the administrator made the backup');
        $this->assertSame('failed', U\updateSteps(array('state' => 'checked', 'checks' => array(
            array('status' => 'error', 'text' => 'SYS_CORE_UPDATE_CHECK_SPACE_MISSING', 'params' => array())
        )))['check']);
        $this->assertSame(array_fill_keys(array_keys(U\UPDATE_STEPS), 'done'), U\updateSteps(array('state' => 'completed')));
    }

    /**
     * @testdox An update that is undone shows where it stopped and the restore
     */
    public function testUpdateStepsWhenUndone(): void
    {
        $this->assertSame('failed', U\updateSteps(array('state' => 'database_failed'))['database']);

        $restoring = U\updateSteps(array('state' => 'restoring_database', 'restoredVersion' => true, 'backup' => 'dump'));
        $this->assertSame(array('done', 'done', 'done', 'done', 'done', 'done', 'failed', 'skipped', 'current'), array_values($restoring));
        $this->assertSame('restore', array_key_last($restoring));

        $filesRestored = U\updateSteps(array('state' => 'rolled_back', 'backup' => 'dump'));
        $this->assertSame(array('failed', 'skipped', 'skipped', 'done'),
            array_values(array_intersect_key($filesRestored, array_flip(array('install', 'database', 'finish', 'restore')))));
    }

    /**
     * @testdox The sidebar names each step and marks the current one
     */
    public function testStepsSidebar(): void
    {
        $texts = array('language' => 'en', 'texts' => array('SYS_CORE_UPDATE_STEP_DOWNLOAD' => 'Download <update>'));

        $html = U\stepsSidebar(U\updateSteps(array('state' => 'downloading')), $texts);

        $this->assertStringContainsString('data-step="download" aria-current="step"><i class="bi bi-arrow-right-circle-fill text-primary"', $html);
        $this->assertStringContainsString('<span>Download &lt;update&gt;</span>', $html);
        $this->assertStringContainsString('"downloading":"download"', $html, 'the script knows the step of each state');
    }
}
