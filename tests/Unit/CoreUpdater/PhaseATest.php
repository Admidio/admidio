<?php
/**
 * Phase A from the choice of the channel to the prepared package, against PHP's built-in web server
 * on localhost (install/core-update/lib/phase-a.php). The key, update.txt and the package are made
 * by the test; the installation is a temporary directory with an old updater in it.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;
use AdmidioCoreUpdater\UpdaterException;
use PDO;
use ZipArchive;

final class PhaseATest extends AdmidioTestCase
{
    /** @var array<string,\OpenSSLAsymmetricKey> */
    private static array $private = array();
    /** @var array<string,string> */
    private static array $public = array();

    /** @var resource|null */
    private static $server = null;
    private static string $serverDirectory = '';
    private static string $baseUrl = '';

    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';

        foreach (array('release', 'stranger') as $name) {
            $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
            self::$private[$name] = $key;
            self::$public[$name] = openssl_pkey_get_details($key)['key'];
        }

        // A web server that answers range requests, and below /norange/ ignores them.
        self::$serverDirectory = sys_get_temp_dir() . '/adm_update_server_' . uniqid();
        mkdir(self::$serverDirectory . '/files', 0777, true);
        file_put_contents(self::$serverDirectory . '/router.php', '<?php
            $path = (string)parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            file_put_contents(__DIR__ . "/requests.log", $path . " " . ($_SERVER["HTTP_RANGE"] ?? "-") . "\n", FILE_APPEND);
            $noRange = str_starts_with($path, "/norange/");
            $file = __DIR__ . "/files" . ($noRange ? substr($path, 8) : $path);
            if (!is_file($file)) { http_response_code(404); exit; }
            $data = file_get_contents($file);
            if (!$noRange && preg_match("/^bytes=(\d+)-(\d+)$/", $_SERVER["HTTP_RANGE"] ?? "", $m) === 1) {
                http_response_code(206);
                echo substr($data, (int)$m[1], (int)$m[2] - (int)$m[1] + 1);
                exit;
            }
            echo $data;');

        $port = 19000 + random_int(0, 999);
        self::$server = proc_open(
            array(PHP_BINARY, '-S', '127.0.0.1:' . $port, self::$serverDirectory . '/router.php'),
            array(1 => array('file', self::$serverDirectory . '/server.log', 'w'), 2 => array('file', self::$serverDirectory . '/server.log', 'a')),
            $pipes
        );
        for ($attempt = 0; $attempt < 50 && @fsockopen('127.0.0.1', $port) === false; $attempt++) {
            usleep(100000);
        }
        self::$baseUrl = 'http://127.0.0.1:' . $port . '/';
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        U\deleteDirectory(self::$serverDirectory);

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // An installation of 5.0.14 with an old updater, configured for the test server and keys.
        $this->root = sys_get_temp_dir() . '/adm_update_root_' . uniqid();
        foreach (array('adm_my_files', 'install/core-update/lib', 'system/bootstrap', 'src') as $directory) {
            mkdir($this->root . '/' . $directory, 0777, true);
        }
        file_put_contents($this->root . '/release-key.pem', self::$public['release']);
        file_put_contents($this->root . '/adm_my_files/config.php', '<?php
            $gDbType = "mysql";
            $g_adm_db = "club";
            $g_root_path = "http://127.0.0.1";
            $gDebug = true;
            $gCoreUpdateServerUrl = ' . var_export(self::$baseUrl, true) . ';
            $gCoreUpdatePublicKeyFile = "release-key.pem";
            $gCoreUpdateArchiveUrl = ' . var_export(self::$baseUrl . 'archive/', true) . ';');
        file_put_contents($this->root . '/system/bootstrap/constants.php', "<?php\nconst ADMIDIO_VERSION_MAIN = 5;\n"
            . "const ADMIDIO_VERSION_MINOR = 0;\nconst ADMIDIO_VERSION_PATCH = 14;\nconst ADMIDIO_VERSION_BETA = 0;\n");
        file_put_contents($this->root . '/index.php', 'OLD INDEX');
        file_put_contents($this->root . '/install/core-update/index.php', 'OLD UPDATER');
        file_put_contents($this->root . '/install/core-update/lib/bootstrap.php', 'OLD BOOTSTRAP');
        file_put_contents($this->root . '/install/core-update/obsolete.php', 'OLD FILE');

        foreach (glob(self::$serverDirectory . '/files/*') ?: array() as $entry) {
            is_dir($entry) ? U\deleteDirectory($entry) : unlink($entry);
        }
        @unlink(self::$serverDirectory . '/requests.log');
    }

    protected function tearDown(): void
    {
        U\releaseLock();
        U\deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * Build a package of version 5.1.0 with its manifest.
     * @param array<string,string> $files path => content, added to the standard files
     * @param array<int,string> $unlisted paths the manifest leaves out
     * @return string The bytes of the ZIP.
     */
    private function buildPackage(array $files = array(), array $unlisted = array(), string $manifestVersion = '5.1.0'): string
    {
        $files = array_merge(array(
            'index.php' => 'NEW INDEX',
            'install/core-update/index.php' => 'NEW UPDATER',
            'install/core-update/cli.php' => 'NEW CLI',
            'install/core-update/entry.php' => 'NEW ENTRY',
            'install/core-update/updater.php' => 'NEW PAGES',
            'install/core-update/updater-cli.php' => 'NEW COMMAND LINE',
            'install/core-update/lib/bootstrap.php' => 'NEW BOOTSTRAP',
            'src/Foo.php' => random_bytes(250000)
        ), $files);

        $hashes = array();
        foreach ($files as $path => $content) {
            if (!in_array($path, $unlisted, true)) {
                $hashes[$path] = hash('sha256', $content);
            }
        }
        $manifest = json_encode(array(
            'version' => $manifestVersion,
            'managedDirectories' => array('src', 'install'),
            'managedRootFiles' => array('index.php'),
            'preservePaths' => array('adm_my_files'),
            'requirements' => array('php' => '>=8.2', 'extensions' => array('zip')),
            'totalSize' => array_sum(array_map('strlen', $files)),
            'files' => $hashes
        ));

        $file = self::$serverDirectory . '/build.zip';
        @unlink($file);
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);
        foreach ($files as $path => $content) {
            $zip->addFromString('admidio-5.1.0/' . $path, $content);
            $zip->setCompressionName('admidio-5.1.0/' . $path, ZipArchive::CM_STORE);
        }
        $zip->addFromString('admidio-5.1.0/install/update-manifest.json', $manifest);
        $zip->close();

        $bytes = (string)file_get_contents($file);
        unlink($file);

        return $bytes;
    }

    /**
     * Publish a package with update.txt and its signature on the test server.
     * @param array<string,string> $changes Lines of update.txt to replace.
     */
    private function publish(string $package, array $changes = array(), string $signer = 'release'): void
    {
        $files = self::$serverDirectory . '/files';
        file_put_contents($files . '/admidio-5.1.0.zip', $package);

        $lines = array_merge(array(
            'Version' => '5.1.0',
            'Package-URL' => self::$baseUrl . 'admidio-5.1.0.zip',
            'Package-SHA256' => hash('sha256', $package),
            'Package-Size' => (string)strlen($package),
            'Minimum-Source-Version' => '5.0.0',
            'Minimum-PHP-Version' => '8.2',
            'Beta-Version' => '',
            'Beta-Release' => ''
        ), $changes);
        $text = '';
        foreach ($lines as $name => $value) {
            $text .= $name . '=' . $value . "\n";
        }
        file_put_contents($files . '/update.txt', $text);
        file_put_contents($files . '/update.txt.sig', U\signData($text, self::$private[$signer]));
    }

    /**
     * The context of phase A for the test installation, with a database of the given version and
     * 100 KB download parts.
     * @return array<string,mixed>
     */
    private function context(string $databaseVersion = '5.0.14'): array
    {
        $pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
        $pdo->exec('CREATE TABLE adm_components (com_type TEXT, com_name_intern TEXT, com_version TEXT, com_beta INTEGER)');
        $pdo->exec("INSERT INTO adm_components VALUES ('SYSTEM', 'CORE', '" . $databaseVersion . "', 0)");

        $config = U\loadConfig($this->root);

        return array(
            'config' => $config,
            'pdo' => $pdo,
            'texts' => array('language' => 'en', 'texts' => array()),
            'updaterPath' => $this->root . '/install/core-update',
            'http' => array('plainHttpBase' => $config['serverUrl']),
            'chunkSize' => 100000
        );
    }

    private function createUpdate(): string
    {
        return U\createUpdate($this->root)['updateId'];
    }

    /**
     * Run the download and the verification until the update is prepared or stops.
     * @return array<string,mixed> The last answer.
     */
    private function runToTheEnd(array $context, string $updateId): array
    {
        for ($request = 0; $request < 20; $request++) {
            $result = U\runStep($context, $updateId);
            if ($result['status'] !== 'success') {
                return $result;
            }
        }

        $this->fail('phase A did not finish');
    }

    /**
     * @testdox Phase A checks, downloads, verifies and extracts the package, and changes nothing outside the workspace
     */
    public function testPhaseA(): void
    {
        $this->publish($this->buildPackage());
        $context = $this->context();
        $updateId = $this->createUpdate();

        $state = U\phaseACheck($context, $updateId, 'stable');
        $this->assertSame('checked', $state['state'], (string)$state['lastError']);
        $this->assertSame('5.0.14', $state['installedVersion']);
        $this->assertSame('5.1.0', $state['targetVersion']);
        $this->assertSame('update', $state['versionRule']);
        $this->assertFalse(U\checksHaveErrors($state['checks']), json_encode($state['checks']));
        $workspace = U\workspace($this->root, $updateId);
        foreach (array('release-key.pem', 'update.txt', 'update.txt.sig', 'probe.txt') as $file) {
            $this->assertFileExists($workspace . '/' . $file, 'kept in the workspace: ' . $file);
        }

        $this->assertSame('downloading', U\phaseAConfirm($context, $updateId)['state']);
        $result = $this->runToTheEnd($context, $updateId);

        $this->assertSame('prepared', $result['status'], $result['message']);
        $state = U\readState($this->root, $updateId);
        $this->assertSame('prepared', $state['state']);
        $this->assertTrue($state['runUnpackedUpdater'], 'from now on the updater of the new release runs');
        $this->assertSame('NEW UPDATER', file_get_contents($workspace . '/extracted/install/core-update/index.php'));
        $this->assertSame('NEW INDEX', file_get_contents($workspace . '/extracted/index.php'));
        $this->assertSame('OLD UPDATER', file_get_contents($this->root . '/install/core-update/index.php'), 'the running updater is not replaced');
        $this->assertSame('OLD BOOTSTRAP', file_get_contents($this->root . '/install/core-update/lib/bootstrap.php'));
        $this->assertFileExists($this->root . '/install/core-update/obsolete.php');
        $this->assertSame('OLD INDEX', file_get_contents($this->root . '/index.php'));
        $this->assertFileExists($workspace . '/update-manifest.json');

        $log = (string)file_get_contents(self::$serverDirectory . '/requests.log');
        $this->assertGreaterThan(2, substr_count($log, '/admidio-5.1.0.zip bytes='), 'the package came in several parts');

        // phpunit.xml runs without a time limit, like the command line; the download must not add one.
        $this->assertSame('0', ini_get('max_execution_time'));
    }

    /**
     * @testdox An interrupted download continues where it stopped
     */
    public function testDownloadContinues(): void
    {
        $package = $this->buildPackage();
        $this->publish($package);
        $context = $this->context();
        $updateId = $this->createUpdate();
        U\phaseACheck($context, $updateId, 'stable');
        U\phaseAConfirm($context, $updateId);

        // The first request got 150000 bytes before it ended.
        file_put_contents(U\workspace($this->root, $updateId) . '/package.zip', substr($package, 0, 150000));

        $this->assertSame('prepared', $this->runToTheEnd($context, $updateId)['status']);
        $log = (string)file_get_contents(self::$serverDirectory . '/requests.log');
        $this->assertStringContainsString('/admidio-5.1.0.zip bytes=150000-249999', $log);
        $this->assertStringNotContainsString('/admidio-5.1.0.zip bytes=0-', $log);
    }

    /**
     * @testdox A server without range requests delivers the package at once
     */
    public function testServerWithoutRanges(): void
    {
        $package = $this->buildPackage();
        $this->publish($package, array('Package-URL' => self::$baseUrl . 'norange/admidio-5.1.0.zip'));
        $context = $this->context();
        $updateId = $this->createUpdate();
        U\phaseACheck($context, $updateId, 'stable');
        U\phaseAConfirm($context, $updateId);

        $this->assertSame('prepared', $this->runToTheEnd($context, $updateId)['status']);
    }

    /**
     * @testdox A failed check returns to the choice of the channel and can be repeated
     */
    public function testFailedCheck(): void
    {
        $this->publish($this->buildPackage(), array(), 'stranger');
        $context = $this->context();
        $updateId = $this->createUpdate();

        $state = U\phaseACheck($context, $updateId, 'stable');
        $this->assertSame('new', $state['state']);
        $this->assertSame('SYS_CORE_UPDATE_SIGNATURE_INVALID', $state['lastError']);

        $this->publish($this->buildPackage());
        $this->assertSame('checked', U\phaseACheck($context, $updateId, 'stable')['state'], 'after the server was fixed');
    }

    /**
     * @testdox An older version and a pending database update are reported, and the download is refused
     */
    public function testBlockingChecks(): void
    {
        $this->publish($this->buildPackage());
        $updateId = $this->createUpdate();

        $context = $this->context('5.0.13');
        $state = U\phaseACheck($context, $updateId, 'stable');
        $this->assertTrue(U\checksHaveErrors($state['checks']));
        $this->assertContains('SYS_CORE_UPDATE_CHECK_DATABASE_PENDING', array_column($state['checks'], 'text'));

        try {
            U\phaseAConfirm($context, $updateId);
            $this->fail('the download was started despite an error');
        } catch (UpdaterException $exception) {
            $this->assertSame('SYS_CORE_UPDATE_REQUEST_INVALID', $exception->getMessage());
        }
    }

    /**
     * @return array<string,array{0: callable(self): void, 1: string}>
     */
    public static function tamperedReleases(): array
    {
        return array(
            'package changed' => array(static function (self $test): void {
                $test->publish($test->buildPackage(), array('Package-SHA256' => str_repeat('0', 64)));
            }, 'SYS_CORE_UPDATE_PACKAGE_INVALID'),
            'manifest of another version' => array(static function (self $test): void {
                $test->publish($test->buildPackage(array(), array(), '5.0.99'));
            }, 'SYS_CORE_UPDATE_MANIFEST_INVALID'),
            'file not in the manifest' => array(static function (self $test): void {
                $test->publish($test->buildPackage(array('install/core-update/lib/Extra.php' => 'EXTRA'), array('install/core-update/lib/Extra.php')));
            }, 'SYS_CORE_UPDATE_MANIFEST_INVALID'),
            'no updater in the package' => array(static function (self $test): void {
                $test->publish($test->buildPackageWithoutUpdater());
            }, 'SYS_CORE_UPDATE_UPDATER_MISSING')
        );
    }

    /**
     * A package whose manifest is valid but which contains no complete updater.
     */
    private function buildPackageWithoutUpdater(): string
    {
        $package = $this->buildPackage();
        $file = self::$serverDirectory . '/strip.zip';
        file_put_contents($file, $package);
        $zip = new ZipArchive();
        $zip->open($file);
        $zip->deleteName('admidio-5.1.0/install/core-update/updater.php');
        $manifest = json_decode((string)$zip->getFromName('admidio-5.1.0/install/update-manifest.json'), true);
        unset($manifest['files']['install/core-update/updater.php']);
        $zip->addFromString('admidio-5.1.0/install/update-manifest.json', (string)json_encode($manifest));
        $zip->close();
        $bytes = (string)file_get_contents($file);
        unlink($file);

        return $bytes;
    }

    /**
     * @testdox A release that fails verification ends the update and leaves the installation untouched
     * @dataProvider tamperedReleases
     */
    public function testFailedVerification(callable $tamper, string $key): void
    {
        $tamper($this);
        $context = $this->context();
        $updateId = $this->createUpdate();
        $this->assertSame('checked', U\phaseACheck($context, $updateId, 'stable')['state']);
        U\phaseAConfirm($context, $updateId);

        $result = $this->runToTheEnd($context, $updateId);

        $this->assertSame('failed', $result['status']);
        $this->assertSame($key, $result['message']);
        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId), 'the workspace is removed');
        $this->assertNull(U\activeUpdateId($this->root));
        $this->assertSame('OLD UPDATER', file_get_contents($this->root . '/install/core-update/index.php'));
        $this->assertFileExists($this->root . '/install/core-update/obsolete.php');
        $this->assertFileDoesNotExist($this->root . '/adm_my_files/maintenance.json');
    }

    /**
     * @testdox Cancelling removes the workspace of an update that changed nothing
     */
    public function testCancel(): void
    {
        $this->publish($this->buildPackage());
        $context = $this->context();
        $updateId = $this->createUpdate();
        U\phaseACheck($context, $updateId, 'stable');

        U\cancelUpdate($context, $updateId);

        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId));
        $this->assertNull(U\activeUpdateId($this->root));
    }

    /**
     * @testdox Only the channels of the installation can be chosen
     */
    public function testChannels(): void
    {
        $this->assertSame(array('stable'), U\availableChannels(U\configFromValues($this->root, array())));
        $this->assertSame(array('stable', 'beta', 'master', 'branch', 'tag', 'pr'),
            U\availableChannels(U\configFromValues($this->root, array('gDebug' => true))));

        $updateId = $this->createUpdate();
        $this->expectExceptionMessage('SYS_CORE_UPDATE_REQUEST_INVALID');
        U\phaseACheck($this->context(), $updateId, 'pr:1234');
    }

    /**
     * Publish a source archive as GitHub makes it: one top directory, no vendor/, no manifest, and
     * the commit in the ZIP comment.
     * @param array<string,string> $files path => content, added to the standard files
     * @return string The SHA-256 of the archive.
     */
    private function publishSourceArchive(string $path, array $files = array(), string $version = '5.1.0', string $comment = ''): string
    {
        [$main, $minor, $patch] = array_map('intval', explode('.', $version));
        $files = array_merge(array(
            'index.php' => 'DEV INDEX',
            'system/bootstrap/constants.php' => "<?php\nconst ADMIDIO_VERSION_MAIN = " . $main . ";\nconst ADMIDIO_VERSION_MINOR = " . $minor
                . ";\nconst ADMIDIO_VERSION_PATCH = " . $patch . ";\nconst ADMIDIO_VERSION_BETA = 0;\n",
            'composer.json' => '{"require": {"php": "^8.2", "ext-zip": "*", "ext-json": "*"},'
                . ' "archive": {"exclude": ["tests/*", ".github", "plugins/*", "!plugins/birthday"]}}',
            'languages/en.xml' => '<?xml version="1.0" encoding="UTF-8"?><resources><string name="SYS_CORE_UPDATE_TITLE">Admidio\'s update</string>'
                . '<string name="SYS_SAVE">Save</string></resources>',
            'plugins/birthday/index.php' => 'OFFICIAL PLUGIN',
            'plugins/my-own/index.php' => 'NOT A PART OF THE RELEASE',
            'composer.lock' => '{"packages": []}',
            'install/core-update/index.php' => 'DEV UPDATER',
            'install/core-update/cli.php' => 'DEV CLI',
            'install/core-update/entry.php' => 'DEV ENTRY',
            'install/core-update/updater.php' => 'DEV PAGES',
            'install/core-update/updater-cli.php' => 'DEV COMMAND LINE',
            'install/core-update/lib/bootstrap.php' => 'DEV BOOTSTRAP',
            'src/Dev.php' => 'DEV FILE',
            'tests/DevTest.php' => 'A TEST',
            '.github/workflows/ci.yml' => 'CI'
        ), $files);

        $file = self::$serverDirectory . '/files/archive/' . $path;
        @mkdir(dirname($file), 0777, true);
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString('admidio-dev/' . $name, $content);
        }
        $zip->setArchiveComment($comment === '' ? str_repeat('3f5711fa', 5) : $comment);
        $zip->close();

        return (string)hash_file('sha256', $file);
    }

    /**
     * @testdox A developer channel installs the confirmed source archive with a manifest of its own
     */
    public function testDeveloperChannel(): void
    {
        $sha256 = $this->publishSourceArchive('refs/heads/feature/new-login.zip');
        file_put_contents($this->root . '/composer.lock', '{"packages": ["old"]}');
        $context = $this->context();
        $updateId = $this->createUpdate();

        $state = U\phaseACheck($context, $updateId, 'branch', 'feature/new-login');
        $this->assertSame('checked', $state['state'], (string)$state['lastError']);
        $this->assertSame('5.1.0', $state['targetVersion']);
        $this->assertSame('5.1.0 (feature/new-login 3f5711f)', $state['targetVersionText']);
        $this->assertSame(str_repeat('3f5711fa', 5), $state['commit']);
        $this->assertSame($sha256, $state['packageSha256'], 'the archive the administrator confirms');
        $this->assertFalse(U\checksHaveErrors($state['checks']), json_encode($state['checks']));
        $this->assertContains('SYS_CORE_UPDATE_CHECK_DEVELOPER', array_column($state['checks'], 'text'));
        $this->assertContains('SYS_CORE_UPDATE_CHECK_COMPOSER', array_column($state['checks'], 'text'));
        $this->assertTrue($state['composerChanged']);

        $this->assertSame('downloaded', U\phaseAConfirm($context, $updateId)['state'], 'the archive is already there');
        $result = $this->runToTheEnd($context, $updateId);

        $this->assertSame('prepared', $result['status'], $result['message']);
        $workspace = U\workspace($this->root, $updateId);
        $this->assertTrue(U\readState($this->root, $updateId)['runUnpackedUpdater']);
        $this->assertSame('DEV UPDATER', file_get_contents($workspace . '/extracted/install/core-update/index.php'));
        $this->assertSame('OLD UPDATER', file_get_contents($this->root . '/install/core-update/index.php'));
        $this->assertSame('OLD INDEX', file_get_contents($this->root . '/index.php'));
        $manifest = json_decode((string)file_get_contents($workspace . '/update-manifest.json'), true);
        $this->assertTrue($manifest['developer']);
        $this->assertSame('5.1.0', $manifest['version']);
        $this->assertSame(hash('sha256', 'DEV FILE'), $manifest['files']['src/Dev.php']);
        $this->assertArrayHasKey('index.php', $manifest['files']);
        $this->assertArrayNotHasKey('tests/DevTest.php', $manifest['files'], 'what a release would contain: archive.exclude of composer.json');
        $this->assertArrayNotHasKey('.github/workflows/ci.yml', $manifest['files']);
        $this->assertArrayHasKey('plugins/birthday/index.php', $manifest['files'], 'the official plugins of the new version');
        $this->assertArrayNotHasKey('plugins/my-own/index.php', $manifest['files']);
        $this->assertSame(array('install', 'languages', 'plugins/birthday', 'src', 'system'), $manifest['managedDirectories']);
        $this->assertNotContains('vendor', $manifest['managedDirectories']);
        $texts = (string)file_get_contents($workspace . '/extracted/install/core-update/texts/en.json');
        $this->assertSame(array('SYS_CORE_UPDATE_TITLE' => 'Admidio\'s update'), json_decode($texts, true),
            'the texts of the new updater, which the installed language files lack');
        $this->assertSame(hash('sha256', $texts), $manifest['files']['install/core-update/texts/en.json'], 'and which are installed with it');
        $this->assertSame(array('php' => '>=8.2', 'extensions' => array('zip', 'json')), $manifest['requirements']);
        $this->assertStringContainsString('/archive/refs/heads/feature/new-login.zip',
            (string)file_get_contents(self::$serverDirectory . '/requests.log'));
    }

    /**
     * @testdox A source archive that changed after the confirmation is not installed
     */
    public function testDeveloperArchiveChanged(): void
    {
        $this->publishSourceArchive('refs/pull/1234/head.zip');
        $context = $this->context();
        $updateId = $this->createUpdate();
        $this->assertSame('checked', U\phaseACheck($context, $updateId, 'pr', '1234')['state']);
        U\phaseAConfirm($context, $updateId);

        file_put_contents(U\workspace($this->root, $updateId) . '/package.zip', 'A NEWER PUSH', FILE_APPEND);
        $result = $this->runToTheEnd($context, $updateId);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('SYS_CORE_UPDATE_PACKAGE_INVALID', $result['message']);
        $this->assertSame('OLD UPDATER', file_get_contents($this->root . '/install/core-update/index.php'));
    }

    /**
     * @testdox A developer may install an older version after a warning
     */
    public function testDeveloperChecks(): void
    {
        $this->publishSourceArchive('refs/tags/v5.0.10.zip', array(), '5.0.10');
        file_put_contents($this->root . '/composer.lock', '{"packages": []}');
        $context = $this->context();
        $updateId = $this->createUpdate();

        $state = U\phaseACheck($context, $updateId, 'tag', 'v5.0.10');
        $this->assertFalse(U\checksHaveErrors($state['checks']), json_encode($state['checks']));
        $this->assertContains('SYS_CORE_UPDATE_CHECK_OLDER_DEVELOPER', array_column($state['checks'], 'text'));
        $this->assertNotContains('SYS_CORE_UPDATE_CHECK_COMPOSER', array_column($state['checks'], 'text'), 'the same composer.lock is installed');

        $state = U\phaseACheck($context, $updateId, 'branch', '../../etc');
        $this->assertSame('new', $state['state']);
        $this->assertSame('SYS_CORE_UPDATE_REF_INVALID', $state['lastError']);
    }

    /**
     * @testdox A source archive without the updater is installed by the installed updater, which it leaves alone
     */
    public function testDeveloperWithoutUpdater(): void
    {
        $this->publishSourceArchive('refs/heads/master.zip');
        $file = self::$serverDirectory . '/files/archive/refs/heads/master.zip';
        $zip = new ZipArchive();
        $zip->open($file);
        foreach (array('index.php', 'cli.php', 'entry.php', 'updater.php', 'updater-cli.php', 'lib/bootstrap.php') as $updaterFile) {
            $zip->deleteName('admidio-dev/install/core-update/' . $updaterFile);
        }
        $zip->close();
        $context = $this->context();
        $updateId = $this->createUpdate();

        $state = U\phaseACheck($context, $updateId, 'master');
        $this->assertFalse(U\checksHaveErrors($state['checks']), json_encode($state['checks']));
        $this->assertContains('SYS_CORE_UPDATE_CHECK_WITHOUT_UPDATER', array_column($state['checks'], 'text'));
        $this->assertTrue($state['withoutUpdater']);

        U\phaseAConfirm($context, $updateId);
        $this->assertSame('prepared', $this->runToTheEnd($context, $updateId)['status']);
        $state = U\readState($this->root, $updateId);
        $this->assertFalse($state['runUnpackedUpdater'], 'the installed updater goes on');
        $manifest = json_decode((string)file_get_contents(U\workspace($this->root, $updateId) . '/update-manifest.json'), true);
        $this->assertSame(array(), preg_grep('#^install/core-update/#', array_keys($manifest['files'])), 'the release installs nothing of an updater');
        $this->assertSame('OLD UPDATER', file_get_contents($this->root . '/install/core-update/index.php'));
        $this->assertFileExists($this->root . '/install/core-update/obsolete.php');
    }

    /**
     * @return array<string,array{0: string, 1: string, 2: string}>
     */
    public static function sourceArchives(): array
    {
        return array(
            'master' => array('master', '', 'refs/heads/master.zip'),
            'branch' => array('branch', 'v5.0', 'refs/heads/v5.0.zip'),
            'branch with a slash' => array('branch', 'feature/new-login', 'refs/heads/feature/new-login.zip'),
            'tag' => array('tag', 'v5.0.17', 'refs/tags/v5.0.17.zip'),
            'pull request' => array('pr', '2243', 'refs/pull/2243/head.zip')
        );
    }

    /**
     * @testdox The address of a source archive is built by the updater: $_dataName
     * @dataProvider sourceArchives
     */
    public function testSourceArchiveUrl(string $channel, string $ref, string $path): void
    {
        $this->assertSame('https://github.com/Admidio/admidio/archive/' . $path,
            U\sourceArchiveUrl(U\configFromValues($this->root, array('gDebug' => true)), $channel, $ref));
    }

    /**
     * @testdox Names that are no plain branch, tag or pull request are refused
     */
    public function testInvalidReferences(): void
    {
        $config = U\configFromValues($this->root, array('gDebug' => true));
        foreach (array(array('branch', ''), array('branch', '../master'), array('branch', 'a b'), array('branch', '/master'),
            array('branch', 'master.lock'), array('tag', 'v1?x=1'), array('pr', '0'), array('pr', '12a'), array('master', 'other'),
            array('stable', '')) as [$channel, $ref]) {
            try {
                U\sourceArchiveUrl($config, $channel, $ref);
                $this->fail('accepted: ' . $channel . ' ' . $ref);
            } catch (UpdaterException $exception) {
                $this->assertSame('SYS_CORE_UPDATE_REF_INVALID', $exception->getMessage());
            }
        }
    }

    /**
     * @testdox The beta channel installs the release of the Beta lines of update.txt
     */
    public function testBetaChannel(): void
    {
        $package = $this->buildPackage(array(), array(), '5.2.0-beta.1');
        $this->publish($package, array(
            'Package-URL' => '',
            'Beta-Version' => '5.2.0',
            'Beta-Release' => '1',
            'Beta-Package-URL' => self::$baseUrl . 'admidio-5.1.0.zip',
            'Beta-Package-SHA256' => hash('sha256', $package),
            'Beta-Package-Size' => (string)strlen($package),
            'Beta-Minimum-Source-Version' => '5.0.0',
            'Beta-Minimum-PHP-Version' => '8.2'
        ));
        $context = $this->context();
        $updateId = $this->createUpdate();

        $this->assertSame('SYS_CORE_UPDATE_NOT_AVAILABLE', U\phaseACheck($context, $updateId, 'stable')['lastError'], 'no stable package');

        $state = U\phaseACheck($context, $updateId, 'beta');
        $this->assertSame('checked', $state['state'], (string)$state['lastError']);
        $this->assertSame('5.2.0-beta.1', $state['targetVersion']);
        U\phaseAConfirm($context, $updateId);
        $this->assertSame('prepared', $this->runToTheEnd($context, $updateId)['status']);
    }

    /**
     * @testdox A changed update.txt in the workspace ends the update
     *
     * The download relies on what was verified at the check; the signature is checked again with
     * every request.
     */
    public function testChangedWorkspace(): void
    {
        $this->publish($this->buildPackage());
        $context = $this->context();
        $updateId = $this->createUpdate();
        U\phaseACheck($context, $updateId, 'stable');
        U\phaseAConfirm($context, $updateId);

        $file = U\workspace($this->root, $updateId) . '/update.txt';
        file_put_contents($file, str_replace('Minimum-PHP-Version=8.2', 'Minimum-PHP-Version=8.1', (string)file_get_contents($file)));

        $result = U\runStep($context, $updateId);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('SYS_CORE_UPDATE_SIGNATURE_INVALID', $result['message']);
        $this->assertNull(U\activeUpdateId($this->root));
    }
}
