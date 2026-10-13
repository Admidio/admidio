<?php
/**
 * The command line of the updater (install/core-update/lib/cli.php): what apply and rollback do in
 * every state, where they ask, and when they hand over to another process. Phase A is represented
 * by the workspace it leaves; the process of the database update by the state it records.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;

final class CliTest extends AdmidioTestCase
{
    use PreparedUpdate;

    /** @var array<int,string> */
    private array $output = array();

    /** @var array<int,string> */
    private array $questions = array();

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::loadUpdater();
        require_once dirname(__DIR__, 3) . '/install/core-update/lib/cli.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createInstallation();
        $this->output = array();
        $this->questions = array();
    }

    protected function tearDown(): void
    {
        $this->removeInstallation();

        parent::tearDown();
    }

    /**
     * Continue the prepared update with the options of update:apply --resume.
     * @param array<string,mixed> $context
     * @param array<string,mixed> $options
     * @return array{exit: int, next: string, message: string, updateId: string, state: array<string,mixed>}
     */
    private function apply(array $context, array $options = array(), bool $answer = true, ?string $updateId = null): array
    {
        return U\commandApply(
            $context,
            array_merge(array('channel' => 'stable', 'ref' => '', 'backup' => 'own', 'onChanged' => 'archive', 'dryRun' => false,
                'skipDatabase' => false, 'resume' => true, 'databaseTried' => false), $options),
            function (string $line): void {
                $this->output[] = $line;
            },
            function (string $question) use ($answer): bool {
                $this->questions[] = $question;

                return $answer;
            },
            $updateId
        );
    }

    /**
     * @return array{exit: int, next: string, message: string, updateId: string, state: array<string,mixed>}
     */
    private function rollback(array $context, bool $databaseRestored = false, bool $answer = true): array
    {
        return U\commandRollback(
            $context,
            $databaseRestored,
            function (string $line): void {
                $this->output[] = $line;
            },
            function (string $question) use ($answer): bool {
                $this->questions[] = $question;

                return $answer;
            }
        );
    }

    private function state(string $updateId): string
    {
        return (string)U\readState($this->root, $updateId)['state'];
    }

    /**
     * @testdox apply installs the files after one question and hands the database over to a process of its own
     */
    public function testApplyInstallsTheFiles(): void
    {
        [$context, $updateId] = $this->prepared();

        $result = $this->apply($context);

        $this->assertSame('database', $result['next'], $result['message']);
        $this->assertSame($updateId, $result['updateId']);
        $this->assertSame('files_installed', $this->state($updateId));
        $this->assertSame('NEW INDEX', $this->read('index.php'));
        $this->assertCount(1, $this->questions, 'asked once, before the installation');
        $this->assertStringContainsString('Install Admidio 5.1.0 now?', $this->questions[0]);
        $output = implode("\n", $this->output);
        $this->assertStringContainsString('SYS_CORE_UPDATE_REVIEW_DESC', $output, 'the plan is shown');
        $this->assertStringContainsString('SYS_CORE_UPDATE_NO_INVENTORY', $output);
        $this->assertStringNotContainsString('src/Unknown.php', $output, 'without an inventory every replaced file would be listed');
        $this->assertStringContainsString('SYS_CORE_UPDATE_FILES_INSTALLING', $output, 'and the progress');
    }

    /**
     * @testdox With an inventory the review lists the changed and the unknown files that are kept
     */
    public function testReviewListsKeptFiles(): void
    {
        $this->write(array('install/update-manifest.json' => json_encode(array('version' => '5.0.14', 'files' => array(
            'index.php' => hash('sha256', 'OLD INDEX'),
            'system/bootstrap/bootstrap.php' => hash('sha256', 'ORIGINAL BOOTSTRAP'),
            'src/Old/Gone.php' => hash('sha256', 'REMOVED BY THE RELEASE'),
            'src/Same.php' => hash('sha256', 'SAME')
        )))));
        [$context] = $this->prepared();

        $this->apply($context, array('dryRun' => true));

        $output = implode("\n", $this->output);
        $this->assertStringContainsString('  modified system/bootstrap/bootstrap.php', $output);
        $this->assertStringContainsString('  unknown  src/Unknown.php', $output);
        $this->assertStringNotContainsString('index.php', $output, 'an original file is not kept');
    }

    /**
     * @testdox A dry run plans the update and cancels it without changing a file
     */
    public function testDryRun(): void
    {
        [$context, $updateId] = $this->prepared();

        $result = $this->apply($context, array('dryRun' => true));

        $this->assertSame(U\EXIT_SUCCESS, $result['exit']);
        $this->assertStringContainsString('Dry run', $result['message']);
        $this->assertSame(array(), $this->questions);
        $this->assertNull(U\activeUpdateId($this->root), 'the update is cancelled');
        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId));
        $this->assertSame('OLD INDEX', $this->read('index.php'));
    }

    /**
     * @testdox --on-changed=cancel cancels an update that would replace changed or unknown files
     */
    public function testOnChangedCancel(): void
    {
        [$context] = $this->prepared();

        $result = $this->apply($context, array('onChanged' => 'cancel'));

        $this->assertSame(U\EXIT_REJECTED, $result['exit']);
        $this->assertStringContainsString('--on-changed=cancel', $result['message']);
        $this->assertNull(U\activeUpdateId($this->root));
        $this->assertSame('UNKNOWN', $this->read('src/Unknown.php'));
    }

    /**
     * @testdox Without an answer the update is cancelled before it changes anything
     */
    public function testDeclined(): void
    {
        [$context] = $this->prepared();

        $result = $this->apply($context, array(), false);

        $this->assertSame(U\EXIT_FAILED, $result['exit']);
        $this->assertSame('SYS_CORE_UPDATE_CANCELLED', $result['message']);
        $this->assertNull(U\activeUpdateId($this->root));
        $this->assertSame('OLD INDEX', $this->read('index.php'));
    }

    /**
     * @testdox A backup by the updater that is not possible here stops at the review, to continue with an own backup
     */
    public function testBackupNotPossible(): void
    {
        [$context, $updateId] = $this->prepared();

        foreach (array('', 'dump') as $backup) {
            $result = $this->apply($context, array('backup' => $backup));
            $this->assertSame(U\EXIT_REJECTED, $result['exit'], $backup);
            $this->assertStringContainsString('--resume --backup=own', $result['message']);
            $this->assertSame('review', $this->state($updateId), 'no mysqldump-php in the test installation');
        }

        $this->assertSame('database', $this->apply($context)['next']);
    }

    /**
     * @testdox --skip-database ends with the files, in maintenance mode
     */
    public function testSkipDatabase(): void
    {
        [$context, $updateId] = $this->prepared();

        $result = $this->apply($context, array('skipDatabase' => true));

        $this->assertSame(U\EXIT_SUCCESS, $result['exit']);
        $this->assertSame('', $result['next']);
        $this->assertSame('files_installed', $this->state($updateId));
        $this->assertFileExists($this->root . '/adm_my_files/maintenance.json');
    }

    /**
     * @testdox An unfinished update is only continued with --resume, and --resume needs one
     */
    public function testResume(): void
    {
        [$context] = $this->prepared();
        $this->apply($context, array('skipDatabase' => true));

        $result = $this->apply($context, array('resume' => false));
        $this->assertSame(U\EXIT_REJECTED, $result['exit']);
        $this->assertStringContainsString('is unfinished (state files_installed)', $result['message']);

        $this->rollback($context);
        $result = $this->apply($context);
        $this->assertSame(U\EXIT_REJECTED, $result['exit']);
        $this->assertSame('No core update is in progress.', $result['message']);
    }

    /**
     * @testdox After the database process the completed update is cleaned up and reported
     */
    public function testCompleted(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->apply($context);
        U\updateState($this->root, $updateId, static fn(array $s): array => array_merge($s, array('state' => 'completed', 'databaseStarted' => true,
            'databaseNotes' => array(array('type' => 'warning', 'text' => 'A PLUGIN NOTE')))));

        $result = $this->apply($context, array('databaseTried' => true), true, $updateId);

        $this->assertSame(U\EXIT_SUCCESS, $result['exit']);
        $this->assertStringContainsString('SYS_CORE_UPDATE_SUCCESSFUL', $result['message']);
        $this->assertStringContainsString('A PLUGIN NOTE', $result['message']);
        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId), 'the workspace is removed');
    }

    /**
     * @testdox A failed database update is reported with the way out, and --resume retries it
     */
    public function testDatabaseFailed(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->apply($context);
        U\updateState($this->root, $updateId, static fn(array $s): array => array_merge($s, array('state' => 'database_failed', 'databaseStarted' => true,
            'lastError' => 'STEP 30 FAILED')));

        $result = $this->apply($context, array('databaseTried' => true), true, $updateId);
        $this->assertSame(U\EXIT_FAILED, $result['exit']);
        $this->assertStringContainsString('STEP 30 FAILED', $result['message']);
        $this->assertStringContainsString('SYS_CORE_UPDATE_DATABASE_FAILED_OWN', $result['message'], 'no backup of the updater');
        $this->assertStringContainsString('rollback --database-restored', $result['message']);

        $this->assertSame('database', $this->apply($context)['next'], '--resume retries');
    }

    /**
     * @testdox A database process that did not get to start the update is not started again and again
     */
    public function testDatabaseDidNotStart(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->apply($context);

        $result = $this->apply($context, array('databaseTried' => true), true, $updateId);

        $this->assertSame(U\EXIT_FAILED, $result['exit']);
        $this->assertSame('', $result['next']);
    }

    /**
     * @testdox rollback cancels an update that has not changed anything
     */
    public function testRollbackCancels(): void
    {
        [$context, $updateId] = $this->prepared();

        $result = $this->rollback($context);

        $this->assertSame(U\EXIT_SUCCESS, $result['exit']);
        $this->assertSame('SYS_CORE_UPDATE_CANCELLED', $result['message']);
        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId));
        $this->assertSame('No core update is in progress.', $this->rollback($context)['message']);
    }

    /**
     * @testdox rollback restores the previous files until the database update starts
     */
    public function testRollbackRestoresTheFiles(): void
    {
        [$context] = $this->prepared();
        $this->apply($context, array('skipDatabase' => true));

        $result = $this->rollback($context);

        $this->assertSame(U\EXIT_SUCCESS, $result['exit'], $result['message']);
        $this->assertSame('SYS_CORE_UPDATE_FILESYSTEM_ROLLED_BACK', $result['message']);
        $this->assertSame('OLD INDEX', $this->read('index.php'));
        $this->assertSame('UNKNOWN', $this->read('src/Unknown.php'));
        $this->assertFileDoesNotExist($this->root . '/adm_my_files/maintenance.json');
        $this->assertNull(U\activeUpdateId($this->root));
    }

    /**
     * @testdox After a failed database update without a backup of the updater, rollback needs the confirmation of an own restore
     */
    public function testRollbackAfterAFailedDatabaseUpdate(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->apply($context);
        U\updateState($this->root, $updateId, static fn(array $s): array => array_merge($s, array('state' => 'database_failed', 'databaseStarted' => true)));

        $result = $this->rollback($context);
        $this->assertSame(U\EXIT_REJECTED, $result['exit']);
        $this->assertStringContainsString('--database-restored', $result['message']);
        $this->assertSame('NEW INDEX', $this->read('index.php'));

        $result = $this->rollback($context, true);
        $this->assertSame(U\EXIT_SUCCESS, $result['exit'], $result['message']);
        $this->assertSame('SYS_CORE_UPDATE_VERSION_RESTORED', $result['message']);
        $this->assertSame('OLD INDEX', $this->read('index.php'));
    }

    /**
     * @testdox rollback does nothing without an answer
     */
    public function testRollbackDeclined(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->apply($context, array('skipDatabase' => true));

        $result = $this->rollback($context, false, false);

        $this->assertSame(U\EXIT_FAILED, $result['exit']);
        $this->assertSame('files_installed', $this->state($updateId));
        $this->assertSame('NEW INDEX', $this->read('index.php'));
    }
}
