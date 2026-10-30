<?php
/**
 * The entry points of the updater (install/core-update/index.php, cli.php and entry.php): which
 * updater answers. Each run defines ADMIDIO_CORE_UPDATE_ROOT, so it runs in a process of its own,
 * with the real entry files copied into an installation whose updaters only say who they are.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;

final class EntryTest extends AdmidioTestCase
{
    private const UPDATE_ID = '0123456789abcdef0123456789abcdef';

    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/adm_updater_entry_' . uniqid();
        $this->createUpdater($this->root . '/install/core-update', 'INSTALLED');
        foreach (array('index.php', 'cli.php', 'entry.php') as $file) {
            copy(dirname(__DIR__, 3) . '/install/core-update/' . $file, $this->root . '/install/core-update/' . $file);
        }
    }

    protected function tearDown(): void
    {
        U\deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * An updater whose updater.php and updater-cli.php print its name and the installation.
     */
    private function createUpdater(string $directory, string $name): void
    {
        mkdir($directory, 0777, true);
        foreach (array('updater.php', 'updater-cli.php') as $file) {
            file_put_contents($directory . '/' . $file, '<?php echo ' . var_export($name . ' ' . $file . ' ', true) . ', ADMIDIO_CORE_UPDATE_ROOT;');
        }
    }

    /**
     * An active update in the given state, with the unpacked updater of the new release.
     * @param array<string,mixed> $state
     */
    private function createUpdate(array $state, bool $withUpdater = true): void
    {
        $workspace = $this->root . '/adm_my_files/core-update/' . self::UPDATE_ID;
        mkdir($workspace, 0777, true);
        file_put_contents($this->root . '/adm_my_files/core-update/active.json', json_encode(array('updateId' => self::UPDATE_ID)));
        file_put_contents($workspace . '/state.json', json_encode(array_merge(array('updateId' => self::UPDATE_ID), $state)));
        if ($withUpdater) {
            $this->createUpdater($workspace . '/extracted/install/core-update', 'NEW');
        }
    }

    private function runEntry(string $script): string
    {
        return (string)shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/install/core-update/' . $script) . ' 2>&1');
    }

    /**
     * @testdox Without an update, and until phase A has extracted the package, the installed updater answers
     */
    public function testInstalledUpdater(): void
    {
        $this->assertSame('INSTALLED updater-cli.php ' . $this->root, $this->runEntry('cli.php'));

        $this->createUpdate(array('state' => 'extracting'));
        $this->assertSame('INSTALLED updater.php ' . $this->root, $this->runEntry('index.php'), 'phase A runs in the installed updater');
        $this->assertSame('INSTALLED updater-cli.php ' . $this->root, $this->runEntry('cli.php'));
    }

    /**
     * @testdox After phase A the unpacked updater of the new release answers, for the installation
     */
    public function testUnpackedUpdater(): void
    {
        $this->createUpdate(array('state' => 'installing', 'runUnpackedUpdater' => true));

        $this->assertSame('NEW updater.php ' . $this->root, $this->runEntry('index.php'));
        $this->assertSame('NEW updater-cli.php ' . $this->root, $this->runEntry('cli.php'));
    }

    /**
     * @testdox Without the unpacked updater the installed one answers, and reports what is wrong
     */
    public function testUnpackedUpdaterMissing(): void
    {
        $this->createUpdate(array('state' => 'installing', 'runUnpackedUpdater' => true), false);

        $this->assertSame('INSTALLED updater.php ' . $this->root, $this->runEntry('index.php'));
    }

    /**
     * @testdox updater.php and updater-cli.php refuse to run without the entry point
     */
    public function testNoDirectCall(): void
    {
        $updater = dirname(__DIR__, 3) . '/install/core-update/';
        foreach (array('updater.php', 'updater-cli.php') as $file) {
            $this->assertSame('', (string)shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($updater . $file) . ' 2>&1'), $file);
        }
    }

    /**
     * @testdox Admidio uses the functions of the unpacked updater, when that one runs, instead of loading its own
     *
     * The database update of the command line loads Admidio in the process of the updater, and
     * Backups and CoreUpdateHandover need the functions of the updater as well.
     */
    public function testAdmidioWithTheUnpackedUpdater(): void
    {
        $repository = dirname(__DIR__, 3);
        $unpackedUpdater = $this->root . '/adm_my_files/core-update/' . self::UPDATE_ID . '/extracted/install/core-update';
        U\copyPath($repository . '/install/core-update/lib', $unpackedUpdater . '/lib');
        $script = '<?php
            require ' . var_export($unpackedUpdater . '/lib/bootstrap.php', true) . ';
            const ADMIDIO_PATH = ' . var_export($repository, true) . ';
            require ADMIDIO_PATH . "/src/Infrastructure/Backups.php";
            require ADMIDIO_PATH . "/src/InstallationUpdate/Service/CoreUpdateHandover.php";
            echo (new ReflectionFunction("AdmidioCoreUpdater\\\\readState"))->getFileName();';
        file_put_contents($this->root . '/script.php', $script);

        $this->assertSame($unpackedUpdater . '/lib/common.php',
            (string)shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/script.php') . ' 2>&1'));
    }
}
