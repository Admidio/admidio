<?php
/**
 * The release builder (build-release.php) against a small git repository that the test makes:
 * the package, its manifest and texts, the bootstrap ZIP and the signed update.txt, and the
 * releases it refuses to build.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;
use AdmidioReleaseBuilder as B;
use RuntimeException;
use ZipArchive;

final class ReleaseBuilderTest extends AdmidioTestCase
{
    private static \OpenSSLAsymmetricKey $key;
    private static string $publicKey = '';

    private string $repository = '';
    private string $out = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/build-release.php';
        self::$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
        self::$publicKey = openssl_pkey_get_details(self::$key)['key'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = sys_get_temp_dir() . '/adm_release_repo_' . uniqid();
        $this->out = sys_get_temp_dir() . '/adm_release_out_' . uniqid();
        $this->commit(array(
            '.gitattributes' => "/.* export-ignore\n",
            '.github/workflows/ci.yml' => 'CI',
            'system/bootstrap/constants.php' => $this->constants(5, 1, 0, 0),
            'composer.json' => json_encode(array(
                'require' => array('php' => '^8.2', 'ext-zip' => '*'),
                'archive' => array('exclude' => array('tests/*', 'plugins/*', '!plugins/birthday', 'build-release.php'))
            )),
            'languages/en.xml' => '<?xml version="1.0" encoding="UTF-8"?><resources>'
                . '<string name="SYS_CORE_UPDATE_TITLE">Admidio update</string><string name="SYS_SAVE">Save</string></resources>',
            'languages/de.xml' => '<?xml version="1.0" encoding="UTF-8"?><resources>'
                . '<string name="SYS_CORE_UPDATE_TITLE">Admidio-Update</string></resources>',
            'languages/countries-de.xml' => '<?xml version="1.0" encoding="UTF-8"?><resources></resources>',
            'install/core-update/index.php' => 'UPDATER',
            'install/core-update/lib/bootstrap.php' => 'BOOTSTRAP',
            'install/core-update/release-key.pem' => self::$publicKey,
            'install/db_scripts/migration-paths.json' => '["adm_plugins"]',
            'index.php' => 'INDEX',
            'src/Club.php' => 'CLUB',
            'adm_my_files/config_example.php' => 'EXAMPLE',
            'plugins/birthday/index.php' => 'OFFICIAL PLUGIN',
            'plugins/my-own/index.php' => 'NOT A PART OF THE RELEASE',
            'tests/ClubTest.php' => 'A TEST',
            'build-release.php' => 'THE BUILDER'
        ), 'v5.1.0');
        file_put_contents($this->repository . '/update.txt', "Version=5.0.17\nBeta-Version=\nBeta-Release=\nSome-Other=kept\n");
    }

    protected function tearDown(): void
    {
        U\deleteDirectory($this->repository);
        U\deleteDirectory($this->out);

        parent::tearDown();
    }

    private function constants(int $main, int $minor, int $patch, int $beta): string
    {
        return "<?php\nconst ADMIDIO_VERSION_MAIN = " . $main . ";\nconst ADMIDIO_VERSION_MINOR = " . $minor . ";\nconst ADMIDIO_VERSION_PATCH = "
            . $patch . ";\nconst ADMIDIO_VERSION_BETA = " . $beta . ";\n";
    }

    /**
     * Commit files to the test repository, which is created with the first commit, and tag it.
     * @param array<string,string> $files
     */
    private function commit(array $files, string $tag = ''): void
    {
        foreach ($files as $path => $content) {
            @mkdir(dirname($this->repository . '/' . $path), 0777, true);
            file_put_contents($this->repository . '/' . $path, $content);
        }
        if (!is_dir($this->repository . '/.git')) {
            B\git($this->repository, array('init', '-q'));
        }
        B\git($this->repository, array('add', '-A'));
        B\git($this->repository, array('-c', 'user.name=Test', '-c', 'user.email=test@example.org', '-c', 'commit.gpgsign=false',
            'commit', '-q', '-m', 'Release'));
        if ($tag !== '') {
            B\git($this->repository, array('-c', 'tag.gpgsign=false', 'tag', $tag));
        }
    }

    /**
     * @param array<string,string|bool> $options
     * @return array<string,mixed>
     */
    private function build(array $options = array()): array
    {
        return B\build($this->repository, self::$key, $options + array('ref' => 'v5.1.0', 'out' => $this->out, 'no-composer' => true,
            'update-txt' => $this->repository . '/update.txt'));
    }

    /**
     * @return array<string,string> path => content of a ZIP
     */
    private function zipEntries(string $file): array
    {
        $zip = new ZipArchive();
        $zip->open($file);
        $entries = array();
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[$zip->getNameIndex($index)] = (string)$zip->getFromIndex($index);
        }
        $zip->close();

        return $entries;
    }

    /**
     * @testdox A release is the clean export of its tag with the files that archive.exclude leaves in, the texts and the manifest
     */
    public function testPackage(): void
    {
        $result = $this->build();

        $this->assertSame('5.1.0', $result['version']);
        $this->assertSame($this->out . '/admidio-5.1.0.zip', $result['package']);
        $entries = $this->zipEntries($result['package']);
        $this->assertSame(array(
            'admidio-5.1.0/adm_my_files/config_example.php',
            'admidio-5.1.0/composer.json',
            'admidio-5.1.0/index.php',
            'admidio-5.1.0/install/core-update/index.php',
            'admidio-5.1.0/install/core-update/lib/bootstrap.php',
            'admidio-5.1.0/install/core-update/release-key.pem',
            'admidio-5.1.0/install/core-update/texts/de.json',
            'admidio-5.1.0/install/core-update/texts/en.json',
            'admidio-5.1.0/install/db_scripts/migration-paths.json',
            'admidio-5.1.0/install/update-manifest.json',
            'admidio-5.1.0/languages/countries-de.xml',
            'admidio-5.1.0/languages/de.xml',
            'admidio-5.1.0/languages/en.xml',
            'admidio-5.1.0/plugins/birthday/index.php',
            'admidio-5.1.0/src/Club.php',
            'admidio-5.1.0/system/bootstrap/constants.php'
        ), array_keys($entries), 'no tests, no foreign plugin, not the builder, not what export-ignore leaves out');
        $this->assertSame(array('SYS_CORE_UPDATE_TITLE' => 'Admidio update'), json_decode($entries['admidio-5.1.0/install/core-update/texts/en.json'], true));

        $manifest = U\readManifest($entries['admidio-5.1.0/install/update-manifest.json'], '5.1.0');
        $this->assertSame(array('adm_my_files', 'install/core-update', 'custom_css.css', 'custom_favicon.png', 'custom_logo.png'), $manifest['preservePaths']);
        $this->assertSame(array('install', 'languages', 'plugins/birthday', 'src', 'system'), $manifest['managedDirectories']);
        $this->assertSame(array('composer.json', 'index.php'), $manifest['managedRootFiles']);
        $this->assertSame(array('adm_plugins'), $manifest['migrationPaths']);
        $this->assertSame(array('php' => '>=8.2', 'extensions' => array('zip')), $manifest['requirements']);
        $this->assertSame(hash('sha256', 'CLUB'), $manifest['files']['src/Club.php']);
        $this->assertCount(15, $manifest['files'], 'every file except the manifest itself');
    }

    /**
     * @testdox update.txt keeps its other lines, gets those of the release and is signed with the release key
     */
    public function testUpdateInfo(): void
    {
        $result = $this->build();

        $text = (string)file_get_contents($result['updateTxt']);
        $this->assertTrue(U\verifySignature($text, (string)file_get_contents($result['updateTxt'] . '.sig'), self::$publicKey));
        $this->assertSame("Version=5.1.0\nBeta-Version=\nBeta-Release=\nSome-Other=kept\n"
            . 'Package-URL=https://github.com/Admidio/admidio/releases/download/v5.1.0/admidio-5.1.0.zip' . "\n"
            . 'Package-SHA256=' . $result['packageSha256'] . "\nPackage-Size=" . $result['packageSize'] . "\n"
            . "Minimum-Source-Version=5.0.0\nMinimum-PHP-Version=8.2\n", $text);
        $release = U\releaseFromUpdateInfo(U\parseUpdateInfo($text), 'stable');
        $this->assertSame('5.1.0', $release['version']);
    }

    /**
     * @testdox The bootstrap ZIP holds the updater alone, and the same commit gives the same bytes
     */
    public function testBootstrapAndReproducibility(): void
    {
        $result = $this->build();

        $entries = array_keys($this->zipEntries($result['bootstrap']));
        $this->assertSame(array('install/core-update/index.php', 'install/core-update/lib/bootstrap.php', 'install/core-update/release-key.pem',
            'install/core-update/texts/de.json', 'install/core-update/texts/en.json'), $entries);

        $again = $this->build(array('out' => $this->out . '/again'));
        $this->assertSame($result['packageSha256'], $again['packageSha256']);
        $this->assertSame($result['bootstrapSha256'], $again['bootstrapSha256']);
    }

    /**
     * @testdox A beta is announced in the Beta lines, and its tag names the beta
     */
    public function testBeta(): void
    {
        $this->commit(array('system/bootstrap/constants.php' => $this->constants(5, 2, 0, 1)), 'v5.2-Beta.1');

        $result = $this->build(array('ref' => 'v5.2-Beta.1', 'package-url' => 'https://downloads.example.org/admidio/'));

        $this->assertSame('5.2.0-beta.1', $result['version']);
        $info = U\parseUpdateInfo((string)file_get_contents($result['updateTxt']));
        $this->assertSame('5.0.17', $info['Version'], 'the stable release stays');
        $this->assertSame('5.2.0', $info['Beta-Version']);
        $this->assertSame('1', $info['Beta-Release']);
        $this->assertSame('https://downloads.example.org/admidio/admidio-5.2.0-beta.1.zip', $info['Beta-Package-URL']);
        $this->assertSame($result['packageSha256'], $info['Beta-Package-SHA256']);
        $this->assertSame('5.2.0-beta.1', U\releaseFromUpdateInfo($info, 'beta')['version']);
    }

    /**
     * @return array<string,array{0: callable(self): array<string,string|bool>, 1: string}>
     */
    public static function refusedReleases(): array
    {
        return array(
            'tag of another version' => array(static function (self $test): array {
                B\git($test->repository, array('-c', 'tag.gpgsign=false', 'tag', 'v5.1.1'));

                return array('ref' => 'v5.1.1');
            }, 'does not match the version'),
            'uncommitted changes' => array(static function (self $test): array {
                file_put_contents($test->repository . '/index.php', 'CHANGED');

                return array();
            }, 'uncommitted changes'),
            'release key of another key' => array(static function (self $test): array {
                $test->commit(array('install/core-update/release-key.pem' => openssl_pkey_get_details(
                    openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA)))['key']));

                return array('ref' => 'HEAD', 'package-url' => 'https://example.org/');
            }, 'is not the public part of the signing key'),
            'commit without a package address' => array(static function (self $test): array {
                return array('ref' => 'HEAD');
            }, 'needs --package-url')
        );
    }

    /**
     * @testdox A release that would not be what it claims is refused: $_dataName
     * @dataProvider refusedReleases
     * @param callable(self): array<string,string|bool> $prepare
     */
    public function testRefused(callable $prepare, string $message): void
    {
        $options = $prepare($this);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $this->build($options);
    }

    /**
     * @testdox Changes of the working tree are allowed with --allow-dirty, and are not part of the release
     */
    public function testAllowDirty(): void
    {
        file_put_contents($this->repository . '/index.php', 'CHANGED');

        $entries = $this->zipEntries($this->build(array('allow-dirty' => true))['package']);

        $this->assertSame('INDEX', $entries['admidio-5.1.0/index.php']);
    }

    /**
     * @testdox The release that ships a new key is signed with the old one
     */
    public function testKeyRotation(): void
    {
        $old = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));

        $result = B\build($this->repository, $old, array('ref' => 'v5.1.0', 'out' => $this->out, 'no-composer' => true, 'key-rotation' => true));

        $text = (string)file_get_contents($result['updateTxt']);
        $this->assertTrue(U\verifySignature($text, (string)file_get_contents($result['updateTxt'] . '.sig'), openssl_pkey_get_details($old)['key']));
    }

    /**
     * @testdox Tags name versions as Admidio writes them
     */
    public function testTagVersion(): void
    {
        $this->assertSame('5.0.17', B\tagVersion('v5.0.17'));
        $this->assertSame('5.0.0-beta.1', B\tagVersion('v5.0-Beta.1'));
        $this->assertSame('6.0.0-beta.2', B\tagVersion('v6.0.0-beta.2'));
        $this->assertNull(B\tagVersion('release-candidate'));
    }
}
