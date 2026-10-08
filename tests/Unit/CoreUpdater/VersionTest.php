<?php
/**
 * The order of versions and the rule which version may be installed.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater\StateStore;
use AdmidioCoreUpdater\UpdaterException;
use AdmidioCoreUpdater\Version;

final class VersionTest extends AdmidioTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    /**
     * @testdox A beta comes before its release, and betas are ordered by number
     */
    public function testOrder(): void
    {
        $ordered = array('5.0.14', '6.0.0-beta.1', '6.0.0-beta.2', '6.0.0-beta.10', '6.0.0', '6.0.1', '6.1.0', '10.0.0');

        for ($i = 1, $count = count($ordered); $i < $count; $i++) {
            $this->assertSame(-1, Version::compare($ordered[$i - 1], $ordered[$i]), $ordered[$i - 1] . ' < ' . $ordered[$i]);
        }
        $this->assertSame(0, Version::compare('6.0.0-beta.1', '6.0.0-beta.1'));
    }

    /**
     * @testdox Versions are written X.Y.Z or X.Y.Z-beta.N
     */
    public function testCanonical(): void
    {
        $this->assertSame('6.0.0-beta.1', Version::fromParts(6, 0, 0, 1));
        $this->assertSame('5.0.14', Version::fromParts(5, 0, 14, 0));

        foreach (array('5.0.14', '6.0.0-beta.1', '10.20.30') as $version) {
            $this->assertTrue(Version::isCanonical($version), $version);
        }
        foreach (array('5.0', '6.0.0 Beta 1', '6.0.0-beta.0', '6.0.0-rc.1', 'v5.0.14', '') as $version) {
            $this->assertFalse(Version::isCanonical($version), $version);
        }
    }

    /**
     * @testdox A newer version is an update, the same version a reinstall
     */
    public function testRule(): void
    {
        $this->assertSame(Version::UPDATE, Version::check('5.1.0', '5.0.14', '5.0.14'));
        $this->assertSame(Version::UPDATE, Version::check('6.0.0', '6.0.0-beta.2', null), 'from a beta to its release');
        $this->assertSame(Version::REINSTALL, Version::check('5.0.14', '5.0.14', '5.0.14'));
    }

    /**
     * @testdox An older version is refused, also when only the database is newer
     */
    public function testOlderIsRefused(): void
    {
        foreach (array(
            'older than the files' => array('5.0.13', '5.0.14', '5.0.14', 'SYS_CORE_UPDATE_VERSION_OLDER'),
            'stable after a newer beta' => array('5.1.0', '6.0.0-beta.1', null, 'SYS_CORE_UPDATE_VERSION_OLDER'),
            'database ahead of the files' => array('5.0.14', '5.0.14', '5.1.0', 'SYS_CORE_UPDATE_DATABASE_NEWER')
        ) as $case => [$target, $installed, $database, $key]) {
            try {
                Version::check($target, $installed, $database);
                $this->fail('accepted: ' . $case);
            } catch (UpdaterException $exception) {
                $this->assertSame($key, $exception->getMessage(), $case);
            }
        }
    }

    /**
     * @testdox The installed version comes from the manifest of the release, or else from the constants
     */
    public function testInstalledVersion(): void
    {
        $root = sys_get_temp_dir() . '/adm_version_' . uniqid();
        mkdir($root . '/system/bootstrap', 0777, true);
        mkdir($root . '/install', 0777, true);

        try {
            file_put_contents($root . '/system/bootstrap/constants.php', "<?php\nconst ADMIDIO_VERSION_MAIN = 6;\n"
                . "const ADMIDIO_VERSION_MINOR = 0;\nconst ADMIDIO_VERSION_PATCH = 0;\nconst ADMIDIO_VERSION_BETA = 2;\n"
                . "exit('never included');\n");
            $this->assertSame('6.0.0-beta.2', Version::ofFiles($root));

            file_put_contents($root . '/install/update-manifest.json', json_encode(array('version' => '6.0.1')));
            $this->assertSame('6.0.1', Version::ofFiles($root), 'the manifest of the installed release wins');

            // The real constants file of this checkout has the form the pattern expects.
            $this->assertTrue(Version::isCanonical(Version::ofFiles(dirname(__DIR__, 3))));
        } finally {
            StateStore::deleteDirectory($root);
        }

        $this->expectException(UpdaterException::class);
        Version::ofFiles($root);
    }
}
