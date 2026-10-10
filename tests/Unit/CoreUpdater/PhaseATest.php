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
            $gCoreUpdatePublicKeyFile = "release-key.pem";');
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
            'preservePaths' => array('adm_my_files', 'install/core-update'),
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
            $result = U\phaseARun($context, $updateId);
            if ($result['status'] !== 'success') {
                return $result;
            }
        }

        $this->fail('phase A did not finish');
    }

    /**
     * @testdox Phase A checks, downloads, verifies and replaces the updater, and nothing else
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
        $this->assertSame('prepared', U\readState($this->root, $updateId)['state']);
        $this->assertSame('NEW UPDATER', file_get_contents($this->root . '/install/core-update/index.php'));
        $this->assertSame('NEW BOOTSTRAP', file_get_contents($this->root . '/install/core-update/lib/bootstrap.php'));
        $this->assertFileDoesNotExist($this->root . '/install/core-update/obsolete.php', 'a file the new updater does not have is removed');
        $this->assertSame('OLD INDEX', file_get_contents($this->root . '/index.php'), 'phase A touches nothing outside the updater');
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
            'updater file not in the manifest' => array(static function (self $test): void {
                $test->publish($test->buildPackage(array('install/core-update/lib/Extra.php' => 'EXTRA'), array('install/core-update/lib/Extra.php')));
            }, 'SYS_CORE_UPDATE_MANIFEST_INVALID'),
            'no updater in the package' => array(static function (self $test): void {
                $test->publish($test->buildPackageWithoutUpdater());
            }, 'SYS_CORE_UPDATE_UPDATER_MISSING')
        );
    }

    /**
     * A package whose manifest is valid but which contains no updater.
     */
    private function buildPackageWithoutUpdater(): string
    {
        $package = $this->buildPackage();
        $file = self::$serverDirectory . '/strip.zip';
        file_put_contents($file, $package);
        $zip = new ZipArchive();
        $zip->open($file);
        $zip->deleteName('admidio-5.1.0/install/core-update/index.php');
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

        U\phaseACancel($context, $updateId);

        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId));
        $this->assertNull(U\activeUpdateId($this->root));
    }

    /**
     * @testdox Only the channels of the installation can be chosen
     */
    public function testChannels(): void
    {
        $this->assertSame(array('stable'), U\availableChannels(U\configFromValues($this->root, array())));
        $this->assertSame(array('stable', 'beta'), U\availableChannels(U\configFromValues($this->root, array('gDebug' => true))));

        $updateId = $this->createUpdate();
        $this->expectExceptionMessage('SYS_CORE_UPDATE_REQUEST_INVALID');
        U\phaseACheck($this->context(), $updateId, 'pr:1234');
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

        $result = U\phaseARun($context, $updateId);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('SYS_CORE_UPDATE_SIGNATURE_INVALID', $result['message']);
        $this->assertNull(U\activeUpdateId($this->root));
    }
}
