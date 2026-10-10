<?php
/**
 * Getting a release and checking it (install/core-update/lib/download.php): the HTTPS transport,
 * the signature of update.txt, the release information in it, the manifest of a package and the
 * reading of packages that may be hostile. The network is replaced by recorded answers, the keys
 * and archives are made by the test.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;
use AdmidioCoreUpdater\UpdaterException;
use OpenSSLAsymmetricKey;
use ZipArchive;

final class DownloadTest extends AdmidioTestCase
{
    /** @var array<string,OpenSSLAsymmetricKey> */
    private static array $private = array();
    /** @var array<string,string> PEM of the public keys */
    private static array $public = array();

    /** @var array<int,string> The URLs that were requested. */
    private array $requested = array();
    private string $directory = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';

        foreach (array('release', 'stranger') as $name) {
            $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
            self::$private[$name] = $key;
            self::$public[$name] = openssl_pkey_get_details($key)['key'];
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/adm_updater_download_' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        U\deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * The options of httpGet() with a network that answers with the given responses, keyed by URL.
     * @param array<string,array{0: int, 1: string}> $responses url => [status, location or body]
     * @return array{plainHttpBase: string, transport: callable}
     */
    private function http(array $responses, string $plainHttpBase = ''): array
    {
        return array('plainHttpBase' => $plainHttpBase, 'transport' => function (string $url, int $maxBytes, array $headers) use ($responses): array {
            $this->requested[] = $url;
            [$status, $value] = $responses[$url] ?? array(404, '');
            $redirect = $status >= 300 && $status < 400;

            return array('status' => $status, 'location' => $redirect ? $value : '', 'body' => $redirect ? '' : $value);
        });
    }

    private function assertRefused(string $key, callable $call, string $message = ''): void
    {
        try {
            $call();
            $this->fail('not refused: ' . $message);
        } catch (UpdaterException $exception) {
            $this->assertSame($key, $exception->getMessage(), $message . ' - ' . $exception->getDetail());
        }
    }

    // --------------------------------------------------------------------------------------------
    // HTTPS
    // --------------------------------------------------------------------------------------------

    /**
     * @testdox Redirects are followed hop by hop, also relative ones
     */
    public function testRedirects(): void
    {
        $http = $this->http(array(
            'https://www.example.org/update.txt' => array(302, '/mirror/update.txt'),
            'https://www.example.org/mirror/update.txt' => array(301, '//cdn.example.net/a/b/update.txt'),
            'https://cdn.example.net/a/b/update.txt' => array(307, 'final.txt?x=1'),
            'https://cdn.example.net/a/b/final.txt?x=1' => array(200, 'Version=5.1.0')
        ));

        $this->assertSame('Version=5.1.0', U\httpGet('https://www.example.org/update.txt', 1000, $http));
        $this->assertCount(4, $this->requested);
    }

    /**
     * @testdox A redirect to plain HTTP is refused
     */
    public function testRedirectToHttpIsRefused(): void
    {
        $http = $this->http(array('https://www.example.org/update.txt' => array(302, 'http://www.example.org/update.txt')));

        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', fn() => U\httpGet('https://www.example.org/update.txt', 1000, $http));
        $this->assertSame(array('https://www.example.org/update.txt'), $this->requested, 'the HTTP URL was never requested');
    }

    /**
     * @testdox More than five redirects are refused
     */
    public function testRedirectLimit(): void
    {
        $responses = array();
        for ($hop = 0; $hop <= U\MAX_REDIRECTS; $hop++) {
            $responses['https://example.org/' . $hop] = array(302, '/' . ($hop + 1));
        }
        $responses['https://example.org/' . (U\MAX_REDIRECTS + 1)] = array(200, 'too late');

        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_FAILED', fn() => U\httpGet('https://example.org/0', 1000, $this->http($responses)));

        $responses['https://example.org/' . U\MAX_REDIRECTS] = array(200, 'in time');
        $this->assertSame('in time', U\httpGet('https://example.org/0', 1000, $this->http($responses)), 'five are fine');
    }

    /**
     * @testdox Only HTTPS without credentials is requested
     */
    public function testAllowedUrls(): void
    {
        foreach (array('http://example.org/a', 'ftp://example.org/a', 'file:///etc/passwd', 'https://user:pw@example.org/a', 'https:///a', 'no url') as $url) {
            $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', fn() => U\assertAllowedUrl($url), $url);
        }
        U\assertAllowedUrl('https://any-mirror.example.net/admidio.zip');
        $this->addToAssertionCount(1);
    }

    /**
     * @testdox Plain HTTP is only allowed below the test server of debug mode
     */
    public function testPlainHttpForTheTestServer(): void
    {
        $http = $this->http(array('http://localhost:8090/update.txt' => array(200, 'local')), 'http://localhost:8090');

        $this->assertSame('local', U\httpGet('http://localhost:8090/update.txt', 1000, $http));
        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', fn() => U\assertAllowedUrl('http://localhost:8080/update.txt', 'http://localhost:8090'));
        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', fn() => U\assertAllowedUrl('http://example.org/update.txt', 'http://localhost:8090'));
    }

    /**
     * @testdox An error status is a failed download
     */
    public function testErrorStatus(): void
    {
        $http = $this->http(array('https://example.org/gone.zip' => array(404, 'not found')));

        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_FAILED', fn() => U\httpGet('https://example.org/gone.zip', 1000, $http));
    }

    /**
     * @testdox A range is requested with its header; a server without ranges sends the whole file
     */
    public function testRange(): void
    {
        $headers = array();
        $http = array('transport' => static function (string $url, int $maxBytes, array $requestHeaders) use (&$headers): array {
            $headers = $requestHeaders;

            return array('status' => 206, 'location' => '', 'body' => 'cdef');
        });
        $this->assertSame(array('status' => 206, 'body' => 'cdef'), U\httpGetRange('https://example.org/p.zip', 2, 4, 100, $http));
        $this->assertSame(array('Range: bytes=2-5'), $headers);

        $whole = $this->http(array('https://example.org/p.zip' => array(200, 'abcdefgh')));
        $this->assertSame(array('status' => 200, 'body' => 'abcdefgh'), U\httpGetRange('https://example.org/p.zip', 2, 4, 100, $whole));

        $tooMuch = array('transport' => static fn(): array => array('status' => 206, 'location' => '', 'body' => 'cdefg'));
        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_FAILED', fn() => U\httpGetRange('https://example.org/p.zip', 2, 4, 100, $tooMuch));
    }

    /**
     * @testdox The real transport reports redirects without following them and stops at the size limit
     *
     * It talks to PHP's built-in web server on localhost, through the plain HTTP exception of a
     * test server. The limit is counted on the bytes that arrive.
     */
    public function testRealTransport(): void
    {
        file_put_contents($this->directory . '/router.php', '<?php
            if ($_SERVER["REQUEST_URI"] === "/moved") { header("Location: /data", true, 302); exit; }
            if (preg_match("/^bytes=(\d+)-(\d+)$/", $_SERVER["HTTP_RANGE"] ?? "", $m) === 1) {
                http_response_code(206); echo substr(implode("", range("a", "z")), (int)$m[1], (int)$m[2] - (int)$m[1] + 1); exit;
            }
            echo str_repeat("x", 5000);');

        $port = 18000 + random_int(0, 999);
        $server = proc_open(
            array(PHP_BINARY, '-S', '127.0.0.1:' . $port, $this->directory . '/router.php'),
            array(1 => array('file', $this->directory . '/server.log', 'w'), 2 => array('file', $this->directory . '/server.log', 'a')),
            $pipes
        );

        try {
            $base = 'http://127.0.0.1:' . $port . '/';
            for ($attempt = 0; $attempt < 50 && @fsockopen('127.0.0.1', $port) === false; $attempt++) {
                usleep(100000);
            }

            $moved = U\curlTransport($base . 'moved', 1000);
            $this->assertSame(302, $moved['status']);
            $this->assertSame('/data', $moved['location']);

            $http = array('plainHttpBase' => $base);
            $this->assertSame(5000, strlen(U\httpGet($base . 'moved', 5000, $http)), 'followed by the updater, not by cURL');
            $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_FAILED', fn() => U\httpGet($base . 'data', 4999, $http));
            $this->assertSame(array('status' => 206, 'body' => 'klmnopqrst'), U\httpGetRange($base . 'data', 10, 10, 5000, $http));
        } finally {
            proc_terminate($server);
            proc_close($server);
        }
    }

    // --------------------------------------------------------------------------------------------
    // Signature and release information
    // --------------------------------------------------------------------------------------------

    /**
     * An update.txt that offers 5.1.0 as stable and 5.2.0-beta.1 as beta.
     * @param array<string,string> $changes Lines to replace or add.
     */
    private static function updateInfo(array $changes = array()): string
    {
        $lines = array_merge(array(
            'Version' => '5.1.0',
            'Package-URL' => 'https://downloads.example.org/admidio-5.1.0.zip',
            'Package-SHA256' => str_repeat('a', 64),
            'Package-Size' => '12345678',
            'Minimum-Source-Version' => '5.0.0',
            'Minimum-PHP-Version' => '8.2',
            'Beta-Version' => '5.2.0',
            'Beta-Release' => '1',
            'Beta-Package-URL' => 'https://downloads.example.org/admidio-5.2.0-beta.1.zip',
            'Beta-Package-SHA256' => str_repeat('b', 64),
            'Beta-Package-Size' => '23456789',
            'Beta-Minimum-Source-Version' => '5.1.0',
            'Beta-Minimum-PHP-Version' => '8.3'
        ), $changes);

        $text = '';
        foreach ($lines as $name => $value) {
            $text .= $name . '=' . $value . "\r\n";
        }

        return $text;
    }

    /**
     * @testdox A signature covers the exact bytes
     */
    public function testSignature(): void
    {
        $signature = U\signData('Version=5.1.0', self::$private['release']);

        $this->assertTrue(U\verifySignature('Version=5.1.0', $signature, self::$public['release']));
        $this->assertFalse(U\verifySignature('Version=5.1.0 ', $signature, self::$public['release']), 'one byte more');
        $this->assertFalse(U\verifySignature('Version=5.1.0', $signature, self::$public['stranger']), 'another key');
        $this->assertFalse(U\verifySignature('Version=5.1.0', 'not base64!', self::$public['release']));
        $this->assertFalse(U\verifySignature('Version=5.1.0', '', self::$public['release']));
        $this->assertFalse(U\verifySignature('Version=5.1.0', $signature, 'not a key'));
    }

    /**
     * @testdox update.txt is only accepted with a signature of the release key
     */
    public function testFetchUpdateInfo(): void
    {
        file_put_contents($this->directory . '/release-key.pem', self::$public['release']);
        $config = U\configFromValues($this->directory, array());
        $text = self::updateInfo();

        $http = $this->http(array(
            U\UPDATE_INFO_URL => array(200, $text),
            U\UPDATE_INFO_URL . '.sig' => array(200, U\signData($text, self::$private['release']))
        ));
        $info = U\fetchUpdateInfo($config, $this->directory, $http);
        $this->assertSame($text, $info['text']);
        $this->assertSame(self::$public['release'], $info['key'], 'the key is kept with the update');

        $forged = $this->http(array(
            U\UPDATE_INFO_URL => array(200, $text),
            U\UPDATE_INFO_URL . '.sig' => array(200, U\signData($text, self::$private['stranger']))
        ));
        $this->assertRefused('SYS_CORE_UPDATE_SIGNATURE_INVALID', fn() => U\fetchUpdateInfo($config, $this->directory, $forged));

        $unsigned = $this->http(array(U\UPDATE_INFO_URL => array(200, $text)));
        $this->assertRefused('SYS_CORE_UPDATE_DOWNLOAD_FAILED', fn() => U\fetchUpdateInfo($config, $this->directory, $unsigned));

        unlink($this->directory . '/release-key.pem');
        $this->assertRefused('SYS_CORE_UPDATE_NOT_AVAILABLE', fn() => U\fetchUpdateInfo($config, $this->directory, $http), 'no key');
    }

    /**
     * @testdox The stable and the beta release are read from their lines of update.txt
     */
    public function testReleaseFromUpdateInfo(): void
    {
        $info = U\parseUpdateInfo("\xEF\xBB\xBF" . self::updateInfo());

        $this->assertSame(array(
            'channel' => 'stable',
            'version' => '5.1.0',
            'versionText' => '5.1.0',
            'packageUrl' => 'https://downloads.example.org/admidio-5.1.0.zip',
            'packageSha256' => str_repeat('a', 64),
            'packageSize' => 12345678,
            'minimumSourceVersion' => '5.0.0',
            'minimumPhpVersion' => '8.2'
        ), U\releaseFromUpdateInfo($info, 'stable'));

        $beta = U\releaseFromUpdateInfo($info, 'beta');
        $this->assertSame('5.2.0-beta.1', $beta['version']);
        $this->assertSame(str_repeat('b', 64), $beta['packageSha256']);
        $this->assertSame('8.3', $beta['minimumPhpVersion']);
    }

    /**
     * @testdox The update.txt of today, without package lines, offers no automatic update
     */
    public function testUpdateInfoWithoutPackage(): void
    {
        $info = U\parseUpdateInfo("Version=5.0.16\nBeta-Version=\nBeta-Release=\n");

        $this->assertRefused('SYS_CORE_UPDATE_NOT_AVAILABLE', fn() => U\releaseFromUpdateInfo($info, 'stable'));
        $this->assertRefused('SYS_CORE_UPDATE_NOT_AVAILABLE', fn() => U\releaseFromUpdateInfo($info, 'beta'));
    }

    /**
     * @testdox A release with a missing or invalid field is refused
     */
    public function testInvalidRelease(): void
    {
        foreach (array(
            'version' => array('Version' => '5.1'),
            'old version form' => array('Version' => '5.1.0 Beta 1'),
            'Package-SHA256' => array('Package-SHA256' => 'abc'),
            'Package-Size' => array('Package-Size' => '0'),
            'Minimum-Source-Version' => array('Minimum-Source-Version' => 'old'),
            'Minimum-PHP-Version' => array('Minimum-PHP-Version' => 'eight')
        ) as $field => $change) {
            $this->assertRefused('SYS_CORE_UPDATE_INFORMATION_INVALID',
                fn() => U\releaseFromUpdateInfo(U\parseUpdateInfo(self::updateInfo($change)), 'stable'), $field);
        }
    }

    /**
     * @testdox An installation that is too old is refused before anything is downloaded
     */
    public function testCompatibility(): void
    {
        $release = U\releaseFromUpdateInfo(U\parseUpdateInfo(self::updateInfo(array('Minimum-Source-Version' => '5.0.10', 'Minimum-PHP-Version' => '8.3'))), 'stable');

        U\checkCompatibility($release, '5.0.10', '8.3.0');
        $this->assertRefused('SYS_CORE_UPDATE_SOURCE_TOO_OLD', fn() => U\checkCompatibility($release, '5.0.9', '8.4.0'));
        $this->assertRefused('SYS_CORE_UPDATE_PHP_TOO_OLD', fn() => U\checkCompatibility($release, '5.0.14', '8.2.29'));
    }

    /**
     * A manifest of version 5.1.0.
     */
    private static function manifest(array $changes = array()): string
    {
        return json_encode(array_merge(array(
            'version' => '5.1.0',
            'managedDirectories' => array('src', 'system', 'themes/simple'),
            'managedRootFiles' => array('index.php', 'composer.json'),
            'preservePaths' => array('adm_my_files', 'install/core-update'),
            'requirements' => array('php' => '>=8.2', 'extensions' => array('pdo', 'zip')),
            'totalSize' => 1000,
            'files' => array('index.php' => str_repeat('c', 64), 'src/Foo.php' => str_repeat('d', 64))
        ), $changes));
    }

    /**
     * @testdox A manifest of the expected version is read with all its fields
     */
    public function testManifest(): void
    {
        $manifest = U\readManifest(self::manifest(), '5.1.0');

        $this->assertSame(array('index.php' => str_repeat('c', 64), 'src/Foo.php' => str_repeat('d', 64)), $manifest['files']);
        $this->assertSame(array('index.php', 'composer.json'), $manifest['managedRootFiles']);
        $this->assertSame('>=8.2', $manifest['requirements']['php']);

        $this->assertRefused('SYS_CORE_UPDATE_MANIFEST_INVALID', fn() => U\readManifest(self::manifest(), '5.1.1'), 'another version');
        $this->assertRefused('SYS_CORE_UPDATE_MANIFEST_INVALID', fn() => U\readManifest('{"version":"5.1.0"', '5.1.0'), 'no JSON');
    }

    /**
     * @testdox A manifest with an unsafe path or names that differ only in case is refused
     */
    public function testUnsafeManifest(): void
    {
        foreach (array(
            'parent directory' => array('files' => array('../index.php' => str_repeat('c', 64))),
            'absolute path' => array('files' => array('/etc/passwd' => str_repeat('c', 64))),
            'drive prefix' => array('files' => array('C:/index.php' => str_repeat('c', 64))),
            'backslash' => array('files' => array('src\\Foo.php' => str_repeat('c', 64))),
            'case collision' => array('files' => array('src/Foo.php' => str_repeat('c', 64), 'src/foo.php' => str_repeat('d', 64))),
            'invalid hash' => array('files' => array('index.php' => 'abc')),
            'no files' => array('files' => array()),
            'root file in a directory' => array('managedRootFiles' => array('src/index.php')),
            'unsafe managed directory' => array('managedDirectories' => array('../../')),
            'unsafe preserved path' => array('preservePaths' => array('/')),
            'no requirements' => array('requirements' => 'php 8.2'),
            'negative size' => array('totalSize' => -1)
        ) as $case => $change) {
            $this->assertRefused('SYS_CORE_UPDATE_MANIFEST_INVALID', fn() => U\readManifest(self::manifest($change), '5.1.0'), $case);
        }
    }

    // --------------------------------------------------------------------------------------------
    // ZIP packages
    // --------------------------------------------------------------------------------------------

    /**
     * Build an archive with the given entries.
     * @param array<string,string> $entries name => content; a name ending with "/" is a directory
     * @param array<string,int> $unixModes name => file mode to set in the external attributes
     */
    private function archive(array $entries, array $unixModes = array()): string
    {
        $file = $this->directory . '/' . uniqid() . '.zip';
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            str_ends_with($name, '/') ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $content);
        }
        foreach ($unixModes as $name => $mode) {
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $mode << 16);
        }
        $zip->close();

        return $file;
    }

    private function assertPackageInvalid(string $file, string $detail, string $case): void
    {
        try {
            U\openPackage($file, 1000000)['zip']->close();
            $this->fail('accepted: ' . $case);
        } catch (UpdaterException $exception) {
            $this->assertSame('SYS_CORE_UPDATE_PACKAGE_INVALID', $exception->getMessage(), $case);
            $this->assertStringContainsString($detail, $exception->getDetail(), $case);
        }
    }

    /**
     * @testdox A package with one top directory is read relative to it, and every byte is hashed
     */
    public function testValidPackage(): void
    {
        $package = U\openPackage($this->archive(array(
            'admidio-5.1.0/' => '',
            'admidio-5.1.0/index.php' => '<?php echo 1;',
            'admidio-5.1.0/src/' => '',
            'admidio-5.1.0/src/Foo.php' => str_repeat('a', 100000)
        )), 1000000);

        try {
            $this->assertSame(array('index.php', 'src/Foo.php'), array_values($package['entries']));
            $this->assertSame('<?php echo 1;', U\packageRead($package, U\packageFind($package, 'index.php'), 100));

            $target = $this->directory . '/Foo.php';
            $hash = U\packageExtract($package, U\packageFind($package, 'src/Foo.php'), $target, 100000);
            $this->assertSame(hash('sha256', str_repeat('a', 100000)), $hash);
            $this->assertSame(hash_file('sha256', $target), $hash);
            $this->assertNull(U\packageFind($package, 'missing.php'));
        } finally {
            $package['zip']->close();
        }
    }

    /**
     * @testdox A package without a top directory is read as it is
     */
    public function testPackageWithoutTopDirectory(): void
    {
        $package = U\openPackage($this->archive(array('index.php' => 'a', 'src/Foo.php' => 'b')), 1000);
        $this->assertSame(array('index.php', 'src/Foo.php'), array_values($package['entries']));
        $package['zip']->close();
    }

    /**
     * @testdox An entry that would leave the installation directory is refused
     */
    public function testUnsafeNames(): void
    {
        foreach (array(
            'parent directory' => 'admidio/../../evil.php',
            'absolute path' => '/etc/cron.d/evil',
            'drive prefix' => 'C:/Windows/evil.php',
            'backslash' => 'admidio\\..\\evil.php',
            'dot segment' => 'admidio/./evil.php'
        ) as $case => $name) {
            $this->assertPackageInvalid($this->archive(array('index.php' => 'a', $name => 'evil')), 'is not a safe path', $case);
        }
    }

    /**
     * @testdox A NUL byte cannot hide a path behind a harmless name
     *
     * libzip turns the NUL into a space, so the name is never cut short at it, and what follows is
     * checked like any other part of the name.
     */
    public function testNulByte(): void
    {
        // ZipArchive refuses to write such a name, so it is patched into a valid archive.
        $file = $this->archive(array('index.php' => 'a', 'index.phpX/../../evil.php' => 'b'));
        file_put_contents($file, str_replace('index.phpX/../../evil.php', "index.php\0/../../evil.php", (string)file_get_contents($file)));

        $this->assertPackageInvalid($file, 'is not a safe path', 'NUL byte');
    }

    /**
     * @testdox A symbolic link is refused
     */
    public function testSymlink(): void
    {
        $file = $this->archive(array('index.php' => 'a', 'config.php' => '../../adm_my_files/config.php'), array('config.php' => 0120777));

        $this->assertPackageInvalid($file, 'is a symbolic link', 'symlink');
    }

    /**
     * @testdox Two entries with the same name, also in another case, are refused
     */
    public function testCollisions(): void
    {
        $this->assertPackageInvalid($this->archive(array('index.php' => 'a', 'Index.PHP' => 'b')), 'exists twice', 'case collision');

        // ZipArchive replaces an entry of the same name, so the second one is patched in.
        $file = $this->archive(array('index.php' => 'a', 'indey.php' => 'b'));
        file_put_contents($file, str_replace('indey.php', 'index.php', (string)file_get_contents($file)));
        $this->assertPackageInvalid($file, 'exists twice', 'duplicate');
    }

    /**
     * @testdox A package larger than the limit is refused before anything is extracted
     */
    public function testDeclaredSizeLimit(): void
    {
        $this->assertPackageInvalid($this->archive(array('a.bin' => str_repeat('x', 600000), 'b.bin' => str_repeat('x', 600000))),
            'larger than', 'two entries over the limit together');
    }

    /**
     * @testdox An entry that lies about its size cannot write more than the limit
     */
    public function testExtractedBytesAreCounted(): void
    {
        $package = U\openPackage($this->archive(array('big.bin' => str_repeat('x', 300000))), 1000000);
        $target = $this->directory . '/big.bin';

        try {
            U\packageExtract($package, U\packageFind($package, 'big.bin'), $target, 299999);
            $this->fail('more bytes than allowed were written');
        } catch (UpdaterException $exception) {
            $this->assertStringContainsString('is larger than 299999 bytes', $exception->getDetail());
            $this->assertFileDoesNotExist($target, 'a partly written file is removed');
        } finally {
            $package['zip']->close();
        }
    }

    /**
     * @testdox A file that is no ZIP archive is refused
     */
    public function testNoArchive(): void
    {
        file_put_contents($this->directory . '/fake.zip', 'not a zip');

        $this->assertPackageInvalid($this->directory . '/fake.zip', 'no readable ZIP', 'no archive');
    }
}
