<?php
/**
 * Reading update packages that may be hostile. Every archive is built by the test.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater\StateStore;
use AdmidioCoreUpdater\UpdaterException;
use AdmidioCoreUpdater\ZipReader;
use ZipArchive;

final class ZipReaderTest extends AdmidioTestCase
{
    private string $directory = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/adm_zip_' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        StateStore::deleteDirectory($this->directory);

        parent::tearDown();
    }

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

    private function assertInvalid(string $file, string $detail, string $case): void
    {
        try {
            ZipReader::open($file, 1000000)->close();
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
        $zip = ZipReader::open($this->archive(array(
            'admidio-5.1.0/' => '',
            'admidio-5.1.0/index.php' => '<?php echo 1;',
            'admidio-5.1.0/src/' => '',
            'admidio-5.1.0/src/Foo.php' => str_repeat('a', 100000)
        )), 1000000);

        try {
            $this->assertSame(array('index.php', 'src/Foo.php'), array_values($zip->getEntries()));
            $this->assertSame('<?php echo 1;', $zip->read($zip->find('index.php'), 100));

            $target = $this->directory . '/Foo.php';
            $hash = $zip->extract($zip->find('src/Foo.php'), $target, 100000);
            $this->assertSame(hash('sha256', str_repeat('a', 100000)), $hash);
            $this->assertSame(hash_file('sha256', $target), $hash);
            $this->assertNull($zip->find('missing.php'));
        } finally {
            $zip->close();
        }
    }

    /**
     * @testdox A package without a top directory is read as it is
     */
    public function testPackageWithoutTopDirectory(): void
    {
        $zip = ZipReader::open($this->archive(array('index.php' => 'a', 'src/Foo.php' => 'b')), 1000);
        $this->assertSame(array('index.php', 'src/Foo.php'), array_values($zip->getEntries()));
        $zip->close();
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
            $this->assertInvalid($this->archive(array('index.php' => 'a', $name => 'evil')), 'is not a safe path', $case);
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

        $this->assertInvalid($file, 'is not a safe path', 'NUL byte');
    }

    /**
     * @testdox A symbolic link is refused
     */
    public function testSymlink(): void
    {
        $file = $this->archive(array('index.php' => 'a', 'config.php' => '../../adm_my_files/config.php'), array('config.php' => 0120777));

        $this->assertInvalid($file, 'is a symbolic link', 'symlink');
    }

    /**
     * @testdox Two entries with the same name, also in another case, are refused
     */
    public function testCollisions(): void
    {
        $this->assertInvalid($this->archive(array('index.php' => 'a', 'Index.PHP' => 'b')), 'exists twice', 'case collision');

        // ZipArchive replaces an entry of the same name, so the second one is patched in.
        $file = $this->archive(array('index.php' => 'a', 'indey.php' => 'b'));
        file_put_contents($file, str_replace('indey.php', 'index.php', (string)file_get_contents($file)));
        $this->assertInvalid($file, 'exists twice', 'duplicate');
    }

    /**
     * @testdox A package larger than the limit is refused before anything is extracted
     */
    public function testDeclaredSizeLimit(): void
    {
        $this->assertInvalid($this->archive(array('a.bin' => str_repeat('x', 600000), 'b.bin' => str_repeat('x', 600000))),
            'larger than', 'two entries over the limit together');
    }

    /**
     * @testdox An entry that lies about its size cannot write more than the limit
     */
    public function testExtractedBytesAreCounted(): void
    {
        $zip = ZipReader::open($this->archive(array('big.bin' => str_repeat('x', 300000))), 1000000);
        $target = $this->directory . '/big.bin';

        try {
            $zip->extract($zip->find('big.bin'), $target, 299999);
            $this->fail('more bytes than allowed were written');
        } catch (UpdaterException $exception) {
            $this->assertStringContainsString('is larger than 299999 bytes', $exception->getDetail());
            $this->assertFileDoesNotExist($target, 'a partly written file is removed');
        } finally {
            $zip->close();
        }
    }

    /**
     * @testdox A file that is no ZIP archive is refused
     */
    public function testNoArchive(): void
    {
        file_put_contents($this->directory . '/fake.zip', 'not a zip');

        $this->assertInvalid($this->directory . '/fake.zip', 'no readable ZIP', 'no archive');
    }
}
