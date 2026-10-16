<?php
/**
 * The checks before an update is downloaded (install/core-update/lib/phase-a.php), one by one.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;

final class PreflightTest extends AdmidioTestCase
{
    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/adm_preflight_' . uniqid();
        mkdir($this->root . '/adm_my_files', 0777, true);
        mkdir($this->root . '/src', 0777, true);
        file_put_contents($this->root . '/index.php', 'index');
    }

    protected function tearDown(): void
    {
        U\deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * A release as releaseFromUpdateInfo() reads it.
     * @return array<string,mixed>
     */
    private static function release(string $version = '5.1.0', string $minimumPhp = '8.2', int $packageSize = 1000): array
    {
        return array(
            'channel' => 'stable', 'version' => $version, 'versionText' => $version, 'packageUrl' => 'https://example.org/p.zip',
            'packageSha256' => str_repeat('a', 64), 'packageSize' => $packageSize, 'minimumSourceVersion' => '5.0.0',
            'minimumPhpVersion' => $minimumPhp
        );
    }

    /**
     * The result of one check.
     * @param array<int,array{check: string, status: string, text: string}> $results
     * @return array{check: string, status: string, text: string, params: array<int,string>}
     */
    private static function result(array $results, string $check): array
    {
        foreach ($results as $result) {
            if ($result['check'] === $check) {
                return $result;
            }
        }

        self::fail('no result for ' . $check);
    }

    /**
     * @testdox A ready installation passes every check
     */
    public function testReadyInstallation(): void
    {
        $results = U\preflightChecks($this->root, self::release(), '5.0.14', '5.0.14', 'mysql', 10 ** 10);

        $this->assertFalse(U\checksHaveErrors($results), json_encode($results));
        $this->assertSame(array('P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P9'), array_column($results, 'check'),
            'P8 only appears with open_basedir, P7 is the state store, P10 the browser');
        $this->assertSame('SYS_CORE_UPDATE_CHECK_VERSION', self::result($results, 'P3')['text']);
        $this->assertSame(array(), glob($this->root . '/.admidio-update-probe-*'), 'the probes are removed');
    }

    /**
     * @testdox The same version is offered as a reinstall
     */
    public function testReinstall(): void
    {
        $results = U\preflightChecks($this->root, self::release('5.0.14'), '5.0.14', '5.0.14', 'mysql', 10 ** 10);

        $this->assertSame('SYS_CORE_UPDATE_CHECK_REINSTALL', self::result($results, 'P3')['text']);
    }

    /**
     * @testdox A PHP version below the release's minimum is an error
     */
    public function testPhpTooOld(): void
    {
        $results = U\preflightChecks($this->root, self::release('5.1.0', '99.0'), '5.0.14', '5.0.14', 'mysql', 10 ** 10);

        $this->assertSame('error', self::result($results, 'P1')['status']);
    }

    /**
     * @testdox An older version and a database ahead of it are errors
     */
    public function testVersionRule(): void
    {
        $results = U\preflightChecks($this->root, self::release('5.0.13'), '5.0.14', '5.0.14', 'mysql', 10 ** 10);
        $this->assertSame('SYS_CORE_UPDATE_VERSION_OLDER', self::result($results, 'P3')['text']);

        $results = U\preflightChecks($this->root, self::release('5.1.0'), '5.0.14', '6.0.0', 'mysql', 10 ** 10);
        $this->assertSame('SYS_CORE_UPDATE_DATABASE_NEWER', self::result($results, 'P3')['text']);
    }

    /**
     * @testdox A database update that never finished must be completed first
     */
    public function testPendingDatabaseUpdate(): void
    {
        $results = U\preflightChecks($this->root, self::release(), '5.0.14', '5.0.12', 'mysql', 10 ** 10);
        $this->assertSame('SYS_CORE_UPDATE_CHECK_DATABASE_PENDING', self::result($results, 'P4')['text']);
        $this->assertSame(array('5.0.12', '5.0.14'), self::result($results, 'P4')['params']);

        $results = U\preflightChecks($this->root, self::release(), '5.0.14', null, 'mysql', 10 ** 10);
        $this->assertSame('SYS_CORE_UPDATE_CHECK_DATABASE_UNKNOWN', self::result($results, 'P4')['text']);
    }

    /**
     * @testdox Too little free space is an error, and an unknown amount a warning
     */
    public function testSpace(): void
    {
        $release = self::release('5.1.0', '8.2', 100 * 1048576);

        $results = U\preflightChecks($this->root, $release, '5.0.14', '5.0.14', 'mysql', 200 * 1048576);
        $this->assertSame('SYS_CORE_UPDATE_CHECK_SPACE_MISSING', self::result($results, 'P6')['text']);
        $this->assertSame(array('840', '200'), self::result($results, 'P6')['params'], '(100 + 2 * 300) * 1.2 MB needed');

        $results = U\preflightChecks($this->root, $release, '5.0.14', '5.0.14', 'mysql', -1);
        $this->assertSame('warning', self::result($results, 'P6')['status']);
        $this->assertFalse(U\checksHaveErrors(array(self::result($results, 'P6'))));
    }

    /**
     * @testdox A directory that cannot hold a new file fails the write probe
     */
    public function testWriteProbe(): void
    {
        $this->assertTrue(U\canCreateAndRename($this->root . '/src'));
        $this->assertFalse(U\canCreateAndRename($this->root . '/index.php/inside-a-file'));
    }

    /**
     * @testdox The manifest makes the requirements exact: its PHP version, its extensions and its size
     */
    public function testManifestChecks(): void
    {
        $manifest = array('version' => '5.1.0', 'requirements' => array('php' => '>=8.2', 'extensions' => array('json')), 'totalSize' => 1048576);
        $this->assertFalse(U\checksHaveErrors(U\manifestChecks($manifest, 10 ** 10)));

        $results = U\manifestChecks(array_merge($manifest, array('requirements' => array('php' => '>=99.0', 'extensions' => array('json', 'no_such_extension')))), 10 ** 10);
        $this->assertSame('SYS_CORE_UPDATE_PHP_TOO_OLD', self::result($results, 'P1')['text']);
        $this->assertSame(array('no_such_extension'), self::result($results, 'P2')['params']);

        $results = U\manifestChecks($manifest, 1048576);
        $this->assertSame('SYS_CORE_UPDATE_CHECK_SPACE_MISSING', self::result($results, 'P6')['text'], 'two copies and a margin');
    }
}
