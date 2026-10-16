<?php
/**
 * update:apply and update:rollback run install/core-update/cli.php in a process of their own,
 * because an update replaces src/ and vendor/, which the Admidio command line has loaded. These
 * tests check the command line that the commands build for it.
 */

namespace Admidio\Tests\Cli;

use Admidio\Infrastructure\Cli\CliTaskRegistry;
use Admidio\Infrastructure\Cli\CoreTasks;
use Admidio\Tests\Support\AdmidioTestCase;
use InvalidArgumentException;

class CoreUpdaterCommandTest extends AdmidioTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__) . '/constants.php';
    }

    /**
     * @testdox The updater runs with the same PHP, the installation's own updater script and the host of the command line
     */
    public function testCommandLineOfTheUpdater(): void
    {
        $command = CoreTasks::coreUpdaterCommand(array('apply', '--channel=beta', '--yes'), array('host' => 'club.example.org'));

        $this->assertSame(array(PHP_BINARY, ADMIDIO_PATH . '/install/core-update/cli.php', 'apply', '--channel=beta', '--yes',
            '--format=text', '--host=club.example.org'), $command);
    }

    /**
     * @testdox JSON is passed on; the updater knows no other machine format
     */
    public function testFormat(): void
    {
        $this->assertContains('--format=json', CoreTasks::coreUpdaterCommand(array('rollback'), array('format' => 'json')));
        $this->assertContains('--format=text', CoreTasks::coreUpdaterCommand(array('rollback'), array('format' => 'table')));
    }

    /**
     * @testdox Another configuration file is refused, because the updater works on the installation's own one
     */
    public function testOtherConfigurationIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CoreTasks::coreUpdaterCommand(array('apply'), array('config' => '/srv/other/config.php'));
    }

    /**
     * @testdox update:apply and update:rollback need an administrator of the preferences and support a dry run only where it plans
     */
    public function testRegistration(): void
    {
        CoreTasks::register();

        $apply = CliTaskRegistry::get('update:apply');
        $rollback = CliTaskRegistry::get('update:rollback');
        $this->assertNotNull($apply);
        $this->assertNotNull($rollback);
        foreach (array($apply, $rollback) as $task) {
            $this->assertSame('PREFERENCES', $task['component']);
            $this->assertTrue($task['actorRequired']);
            $this->assertSame(CliTaskRegistry::ACCESS_ADMINISTRABLE, $task['componentAccess']);
        }
        $this->assertTrue($apply['supportsDryRun']);
        $this->assertFalse($rollback['supportsDryRun']);
    }
}
