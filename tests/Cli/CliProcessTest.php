<?php
/**
 * Command Line Process Tests
 *
 * Tests the command line utility as the operating system runs it: ./admidio started as its own
 * process, with its own bootstrap, its own database connection and its own exit code. The other
 * CLI tests exercise the classes behind it, these ones exercise the entry point.
 *
 * The process is pointed at the test database with --config. Database-only workflows may write
 * through the subprocess when they create and clean up their own data; the assertions then prove
 * that a commit is visible to a second, independent Admidio process. File-writing commands are kept
 * out of this class because the utility uses the adm_my_files of the checkout for those files.
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
     * Test a complete mutating workflow across independent CLI processes
     *
     * @testdox A user created by one real CLI process is persisted, can be read by another and can be deleted
     */
    public function testUserLifecyclePersistsAcrossIndependentProcesses(): void
    {
        $login = 'cli-e2e-' . bin2hex(random_bytes(5));
        $email = $login . '@example.local';
        $created = false;

        try {
            $create = $this->runCli(array(
                '--as=admin',
                'user:add',
                '--login=' . $login,
                '--field=FIRST_NAME=CLI',
                '--field=LAST_NAME=Regression',
                '--field=EMAIL=' . $email
            ));

            $this->assertSame(0, $create->getExitCode(), $create->getErrorOutput());
            $created = true;

            // A second process has a different PDO connection. Seeing the record here proves the
            // production CLI command committed an actual Admidio database write.
            $show = $this->runCli(array('--as=admin', 'user:show', $login, '--format=json'));
            $this->assertSame(0, $show->getExitCode(), $show->getErrorOutput());

            $user = $this->cliJson($show);
            $this->assertSame($login, $user['login']);
            $this->assertSame('CLI', $user['profile']['FIRST_NAME']);
            $this->assertSame('Regression', $user['profile']['LAST_NAME']);
            $this->assertSame($email, $user['profile']['EMAIL']);
        } finally {
            if ($created) {
                $delete = $this->runCli(array('--as=admin', 'user:delete', $login, '--yes'));
                $this->assertSame(0, $delete->getExitCode(), $delete->getErrorOutput());
            }
        }

        $missing = $this->runCli(array('--as=admin', 'user:show', $login, '--format=json'));
        $this->assertSame(2, $missing->getExitCode());
        $this->assertStringContainsString('was not found', $missing->getErrorOutput());
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
