<?php
/**
 * Command Line Process Tests
 *
 * Tests the command line utility as the operating system runs it: ./admidio started as its own
 * process, with its own bootstrap, its own database connection and its own exit code. The other
 * CLI tests exercise the classes behind it, these ones exercise the entry point.
 *
 * The process is pointed at the test database with --config. It only reads, because what a test
 * writes lives in a transaction that another connection cannot see, and because the utility uses
 * the adm_my_files of the checkout for its files.
 */

namespace Admidio\Tests\Cli;

use Admidio\Tests\Support\CliSubprocess;
use Admidio\Tests\Support\DatabaseTestCase;

class CliProcessTest extends DatabaseTestCase
{
    use CliSubprocess;

    /**
     * Test that the program starts
     *
     * @testdox The utility starts as a process and describes itself
     */
    public function testTheUtilityStartsAsAProcessAndDescribesItself(): void
    {
        $process = $this->runCli(array('help'));

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Admidio command-line administration utility', $process->getOutput());
        $this->assertStringContainsString('Global options', $process->getOutput());
        $this->assertStringContainsString('--config=FILE', $process->getOutput());
    }

    /**
     * Test that the configuration option decides which installation is addressed
     *
     * @testdox The configuration file decides which installation the process works on
     */
    public function testTheConfigurationFileDecidesWhichInstallationTheProcessWorksOn(): void
    {
        // the administrator of the installation, read through the connection of the test
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $administratorId = (int) $this->getDatabase()->queryPrepared($sql, array('admin'))->fetchColumn();

        $this->assertGreaterThan(0, $administratorId);

        $process = $this->runCli(array('--as=admin', 'user:show', 'admin', '--format=json'));

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $user = $this->cliJson($process);

        // the same record, so the process really worked on the database of this test run
        $this->assertSame($administratorId, (int) $user['id']);
        $this->assertSame('admin', $user['login']);
        $this->assertSame('admin@test.local', $user['profile']['EMAIL']);
    }

    /**
     * Test that a command is refused without an acting user
     *
     * @testdox A command that acts for somebody refuses to run without an acting user
     */
    public function testACommandThatActsForSomebodyRefusesToRunWithoutAnActingUser(): void
    {
        $process = $this->runCli(array('user:show', 'admin', '--format=json'));

        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString('--as=', $process->getErrorOutput());
    }

    /**
     * Test that an unknown command is refused
     *
     * @testdox An unknown command is refused with a hint at the command list
     */
    public function testAnUnknownCommandIsRefusedWithAHintAtTheCommandList(): void
    {
        $process = $this->runCli(array('not:acommand'));

        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString('Unknown command', $process->getErrorOutput());
        $this->assertStringContainsString('admidio list', $process->getErrorOutput());
    }

    /**
     * Test that a configuration file that is not there is reported
     *
     * @testdox A missing configuration file is reported with the path that was looked for
     */
    public function testAMissingConfigurationFileIsReportedWithThePathThatWasLookedFor(): void
    {
        $missing = ADMIDIO_PATH . FOLDER_DATA . '/no-such-config.php';

        $this->assertFileDoesNotExist($missing);

        $process = $this->runCli(array('user:show', 'admin'), $missing);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('was not found', $process->getErrorOutput());
        $this->assertStringContainsString('no-such-config.php', $process->getErrorOutput());
    }
}
